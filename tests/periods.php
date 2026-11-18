#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';
use HeadlineScout\App;
use HeadlineScout\WordStats;

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
App::install($db);
$periods=WordStats::periods();
$expected=[900,3600,86400,604800,2592000,31536000];
if(array_column($periods,'seconds')!==$expected ||
   array_column($periods,'label')!==['NOW','1 hour','24 hours','7 days','30 days','1 year']) {
    fwrite(STDERR,"FAIL: six dashboard periods must match the specified durations and order\n");exit(1);
}
$source=(int)$db->query('SELECT id FROM sources LIMIT 1')->fetchColumn();
$scan=$db->prepare("INSERT INTO scans(source_id,started_at,finished_at,status,http_status,found,added) VALUES(?,?,?,'success',200,1,1)");
$headline=$db->prepare('INSERT INTO headlines(source_id,url,title,first_seen_at,last_seen_at,is_current) VALUES(?,?,?,?,?,1)');
$appearance=$db->prepare('INSERT INTO appearances(scan_id,headline_id,position) VALUES(?,?,1)');
$now=time();
foreach([
    [5*60,'Solar solar'],
    [20*60,'Lunar lunar'],
    [40*86400,'Comet comet'],
    [400*86400,'Galaxy galaxy'],
] as [$age,$title]) {
    $ts=gmdate('c',$now-$age);
    $scan->execute([$source,$ts,$ts]);$scanId=(int)$db->lastInsertId();
    $headline->execute([$source,'https://example.org/'.urlencode($title),$title,$ts,$ts]);$headlineId=(int)$db->lastInsertId();
    $appearance->execute([$scanId,$headlineId]);
}
$ignored=WordStats::ignored($db);
$nowRows=array_column(WordStats::trendingSeconds($db,900,$ignored),null,'word');
if(($nowRows['solar']['count']??0)!==2 || $nowRows['solar']['direction']!=='new'){
    fwrite(STDERR,"FAIL: NOW must compare most recent 15 minutes with previous 15 minutes\n");exit(1);
}
$beforeNow=WordStats::counts($db,$ignored,$now-1800,$now-900);
if(($beforeNow['lunar']??0)!==2 || isset($beforeNow['solar'])){
    fwrite(STDERR,"FAIL: previous 15-minute boundary\n");exit(1);
}
$monthRows=array_column(WordStats::trendingSeconds($db,30*86400,$ignored),null,'word');
if(!isset($monthRows['solar']) || isset($monthRows['comet'])){
    fwrite(STDERR,"FAIL: 30-day comparison must exclude 40-day-old headlines from current list\n");exit(1);
}
$yearRows=array_column(WordStats::trendingSeconds($db,365*86400,$ignored),null,'word');
if(($yearRows['comet']['count']??0)!==2 || isset($yearRows['galaxy'])){
    fwrite(STDERR,"FAIL: 365-day current window and preceding-year split\n");exit(1);
}
$previousYear=WordStats::counts($db,$ignored,$now-730*86400,$now-365*86400);
if(($previousYear['galaxy']??0)!==2 || isset($previousYear['comet'])){
    fwrite(STDERR,"FAIL: preceding 365 days must be used as year comparison\n");exit(1);
}
echo "PASS: six windows, NOW 15-minute comparison, 30-day and 365-day lookback\n";
