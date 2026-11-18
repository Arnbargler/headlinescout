#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';

use HeadlineScout\App;

// By default this only reports proposed changes. Run --apply after making a
// SQLite backup to update previously stored titles without changing history.
$apply=in_array('--apply',$argv,true);
if (array_diff(array_slice($argv,1),['--apply'])) {
    fwrite(STDERR,"Usage: php bin/clean-headlines.php [--apply]\n");
    exit(1);
}
$db=App::db();
App::install($db);
$lock=fopen(dirname(__DIR__).'/var/scan.lock','c');
if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) {
    fwrite(STDERR,"Another scan/repair is running; retry when it finishes.\n");
    exit(2);
}
try {
    $query=$db->query('SELECT id,title FROM headlines ORDER BY id');
    $update=$apply ? $db->prepare('UPDATE headlines SET title=? WHERE id=?') : null;
    $checked=0;$changed=0;$examples=0;
    if($apply) $db->beginTransaction();
    try {
        while($row=$query->fetch(PDO::FETCH_ASSOC)) {
            $checked++;
            $clean=App::firstSentence($row['title']);
            if ($clean==='' || $clean===$row['title']) continue;
            $changed++;
            if($examples<15){
                echo '#'.$row['id']." BEFORE: ".$row['title']."\n";
                echo "     AFTER: ".$clean."\n";
                $examples++;
            }
            if($apply) $update->execute([$clean,$row['id']]);
        }
        if($apply) $db->commit();
    } catch(Throwable $e) {
        if($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    printf("%s: checked %d stored headlines; %d %s.\n",
        $apply?'APPLIED':'DRY RUN',$checked,$changed,$apply?'updated':'would change');
    if(!$apply) echo "No data was modified. Back up var/headlines.sqlite, then run: php bin/clean-headlines.php --apply\n";
} finally {
    flock($lock,LOCK_UN);
    fclose($lock);
}
