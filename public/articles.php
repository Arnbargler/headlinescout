<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';
require dirname(__DIR__).'/src/ArticleSearch.php';
use HeadlineScout\App;
$db=App::db(); App::install($db);
function e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
$activeTab='articles';
$sourceFilter=filter_input(INPUT_GET,'source',FILTER_VALIDATE_INT) ?: 0;
$q=mb_substr(trim((string)($_GET['q']??'')),0,100);
$periods=\HeadlineScout\ArticleSearch::periods();
$period=(string)($_GET['period']??'');
if (!isset($periods[$period])) $period='';
$historical=$period!=='';
$defaultFeed=!$historical && $q==='';
$page=max(1,min(10000,(int)($_GET['page']??1)));
$history=['items'=>[],'has_more'=>false,'page'=>1];
if ($historical && $q!=='') {
    $history=\HeadlineScout\ArticleSearch::search($db,$q,$period,max(0,$sourceFilter),$page);
}
$sources=$db->query('SELECT id,name,last_success_at,last_status FROM sources WHERE enabled=1 ORDER BY id')->fetchAll();
$trendingOnly=!$historical && (($_GET['trending']??'')==='1');
$trendingWords=[];
if ($trendingOnly) {
    $ignored=\HeadlineScout\WordStats::ignored($db);
    foreach (['hour','day','week'] as $trendPeriod) {
        foreach (\HeadlineScout\StatsCache::period($db,$trendPeriod,$ignored)['top'] as $word=>$count) {
            $trendingWords[mb_strtolower((string)$word,'UTF-8')]=true;
        }
    }
}
$matchesTrending=static function(string $title) use($trendingWords): bool {
    if (!$trendingWords) return false;
    preg_match_all("/[\\p{L}\\p{N}]+(?:['’\\-][\\p{L}\\p{N}]+)*/u",mb_strtolower($title,'UTF-8'),$parts);
    foreach($parts[0] as $word) if(isset($trendingWords[$word])) return true;
    return false;
};
$sections=[];$visibleCount=0;$defaultItems=[];
$latest=$db->prepare("SELECT id,finished_at FROM scans WHERE source_id=? AND status='success' ORDER BY id DESC LIMIT 1");
$top=$db->prepare('SELECT h.title,h.url,h.first_seen_at,a.position FROM appearances a JOIN headlines h ON h.id=a.headline_id WHERE a.scan_id=? ORDER BY a.position ASC LIMIT 3');
if (!$historical) foreach($sources as $source){
    if($sourceFilter && $sourceFilter!==(int)$source['id']) continue;
    $latest->execute([$source['id']]);$scan=$latest->fetch();$items=[];
    if($scan){$top->execute([$scan['id']]);$items=$top->fetchAll();}
    if($defaultFeed){
        // One highest-ranked headline from each source's latest successful scan.
        // Combine sources into one list, newest successful scan first.
        if($items){
            $item=$items[0];
            $item['source_name']=$source['name'];
            $item['scan_at']=$scan['finished_at'];
            $defaultItems[]=$item;
        }
        continue;
    }
    if($q!=='')$items=array_values(array_filter($items,fn($h)=>mb_stripos($h['title'],$q)!==false));
    if($trendingOnly)$items=array_values(array_filter($items,fn($h)=>$matchesTrending($h['title'])));
    $visibleCount+=count($items);
    $sections[]=['source'=>$source,'scan'=>$scan,'items'=>$items];
}
if($defaultFeed){
    usort($defaultItems,static fn(array $a,array $b):int =>
        strcmp($b['scan_at'],$a['scan_at']) ?: strcmp($a['source_name'],$b['source_name'])
    );
    $visibleCount=count($defaultItems);
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php if(!$historical): ?><meta http-equiv="refresh" content="300"><?php endif; ?>
<title>Articles | HeadlineScout</title><link rel="stylesheet" href="style.css"></head><body>
<?php require __DIR__.'/_chrome.php'; ?>
<main class="shell articles-page">
<section class="panel">
<div class="panel-head"><h2><?= $historical?'HISTORICAL HEADLINES':'CURRENT FRONT PAGES' ?></h2><span><?= $historical ? e($periods[$period]['label'].' · '.count($history['items']).' shown') : $visibleCount.($defaultFeed?' sources · latest top headline each':' headlines · up to 3 per source') ?></span></div>
<form method="get" class="filters">
<label>Source <select name="source"><option value="0">All sources</option><?php foreach($sources as $s): ?><option value="<?=(int)$s['id']?>" <?=$sourceFilter===(int)$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach; ?></select></label>
<label class="search">Search headlines <input type="search" name="q" placeholder="Search for a word or phrase" value="<?=e($q)?>"></label>
<label>Time range <select name="period"><option value="">Current front pages</option><?php foreach($periods as $key=>$details): ?><option value="<?=e($key)?>" <?=$period===$key?'selected':''?>><?=e($details['label'])?></option><?php endforeach; ?></select></label>
<?php if(!$historical): ?><label class="trend-toggle"><input type="checkbox" name="trending" value="1" <?=$trendingOnly?'checked':''?> onchange="this.form.requestSubmit()"> <span>Only headlines with top-count words</span></label><?php endif; ?>
<button type="submit">Search</button></form>
<?php if($historical): ?>
<p class="history-context">Showing headlines that appeared among a source's top three at least once during the last <?=e($periods[$period]['label'])?>. Each article appears once, even when seen in multiple scans.</p>
<?php if($q===''): ?><div class="empty">Enter a search term to explore this time range.</div>
<?php elseif(!$history['items']): ?><div class="empty">No matching headlines found in this period<?= $page>1?' on this page':'' ?>.</div>
<?php else: ?>
<div class="entries historical-entries">
<?php foreach($history['items'] as $h):
    $displaySource=''; foreach($sources as $s) if((int)$s['id']===(int)$h['source_id']) {$displaySource=$s['name'];break;}
?>
<article class="entry"><div class="bullet on"></div><div class="story">
<a href="<?=e($h['url'])?>" target="_blank" rel="noopener noreferrer"><?=e($h['title'])?></a>
<div class="byline"><span><?=e($displaySource)?></span><span>Last seen <?=e(gmdate('M j, H:i',strtotime($h['matched_at'])))?> UTC</span></div></div><span class="out">↗</span></article>
<?php endforeach; ?></div>
<?php endif;?>
<?php if($page>1 || $history['has_more']): ?><nav class="history-pagination" aria-label="Search result pages">
<?php if($page>1): ?><a href="?<?=e(http_build_query(['q'=>$q,'period'=>$period,'source'=>$sourceFilter,'page'=>$page-1]))?>">← Previous</a><?php endif;?>
<span>Page <?=(int)$page?></span>
<?php if($history['has_more']): ?><a href="?<?=e(http_build_query(['q'=>$q,'period'=>$period,'source'=>$sourceFilter,'page'=>$page+1]))?>">Next →</a><?php endif;?>
</nav><?php endif;?>
<?php else: ?>
<?php if($defaultFeed): ?>
<?php if(!$defaultItems): ?><div class="empty">No current headlines available yet. Check sources in Settings.</div>
<?php else: ?><div class="entries">
<?php foreach($defaultItems as $h): ?>
<article class="entry"><div class="bullet on"></div><div class="story">
<a href="<?=e($h['url'])?>" target="_blank" rel="noopener noreferrer"><?=e($h['title'])?></a>
<div class="byline"><span><?=e($h['source_name'])?></span><span>First seen <?=e(gmdate('M j, H:i',strtotime($h['first_seen_at'])))?> UTC</span></div></div><span class="out">↗</span></article>
<?php endforeach; ?></div><?php endif;?>
<?php else: ?>
<?php if(!$sections): ?><div class="empty">No sources configured. Add one in Settings.</div><?php endif;?>
<?php foreach($sections as $section): $s=$section['source']; $scan=$section['scan']; $items=$section['items']; ?>
<div class="feed-source">
<div class="feed-source-heading">
<h3><?=e($s['name'])?></h3>
<span><?= $scan ? 'Last successful scan: '.e(gmdate('M j, H:i',strtotime($scan['finished_at']))).' UTC' : 'Not yet scanned' ?></span>
</div>
<?php if(!$items): ?>
<div class="source-empty"><?= ($q!=='' || $trendingOnly) ? 'No headlines match the selected filters in this source’s latest top 3.' : ($scan ? 'No headlines found in its most recent successful scan.' : 'No successful scan yet. Check Settings.') ?></div>
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
<?php endif; // default feed versus grouped search results ?>
<?php endif; // historical versus current mode ?>
</section>
<footer>HeadlineScout · <?= $historical?'Historical headline search':($defaultFeed?'Current front pages · Latest headline per source':'Current front pages · Latest 3 headlines per source') ?></footer>
</main><script src="/ticker.js" defer></script></body></html>
