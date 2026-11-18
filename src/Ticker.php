<?php
declare(strict_types=1);
namespace HeadlineScout;

use PDO;

/**
 * The ticker is based on currently displayed front-page headlines, independent of
 * dashboard search/source filters. It is calculated on request from SQLite so
 * automatic/Source Lab scans are reflected on the next lightweight refresh.
 */
final class Ticker {
    public static function items(PDO $db): array {
        $ignored=WordStats::ignored($db);
        $trending=[];
        foreach ([1,24,168] as $hours) {
            foreach (WordStats::top($db,$hours,$ignored) as $word=>$mentions) {
                $trending[mb_strtolower((string)$word,'UTF-8')]=true;
            }
        }
        if (!$trending) return [];

        $sql=<<<'SQL'
SELECT h.title,h.url,s.name AS source_name,h.id AS headline_id,
       a.position,sc.id AS scan_id
FROM sources s
JOIN scans sc ON sc.id=(
    SELECT sc2.id FROM scans sc2
    WHERE sc2.source_id=s.id AND sc2.status='success'
    ORDER BY sc2.id DESC LIMIT 1
)
JOIN appearances a ON a.scan_id=sc.id AND a.position<=5
JOIN headlines h ON h.id=a.headline_id
WHERE s.enabled=1
ORDER BY s.id,a.position
SQL;
        $rows=$db->query($sql)->fetchAll();
        $ranked=[];
        foreach ($rows as $row) {
            $words=WordStats::tokens($row['title'],$ignored);
            $score=0;
            foreach ($words as $word) {
                if (isset($trending[$word])) $score++;
            }
            if ($score===0) continue;
            $row['matches']=$score;
            $ranked[]=$row;
        }
        usort($ranked,static function (array $a,array $b): int {
            // More matched mentions wins; homepage rank breaks ties, then stable ID.
            return ($b['matches'] <=> $a['matches'])
                ?: ($a['position'] <=> $b['position'])
                ?: ($b['scan_id'] <=> $a['scan_id'])
                ?: ($a['headline_id'] <=> $b['headline_id']);
        });
        return array_map(static fn($row)=>[
            'title'=>$row['title'],
            'url'=>$row['url'],
            'source'=>$row['source_name'],
            'matches'=>$row['matches']
        ],array_slice($ranked,0,3));
    }
}
