<?php
declare(strict_types=1);
namespace HeadlineScout;

use PDO;
use RuntimeException;
use Throwable;

/**
 * File-backed, per-period Word Count cache shared by Dashboard, Articles and ticker.
 * Each window expires independently. Cached top words and trend rows come from the
 * same calculation; changing the ignored terms invalidates the cache key.
 */
final class StatsCache {
    public const TTL = [
        'now' => 60,
        'hour' => 60,
        'day' => 300,
        'week' => 900,
        'month' => 3600,
        'year' => 21600,
    ];

    public static function period(PDO $db, string $key, array $ignored, ?string $directory=null): array {
        $periods=array_column(WordStats::periods(),null,'key');
        if (!isset($periods[$key],self::TTL[$key])) {
            throw new RuntimeException('Unknown word-statistics period: '.$key);
        }
        $span=$periods[$key]['seconds'];
        $ttl=self::TTL[$key];
        $hash=hash('sha256',json_encode(array_column($ignored,'term'),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $directory ??= dirname(__DIR__).'/var/cache';
        $file=$directory.'/word-'.$key.'.json';
        $now=time();

        $read=static function() use ($file,$hash): ?array {
            if (!is_file($file)) return null;
            $json=@file_get_contents($file);
            if ($json===false) return null;
            $data=json_decode($json,true);
            if (!is_array($data) || ($data['ignore_hash']??null)!==$hash ||
                !isset($data['expires_at'],$data['rows'],$data['top']) ||
                !is_array($data['rows']) || !is_array($data['top'])) return null;
            return $data;
        };

        $cached=$read();
        if ($cached && $cached['expires_at']>$now) return $cached;

        // On a busy host only the first request should do the expensive work.
        // Others may use slightly stale data while the cache is refreshed.
        if (!is_dir($directory) && !@mkdir($directory,0770,true) && !is_dir($directory)) {
            return self::calculate($db,$span,$ignored,$ttl,$hash);
        }
        $lock=@fopen($directory.'/word-'.$key.'.lock','c');
        if (!$lock) return self::calculate($db,$span,$ignored,$ttl,$hash);
        if (!flock($lock,LOCK_EX|LOCK_NB)) {
            if ($cached) { fclose($lock); return $cached; }
            // Initial cold-cache request waits rather than duplicating a costly query.
            if (!flock($lock,LOCK_EX)) {
                fclose($lock);
                return self::calculate($db,$span,$ignored,$ttl,$hash);
            }
        }
        try {
            $cached=$read();
            if ($cached && $cached['expires_at']>time()) return $cached;
            $fresh=self::calculate($db,$span,$ignored,$ttl,$hash);
            $temp=@tempnam($directory,'.word-'.$key.'-');
            if ($temp!==false) {
                try {
                    $written=@file_put_contents($temp,json_encode($fresh,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
                    if ($written!==false) @rename($temp,$file);
                } finally {
                    if (is_file($temp)) @unlink($temp);
                }
            }
            return $fresh;
        } finally {
            flock($lock,LOCK_UN);
            fclose($lock);
        }
    }

    private static function calculate(PDO $db,int $span,array $ignored,int $ttl,string $hash): array {
        $calculated=time();
        $snapshot=WordStats::snapshot($db,$span,$ignored,$calculated);
        return [
            'ignore_hash'=>$hash,
            'calculated_at'=>$calculated,
            'expires_at'=>$calculated+$ttl,
            'top'=>$snapshot['top'],
            'rows'=>$snapshot['rows'],
        ];
    }
}
