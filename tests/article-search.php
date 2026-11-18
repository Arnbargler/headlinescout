#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';
require dirname(__DIR__).'/src/ArticleSearch.php';

use HeadlineScout\App;
use HeadlineScout\ArticleSearch;

function verify(bool $ok,string $message):void {
    if (!$ok) {fwrite(STDERR,"FAIL: $message\n");exit(1);}
}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
App::install($db);
$sources=$db->query('SELECT id FROM sources ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
[$first,$second]=array_map('intval',$sources);
$now=time();
$add=function(int $source,string $title,int $age,int $position=1,?int $existingId=null) use ($db,$now):int {
    $ts=gmdate('c',$now-$age);
    if ($existingId===null) {
        $h=$db->prepare('INSERT INTO headlines(source_id,url,title,first_seen_at,last_seen_at,is_current) VALUES(?,?,?,?,?,1)');
        $h->execute([$source,'https://example.org/item-'.bin2hex(random_bytes(8)),$title,$ts,$ts]);
        $existingId=(int)$db->lastInsertId();
    }
    $scan=$db->prepare("INSERT INTO scans(source_id,started_at,finished_at,status,http_status,found,added) VALUES(?,?,?,'success',200,3,1)");
    $scan->execute([$source,$ts,$ts]);
    $db->prepare('INSERT INTO appearances(scan_id,headline_id,position) VALUES(?,?,?)')
        ->execute([(int)$db->lastInsertId(),$existingId,$position]);
    return $existingId;
};
$shared=$add($first,'War summit opens in Geneva',80);
$add($first,'War summit opens in Geneva',40,1,$shared); // repeated appearance should dedupe
$add($second,'War negotiations resume today',50);
$add($first,'Award ceremony held downtown',50); // substring, not whole word
$add($first,'War-time plans announced today',49); // hyphenated token, not standalone war
$add($first,'War headline in fourth place',55,4); // old scan beyond retained top 3
$add($first,'War reports from earlier today',4000); // outside 1 hour
$hour=ArticleSearch::search($db,'war','hour',0,1,$now);
verify(count($hour['items'])===2,'find two distinct top-three headlines inside current hour; got '.count($hour['items']).' results: '.json_encode(array_column($hour['items'],'title'),JSON_UNESCAPED_UNICODE));

verify($hour['items'][0]['title']==='War summit opens in Geneva','newest matching appearance orders first');
verify(count(array_unique(array_column($hour['items'],'id')) )===2,'repeated scan appearances deduplicated');
verify(!$hour['has_more'],'no extra page for two matches');
verify(count(ArticleSearch::search($db,'war','hour',$second,1,$now)['items'])===1,'source selector scopes historical results');
verify(count(ArticleSearch::search($db,'war','day',0,1,$now)['items'])===3,'day period includes older matching headline');
verify(ArticleSearch::periods()['now']['seconds']===900,'NOW drilldown represents 15 minutes');
verify(ArticleSearch::periods()['year']['seconds']===31536000,'year drilldown represents 365 days');
verify(count(ArticleSearch::search($db,'award','hour',0,1,$now)['items'])===1,'independent whole-word search');
verify(ArticleSearch::search($db,'war','hour',0,2,$now)['items']===[],'empty page when beyond result set');
echo "PASS: historical word search, windows, source filtering, top-three only, deduplication\n";
