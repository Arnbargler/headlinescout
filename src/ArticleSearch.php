<?php
declare(strict_types=1);
namespace HeadlineScout;

use PDO;

/** Historical, top-three-per-scan headline lookups used by Word Count drill-down. */
final class ArticleSearch {
    public const PAGE_SIZE=100;

    public static function periods(): array {
        return array_column(WordStats::periods(),null,'key');
    }

    public static function search(PDO $db,string $term,string $period,int $source=0,int $page=1,?int $now=null): array {
        $periods=self::periods();
        if (!isset($periods[$period])) throw new \InvalidArgumentException('Unknown search period');
        $term=trim($term);
        if ($term==='') return ['items'=>[],'has_more'=>false,'page'=>1];
        $now??=time();
        $start=gmdate('c',$now-$periods[$period]['seconds']);
        $end=gmdate('c',$now);
        // Exact word boundaries match the headline tokenizer more closely than
        // substring matching ("war" should not match "award").
        $pattern='/(?<![\p{L}\p{N}])'.preg_quote($term,'/').'(?![\p{L}\p{N}])/iu';
        $singleWord=!preg_match('/\s/u',$term);
        $normalized=mb_strtolower($term,'UTF-8');
        $stmt=$db->prepare(<<<'SQL'
SELECT h.id,h.source_id,h.title,h.url,h.first_seen_at,
       MAX(sc.started_at) AS matched_at,MIN(a.position) AS position
FROM scans sc
JOIN appearances a ON a.scan_id=sc.id AND a.position<=3
JOIN headlines h ON h.id=a.headline_id
JOIN sources s ON s.id=h.source_id AND s.enabled=1
WHERE sc.status='success' AND sc.started_at>=? AND sc.started_at<?
  AND (?=0 OR h.source_id=?)
  AND instr(lower(h.title),lower(?))>0
GROUP BY h.id
ORDER BY matched_at DESC,h.id DESC
SQL);
        $stmt->execute([$start,$end,$source,$source,$term]);
        $page=max(1,$page);
        $skip=($page-1)*self::PAGE_SIZE;
        $items=[];$seen=0;
        while ($row=$stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($singleWord) {
                // Match exactly the same token boundaries as Word Count,
                // including hyphenated words and apostrophes.
                if (!in_array($normalized,WordStats::tokens($row['title'],[]),true)) continue;
            } elseif (!preg_match($pattern,$row['title'])) {
                continue;
            }
            if ($seen++<$skip) continue;
            if (count($items)===self::PAGE_SIZE) break;
            $items[]=$row;
        }
        return ['items'=>$items,'has_more'=>count($items)===self::PAGE_SIZE && $seen>$skip+self::PAGE_SIZE,'page'=>$page];
    }
}
