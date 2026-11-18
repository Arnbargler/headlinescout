<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/App.php';
require dirname(__DIR__) . '/src/Ticker.php';
use HeadlineScout\App;
$db=App::db(); App::install($db);
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
$sourceFilter=filter_input(INPUT_GET,'source',FILTER_VALIDATE_INT) ?: 0;
$q=trim((string)($_GET['q']??''));
$sources=$db->query('SELECT id,name,last_success_at,last_status FROM sources WHERE enabled=1 ORDER BY id')->fetchAll();
$lastScanAt=$sources ? max(array_map(static fn($s)=>strtotime((string)($s['last_success_at']??''))?:0,$sources)) : 0;
$scansStale=$lastScanAt>0 && time()-$lastScanAt>3600;
$ignored=\HeadlineScout\WordStats::ignored($db);
$wordStats=[];
foreach ([1=>'1 hour',24=>'24 hours',168=>'7 days'] as $hours=>$label) {
    $wordStats[]=['label'=>$label,'words'=>\HeadlineScout\WordStats::top($db,$hours,$ignored)];
}
// Union of the three displayed top-ten word lists; ignore repeated words.
$trendingWords=[];
foreach ($wordStats as $period) {
    foreach ($period['words'] as $word=>$count) $trendingWords[mb_strtolower((string)$word,'UTF-8')]=true;
}
$tickerItems=\HeadlineScout\Ticker::items($db);
$trendingOnly=(($_GET['trending']??'')==='1');
$matchesTrending=static function (string $title) use ($trendingWords): bool {
    if (!$trendingWords) return false;
    preg_match_all("/[\\p{L}\\p{N}]+(?:['’\\-][\\p{L}\\p{N}]+)*/u", mb_strtolower($title,'UTF-8'), $parts);
    foreach ($parts[0] as $word) {
        if (isset($trendingWords[$word])) return true;
    }
    return false;
};
$sections=[];
$visibleCount=0;
// For each source, use its most recent SUCCESSFUL scan, not the complete historical
// headline collection. Limit to ten BEFORE applying the optional dashboard search.
$latest=$db->prepare("SELECT id,finished_at FROM scans WHERE source_id=? AND status='success' ORDER BY id DESC LIMIT 1");
$top=$db->prepare('SELECT h.title,h.url,h.first_seen_at,a.position FROM appearances a JOIN headlines h ON h.id=a.headline_id WHERE a.scan_id=? ORDER BY a.position ASC LIMIT 10');
foreach ($sources as $source) {
    if ($sourceFilter && $sourceFilter!==(int)$source['id']) continue;
    $latest->execute([$source['id']]);
    $scan=$latest->fetch();
    $items=[];
    if ($scan) {
        $top->execute([$scan['id']]);
        $items=$top->fetchAll();
    }
    if ($q!=='') {
        $items=array_values(array_filter($items,fn($h)=>mb_stripos($h['title'],$q)!==false));
    }
    if ($trendingOnly) {
        $items=array_values(array_filter($items,fn($h)=>$matchesTrending($h['title'])));
    }
    $visibleCount+=count($items);
    $sections[]=['source'=>$source,'scan'=>$scan,'items'=>$items];
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="300">
<title>HeadlineScout</title><link rel="stylesheet" href="style.css"></head>
<body>
<header><div class="shell top"><h1 class="site-title">Headline<span>Scout</span></h1><a class="header-button" href="/lab.php"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06-1.91 1.91-.06-.06A1.65 1.65 0 0 0 16 18.4a1.65 1.65 0 0 0-1 1.52V20h-2.7v-.08a1.65 1.65 0 0 0-1.06-1.52 1.65 1.65 0 0 0-1.81.33l-.06.06-1.91-1.91.06-.06A1.65 1.65 0 0 0 7.86 15a1.65 1.65 0 0 0-1.52-1H6v-2.7h.34a1.65 1.65 0 0 0 1.52-1.06 1.65 1.65 0 0 0-.33-1.81l-.06-.06 1.91-1.91.06.06a1.65 1.65 0 0 0 1.81.33A1.65 1.65 0 0 0 12.3 5.3V5h2.7v.3a1.65 1.65 0 0 0 1.06 1.52 1.65 1.65 0 0 0 1.81-.33l.06-.06 1.91 1.91-.06.06a1.65 1.65 0 0 0-.33 1.81 1.65 1.65 0 0 0 1.52 1.06H21v2.7h-.03A1.65 1.65 0 0 0 19.4 15Z"/></svg><span>Settings</span></a></div></header>
<div class="ticker-bar" aria-label="Top three trending headlines">
  <div class="shell ticker-shell">
    <span class="ticker-caption">TOP HEADLINES</span>
    <div class="ticker-viewport">
      <div id="headline-ticker-track" class="ticker-track <?=$tickerItems?'ticker-scroll':''?>" data-signature="<?=e(json_encode($tickerItems, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>">
        <?php if(!$tickerItems): ?><span class="ticker-empty">No trending headlines yet — scan a source to begin.</span><?php else: ?>
        <?php foreach($tickerItems as $headline): ?>
        <a class="ticker-story" href="<?=e($headline['url'])?>" target="_blank" rel="noopener noreferrer"><strong><?=e($headline['source'])?> · </strong><?=e($headline['title'])?></a>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<main class="shell">
<section class="word-stats">
<div class="word-stats-title"><h2>WORD COUNT</h2><span>Headline titles first collected within each rolling period · ignored words excluded</span></div>
<div class="word-stats-grid">
<?php foreach($wordStats as $period): ?>
<div class="word-period"><h3><?=e($period['label'])?></h3>
<?php if(!$period['words']):?><p class="word-empty">No matching words in this period.<?=($period['label']==='1 hour'&&$scansStale)?' The most recent successful scan is over an hour old; check cron or scan a source in Settings.':''?></p><?php else:?>
<ol class="word-ranking"><?php foreach($period['words'] as $word=>$count):?>
<li><span class="word-label"><?=e($word)?></span><strong class="word-total"><?=(int)$count?></strong></li>
<?php endforeach;?></ol>
<?php endif;?></div>
<?php endforeach;?>
</div></section>

<section class="panel">
<div class="panel-head"><h2>CURRENT FRONT PAGES</h2><span><?=$visibleCount?> headlines · up to 10 per source</span></div>
<form method="get" class="filters">
<label>Source <select name="source"><option value="0">All sources</option><?php foreach($sources as $s): ?><option value="<?=(int)$s['id']?>" <?=$sourceFilter===(int)$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach; ?></select></label>
<label class="search">Search current headlines <input type="search" name="q" placeholder="Filter displayed headlines" value="<?=e($q)?>"></label>
<label class="trend-toggle"><input type="checkbox" name="trending" value="1" <?=$trendingOnly?'checked':''?> onchange="this.form.requestSubmit()"> <span>Only headlines with top-count words</span></label>
<button type="submit">Filter</button></form>
<?php if(!$sections): ?><div class="empty">No sources configured. Add one in Source Lab.</div><?php endif;?>
<?php foreach($sections as $section): $s=$section['source']; $scan=$section['scan']; $items=$section['items']; ?>
<div class="feed-source">
<div class="feed-source-heading">
<h3><?=e($s['name'])?></h3>
<span><?= $scan ? 'Last successful scan: '.e(gmdate('M j, H:i',strtotime($scan['finished_at']))).' UTC' : 'Not yet scanned' ?></span>
</div>
<?php if(!$items): ?>
<div class="source-empty"><?= ($q!=='' || $trendingOnly) ? 'No headlines match the selected filters in this source’s latest top 10.' : ($scan ? 'No headlines found in its most recent successful scan.' : 'No successful scan yet. Check Source Lab.') ?></div>
<?php else: ?>
<div class="entries">
<?php foreach($items as $h): ?>
<article class="entry"><div class="bullet on"></div><div class="story">
<a href="<?=e($h['url'])?>" target="_blank" rel="noopener noreferrer"><?=e($h['title'])?></a>
<div class="byline"><span>#<?=(int)$h['position']?> on front page</span><span>First seen <?=e(gmdate('M j, H:i',strtotime($h['first_seen_at'])))?> UTC</span></div></div><span class="out">↗</span></article>
<?php endforeach; ?></div>
<?php endif;?>
</div>
<?php endforeach; ?>
</section>
<footer>HeadlineScout · Latest 10 per source · Historical headlines retained for a future History page · AI analysis coming later</footer>
</main><script src="/ticker.js" defer></script></body></html>
