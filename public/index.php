<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/App.php';
use HeadlineScout\App;
$db=App::db(); App::install($db);
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
$sourceFilter=filter_input(INPUT_GET,'source',FILTER_VALIDATE_INT) ?: 0;
$current=($_GET['show']??'current') !== 'all';
$q=trim((string)($_GET['q']??''));
$sources=$db->query('SELECT s.*, (SELECT COUNT(*) FROM headlines h WHERE h.source_id=s.id AND h.is_current=1) AS active_count FROM sources s ORDER BY id')->fetchAll();
$sql='SELECT h.*,s.name AS source_name FROM headlines h JOIN sources s ON s.id=h.source_id WHERE 1=1';
$args=[];
if ($current) $sql.=' AND h.is_current=1';
if ($sourceFilter) { $sql.=' AND h.source_id=?'; $args[]=$sourceFilter; }
if ($q!=='') { $sql.=' AND h.title LIKE ?'; $args[]='%'.$q.'%'; }
$sql.=' ORDER BY h.first_seen_at DESC LIMIT 250';
$stmt=$db->prepare($sql);$stmt->execute($args);$headlines=$stmt->fetchAll();
$lastSuccess=max(array_map(fn($s)=>strtotime($s['last_success_at']??'')?:0,$sources));
$errors=array_filter($sources,fn($s)=>$s['last_status']==='error');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="300">
<title>HeadlineScout</title><link rel="stylesheet" href="style.css"></head>
<body>
<header><div class="shell top"><div><div class="eyebrow">FRONT PAGE INTELLIGENCE</div><h1>HEADLINE<span>SCOUT</span></h1></div><div class="health"><span class="led"></span> <?=count($sources)?> sources · <?=count($errors)?> errors<br><small>Last success: <?= $lastSuccess?e(gmdate('M j, H:i',$lastSuccess)).' UTC':'Not yet scanned' ?></small></div></div></header>
<main class="shell">
<section class="summary">
<?php foreach($sources as $source): ?>
<div class="source"><div class="source-top"><strong><?=e($source['name'])?></strong><span class="state <?=e($source['last_status']??'pending')?>"><?=e($source['last_status']??'pending')?></span></div>
<div class="count"><?= (int)$source['active_count'] ?> <small>current headlines</small></div>
<div class="meta">Last scan: <?= $source['last_scan_at'] ? e(gmdate('M j, H:i',strtotime($source['last_scan_at']))).' UTC' : 'never' ?></div>
<?php if($source['last_error']): ?><div class="error" title="<?=e($source['last_error'])?>"><?=e($source['last_error'])?></div><?php endif; ?></div>
<?php endforeach; ?>
</section>
<section class="panel">
<div class="panel-head"><h2>HEADLINE FEED</h2><span><?=count($headlines)?> results</span></div>
<form method="get" class="filters">
<label>Source <select name="source"><option value="0">All sources</option><?php foreach($sources as $s): ?><option value="<?=(int)$s['id']?>" <?=$sourceFilter===(int)$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach; ?></select></label>
<label>View <select name="show"><option value="current" <?=$current?'selected':''?>>Current front pages</option><option value="all" <?=!$current?'selected':''?>>All history</option></select></label>
<label class="search">Search <input type="search" name="q" placeholder="Search headlines" value="<?=e($q)?>"></label>
<button type="submit">Filter</button></form>
<?php if(!$headlines): ?><div class="empty">No headlines yet. Run the collector on Colossus, then reload this page. See README for the setup commands.</div><?php endif;?>
<div class="entries">
<?php foreach($headlines as $h): ?>
<article class="entry"><div class="bullet <?=$h['is_current']?'on':''?>"></div><div class="story">
<a href="<?=e($h['url'])?>" target="_blank" rel="noopener noreferrer"><?=e($h['title'])?></a>
<div class="byline"><span><?=e($h['source_name'])?></span><span>First seen <?=e(gmdate('M j, H:i',strtotime($h['first_seen_at'])))?> UTC</span><?php if(!$h['is_current']):?><em>Archived</em><?php endif;?></div></div><span class="out">↗</span></article>
<?php endforeach; ?>
</div></section>
<footer>HeadlineScout v0.1 · Read-only LAN dashboard · Polling is performed by cron, not the browser · AI analysis coming later</footer>
</main></body></html>
