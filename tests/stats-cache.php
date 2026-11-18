#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';

use HeadlineScout\App;
use HeadlineScout\WordStats;
use HeadlineScout\StatsCache;

function ensure(bool $condition,string $message):void {
    if (!$condition) {fwrite(STDERR,"FAIL: $message\n");exit(1);}
}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
App::install($db);
$source=(int)$db->query('SELECT id FROM sources ORDER BY id LIMIT 1')->fetchColumn();
$stamp=gmdate('c',time()-40);
$scan=$db->prepare("INSERT INTO scans(source_id,started_at,finished_at,status,http_status,found,added) VALUES(?,?,?,'success',200,1,1)");
$headline=$db->prepare('INSERT INTO headlines(source_id,url,title,first_seen_at,last_seen_at,is_current) VALUES(?,?,?,?,?,1)');
$appearance=$db->prepare('INSERT INTO appearances(scan_id,headline_id,position) VALUES(?,?,1)');
$add=function(string $title) use ($scan,$headline,$appearance,$source,$stamp,$db): void {
    $scan->execute([$source,$stamp,$stamp]);$scanId=(int)$db->lastInsertId();
    $headline->execute([$source,'https://example.org/'.str_replace(' ','-',strtolower($title)),$title,$stamp,$stamp]);
    $appearance->execute([$scanId,(int)$db->lastInsertId()]);
};
$add('Rocket economy grows');
$ignored=WordStats::ignored($db);
$dir=sys_get_temp_dir().'/headlinescout-cache-test-'.getmypid();
ensure(mkdir($dir,0700),'make isolated cache directory');
try {
    ensure(StatsCache::TTL===['now'=>30,'hour'=>60,'day'=>300,'week'=>900,'month'=>3600,'year'=>21600],'specified independent TTL values');
    $nowSnapshot=StatsCache::period($db,'now',$ignored,$dir);
    ensure($nowSnapshot['expires_at']-$nowSnapshot['calculated_at']===30,'NOW refreshes every 30 seconds, while its ranking window stays 15 minutes');
    $first=StatsCache::period($db,'hour',$ignored,$dir);
    ensure(($first['top']['rocket']??null)===1,'first snapshot has rocket term');
    ensure($first['rows']===WordStats::snapshot($db,3600,$ignored,$first['calculated_at'])['rows'],'cached rows match original calculation');
    ensure($first['expires_at']-$first['calculated_at']===60,'hour cache expires in one minute');
    $add('Satellite economy develops');
    $warm=StatsCache::period($db,'hour',$ignored,$dir);
    ensure($warm['top']===$first['top'],'repeat read uses warm cached snapshot');
    $day=StatsCache::period($db,'day',$ignored,$dir);
    ensure(($day['top']['satellite']??null)===1,'independent day cache captures newer data');
    $file=$dir.'/word-hour.json';
    $expired=json_decode(file_get_contents($file),true);
    $expired['expires_at']=0;
    file_put_contents($file,json_encode($expired));
    $recomputed=StatsCache::period($db,'hour',$ignored,$dir);
    ensure(($recomputed['top']['satellite']??null)===1,'expired cache is recomputed');
    WordStats::add($db,'satellite');
    $modifiedIgnores=WordStats::ignored($db);
    $invalidated=StatsCache::period($db,'hour',$modifiedIgnores,$dir);
    ensure(!isset($invalidated['top']['satellite']),'ignore-list change invalidates warm cache');
    ensure($invalidated['ignore_hash']!==$recomputed['ignore_hash'],'cache marker changes with ignore list');
    ensure(StatsCache::period($db,'year',$ignored,$dir)['expires_at']-time()<=21600,'year period uses six-hour ttl');
    echo "PASS: independent cache TTLs, source snapshots, warm hits, expiry, ignore-list invalidation\n";
} finally {
    foreach(glob($dir.'/*')?:[] as $file) @unlink($file);
    @rmdir($dir);
}
