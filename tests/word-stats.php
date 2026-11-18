#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/WordStats.php';
use HeadlineScout\WordStats;

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE settings(key TEXT PRIMARY KEY,value TEXT NOT NULL)');
$db->exec('CREATE TABLE headlines(id INTEGER PRIMARY KEY,title TEXT NOT NULL,first_seen_at TEXT NOT NULL,last_seen_at TEXT NOT NULL)');
WordStats::install($db);
$now=gmdate('c');
$insert=$db->prepare('INSERT INTO headlines(title,first_seen_at,last_seen_at) VALUES(?,?,?)');
$insert->execute(['The rocket launch with rocket boosters',gmdate('c',time()-2*86400),$now]); // old headline still present
$insert->execute(['Breaking News about the rocket',gmdate('c',time()-7200),gmdate('c',time()-7200)]);
$insert->execute(['Rocket launch after historic test',gmdate('c',time()-3*86400),gmdate('c',time()-3*86400)]);
$insert->execute(['Rocket technology from last month',gmdate('c',time()-35*86400),gmdate('c',time()-35*86400)]);
WordStats::add($db,'rocket launch');
$ignored=WordStats::ignored($db);
$one=WordStats::top($db,1,$ignored);
$day=WordStats::top($db,24,$ignored);
$week=WordStats::top($db,168,$ignored);
function verify(bool $yes,string $message):void {
    if (!$yes) {fwrite(STDERR,"FAIL: $message\n");exit(1);}
}
verify(($one['rocket']??0)===1,'Old headline still observed within last hour counts, ignoring matching phrase');
verify(isset($one['boosters'])&&$one['boosters']===1,'Word after ignored phrase must remain');
verify(($day['rocket']??0)===2,'Twenty-four-hour window includes two distinct rocket mentions');
verify(($week['rocket']??0)===2,'7-day range excludes ignored phrase and older month');
verify(!isset($week['the'])&&!isset($week['with']),'Default ignored words are omitted');
$id=$db->query("SELECT id FROM ignored_terms WHERE term='rocket launch'")->fetchColumn();
verify(WordStats::remove($db,(int)$id),'Ignore phrase should be removable');
verify(($week=WordStats::top($db,168,WordStats::ignored($db)))['rocket']===4,'Removed phrase restores counts');
echo "PASS: word frequency, time windows, ignored words and phrases\n";
