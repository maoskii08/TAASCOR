<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/feature.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if (!recruitment_public_jobs_enabled()) {
    header('Cache-Control: no-store');
    http_response_code(503);
    echo json_encode([
        'success' => 0,
        'error' => 'Public job publication is not available.',
        'data' => [],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/db_connect.php';
require_once dirname(__DIR__) . '/model/RecruitmentRepository.php';

try {
    $repository = new RecruitmentRepository($pdoConn);
    $requestedId = trim((string)($_GET['id'] ?? ''));
    if ($requestedId !== '' && preg_match('/^[0-9a-f-]{36}$/i', $requestedId) !== 1) {
        http_response_code(400); echo json_encode(['success'=>0,'error'=>'A valid job identifier is required.','data'=>[]]); exit;
    }
    $jobs = $requestedId === '' ? $repository->publishedJobs(100) : array_values(array_filter([$repository->publishedJob($requestedId)]));
    $representation = json_encode($jobs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $etag = '"' . hash('sha256', $representation) . '"';
    header('ETag: ' . $etag);
    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }
    header('Cache-Control: public, max-age=60, stale-if-error=300');
    echo json_encode([
        'success' => 1,
        'data' => $jobs,
        'meta' => [
            'schema_version' => 1,
            'generated_at' => gmdate('c'),
            'count' => count($jobs),
            'content_sha256' => trim($etag, '"'),
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Recruitment public jobs feed failed: ' . $error->getMessage());
    header('Cache-Control: no-store');
    http_response_code(503);
    echo json_encode([
        'success' => 0,
        'error' => 'Published jobs are temporarily unavailable.',
        'data' => [],
    ], JSON_UNESCAPED_SLASHES);
}
