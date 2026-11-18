<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/App.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
try {
    $db=\HeadlineScout\App::db();
    \HeadlineScout\App::install($db);
    $ignored=\HeadlineScout\WordStats::ignored($db);
    $periods=[];
    foreach ([1,24,168] as $hours) {
        $periods[]=['hours'=>$hours,'rows'=>\HeadlineScout\WordStats::trending($db,$hours,$ignored)];
    }
    echo json_encode(['periods'=>$periods],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>'Could not load word statistics']);
}
