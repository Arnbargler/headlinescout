<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';
require dirname(__DIR__).'/src/SourceLab.php';
use HeadlineScout\App;
use HeadlineScout\SourceLab;
session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Strict','use_strict_mode'=>true]);
function h(mixed $v): string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function csrf(): string {return $_SESSION['csrf']??=bin2hex(random_bytes(24));}
$db=App::db(); App::install($db);
$keyFile=dirname(__DIR__).'/var/source-lab.key';
$authenticated=!empty($_SESSION['source_lab_auth']);
$error=null;$notice=null;$probe=$_SESSION['source_lab_probe']??null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals(csrf(),(string)($_POST['csrf']??''))) {
        $error='Invalid form token; reload the page.';
    } else {
        $action=(string)($_POST['action']??'');
        if ($action==='login') {
            if (!is_file($keyFile)) $error='Run php bin/lab-key.php on Colossus first.';
            elseif (hash_equals(trim(file_get_contents($keyFile)),(string)($_POST['key']??''))) {
                session_regenerate_id(true);
                $_SESSION['source_lab_auth']=true; $authenticated=true;
                $notice='Source Lab unlocked.';
            } else $error='Incorrect access key.';
        } elseif (!$authenticated) $error='Unlock Source Lab first.';
        elseif ($action==='logout') {
            unset($_SESSION['source_lab_auth'],$_SESSION['source_lab_probe']);
            $authenticated=false;$probe=null;$notice='Locked.';
        } elseif ($action==='test') {
            try {
                $url=trim((string)($_POST['url']??''));
                if (strlen($url)>500) throw new RuntimeException('URL too long');
                $probe=SourceLab::probe($url);
                $_SESSION['source_lab_probe']=$probe;
                $notice='Homepage fetched and six extraction strategies tested. Review results before saving.';
            } catch (Throwable $e) { $error=$e->getMessage(); $probe=null;unset($_SESSION['source_lab_probe']); }
        } elseif ($action==='save') {
            if (!$probe) $error='Test a site first.';
            else {
                $method=(string)($_POST['method']??'');
                if (!isset(SourceLab::METHODS[$method])) $error='Unknown method.';
                elseif (($probe['methods'][$method]['count']??0)<3) $error='This method found fewer than three candidates.';
                else {
                    $url=$probe['url'];
                    $name=trim((string)($_POST['name']??''));
                    if ($name==='' || mb_strlen($name)>70) $error='Enter a source name (1–70 characters).';
                    else {
                        $selector=SourceLab::METHODS[$method]['xpath'];
                        $existing=$db->prepare('SELECT id FROM sources WHERE homepage_url=?');
                        $existing->execute([$url]);
                        $id=$existing->fetchColumn();
                        if ($id) {
                            $stmt=$db->prepare('UPDATE sources SET name=?,selector=?,enabled=1,last_scan_at=NULL WHERE id=?');
                            $stmt->execute([$name,$selector,$id]);
                            $notice='Updated source #'.$id.'. Run the collector to apply its new extraction method.';
                        } else {
                            $stmt=$db->prepare('INSERT INTO sources(name,homepage_url,selector) VALUES(?,?,?)');
                            $stmt->execute([$name,$url,$selector]);
                            $notice='Added '.$name.' as source #'.$db->lastInsertId().'. Run the collector to scan it.';
                        }
                    }
                }
            }
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Source Lab | HeadlineScout</title><link rel="stylesheet" href="style.css"></head><body>
<header><div class="shell top"><div><div class="eyebrow">HEADLINESCOUT / TOOLS</div><h1>SOURCE <span>LAB</span></h1></div><a class="navlink" href="/">← Dashboard</a></div></header>
<main class="shell lab">
<p class="intro">Test a potential news homepage, compare six HTML extraction strategies, inspect candidate headlines, then save a selected method. This tests front-page content only; it does not use RSS or bypass site restrictions.</p>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?>
<?php if($notice):?><div class="alert notice"><?=h($notice)?></div><?php endif;?>
<?php if(!$authenticated):?>
<section class="panel formpanel"><h2>UNLOCK SOURCE LAB</h2><p>Source testing fetches external URLs and source editing changes collection settings. These controls are locked behind a key generated locally on Colossus.</p>
<p>Run <code>php bin/lab-key.php</code> from your project directory and enter the displayed key.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="login"><label>Access key <input name="key" type="password" required autocomplete="off"></label><button type="submit">Unlock</button></form>
</section>
<?php else:?>
<form method="post" class="logout"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="logout"><button type="submit">Lock lab</button></form>
<section class="panel formpanel"><h2>TEST A HOMEPAGE</h2>
<form method="post" class="lab-form"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="test">
<label>Website URL <input type="url" name="url" placeholder="https://example.com/" value="<?=h($probe['url']??'')?>" required maxlength="500"></label>
<button type="submit">Test extraction methods</button></form>
<p class="fine">One homepage fetch, six extraction strategies. A site can fail due to robots policy, anti-bot protection, network errors or incompatible markup. Results are advisory; always inspect the preview.</p>
</section>
<?php if($probe):?>
<section class="panel formpanel">
<div class="panel-head"><h2>TEST RESULTS</h2><span><?=h($probe['tested_at'])?> UTC</span></div>
<p><strong><?=h($probe['final_url'])?></strong> — HTTP <?= (int)$probe['http_status']?> · <?=number_format($probe['bytes'])?> bytes downloaded</p>
<div class="methods">
<?php foreach($probe['methods'] as $id=>$m):?>
<div class="method"><strong><?=h($m['label'])?></strong><?php if($id===$probe['suggested']):?><span class="suggest">Suggested</span><?php endif;?>
<div><span class="big"><?= (int)$m['count'] ?></span> candidate links</div>
<?php if(!empty($m['error'])):?><small><?=h($m['error'])?></small><?php endif;?>
<details><summary>Preview <?=min(count($m['sample']),35)?> headlines</summary>
<ol class="preview"><?php foreach($m['sample'] as $item):?><li><a target="_blank" rel="noopener noreferrer" href="<?=h($item['url'])?>"><?=h($item['title'])?></a></li><?php endforeach;?></ol></details>
</div>
<?php endforeach;?></div>
</section>
<section class="panel formpanel"><h2>SAVE SOURCE</h2><p>Choose the method whose preview most accurately matches the actual front page. Saving an existing homepage URL updates its extraction method.</p>
<form method="post" class="lab-form"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="save">
<label>Display name <input name="name" required maxlength="70" value="<?=h(parse_url($probe['url'],PHP_URL_HOST)??'')?>" placeholder="Source name"></label>
<label>Extraction method <select name="method"><?php foreach($probe['methods'] as $id=>$m):?><option value="<?=h($id)?>" <?=$id===$probe['suggested']?'selected':''?>><?=h($m['label'])?> (<?=(int)$m['count']?>)</option><?php endforeach;?></select></label>
<button type="submit">Save source / method</button></form>
<p class="fine">No scan is triggered when saving. Run <code>php bin/scan.php --all</code> to test ingestion.</p></section>
<?php endif;?>
<?php endif;?>
</main></body></html>
