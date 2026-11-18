<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';
use HeadlineScout\App;
$db=App::db(); App::install($db);
function e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
$activeTab='articles';
$sourceFilter=filter_input(INPUT_GET,'source',FILTER_VALIDATE_INT) ?: 0;
$q=trim((string)($_GET['q']??''));
$sources=$db->query('SELECT id,name,last_success_at,last_status FROM sources WHERE enabled=1 ORDER BY id')->fetchAll();
$ignored=\HeadlineScout\WordStats::ignored($db);
$trendingWords=[];
foreach ([1,24,168] as $hours) {
    foreach (\HeadlineScout\WordStats::top($db,$hours,$ignored) as $word=>$count) {
        $trendingWords[mb_strtolower((string)$word,'UTF-8')]=true;
    }
}
$trendingOnly=(($_GET['trending']??'')==='1');
$matchesTrending=static function(string $title) use($trendingWords): bool {
    if (!$trendingWords) return false;
    preg_match_all("/[\\p{L}\\p{N}]+(?:['’\\-][\\p{L}\\p{N}]+)*/u",mb_strtolower($title,'UTF-8'),$parts);
    foreach($parts[0] as $word) if(isset($trendingWords[$word])) return true;
    return false;
};
$sections=[];$visibleCount=0;
$latest=$db->prepare("SELECT id,finished_at FROM scans WHERE source_id=? AND status='success' ORDER BY id DESC LIMIT 1");
$top=$db->prepare('SELECT h.title,h.url,h.first_seen_at,a.position FROM appearances a JOIN headlines h ON h.id=a.headline_id WHERE a.scan_id=? ORDER BY a.position ASC LIMIT 5');
foreach($sources as $source){
    if($sourceFilter && $sourceFilter!==(int)$source['id']) continue;
    $latest->execute([$source['id']]);$scan=$latest->fetch();$items=[];
    if($scan){$top->execute([$scan['id']]);$items=$top->fetchAll();}
    if($q!=='')$items=array_values(array_filter($items,fn($h)=>mb_stripos($h['title'],$q)!==false));
    if($trendingOnly)$items=array_values(array_filter($items,fn($h)=>$matchesTrending($h['title'])));
    $visibleCount+=count($items);
    $sections[]=['source'=>$source,'scan'=>$scan,'items'=>$items];
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="300">
<title>Articles | HeadlineScout</title><link rel="stylesheet" href="style.css"></head><body>
<?php require __DIR__.'/_chrome.php'; ?>
<main class="shell articles-page">
<section class="panel">
<div class="panel-head"><h2>CURRENT FRONT PAGES</h2><span><?=$visibleCount?> headlines · up to 5 per source</span></div>
<form method="get" class="filters">
<label>Source <select name="source"><option value="0">All sources</option><?php foreach($sources as $s): ?><option value="<?=(int)$s['id']?>" <?=$sourceFilter===(int)$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach; ?></select></label>
<label class="search">Search current headlines <input type="search" name="q" placeholder="Filter displayed headlines" value="<?=e($q)?>"></label>
<label class="trend-toggle"><input type="checkbox" name="trending" value="1" <?=$trendingOnly?'checked':''?> onchange="this.form.requestSubmit()"> <span>Only headlines with top-count words</span></label>
<button type="submit">Filter</button></form>
<?php if(!$sections): ?><div class="empty">No sources configured. Add one in Settings.</div><?php endif;?>
<?php foreach($sections as $section): $s=$section['source']; $scan=$section['scan']; $items=$section['items']; ?>
<div class="feed-source">
<div class="feed-source-heading">
<h3><?=e($s['name'])?></h3>
<span><?= $scan ? 'Last successful scan: '.e(gmdate('M j, H:i',strtotime($scan['finished_at']))).' UTC' : 'Not yet scanned' ?></span>
</div>
<?php if(!$items): ?>
<div class="source-empty"><?= ($q!=='' || $trendingOnly) ? 'No headlines match the selected filters in this source’s latest top 5.' : ($scan ? 'No headlines found in its most recent successful scan.' : 'No successful scan yet. Check Settings.') ?></div>
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
<footer>HeadlineScout · Current front pages · Latest 5 headlines per source</footer>
</main><script src="/ticker.js" defer></script></body></html>
