<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';
use HeadlineScout\App;
$db=App::db();App::install($db);
function e(mixed $value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$activeTab='dashboard';
$sources=$db->query('SELECT last_success_at FROM sources WHERE enabled=1')->fetchAll();
$lastScanAt=$sources ? max(array_map(static fn($s)=>strtotime((string)($s['last_success_at']??''))?:0,$sources)) : 0;
$scansStale=$lastScanAt>0 && time()-$lastScanAt>3600;
$ignored=\HeadlineScout\WordStats::ignored($db);
$wordStats=[];
foreach (\HeadlineScout\WordStats::periods() as $period){
    $wordStats[]=[
        'key'=>$period['key'],
        'label'=>$period['label'],
        'rows'=>\HeadlineScout\StatsCache::period($db,$period['key'],$ignored)['rows']
    ];
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard | HeadlineScout</title><link rel="stylesheet" href="style.css"></head><body>
<?php require __DIR__.'/_chrome.php'; ?>
<main class="shell dashboard-page">

<section class="word-stats">
<div class="word-stats-title"><h2>WORD COUNT</h2><span>Mentions in unique headlines per period · arrows compare with previous matching period</span></div>
<div class="word-stats-grid">
<?php foreach($wordStats as $period): ?>
<div class="word-period" data-period="<?=e($period['key'])?>"><h3><?=e($period['label'])?></h3>
<?php if(!$period['rows']):?><p class="word-empty">No matching words in this period.<?=($period['key']==='hour'&&$scansStale)?' The most recent successful scan is over an hour old; check cron or scan a source in Settings.':''?></p><?php else:?>
<ol class="word-ranking"><?php foreach($period['rows'] as $entry): ?>
<li class="word-trend-<?=e($entry['direction'])?>" data-word="<?=e($entry['word'])?>" data-count="<?=(int)$entry['count']?>">
  <span class="word-label"><?=e($entry['word'])?></span>
  <span class="word-bar" aria-hidden="true"><span class="word-bar-fill" style="width:<?=e($entry['percent'])?>%"></span></span>
  <strong class="word-total"><?=(int)$entry['count']?></strong>
  <span class="word-direction direction-<?=e($entry['direction'])?>" title="<?=e($entry['direction']==='new'?'Not in previous top 10':('Previous period: '.$entry['previous'].' mentions'))?>" aria-label="<?=e($entry['direction']==='new'?'New to top ten':($entry['direction']==='up'?'Trending up':($entry['direction']==='down'?'Trending down':'No change')))?>">
  <?=match($entry['direction']){'up'=>'↑','down'=>'↓','flat'=>'→',default=>'NEW'}?></span>
</li>
<?php endforeach;?></ol>
<?php endif;?></div>
<?php endforeach;?>
</div></section>


<footer>HeadlineScout · News trends and analysis · More insights coming soon</footer>
</main><script src="/ticker.js" defer></script><script src="/word-stats.js" defer></script></body></html>
