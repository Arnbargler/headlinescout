<?php
declare(strict_types=1);
namespace HeadlineScout;

/** Choose the most overdue eligible sources, not simply the lowest database IDs. */
final class Scheduler {
    public static function due(array $sources, int $now, int $limit=3): array {
        $eligible=[];
        foreach ($sources as $source) {
            $last=empty($source['last_scan_at']) ? 0 : (strtotime((string)$source['last_scan_at']) ?: 0);
            $interval=max(1,(int)$source['interval_seconds']);
            $dueAt=$last ? $last+$interval : 0;
            if ($dueAt > $now) continue;
            $source['_due_at']=$dueAt;
            $eligible[]=$source;
        }
        usort($eligible,static fn(array $a,array $b):int =>
            ($a['_due_at'] <=> $b['_due_at']) ?: ((int)$a['id'] <=> (int)$b['id'])
        );
        return array_map(static function(array $row):array {
            unset($row['_due_at']);
            return $row;
        },array_slice($eligible,0,$limit));
    }
}
