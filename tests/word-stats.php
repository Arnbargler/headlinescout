#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';
use HeadlineScout\App;
use HeadlineScout\WordStats;

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
App::install($db);
function verify(bool $ok,string $message):void {
    if (!$ok) {fwrite(STDERR,"FAIL: $message\n");exit(1);}
}
$source=(int)$db->query('SELECT id FROM sources ORDER BY id LIMIT 1')->fetchColumn();
$insertHeadline=$db->prepare('INSERT INTO headlines(source_id,url,title,first_seen_at,last_seen_at,is_current) VALUES(?,?,?,?,?,1)');
$insertScan=$db->prepare("INSERT INTO scans(source_id,started_at,finished_at,status,http_status,found,added) VALUES(?,?,?,'success',200,0,0)");
$insertAppearance=$db->prepare('INSERT INTO appearances(scan_id,headline_id,position) VALUES(?,?,?)');
$now=time();
$headlineIds=[];
$add=function (int $minutesAgo,array $titles) use ($db,$source,$now,$insertHeadline,$insertScan,$insertAppearance,&$headlineIds): void {
    $ts=gmdate('c',$now-$minutesAgo*60);
    $insertScan->execute([$source,$ts,$ts]);
    $scanId=(int)$db->lastInsertId();
    foreach($titles as $position=>$title){
        if (!isset($headlineIds[$title])) {
            $insertHeadline->execute([$source,'https://example.org/item-'.count($headlineIds),$title,$ts,$ts]);
            $headlineIds[$title]=(int)$db->lastInsertId();
        }
        $insertAppearance->execute([$scanId,$headlineIds[$title],$position+1]);
    }
};
$add(30,['Rocket market rocket launch','Market economy market','Drone economy','Tech progress']);
$add(15,['Rocket market rocket launch']); // same headline in second scan, counts only once
$add(90,['Rocket rocket drone','Market drone','Tech progress previous']);
$add(150,['Market tech']);
$add(26*60,['Drone tech yesterday']);
$add(3*24*60,['Tech market week']);
$add(9*24*60,['Drone from earlier week']);

WordStats::add($db,'rocket launch');
$ignore=WordStats::ignored($db);
$current=WordStats::top($db,1,$ignore);
$previous=WordStats::counts($db,$ignore,$now-7200,$now-3600);
verify(($current['market']??0)===3,'Current hour counts 3 market occurrences without repeat scans');
verify(($current['rocket']??0)===1,'Ignore phrase removes rocket launch but keeps standalone rocket');
verify(($previous['rocket']??0)===2,'Previous hour remains accessible after a more recent scan');
verify(($current['economy']??0)===2,'Current hour counts economy');
$rows=WordStats::trendingSeconds($db,3600,$ignore);
$byWord=array_column($rows,null,'word');
verify($byWord['market']['direction']==='up','Market trends up vs preceding hour');
verify($byWord['rocket']['direction']==='down','Rocket trends down vs preceding hour');
verify($byWord['tech']['direction']==='flat','Tech maintains same mention count');
verify($byWord['economy']['direction']==='new','Economy is new to previous top 10');
verify($byWord['market']['percent']===100.0,'Top count always receives full bar');
verify($byWord['economy']['percent']<100,'Smaller count receives shorter bar');
$day=WordStats::top($db,24,$ignore);
verify(($day['market']??0)===5,'24-hour window counts all unique relevant headlines');
verify(!isset($day['the']),'Stop words are excluded');
$dayTrend=array_column(WordStats::trendingSeconds($db,24*3600,$ignore),null,'word');
$weekTrend=array_column(WordStats::trendingSeconds($db,168*3600,$ignore),null,'word');
verify($dayTrend['tech']['direction']==='up','24-hour trend compares to immediately preceding day');
verify($weekTrend['drone']['direction']==='up','7-day trend compares to immediately preceding week');
$id=$db->query("SELECT id FROM ignored_terms WHERE term='rocket launch'")->fetchColumn();
verify(WordStats::remove($db,(int)$id),'Ignore phrase can be removed');
verify((WordStats::top($db,1,WordStats::ignored($db))['rocket']??0)===2,'Removing ignored phrase restores rocket count');
echo "PASS: rolling periods, comparisons, deduplication, ignored phrases and proportional bars\n";
