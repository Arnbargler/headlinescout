#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Scheduler.php';
use HeadlineScout\Scheduler;
$now=1700000000;
$sources=[
    ['id'=>1,'interval_seconds'=>900,'last_scan_at'=>gmdate('c',$now-950)],
    ['id'=>2,'interval_seconds'=>900,'last_scan_at'=>gmdate('c',$now-3600)],
    ['id'=>3,'interval_seconds'=>900,'last_scan_at'=>null],
    ['id'=>4,'interval_seconds'=>900,'last_scan_at'=>gmdate('c',$now-920)],
    ['id'=>5,'interval_seconds'=>900,'last_scan_at'=>gmdate('c',$now-300)],
    ['id'=>6,'interval_seconds'=>1800,'last_scan_at'=>gmdate('c',$now-1100)],
];
$chosen=Scheduler::due($sources,$now,3);
$ids=array_column($chosen,'id');
if($ids!==[3,2,1]) {
    fwrite(STDERR,'FAIL: expected never scanned, most overdue, then next overdue; got '.json_encode($ids)."\n");
    exit(1);
}
if(count(Scheduler::due($sources,$now,10))!==4){
    fwrite(STDERR,"FAIL: scheduler included sources not yet due\n");
    exit(1);
}
echo "PASS: scheduler prioritizes overdue sources, respects intervals and three-source limit\n";
