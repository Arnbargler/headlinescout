<?php
// Shared navigation and live ticker on Dashboard, Articles and Settings.
require_once dirname(__DIR__) . '/src/Ticker.php';
$tickerItems=\HeadlineScout\Ticker::items($db);
$activeTab=$activeTab??'dashboard';
$esc=static fn(mixed $v): string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<header><div class="shell top"><a href="/" class="brand-link"><h1 class="site-title">Headline<span>Scout</span></h1></a></div></header>
<div class="ticker-bar" aria-label="Top three trending headlines">
  <div class="shell ticker-shell">
    <span class="ticker-caption">TOP HEADLINES</span>
    <div class="ticker-viewport">
      <div id="headline-ticker-track" class="ticker-track" data-signature="<?=$esc(json_encode($tickerItems, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>">
        <?php if (!$tickerItems): ?>
        <span class="ticker-empty">No trending headlines yet — scan a source to begin.</span>
        <?php else: foreach($tickerItems as $headline): ?>
        <a class="ticker-story" href="<?=$esc($headline['url'])?>" target="_blank" rel="noopener noreferrer"><?=$esc($headline['title'])?></a>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>
<nav class="section-tabs" aria-label="HeadlineScout pages"><div class="shell section-tabs-inner">
<?php foreach (['dashboard'=>['Dashboard','/'],'articles'=>['Articles','/articles.php'],'settings'=>['Settings','/settings.php']] as $key=>[$label,$href]): ?>
<a href="<?=$href?>" class="section-tab <?=$activeTab===$key?'active':''?>" <?=$activeTab===$key?'aria-current="page"':''?>><?=$label?></a>
<?php endforeach; ?>
</div></nav>
