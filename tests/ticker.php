#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';
require dirname(__DIR__).'/src/Ticker.php';

use HeadlineScout\App;
use HeadlineScout\Ticker;
use HeadlineScout\WordStats;

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
App::install($db);
function check(bool $ok,string $message): void {
    if (!$ok) { fwrite(STDERR,"FAIL: $message\n"); exit(1); }
}
$sourceId=(int)$db->query("SELECT id FROM sources ORDER BY id LIMIT 1")->fetchColumn();
$db->prepare('DELETE FROM sources WHERE id<>?')->execute([$sourceId]);
// Keep the fixture inside the rolling window, not on its exclusive end boundary.
$now=gmdate('c',time()-30);
$headline=$db->prepare('INSERT INTO headlines(source_id,url,title,first_seen_at,last_seen_at,is_current) VALUES(?,?,?,?,?,1)');
$scan=$db->prepare("INSERT INTO scans(source_id,started_at,finished_at,status,http_status,found,added) VALUES(?,?,?,'success',200,4,4)");
$appearance=$db->prepare('INSERT INTO appearances(scan_id,headline_id,position) VALUES(?,?,?)');
// One dominant topic, two distinct alternatives, and a tempting duplicate.
$titles=[
    'Rocket rocket rocket rocket launch',
    str_repeat('Rocket ',20), // over 110 characters; must never be selected
    'Economy economy outlook',
    'Climate climate forecast',
    'Rocket rocket update',
    'Archived rocket rocket rocket rocket rocket'
];
$scan->execute([$sourceId,$now,$now]);$scanId=(int)$db->lastInsertId();
foreach($titles as $i=>$title){
    $headline->execute([$sourceId,'https://example.com/story-'.$i,$title,$now,$now]);
    $id=(int)$db->lastInsertId();
    if($i<5)$appearance->execute([$scanId,$id,$i+1]);
}
$items=Ticker::items($db);
check(count($items)===3,'Ticker should choose exactly three current headlines');
check($items[0]['title']===$titles[0],'First headline must have strongest original word match');
check($items[1]['title']===$titles[2],'Second headline should reward uncovered economy words');
check($items[2]['title']===$titles[3],'Third headline should reward remaining uncovered climate words');
check($items[0]['matches']===5,'Keep the original match count for the first headline');
check(mb_strlen($titles[1],'UTF-8')>110,'Fixture exceeds 110 Unicode characters');
check(!in_array($titles[1],array_column($items,'title'),true),'Long headline must be excluded before ranking');
check(!in_array($titles[5],array_column($items,'title'),true),'Exclude archived noncurrent headlines');

echo "PASS: ticker diversity, ranking, current scan appearances and top-three limit\n";
