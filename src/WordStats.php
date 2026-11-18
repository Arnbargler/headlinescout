<?php
declare(strict_types=1);
namespace HeadlineScout;

use PDO;
use InvalidArgumentException;

final class WordStats {
    private const DEFAULT_IGNORES = [
        'a','an','and','are','as','at','be','been','but','by','for','from','has','have',
        'in','into','is','it','its','of','on','or','our','that','the','their','there',
        'these','this','to','was','were','what','when','where','which','who','will',
        'with','you','your','after','before','about','over','under','more','new',
        'not','than','they','he','she','his','her','we','us','says','said'
    ];

    public static function install(PDO $db): void {
        $db->exec('CREATE TABLE IF NOT EXISTS ignored_terms (id INTEGER PRIMARY KEY, term TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL)');
        // Marker prevents deleted defaults from being recreated on every page load.
        $insertMarker=$db->prepare("INSERT OR IGNORE INTO settings(key,value) VALUES('default_ignored_terms_seeded','1')");
        $insertMarker->execute();
        if ($insertMarker->rowCount() > 0) {
            $stmt=$db->prepare('INSERT OR IGNORE INTO ignored_terms(term,created_at) VALUES(?,?)');
            foreach(self::DEFAULT_IGNORES as $term) $stmt->execute([$term,gmdate('c')]);
        }
    }

    public static function normalize(string $term): string {
        $term=mb_strtolower(trim(preg_replace('/\s+/u',' ', $term)), 'UTF-8');
        $term=trim($term, " \t\n\r\0\x0B.,!?;:–—-\"'“”‘’()");
        if ($term==='' || mb_strlen($term,'UTF-8')>100 || !preg_match('/^[\p{L}\p{N}]+(?:[\s\'’\-][\p{L}\p{N}]+)*$/u',$term)) {
            throw new InvalidArgumentException('Enter a word or phrase (letters/numbers, spaces or hyphens; maximum 100 characters).');
        }
        return $term;
    }

    public static function ignored(PDO $db): array {
        return $db->query('SELECT id,term FROM ignored_terms ORDER BY term COLLATE NOCASE')->fetchAll();
    }

    public static function add(PDO $db, string $raw): bool {
        $term=self::normalize($raw);
        $stmt=$db->prepare('INSERT OR IGNORE INTO ignored_terms(term,created_at) VALUES(?,?)');
        $stmt->execute([$term,gmdate('c')]);
        return $stmt->rowCount()>0;
    }

    public static function remove(PDO $db, int $id): bool {
        $stmt=$db->prepare('DELETE FROM ignored_terms WHERE id=?');
        $stmt->execute([$id]);
        return $stmt->rowCount()>0;
    }

    /**
     * Headline mentions in a half-open time window: [start,end).
     * Read historical appearances (not the mutable last_seen_at), so a scan
     * tomorrow cannot erase evidence from yesterday's comparison window.
     * The same headline is counted only once within each window.
     */
    public static function counts(PDO $db, array $ignored, int $start, int $end): array {
        if ($end <= $start) return [];
        $stmt=$db->prepare(<<<'SQL'
SELECT DISTINCT h.id, h.title
FROM scans s
JOIN appearances a ON a.scan_id=s.id
JOIN headlines h ON h.id=a.headline_id
WHERE s.status='success' AND s.started_at >= ? AND s.started_at < ?
SQL);
        $stmt->execute([gmdate('c',$start),gmdate('c',$end)]);
        $counts=[];
        while($row=$stmt->fetch()){
            foreach(self::tokens($row['title'],$ignored) as $word) {
                $counts[$word]=($counts[$word]??0)+1;
            }
        }
        uksort($counts,'strnatcasecmp');
        arsort($counts,SORT_NUMERIC);
        return $counts;
    }

    public static function top(PDO $db, int $hours, array $ignored, int $limit=10): array {
        $now=time();
        return array_slice(self::counts($db,$ignored,$now-$hours*3600,$now),0,$limit,true);
    }

    /**
     * Compare the current top 10 to the top 10 from the immediately prior,
     * equally sized interval. "new" means absent from the previous TOP TEN,
     * not necessarily absent from all previous headlines.
     */
    public static function trending(PDO $db, int $hours, array $ignored, int $limit=10): array {
        $now=time();$span=$hours*3600;
        $current=array_slice(self::counts($db,$ignored,$now-$span,$now),0,$limit,true);
        $previous=array_slice(self::counts($db,$ignored,$now-2*$span,$now-$span),0,$limit,true);
        $max=$current ? max($current) : 0;
        $rows=[];
        foreach($current as $word=>$mentions){
            $prior=$previous[$word]??null;
            $direction=$prior===null ? 'new' : ($mentions>$prior ? 'up' : ($mentions<$prior ? 'down' : 'flat'));
            $rows[]=[
                'word'=>(string)$word,
                'count'=>$mentions,
                'previous'=>$prior,
                'direction'=>$direction,
                'percent'=>$max>0 ? round(100*$mentions/$max,1) : 0
            ];
        }
        return $rows;
    }

    /** Tokenization shared by frequency analysis and ticker ranking. */
    public static function tokens(string $title, array $ignored): array {
        $terms=array_map(static fn($entry)=>(string)$entry['term'],$ignored);
        usort($terms,fn($a,$b)=>mb_strlen($b,'UTF-8')<=>mb_strlen($a,'UTF-8'));
        $title=mb_strtolower($title,'UTF-8');
        $stop=[];
        foreach ($terms as $term) {
            if (str_contains($term,' ') || str_contains($term,'-')) {
                $parts=preg_split('/[\s\-]+/u',$term);
                $pattern='/(?<![\p{L}\p{N}])'.implode('[\s\-]+',array_map(fn($x)=>preg_quote($x,'/'),$parts)).'(?![\p{L}\p{N}])/iu';
                $title=preg_replace($pattern,' ',$title);
            } else {
                $stop[$term]=true;
            }
        }
        preg_match_all('/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u',$title,$matches);
        return array_values(array_filter($matches[0],static fn($word)=>
            !isset($stop[$word]) && mb_strlen($word,'UTF-8')>=2
        ));
    }

}
