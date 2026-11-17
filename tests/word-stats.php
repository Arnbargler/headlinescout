#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/WordStats.php';
use HeadlineScout\WordStats;

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE settings(key TEXT PRIMARY KEY,value TEXT NOT NULL)');
$db->exec('CREATE TABLE headlines(id INTEGER PRIMARY KEY,title TEXT NOT NULL,first_seen_at TEXT NOT NULL)');
WordStats::install($db);
$now=gmdate('c');
$insert=$db->prepare('INSERT INTO headlines(title,first_seen_at) VALUES(?,?)');
$insert->execute(['The rocket launch with rocket boosters',$now]);
$insert->execute(['Breaking News about the rocket',gmdate('c',time()-7200)]);
$insert->execute(['Rocket launch after historic test',gmdate('c',time()-3*86400)]);
$insert->execute(['Rocket technology from last month',gmdate('c',time()-35*86400)]);
WordStats::add($db,'rocket launch');
$ignored=WordStats::ignored($db);
$one=WordStats::top($db,1,$ignored);
$day=WordStats::top($db,24,$ignored);
$week=WordStats::top($db,168,$ignored);
function verify(bool $yes,string $message):void {
    if (!$yes) {fwrite(STDERR,"FAIL: $message\n");exit(1);}
}
verify(!isset($one['rocket']),'Ignored phrase should remove rocket from phrase in latest title');
verify(isset($one['boosters'])&&$one['boosters']===1,'Word after ignored phrase must remain');
verify(isset($day['rocket'])&&$day['rocket']===1,'Yesterday rocket mention counted once');
verify(($week['rocket']??0)===2,'7-day range counts unique titles without old month');
verify(!isset($week['the'])&&!isset($week['with']),'Default ignored words are omitted');
$id=$db->query("SELECT id FROM ignored_terms WHERE term='rocket launch'")->fetchColumn();
verify(WordStats::remove($db,(int)$id),'Ignore phrase should be removable');
verify(($week=WordStats::top($db,168,WordStats::ignored($db)))['rocket']===3,'Removed phrase restores counts');
echo "PASS: word frequency, time windows, ignored words and phrases\n";
