#!/usr/bin/env php
<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';
use HeadlineScout\App;

$html='<!doctype html><html><body><main>';
foreach(range(1,9) as $n){
    $html.='<h2><a href="/news/story-'.$n.'">Important headline number '.$n.' is here</a></h2>';
}
$html.='</main></body></html>';
$selector='//a[.//h1 or .//h2 or .//h3 or ancestor::h1 or ancestor::h2 or ancestor::h3]';
$all=App::extract($html,'https://example.org/',$selector);
$limited=App::extract($html,'https://example.org/',$selector,5);
if(count($all)!==9 || count($limited)!==5 || !str_ends_with($limited[4]['url'],'story-5')){
    fwrite(STDERR,"FAIL: extraction limit or page order\n"); exit(1);
}
echo "PASS: scan retains first five links; lab preview still returns full candidates\n";
