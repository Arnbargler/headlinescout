#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';

use HeadlineScout\App;
use HeadlineScout\StatsCache;
use HeadlineScout\WordStats;

$db=App::db();
App::install($db);
$ignored=WordStats::ignored($db);
$directory=sys_get_temp_dir().'/headlinescout-benchmark-'.getmypid();
if (!mkdir($directory,0700) && !is_dir($directory)) {
    fwrite(STDERR,"Cannot create benchmark cache directory\n");
    exit(1);
}
$elapsed=static function(callable $action): float {
    $start=hrtime(true);
    $action();
    return (hrtime(true)-$start)/1_000_000;
};
$sumRaw=0.0;
$sumCold=0.0;
$sumWarm=0.0;
echo "HeadlineScout word-statistics benchmark (milliseconds)\n";
echo "Database: var/headlines.sqlite (read-only queries; cache isolated in system temp)\n";
printf("%-9s %12s %12s %12s\n",'Period','Uncached','Cache miss','Cache hit');
try {
    foreach (WordStats::periods() as $period) {
        $key=$period['key'];
        $raw=$elapsed(static function() use ($db,$period,$ignored):void {
            WordStats::snapshot($db,$period['seconds'],$ignored);
        });
        $cold=$elapsed(static function() use ($db,$key,$ignored,$directory):void {
            StatsCache::period($db,$key,$ignored,$directory);
        });
        $warm=$elapsed(static function() use ($db,$key,$ignored,$directory):void {
            StatsCache::period($db,$key,$ignored,$directory);
        });
        $sumRaw+=$raw;
        $sumCold+=$cold;
        $sumWarm+=$warm;
        printf("%-9s %12.2f %12.2f %12.2f\n",$period['label'],$raw,$cold,$warm);
    }
    printf("%-9s %12.2f %12.2f %12.2f\n",'TOTAL',$sumRaw,$sumCold,$sumWarm);
    if ($sumWarm>0) printf("Warm-cache speedup (six periods): %.1fx\n",$sumRaw/$sumWarm);
    echo "These are one-run timings, not a production capacity forecast; repeat when history grows.\n";
} finally {
    foreach (glob($directory.'/*') ?: [] as $file) @unlink($file);
    @rmdir($directory);
}
