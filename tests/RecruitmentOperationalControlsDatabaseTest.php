<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/mysql-config.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentSecurity.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentContentPolicy.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentDocumentPolicy.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentDocumentService.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentDocumentScanService.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentNotificationOutbox.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentNotificationDeliveryService.php';

$hostParts = explode(':', trim((string)HOST), 2);
$host = strtolower(trim($hostParts[0]));
$port = isset($hostParts[1]) && ctype_digit($hostParts[1]) ? $hostParts[1] : '3306';
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || !str_starts_with((string)DATABASE, 'taascor_codex_')) {
    fwrite(STDERR, "FAIL: operational-control qualification requires a loopback taascor_codex_* database.\n");
    exit(2);
}

$run = bin2hex(random_bytes(5));
$schema = substr((string)DATABASE . '_ops_' . $run, 0, 64);
$root = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/taascor-recruitment-ops-' . $run;
$documentRoot = __DIR__ . '/../public_html';
$dataKey = str_repeat('operational-data-key-', 2);
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", USER, PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        echo "FAIL: {$message}\n";
        return;
    }
    echo "PASS: {$message}\n";
};
$rejects = static function (callable $action, string $message) use ($check): void {
    try {
        $action();
        $check(false, $message);
    } catch (DomainException|RuntimeException|InvalidArgumentException $expected) {
        $check(true, $message);
    }
};
$uuid = static function (): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
};
$removeTree = static function (string $path) use ($root): void {
    $normalized = rtrim(str_replace('\\', '/', $path), '/');
    if ($normalized === '' || $normalized !== $root || !str_contains($normalized, '/taascor-recruitment-ops-')) {
        throw new RuntimeException('Refusing to remove an unexpected qualification directory.');
    }
    if (!is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
};

try {
    $server->exec("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db = new PDO("mysql:host={$host};port={$port};dbname={$schema};charset=utf8mb4", USER, PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    foreach (glob(__DIR__ . '/../recruitment/migrations/*.sql') ?: [] as $migration) {
        $db->exec((string)file_get_contents($migration));
    }

    mkdir($root, 0700, true);
    $_SERVER['DOCUMENT_ROOT'] = $documentRoot;

    $email = "operational.{$run}@example.invalid";
    $candidatePublicId = $uuid();
    $candidate = $db->prepare(
        "INSERT INTO recruitment_candidates
            (public_id, email_lookup_hash, email_ciphertext, password_hash, account_status,
             email_verified_at, privacy_notice_version, privacy_acknowledged_at)
         VALUES (:public_id, :email_hash, :email, :password, 'active', UTC_TIMESTAMP(), 'qualification-v1', UTC_TIMESTAMP())"
    );
    $candidate->execute([
        'public_id' => $candidatePublicId,
        'email_hash' => hash('sha256', strtolower($email)),
        'email' => RecruitmentSecurity::encrypt($email, $dataKey),
        'password' => password_hash('Synthetic-Operational-42!', PASSWORD_DEFAULT),
    ]);
    $candidateId = (int)$db->lastInsertId();

    $outbox = new RecruitmentNotificationOutbox($db);
    $retryId = $outbox->enqueue($candidateId, 'candidate_message', [], 'retry-' . $run);
    $deadId = $outbox->enqueue($candidateId, 'candidate_message', [], 'dead-' . $run);
    $suppressedId = $outbox->enqueue($candidateId, 'onboarding_action', [], 'suppressed-' . $run);
    $staleId = $outbox->enqueue($candidateId, 'candidate_message', [], 'stale-' . $run);
    $db->exec("UPDATE recruitment_notification_outbox SET attempt_count=4 WHERE notification_id={$deadId}");
    $db->exec("UPDATE recruitment_notification_outbox SET delivery_status='processing', attempt_count=1, claimed_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE) WHERE notification_id={$staleId}");
    $preference = $db->prepare(
        "INSERT INTO recruitment_candidate_notification_preferences
            (candidate_id, channel, message_type, enabled_flag)
         VALUES (:candidate_id, 'email', 'onboarding_action', 0)"
    );
    $preference->execute(['candidate_id' => $candidateId]);

    $sendCounts = [];
    $sender = static function (string $recipient, string $subject, string $body, int $id) use (&$sendCounts, $email, $retryId, $deadId): string {
        if ($recipient !== $email || $subject === '' || $body === '') {
            throw new RuntimeException('invalid_rendered_message');
        }
        $sendCounts[$id] = ($sendCounts[$id] ?? 0) + 1;
        if ($id === $deadId || ($id === $retryId && $sendCounts[$id] === 1)) {
            throw new RuntimeException('synthetic_provider_rejection');
        }
        return 'sandbox:' . $id . ':' . $sendCounts[$id];
    };
    $delivery = new RecruitmentNotificationDeliveryService(
        $db,
        $dataKey,
        'https://taascor.com',
        'synthetic_sandbox',
        $sender
    );
    $firstDelivery = $delivery->processBatch();
    $check($firstDelivery['recovered'] === 1, 'stale notification claims are recovered before delivery');
    $statuses = $db->query(
        "SELECT notification_id, delivery_status, attempt_count, claimed_at, last_error_code
         FROM recruitment_notification_outbox
         WHERE notification_id IN ({$retryId}, {$deadId}, {$suppressedId}, {$staleId})"
    )->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    $check(($statuses[$retryId]['delivery_status'] ?? '') === 'retry', 'recoverable provider failure enters retry state');
    $check(($statuses[$deadId]['delivery_status'] ?? '') === 'dead_letter', 'fifth provider failure enters terminal dead-letter state');
    $check(($statuses[$suppressedId]['delivery_status'] ?? '') === 'suppressed', 'optional notification preference suppresses delivery');
    $check(($statuses[$staleId]['delivery_status'] ?? '') === 'sent', 'recovered stale claim can complete delivery');
    $check(
        array_key_exists('claimed_at', $statuses[$retryId] ?? []) && $statuses[$retryId]['claimed_at'] === null,
        'failed delivery releases its worker claim'
    );
    $db->exec("UPDATE recruitment_notification_outbox SET available_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE notification_id={$retryId}");
    $delivery->processBatch();
    $retryStatus = $db->query("SELECT delivery_status, attempt_count, sent_at FROM recruitment_notification_outbox WHERE notification_id={$retryId}")->fetch();
    $check(($retryStatus['delivery_status'] ?? '') === 'sent' && (int)($retryStatus['attempt_count'] ?? 0) === 2, 'retry succeeds on the next due attempt');
    $attempts = (int)$db->query(
        "SELECT COUNT(*) FROM recruitment_notification_attempts
         WHERE notification_id IN ({$retryId}, {$deadId}, {$suppressedId}, {$staleId})"
    )->fetchColumn();
    $check($attempts === 5, 'every provider outcome is retained as durable attempt evidence');

    $requisitionId = (function () use ($db, $uuid, $run): int {
        $statement = $db->prepare(
            "INSERT INTO recruitment_requisitions
                (public_id, requisition_code, title, headcount, employment_type, hiring_owner_username,
                 recruitment_owner_username, requested_by_username, business_reason, status)
             VALUES (:public_id, :code, 'Operational qualification', 1, 'project_based',
                     'synthetic.owner', 'synthetic.recruiter', 'synthetic.requester', 'Qualification only', 'approved')"
        );
        $statement->execute(['public_id' => $uuid(), 'code' => 'OPS-' . $run]);
        return (int)$db->lastInsertId();
    })();
    $job = $db->prepare(
        "INSERT INTO recruitment_jobs
            (requisition_id, public_id, slug, title, location_label, work_arrangement, employment_type,
             summary, description_html, requirements_html, hiring_process_html, publication_status)
         VALUES (:requisition_id, :public_id, :slug, 'Operational qualification', 'Synthetic', 'onsite',
                 'project_based', 'Qualification only', 'Qualification only', 'Qualification only',
                 'Qualification only', 'closed')"
    );
    $job->execute(['requisition_id' => $requisitionId, 'public_id' => $uuid(), 'slug' => 'ops-' . $run]);
    $jobId = (int)$db->lastInsertId();
    $applicationPublicId = $uuid();
    $application = $db->prepare(
        "INSERT INTO recruitment_applications
            (public_id, candidate_id, job_id, current_status, job_snapshot)
         VALUES (:public_id, :candidate_id, :job_id, 'requirements', '{}')"
    );
    $application->execute(['public_id' => $applicationPublicId, 'candidate_id' => $candidateId, 'job_id' => $jobId]);
    $applicationId = (int)$db->lastInsertId();
    $requestPublicId = $uuid();
    $request = $db->prepare(
        "INSERT INTO recruitment_document_requests
            (public_id, application_id, purpose_code, classification, request_status, requested_by_username, requested_at)
         VALUES (:public_id, :application_id, 'qualification', 'restricted', 'open', 'synthetic.recruiter', UTC_TIMESTAMP())"
    );
    $request->execute(['public_id' => $requestPublicId, 'application_id' => $applicationId]);
    $requestId = (int)$db->lastInsertId();

    $fixture = static function (string $label, string $content) use ($db, $uuid, $root, $requestId, $candidateId, $dataKey): array {
        $storageKey = gmdate('Y/m') . '/' . bin2hex(random_bytes(24)) . '.quarantine';
        $path = RecruitmentDocumentPolicy::storagePath($root, $storageKey);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, $content);
        $statement = $db->prepare(
            "INSERT INTO recruitment_documents
                (public_id, request_id, candidate_id, storage_key, original_name_ciphertext,
                 content_sha256, media_type, byte_size, scan_status, review_status)
             VALUES (:public_id, :request_id, :candidate_id, :storage_key, :name,
                     :sha, 'application/pdf', :bytes, 'quarantined', 'not_available')"
        );
        $publicId = $uuid();
        $statement->execute([
            'public_id' => $publicId,
            'request_id' => $requestId,
            'candidate_id' => $candidateId,
            'storage_key' => $storageKey,
            'name' => RecruitmentSecurity::encrypt($label . '.pdf', $dataKey),
            'sha' => hash_file('sha256', $path),
            'bytes' => filesize($path),
        ]);
        return ['public_id' => $publicId, 'path' => $path];
    };
    $clean = $fixture('clean', 'CLEAN DOCUMENT');
    $infected = $fixture('infected', 'EICAR SYNTHETIC MARKER');
    $flaky = $fixture('flaky', 'SCANNER TEMPORARY FAILURE');
    $tampered = $fixture('tampered', 'ORIGINAL DOCUMENT');

    $scanCounts = [];
    $scanner = static function (string $path) use (&$scanCounts): int {
        $scanCounts[$path] = ($scanCounts[$path] ?? 0) + 1;
        $content = (string)file_get_contents($path);
        if (str_contains($content, 'EICAR')) {
            return 1;
        }
        if (str_contains($content, 'TEMPORARY') && $scanCounts[$path] === 1) {
            return 2;
        }
        return 0;
    };
    $documents = new RecruitmentDocumentService($db, $dataKey, $root);
    $scanService = new RecruitmentDocumentScanService($db, $documents, $root, $scanner, 'synthetic-scanner');
    $check($scanService->processBatch() === 4, 'quarantined documents are processed by the scanner adapter');
    $documentStatus = $db->prepare('SELECT scan_status, review_status FROM recruitment_documents WHERE public_id=:id');
    $documentStatus->execute(['id' => $clean['public_id']]);
    $check($documentStatus->fetch() === ['scan_status' => 'clean', 'review_status' => 'pending'], 'clean scan enters independent review');
    $documentStatus->execute(['id' => $infected['public_id']]);
    $check($documentStatus->fetch() === ['scan_status' => 'infected', 'review_status' => 'not_available'], 'infected document remains unavailable');
    $documentStatus->execute(['id' => $flaky['public_id']]);
    $check($documentStatus->fetch() === ['scan_status' => 'scan_failed', 'review_status' => 'not_available'], 'scanner fault fails closed');
    $check($scanService->processBatch() === 1, 'failed scan is eligible for a controlled retry');
    $documentStatus->execute(['id' => $flaky['public_id']]);
    $check($documentStatus->fetch() === ['scan_status' => 'clean', 'review_status' => 'pending'], 'scanner retry can advance a verified clean document');

    $rejects(fn () => $documents->releaseToCandidate($candidateId, $clean['public_id']), 'clean but unreviewed document cannot be released');
    $documents->review($clean['public_id'], 'approved', 'Synthetic clean-document review.', 'synthetic.reviewer');
    $released = $documents->releaseToCandidate($candidateId, $clean['public_id']);
    $check(($released['name'] ?? '') === 'clean.pdf' && ($released['path'] ?? '') === $clean['path'], 'approved clean document passes identity and integrity-controlled release');
    $rejects(fn () => $documents->releaseToCandidate($candidateId, $infected['public_id']), 'infected document cannot be released');

    $documents->review($tampered['public_id'], 'approved', 'Synthetic clean-document review.', 'synthetic.reviewer');
    file_put_contents($tampered['path'], 'TAMPERED AFTER APPROVAL');
    $rejects(fn () => $documents->releaseToCandidate($candidateId, $tampered['public_id']), 'post-approval file tampering fails hash verification');
    $eventCount = (int)$db->query('SELECT COUNT(*) FROM recruitment_document_events')->fetchColumn();
    $reviewCount = (int)$db->query('SELECT COUNT(*) FROM recruitment_document_reviews')->fetchColumn();
    $check($eventCount >= 8 && $reviewCount === 2, 'scan, review, and release actions retain durable document evidence');
    $rejects(
        fn () => RecruitmentDocumentPolicy::storagePath($root, '../public_html/escape.php'),
        'document storage keys reject traversal outside the private root'
    );
} finally {
    $server->exec("DROP DATABASE IF EXISTS `{$schema}`");
    $removeTree($root);
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Recruitment notification and document operational-control qualification passed.\n";
