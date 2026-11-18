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
$titles=[
    'Rocket rocket rocket market rally',
    'Rocket market',
    'Rocket rocket market',
    'Market report today',
    'Old page rocket rocket rocket rocket'
];
$scan->execute([$sourceId,$now,$now]);$scanId=(int)$db->lastInsertId();
foreach($titles as $i=>$title){
    $headline->execute([$sourceId,'https://example.com/story-'.$i,$title,$now,$now]);
    $id=(int)$db->lastInsertId();
    if($i<4)$appearance->execute([$scanId,$id,$i+1]);
}
$items=Ticker::items($db);
check(count($items)===3,'Ticker should pick exactly three from latest appearances');
check($items[0]['title']===$titles[0],'Highest matching-word count wins');
check($items[1]['title']===$titles[2],'Two rocket mentions beats one');
check($items[2]['title']===$titles[1],'Third ranking uses matching mentions');
check(!in_array($titles[4],array_column($items,'title'),true),'Do not include archived/noncurrent headlines');
echo "PASS: ticker ranking, current scan appearances and top-three limit\n";
