<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/feature.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/model/RecruitmentSecurity.php';
require_once dirname(__DIR__) . '/model/RecruitmentContentPolicy.php';
require_once dirname(__DIR__) . '/model/RecruitmentDocumentPolicy.php';
require_once dirname(__DIR__) . '/model/RecruitmentDocumentService.php';
require_once dirname(__DIR__) . '/model/RecruitmentDocumentScanService.php';

if (!recruitment_mutations_enabled()) {
    fwrite(STDERR, "Document scanning is source-locked until qualified.\n");
    exit(3);
}

$scanner = trim((string)getenv('TAASCOR_RECRUITMENT_SCANNER_COMMAND'));
$root = trim((string)getenv('TAASCOR_RECRUITMENT_DOCUMENT_ROOT'));
$webRoot = trim((string)getenv('TAASCOR_RECRUITMENT_WEB_ROOT'));
if ($scanner === '' || !is_executable($scanner) || !RecruitmentDocumentPolicy::privateRootIsSafe($root, $webRoot)) {
    fwrite(STDERR, "An executable scanner command and safe private/web roots are required.\n");
    exit(3);
}

$commandScanner = static function (string $path) use ($scanner): int {
    $output = [];
    $exitCode = 2;
    $command = escapeshellarg($scanner) . ' --no-summary ' . escapeshellarg($path);
    exec($command, $output, $exitCode);
    return $exitCode;
};

$db = recruitment_database();
$documents = new RecruitmentDocumentService($db, recruitment_data_key(), $root, $webRoot);
$service = new RecruitmentDocumentScanService($db, $documents, $root, $commandScanner, basename($scanner));
$processed = $service->processBatch();
fwrite(STDOUT, 'Scanned ' . $processed . " document(s).\n");
