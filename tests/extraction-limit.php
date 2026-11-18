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
$limited=App::extract($html,'https://example.org/',$selector,3);
if(count($all)!==9 || count($limited)!==3 || !str_ends_with($limited[2]['url'],'story-3')){
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
    ['Mary Peltola defeats Republican Dan Sullivan in Alaska Senate race chevron_right','Mary Peltola defeats Republican Dan Sullivan in Alaska Senate race'],
    ['A valid headline about the economy chevron_right chevron_right','A valid headline about the economy'],
    ['A headline about chevron_right icon rendering','A headline about chevron_right icon rendering'],
    ['.css-1f6sr3w{color:#727272;}Gallery | Robots on show in Shenzhen ahead of Apec China 2026 summit',
     'Robots on show in Shenzhen ahead of Apec China 2026 summit'],
    ['US Test-Fires Nuclear-Capable ICBM Missile Ahead of Trump, Putin, Xi SummitNov 18, 2026, 21:09 IST',
     'US Test-Fires Nuclear-Capable ICBM Missile Ahead of Trump, Putin, Xi Summit'],
    ['Why Microsoft, JP Morgan, the FAA and the NSA All Went DownNov 18, 2026, 20:33 IST',
     'Why Microsoft, JP Morgan, the FAA and the NSA All Went Down'],
    ['.css-abc{color:red;} .css-def{font-weight:bold;} Some important headline appears',
     'Some important headline appears'],
    ['The CSS specification changes how .css-abc selectors work',
     'The CSS specification changes how .css-abc selectors work'],
    ['Apec China 2026 summit planned for Shenzhen','Apec China 2026 summit planned for Shenzhen'],
    ['Nov 18, 2026, 21:09 IST','Nov 18, 2026, 21:09 IST'],
    ['Date Nov 18, 2026 marks the beginning of voting','Date Nov 18, 2026 marks the beginning of voting'],
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

$google='<h2><a href="/news/google-story">Mary Peltola defeats Republican Dan Sullivan in Alaska Senate race chevron_right</a></h2>';
$googleResult=App::extract('<html><body>'.$google.'</body></html>','https://example.org/',$selector);
if(count($googleResult)!==1 || $googleResult[0]['title']!=='Mary Peltola defeats Republican Dan Sullivan in Alaska Senate race') {
    fwrite(STDERR,"FAIL: Google News chevron icon should not be stored\n");
    exit(1);
}
echo "PASS: extraction limits, first sentence, CSS leakage, timestamp and chevron cleanup\n";
