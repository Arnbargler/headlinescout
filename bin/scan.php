#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/App.php';
use HeadlineScout\App;
$db = App::db(); App::install($db);
$force = in_array('--all', $argv, true);
$lock = fopen(dirname(__DIR__) . '/var/scan.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { fwrite(STDERR, "Collector already running\n"); exit(2); }
$sources=$db->query('SELECT * FROM sources WHERE enabled=1 ORDER BY id')->fetchAll();
$now = time(); $attempted=0;
foreach ($sources as $source) {
    $last = $source['last_scan_at'] ? strtotime($source['last_scan_at']) : 0;
    if (!$force && $last && $now-$last < (int)$source['interval_seconds']) continue;
    $result=App::scan($db,$source);
    echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . PHP_EOL;
    $attempted++;
    if (!$force && $attempted>=3) break;
    usleep(350000);
}
if (!$attempted) echo "No sources due\n";
flock($lock,LOCK_UN); fclose($lock);
