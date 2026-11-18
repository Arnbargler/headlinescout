<?php
declare(strict_types=1);
namespace HeadlineScout;

use DOMDocument;
use DOMXPath;
use PDO;
use RuntimeException;
use Throwable;

final class App {
    public static function db(): PDO {
        $file = dirname(__DIR__) . '/var/headlines.sqlite';
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0770, true);
        $db = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000; PRAGMA journal_mode = WAL;');
        return $db;
    }

    public static function install(PDO $db): void {
        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS sources (
 id INTEGER PRIMARY KEY, name TEXT NOT NULL, homepage_url TEXT NOT NULL UNIQUE,
 interval_seconds INTEGER NOT NULL DEFAULT 900, enabled INTEGER NOT NULL DEFAULT 1,
 selector TEXT NOT NULL DEFAULT '//a[.//h1 or .//h2 or .//h3 or ancestor::h1 or ancestor::h2 or ancestor::h3]',
 last_scan_at TEXT, last_success_at TEXT, last_status TEXT, last_error TEXT
);
CREATE TABLE IF NOT EXISTS headlines (
 id INTEGER PRIMARY KEY, source_id INTEGER NOT NULL REFERENCES sources(id),
 url TEXT NOT NULL, title TEXT NOT NULL, first_seen_at TEXT NOT NULL,
 last_seen_at TEXT NOT NULL, is_current INTEGER NOT NULL DEFAULT 1,
 UNIQUE(source_id,url)
);
CREATE TABLE IF NOT EXISTS scans (
 id INTEGER PRIMARY KEY, source_id INTEGER NOT NULL REFERENCES sources(id),
 started_at TEXT NOT NULL, finished_at TEXT NOT NULL, status TEXT NOT NULL,
 http_status INTEGER, found INTEGER NOT NULL DEFAULT 0, added INTEGER NOT NULL DEFAULT 0,
 error TEXT
);
CREATE TABLE IF NOT EXISTS appearances (
 scan_id INTEGER NOT NULL REFERENCES scans(id), headline_id INTEGER NOT NULL REFERENCES headlines(id),
 position INTEGER NOT NULL, PRIMARY KEY(scan_id,headline_id)
);
CREATE INDEX IF NOT EXISTS headlines_recent ON headlines(last_seen_at DESC);
CREATE INDEX IF NOT EXISTS scans_source ON scans(source_id,started_at DESC);
CREATE INDEX IF NOT EXISTS scans_time_status ON scans(started_at,status);
SQL);
        require_once __DIR__ . '/WordStats.php';
        WordStats::install($db);
        // Seed defaults only on the first initialization; never resurrect deleted sources.
        // Existing installations already have sources and simply acquire the seed marker.
        $newDb = ((int)$db->query('SELECT COUNT(*) FROM sources')->fetchColumn()) === 0;
        $marker = $db->prepare("INSERT OR IGNORE INTO settings(key,value) VALUES('initial_sources_seeded','1')");
        $marker->execute();
        if ($marker->rowCount() > 0 && $newDb) {
            $seed = $db->prepare('INSERT OR IGNORE INTO sources(name,homepage_url) VALUES(?,?)');
            foreach ([['CNN','https://www.cnn.com/'],['ZeroHedge','https://www.zerohedge.com/'],['The Gateway Pundit','https://www.thegatewaypundit.com/']] as $source) $seed->execute($source);
        }
    }

    private static function publicUrl(string $url): bool {
        $p = parse_url($url);
        if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http','https'], true) || empty($p['host']) || isset($p['user']) || isset($p['pass'])) return false;
        $host = strtolower($p['host']);
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) return false;
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool)filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        // All resolved addresses must be public, including IPv6 if present.
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (!$records) return false;
        foreach ($records as $r) {
            $ip = $r['ip'] ?? $r['ipv6'] ?? '';
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        }
        return true;
    }

    private static function absolute(string $base, string $relative): string {
        $relative = trim(html_entity_decode($relative, ENT_QUOTES | ENT_HTML5));
        if ($relative === '' || preg_match('~^(?:javascript|data|mailto|tel):~i', $relative)) return '';
        if (preg_match('~^https?://~i', $relative)) return $relative;
        $b = parse_url($base);
        if (!$b || !isset($b['host'])) return '';
        $origin = ($b['scheme'] ?? 'https') . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($relative, '//')) return ($b['scheme'] ?? 'https') . ':' . $relative;
        if (str_starts_with($relative, '#')) return '';
        if (str_starts_with($relative, '/')) return $origin . $relative;
        $path = $b['path'] ?? '/';
        $dir = substr($path, 0, strrpos($path, '/') + 1);
        $parts = explode('/', $dir . $relative);
        $stack = [];
        foreach ($parts as $part) {
            if ($part === '..') array_pop($stack);
            elseif ($part !== '.' && $part !== '') $stack[] = $part;
        }
        return $origin . '/' . implode('/', $stack);
    }

    public static function fetch(string $url): array {
        for ($hop=0; $hop<4; $hop++) {
            if (!self::publicUrl($url)) throw new RuntimeException('Blocked unsafe/unresolvable destination: ' . $url);
            $headers = [];
            $body = '';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 25, CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => 'HeadlineScout/0.1 (+self-hosted personal front-page monitor)',
                CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: en-US,en;q=0.8'],
                CURLOPT_HEADERFUNCTION => function($ch,$line) use (&$headers) {
                    $i = strpos($line, ':');
                    if ($i !== false) $headers[strtolower(trim(substr($line,0,$i)))] = trim(substr($line,$i+1));
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => function($ch,$part) use (&$body) {
                    if (strlen($body) + strlen($part) > 12_000_000) return 0;
                    $body .= $part; return strlen($part);
                }
            ]);
            $ok = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($ok === false) throw new RuntimeException(str_contains($error, 'Failure writing output') ? 'Homepage exceeded 12 MB download limit (or response write failed)' : 'HTTP fetch failed: ' . $error);
            if ($status >= 300 && $status < 400 && isset($headers['location'])) {
                $url = self::absolute($url, $headers['location']); continue;
            }
            return [$body, $status, $url, $headers];
        }
        throw new RuntimeException('Too many redirects');
    }

    public static function robotsAllowed(string $url): bool {
        $p = parse_url($url);
        $robotsUrl = ($p['scheme'] ?? 'https') . '://' . $p['host'] . '/robots.txt';
        try {
            [$body,$status] = self::fetch($robotsUrl);
            if ($status === 404 || $status === 410) return true;
            if ($status !== 200) return false; // fail closed on uncertain robots state
            $path = ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
            $groups = []; $agents = []; $rules = []; $inRules = false;
            foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
                $line = trim(preg_replace('/#.*$/', '', $line));
                if ($line === '' || !str_contains($line, ':')) continue;
                [$key,$value] = array_map('trim', explode(':', $line, 2)); $key = strtolower($key);
                if ($key === 'user-agent') {
                    if ($inRules) { $groups[] = [$agents,$rules]; $agents=[]; $rules=[]; $inRules=false; }
                    $agents[] = strtolower($value);
                } elseif (in_array($key,['allow','disallow'],true) && $agents) {
                    $inRules = true; $rules[] = [$key,$value];
                }
            }
            if ($agents) $groups[] = [$agents,$rules];
            $specific = []; $wildcard = [];
            foreach ($groups as [$a,$r]) {
                if (in_array('headlinescout', $a, true)) array_push($specific, ...$r);
                elseif (in_array('*', $a, true)) array_push($wildcard, ...$r);
            }
            $selected = $specific ?: $wildcard; $bestLen=-1; $allowed=true;
            foreach ($selected as [$type,$rule]) {
                if ($rule === '') continue;
                $regex='~^' . str_replace(['\*','\$'], ['.*','$'], preg_quote($rule,'~')) . '~';
                if (preg_match($regex,$path) && strlen($rule) >= $bestLen) {
                    if (strlen($rule)>$bestLen || $type==='allow') { $allowed=$type==='allow'; $bestLen=strlen($rule); }
                }
            }
            return $allowed;
        } catch (Throwable) { return false; }
    }

    /**
     * Keep only the first sentence of a headline. Only split at sentence-ending
     * punctuation followed by whitespace and the start of another sentence.
     * Protect common abbreviated titles and initialisms (Dr., U.S., etc.).
     */
    public static function firstSentence(string $title): string {
        $title=trim(preg_replace('/\s+/u',' ',$title));
        // Google News can expose its trailing Material icon name as link text.
        $title=trim(preg_replace('/(?:\s+chevron_right)+\s*$/iu','',$title));
        preg_match_all('/[.!?]+[”"’\x{0027}]?(?=\s+[\p{Lu}\p{N}"“‘])/u',
            $title,$boundaries,PREG_OFFSET_CAPTURE);
        foreach ($boundaries[0] as [$punctuation,$offset]) {
            $preceding=substr($title,0,$offset+strlen($punctuation));
            if (str_starts_with($punctuation,'.') &&
                preg_match('/(?:\b(?:Mr|Mrs|Ms|Dr|Prof|Sen|Rep|Gov|Gen|St|Jr|Sr|vs|etc)\.|(?:\b\p{L}\.){2,})[”"’\x{0027}]?$/iu',$preceding)) {
                continue;
            }
            return trim($preceding);
        }
        return $title;
    }

    public static function extract(string $html, string $url, string $selector, int $limit=120): array {
        $dom = new DOMDocument();
        $prior = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors(); libxml_use_internal_errors($prior);
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query($selector);
        if ($nodes === false) throw new RuntimeException('Invalid XPath selector');
        $out = [];
        $basehost = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        foreach ($nodes as $node) {
            $href = $node->attributes?->getNamedItem('href')?->nodeValue ?? '';
            $link = self::absolute($url, $href);
            $parts = parse_url($link);
            if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''),['http','https'],true) || !isset($parts['host'])) continue;
            $host = strtolower($parts['host']);
            if ($host !== $basehost && !str_ends_with($host, '.' . $basehost)) continue;
            $path = $parts['path'] ?? '/';
            if ($path === '/' || preg_match('~^/(?:tag|tags|category|categories|author|authors|about|contact|search|videos|live|newsletter|subscribe)(?:/|$)~i',$path)) continue;
            $title = self::firstSentence($node->textContent);
            if (mb_strlen($title) < 18 || mb_strlen($title) > 300) continue;
            $link = preg_replace('/#.*$/','',$link);
            if (!isset($out[$link])) $out[$link] = ['url'=>$link,'title'=>$title];
            if (count($out) >= $limit) break;
        }
        return array_values($out);
    }

    public static function scan(PDO $db, array $source): array {
        $started = gmdate('c'); $http = null; $items=[]; $added=0; $status='error'; $error=null;
        try {
            $url = $source['homepage_url'];
            if (!self::robotsAllowed($url)) throw new RuntimeException('Robots policy disallows scan or could not be verified');
            [$html,$http,$final,$headers] = self::fetch($url);
            if ($http !== 200) throw new RuntimeException('HTTP status ' . $http);
            $ct = strtolower($headers['content-type'] ?? '');
            if ($ct && !str_contains($ct,'html')) throw new RuntimeException('Unexpected content type: ' . $ct);
            $items = self::extract($html,$final,$source['selector'],5);
            $db->beginTransaction();
            $ins = $db->prepare('INSERT OR IGNORE INTO headlines(source_id,url,title,first_seen_at,last_seen_at,is_current) VALUES(?,?,?,?,?,1)');
            $upd = $db->prepare('UPDATE headlines SET title=?,last_seen_at=?,is_current=1 WHERE source_id=? AND url=?');
            $get = $db->prepare('SELECT id FROM headlines WHERE source_id=? AND url=?');
            $db->prepare('UPDATE headlines SET is_current=0 WHERE source_id=?')->execute([$source['id']]);
            $scan = $db->prepare('INSERT INTO scans(source_id,started_at,finished_at,status,http_status,found,added) VALUES(?,?,?,?,?,?,0)');
            $scan->execute([$source['id'],$started,gmdate('c'),'success',$http,count($items)]);
            $scanId = (int)$db->lastInsertId();
            $appear=$db->prepare('INSERT INTO appearances(scan_id,headline_id,position) VALUES(?,?,?)');
            foreach ($items as $idx=>$item) {
                $ins->execute([$source['id'],$item['url'],$item['title'],$started,$started]);
                $added += $ins->rowCount();
                $upd->execute([$item['title'],$started,$source['id'],$item['url']]);
                $get->execute([$source['id'],$item['url']]);
                $appear->execute([$scanId,(int)$get->fetchColumn(),$idx+1]);
            }
            $db->prepare('UPDATE scans SET added=? WHERE id=?')->execute([$added,$scanId]);
            $db->prepare("UPDATE sources SET last_scan_at=?,last_success_at=?,last_status='success',last_error=NULL WHERE id=?")->execute([$started,$started,$source['id']]);
            $db->commit(); $status='success';
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error=$e->getMessage();
            $db->prepare('INSERT INTO scans(source_id,started_at,finished_at,status,http_status,found,added,error) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$source['id'],$started,gmdate('c'),'error',$http,count($items),0,$error]);
            $db->prepare("UPDATE sources SET last_scan_at=?,last_status='error',last_error=? WHERE id=?")->execute([$started,$error,$source['id']]);
        }
        return ['source'=>$source['name'],'status'=>$status,'found'=>count($items),'added'=>$added,'error'=>$error];
    }
}
