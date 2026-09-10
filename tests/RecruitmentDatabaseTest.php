<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/mysql-config.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentRepository.php';

$hostValue = trim((string)HOST);
$hostParts = explode(':', $hostValue, 2);
$host = strtolower(trim($hostParts[0]));
$port = isset($hostParts[1]) && ctype_digit($hostParts[1]) ? $hostParts[1] : '3306';
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "FAIL: database integration test requires a loopback host.\n");
    exit(2);
}
if (!str_starts_with((string)DATABASE, 'taascor_codex_')) {
    fwrite(STDERR, "FAIL: database integration test requires a taascor_codex_* disposable schema.\n");
    exit(2);
}

$db = new PDO(
    "mysql:host={$host};port={$port};dbname=" . DATABASE . ';charset=utf8mb4',
    USER,
    PASSWORD,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        return;
    }
    echo "PASS: {$message}\n";
};

$db->beginTransaction();
try {
    $candidate = $db->prepare(
        'INSERT INTO recruitment_candidates
            (public_id, email_lookup_hash, email_ciphertext, password_hash, account_status,
             email_verified_at, privacy_notice_version, privacy_acknowledged_at)
         VALUES
            (:public_id, :email_lookup_hash, :email_ciphertext, :password_hash, :account_status,
             UTC_TIMESTAMP(), :privacy_notice_version, UTC_TIMESTAMP())'
    );
    $candidate->execute([
        'public_id' => '10000000-0000-4000-8000-000000000001',
        'email_lookup_hash' => hash('sha256', 'synthetic.candidate@example.invalid'),
        'email_ciphertext' => 'synthetic-ciphertext-not-a-real-address',
        'password_hash' => password_hash('Synthetic-Only-42!', PASSWORD_DEFAULT),
        'account_status' => 'active',
        'privacy_notice_version' => 'synthetic-test-v1',
    ]);
    $candidateId = (int)$db->lastInsertId();

    $requisition = $db->prepare(
        'INSERT INTO recruitment_requisitions
            (public_id, requisition_code, title, headcount, employment_type,
             hiring_owner_username, recruitment_owner_username, requested_by_username,
             business_reason, status, approved_at)
         VALUES
            (:public_id, :requisition_code, :title, :headcount, :employment_type,
             :hiring_owner, :recruitment_owner, :requested_by,
             :business_reason, :status, UTC_TIMESTAMP())'
    );
    $requisition->execute([
        'public_id' => '20000000-0000-4000-8000-000000000001',
        'requisition_code' => 'SYNTH-REQ-001',
        'title' => 'Synthetic Warehouse Associate',
        'headcount' => 3,
        'employment_type' => 'Synthetic test only',
        'hiring_owner' => 'synthetic.hiring.owner',
        'recruitment_owner' => 'synthetic.recruiter',
        'requested_by' => 'synthetic.requestor',
        'business_reason' => 'Disposable transaction test. Not a real vacancy.',
        'status' => 'approved',
    ]);
    $requisitionId = (int)$db->lastInsertId();

    $job = $db->prepare(
        'INSERT INTO recruitment_jobs
            (requisition_id, public_id, slug, title, location_label, work_arrangement,
             employment_type, summary, description_html, requirements_html,
             hiring_process_html, publication_status, opens_at, closes_at,
             published_at, published_by_username)
         VALUES
            (:requisition_id, :public_id, :slug, :title, :location_label, :work_arrangement,
             :employment_type, :summary, :description_html, :requirements_html,
             :hiring_process_html, :publication_status, UTC_TIMESTAMP(),
             DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY), UTC_TIMESTAMP(), :published_by)'
    );
    $job->execute([
        'requisition_id' => $requisitionId,
        'public_id' => '30000000-0000-4000-8000-000000000001',
        'slug' => 'synthetic-warehouse-associate',
        'title' => 'Synthetic Warehouse Associate',
        'location_label' => 'Synthetic Site',
        'work_arrangement' => 'On-site',
        'employment_type' => 'Synthetic test only',
        'summary' => 'Disposable job record used only for transactional verification.',
        'description_html' => '<p>Synthetic test only.</p>',
        'requirements_html' => '<p>No real requirements.</p>',
        'hiring_process_html' => '<p>Not a real hiring process.</p>',
        'publication_status' => 'published',
        'published_by' => 'synthetic.publisher',
    ]);
    $jobId = (int)$db->lastInsertId();

    $application = $db->prepare(
        'INSERT INTO recruitment_applications
            (public_id, candidate_id, job_id, current_status, job_snapshot, submitted_at)
         VALUES
            (:public_id, :candidate_id, :job_id, :current_status, :job_snapshot, UTC_TIMESTAMP())'
    );
    $application->execute([
        'public_id' => '40000000-0000-4000-8000-000000000001',
        'candidate_id' => $candidateId,
        'job_id' => $jobId,
        'current_status' => 'submitted',
        'job_snapshot' => json_encode(['title' => 'Synthetic Warehouse Associate'], JSON_THROW_ON_ERROR),
    ]);

    $repository = new RecruitmentRepository($db);
    $jobs = $repository->publishedJobs();
    $summary = $repository->staffSummary();

    $check(count($jobs) === 1, 'repository returns the one active synthetic published job');
    $check(($jobs[0]['public_id'] ?? '') === '30000000-0000-4000-8000-000000000001', 'public job projection uses the immutable public identifier');
    $check(!array_key_exists('requisition_id', $jobs[0]), 'public job projection omits internal requisition identifiers');
    $check(!array_key_exists('published_by_username', $jobs[0]), 'public job projection omits staff identity');
    $check(($summary['approved_requisitions'] ?? 0) === 1, 'staff summary counts approved requisitions');
    $check(($summary['published_jobs'] ?? 0) === 1, 'staff summary counts published jobs');
    $check(($summary['active_applications'] ?? 0) === 1, 'staff summary counts active applications');
    $check(($summary['ready_for_conversion'] ?? 0) === 0, 'staff summary does not fabricate conversion-ready candidates');
} finally {
    $db->rollBack();
}

$remainingRows = (int)$db->query(
    'SELECT
        (SELECT COUNT(*) FROM recruitment_candidates)
      + (SELECT COUNT(*) FROM recruitment_requisitions)
      + (SELECT COUNT(*) FROM recruitment_jobs)
      + (SELECT COUNT(*) FROM recruitment_applications)'
)->fetchColumn();
$check($remainingRows === 0, 'synthetic database verification rolls back completely');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Recruitment disposable-database checks passed.\n";
