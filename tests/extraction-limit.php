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

// Titles are shortened before saving and before Source Lab previews them.
$cases=[
    ['Major announcement shakes the financial markets. Investors react overnight.','Major announcement shakes the financial markets.'],
    ['Officials ask what happens next? Experts respond with concern.','Officials ask what happens next?'],
    ['Markets rally 2.5% after announcement. Analysts weigh in.','Markets rally 2.5% after announcement.'],
    ['Dr. Smith discusses the new satellite. Experts respond.','Dr. Smith discusses the new satellite.'],
    ['U.S. President announces sweeping policy. Critics respond.','U.S. President announces sweeping policy.'],
    ['A single sentence with no trailing punctuation','A single sentence with no trailing punctuation'],
    ['An urgent statement! Details will follow.','An urgent statement!'],
];
foreach($cases as [$title,$expected]) {
    if(App::firstSentence($title)!==$expected) {
        fwrite(STDERR,"FAIL: headline sentence truncation for: $title\n");
        exit(1);
    }
}
$multisentence='<h2><a href="/news/combined">Headline begins with the central announcement. The second sentence is extra context.</a></h2>';
$extracted=App::extract('<html><body>'.$multisentence.'</body></html>','https://example.org/',$selector);
if(count($extracted)!==1 || $extracted[0]['title']!=='Headline begins with the central announcement.') {
    fwrite(STDERR,"FAIL: extraction must store only the first sentence\n");
    exit(1);
}

echo "PASS: extraction limits and first-sentence headline cleanup\n";
