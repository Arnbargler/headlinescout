<?php
declare(strict_types=1);
namespace HeadlineScout;

use RuntimeException;
use Throwable;

final class SourceLab {
    /** Multiple extraction approaches over ONE fetched homepage. No RSS: front page only. */
    public const METHODS = [
        'headings' => ['label'=>'Heading links (H1–H3)','xpath'=>'//a[.//h1 or .//h2 or .//h3 or ancestor::h1 or ancestor::h2 or ancestor::h3]'],
        'article'  => ['label'=>'Links inside article elements','xpath'=>'//article//a[@href]'],
        'headline' => ['label'=>'Headline/title class links','xpath'=>'//a[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"headline") or contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"title")]'],
        'headline_wrap' => ['label'=>'Links inside headline/title blocks','xpath'=>'//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"headline") or contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"title")]//a[@href]'],
        'main' => ['label'=>'Main content heading links','xpath'=>'//main//a[.//h1 or .//h2 or .//h3 or ancestor::h1 or ancestor::h2 or ancestor::h3]'],
        'all' => ['label'=>'All on-site links (diagnostic)','xpath'=>'//a[@href]'],
    ];

    public static function probe(string $url): array {
        $url=trim($url);
        if (!preg_match('~^https?://~i',$url)) throw new RuntimeException('Use an absolute http:// or https:// URL');
        if (!App::robotsAllowed($url)) throw new RuntimeException('robots.txt disallows the URL, or policy could not be confirmed');
        [$body,$status,$final,$headers]=App::fetch($url);
        if ($status!==200) throw new RuntimeException('HTTP status ' . $status . ' returned for homepage');
        $ct=strtolower($headers['content-type'] ?? '');
        if ($ct && !str_contains($ct,'html')) throw new RuntimeException('Expected HTML; server returned ' . $ct);
        $results=[];
        foreach (self::METHODS as $id=>$method) {
            try {
                $items=App::extract($body,$final,$method['xpath']);
                // A balanced candidate range is a starting suggestion, not an accuracy guarantee.
                $n=count($items);
                $score=$id==='all' ? -50 : (min($n,70) - ($n>85?($n-85):0));
                $results[$id]=['label'=>$method['label'],'count'=>$n,'score'=>$score,'sample'=>array_slice($items,0,35)];
            } catch (Throwable $e) {
                $results[$id]=['label'=>$method['label'],'count'=>0,'score'=>-999,'sample'=>[],'error'=>$e->getMessage()];
            }
        }
        $ranking=array_keys($results);
        usort($ranking,fn($a,$b)=>$results[$b]['score'] <=> $results[$a]['score']);
        return ['url'=>$url,'final_url'=>$final,'http_status'=>$status,'bytes'=>strlen($body),
            'methods'=>$results,'suggested'=>$ranking[0]??'headings','tested_at'=>gmdate('c')];
    }
}
