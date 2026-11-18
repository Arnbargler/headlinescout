<?php
declare(strict_types=1);
namespace HeadlineScout;

use PDO;

/**
 * The ticker is based on currently displayed front-page headlines, independent of
 * dashboard search/source filters. Shared cached Word Count results avoid
 * re-tokenizing historical headlines at every 15-second ticker refresh.
 */
final class Ticker {
    public static function items(PDO $db): array {
        $ignored=WordStats::ignored($db);
        $trending=[];
        foreach (['hour','day','week'] as $period) {
            foreach (StatsCache::period($db,$period,$ignored)['top'] as $word=>$mentions) {
                $trending[mb_strtolower((string)$word,'UTF-8')]=true;
            }
        }
        if (!$trending) return [];

        $sql=<<<'SQL'
SELECT h.title,h.url,h.id AS headline_id,
       a.position,sc.id AS scan_id
FROM sources s
JOIN scans sc ON sc.id=(
    SELECT sc2.id FROM scans sc2
    WHERE sc2.source_id=s.id AND sc2.status='success'
    ORDER BY sc2.id DESC LIMIT 1
)
JOIN appearances a ON a.scan_id=sc.id AND a.position<=3
JOIN headlines h ON h.id=a.headline_id
WHERE s.enabled=1
ORDER BY s.id,a.position
SQL;
        $rows=$db->query($sql)->fetchAll();
        $candidates=[];
        foreach ($rows as $row) {
            // Long titles are not eligible for the three-slot header rotator.
            if (mb_strlen($row['title'],'UTF-8') > 110) continue;
            $matching=[];
            foreach (WordStats::tokens($row['title'],$ignored) as $word) {
                if (isset($trending[$word])) $matching[]=$word;
            }
            if (!$matching) continue;
            $row['matching_words']=$matching;
            $row['matches']=count($matching);
            $candidates[]=$row;
        }

        // Greedy diversity: headline #1 wins on raw word matches; thereafter,
        // previously unused trending words receive triple weight. Repeated
        // terms still contribute, so topic coverage does not override relevance.
        $selected=[];
        $covered=[];
        while ($candidates && count($selected)<3) {
            usort($candidates,static function(array $a,array $b) use ($covered): int {
                $score=static function(array $item) use ($covered): int {
                    $total=0;
                    foreach ($item['matching_words'] as $word) {
                        $total+=isset($covered[$word]) ? 1 : 3;
                    }
                    return $total;
                };
                return ($score($b) <=> $score($a))
                    ?: ($b['matches'] <=> $a['matches'])
                    ?: ($a['position'] <=> $b['position'])
                    ?: ($b['scan_id'] <=> $a['scan_id'])
                    ?: ($a['headline_id'] <=> $b['headline_id']);
            });
            $winner=array_shift($candidates);
            foreach ($winner['matching_words'] as $word) $covered[$word]=true;
            $selected[]=$winner;
        }
        return array_map(static fn($row)=>[
            'title'=>$row['title'],
            'url'=>$row['url']
        ],$selected);
    }
}
