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
     * Counts occurrences of each word in unique stored headline titles first seen
     * inside rolling UTC intervals. One headline is counted once, not once per scan.
     * Ignored multiword phrases are removed before individual word counting.
     */
    public static function top(PDO $db, int $hours, array $ignored, int $limit=10): array {
        $cutoff=gmdate('c',time()-$hours*3600);
        $stmt=$db->prepare('SELECT title FROM headlines WHERE first_seen_at >= ?');
        $stmt->execute([$cutoff]);
        $terms=array_column($ignored,'term');
        // Remove longer ignored phrases first so overlapping terms behave predictably.
        usort($terms,fn($a,$b)=>mb_strlen($b,'UTF-8')<=>mb_strlen($a,'UTF-8'));
        $phrasePatterns=[];
        $stop=[];
        foreach ($terms as $term) {
            if (str_contains($term,' ') || str_contains($term,'-')) {
                $parts=preg_split('/[\s\-]+/u',$term);
                $phrasePatterns[]='/(?<![\p{L}\p{N}])'.implode('[\s\-]+',array_map(fn($x)=>preg_quote($x,'/'),$parts)).'(?![\p{L}\p{N}])/iu';
            } else $stop[$term]=true;
        }
        $counts=[];
        while ($row=$stmt->fetch()) {
            $title=mb_strtolower($row['title'],'UTF-8');
            foreach ($phrasePatterns as $pattern) $title=preg_replace($pattern,' ',$title);
            preg_match_all('/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u',$title,$matches);
            foreach ($matches[0] as $word) {
                if (isset($stop[$word]) || mb_strlen($word,'UTF-8')<2) continue;
                $counts[$word]=($counts[$word]??0)+1;
            }
        }
        uksort($counts,'strnatcasecmp');
        arsort($counts,SORT_NUMERIC); // stable tie ordering (alphabetical pre-sort)
        return array_slice($counts,0,$limit,true);
    }
}
