#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';
use HeadlineScout\App;

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
App::install($db);
foreach(['dashboard','articles','settings'] as $tab) {
    $activeTab=$tab;
    ob_start();
    include dirname(__DIR__).'/public/_chrome.php';
    $html=ob_get_clean();
    foreach(['/articles.php','/settings.php','TOP HEADLINES','/ticker.php'] as $expected) {
        // ticker.js fetches /ticker.php; HTML includes the shared ticker track.
        if($expected==='/ticker.php') $expected='headline-ticker-track';
        if(!str_contains($html,$expected)){
            fwrite(STDERR,"FAIL: $tab missing shared chrome element $expected\n");
            exit(1);
        }
    }
    $label=['dashboard'=>'Dashboard','articles'=>'Articles','settings'=>'Settings'][$tab];
    if(substr_count($html,'aria-current="page"')!==1 ||
       !str_contains($html,'class="section-tab active" aria-current="page">'.$label.'</a>')){
        fwrite(STDERR,"FAIL: active tab indicator for $tab\n");
        exit(1);
    }
}
echo "PASS: shared ticker, page tabs, and selected tab across all three views\n";
