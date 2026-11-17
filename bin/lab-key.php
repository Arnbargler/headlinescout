#!/usr/bin/env php
<?php
declare(strict_types=1);
$file=dirname(__DIR__).'/var/source-lab.key';
if (!is_dir(dirname($file))) mkdir(dirname($file),0770,true);
if (!is_file($file)) {
    file_put_contents($file,bin2hex(random_bytes(24))."\n",LOCK_EX);
    chmod($file,0600);
}
echo "Source Lab access key: " . trim(file_get_contents($file)) . PHP_EOL;
echo "Keep this private. It unlocks source testing and editing on your LAN dashboard.\n";
