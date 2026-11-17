<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/App.php';
use HeadlineScout\App;
$db=App::db(); App::install($db);
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
$sourceFilter=filter_input(INPUT_GET,'source',FILTER_VALIDATE_INT) ?: 0;
$q=trim((string)($_GET['q']??''));
$sources=$db->query('SELECT id,name,last_success_at,last_status FROM sources WHERE enabled=1 ORDER BY id')->fetchAll();
$lastSuccess=$sources ? max(array_map(fn($s)=>strtotime($s['last_success_at']??'')?:0,$sources)) : 0;
$errors=array_filter($sources,fn($s)=>$s['last_status']==='error');
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
    $visibleCount+=count($items);
    $sections[]=['source'=>$source,'scan'=>$scan,'items'=>$items];
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="300">
<title>HeadlineScout</title><link rel="stylesheet" href="style.css"></head>
<body>
<header><div class="shell top"><div><div class="eyebrow">FRONT PAGE INTELLIGENCE</div><h1>HEADLINE<span>SCOUT</span></h1></div><div class="health"><a class="navlink" href="/lab.php">Source Lab →</a><br><span class="led"></span> <?=count($sources)?> sources · <?=count($errors)?> errors<br><small>Last success: <?= $lastSuccess?e(gmdate('M j, H:i',$lastSuccess)).' UTC':'Not yet scanned' ?></small></div></div></header>
<main class="shell">
<section class="panel">
<div class="panel-head"><h2>CURRENT FRONT PAGES</h2><span><?=$visibleCount?> headlines · up to 10 per source</span></div>
<form method="get" class="filters">
<label>Source <select name="source"><option value="0">All sources</option><?php foreach($sources as $s): ?><option value="<?=(int)$s['id']?>" <?=$sourceFilter===(int)$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach; ?></select></label>
<label class="search">Search current headlines <input type="search" name="q" placeholder="Filter displayed headlines" value="<?=e($q)?>"></label>
<button type="submit">Filter</button></form>
<?php if(!$sections): ?><div class="empty">No sources configured. Add one in Source Lab.</div><?php endif;?>
<?php foreach($sections as $section): $s=$section['source']; $scan=$section['scan']; $items=$section['items']; ?>
<div class="feed-source">
<div class="feed-source-heading">
<h3><?=e($s['name'])?></h3>
<span><?= $scan ? 'Last successful scan: '.e(gmdate('M j, H:i',strtotime($scan['finished_at']))).' UTC' : 'Not yet scanned' ?></span>
</div>
<?php if(!$items): ?>
<div class="source-empty"><?= $q!=='' ? 'No matching headlines in this source’s latest top 10.' : ($scan ? 'No headlines found in its most recent successful scan.' : 'No successful scan yet. Check Source Lab.') ?></div>
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
</main></body></html>
