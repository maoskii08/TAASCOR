<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This validator is CLI-only.\n");
    exit(1);
}

$options = getopt('', [
    'confirm-local',
    'fuji-file:',
    'fuji-client:',
    'fuji-template:',
    'samples:',
    'period-start::',
    'period-end::',
    'pay-date::',
]);
if (!array_key_exists('confirm-local', $options)) {
    fwrite(STDERR, "Refusing to write staging data without --confirm-local.\n");
    exit(1);
}

require dirname(__DIR__) . '/config/mysql-config.php';
require dirname(__DIR__) . '/dtr-format-engine/model/FujiPayrollSummaryAdapter.php';
require dirname(__DIR__) . '/dtr-format-engine/model/DtrAdapterRegistry.php';
require dirname(__DIR__) . '/dtr-format-engine/model/SyntheticUploadParser.php';
require dirname(__DIR__) . '/dtr-format-engine/model/GenericRealDtrUploadService.php';
require dirname(__DIR__) . '/dtr-format-engine/model/RealSampleAdapter.php';
require dirname(__DIR__) . '/dtr-format-engine/model/EmployeeIdentityNotificationManager.php';

$host = (string)HOST;
$normalizedHost = strtolower(trim($host));
if (!preg_match('/^(?:127\.0\.0\.1|localhost)(?::[0-9]+)?$/', $normalizedHost)
    && $normalizedHost !== '::1') {
    fwrite(STDERR, "Refusing to run against a non-loopback database host.\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=' . HOST . ';dbname=' . DATABASE . ';charset=utf8mb4',
    USER,
    PASSWORD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$maker = 'codex.local.validation.maker';
$checker = 'codex.local.validation.checker';
$periodStart = trim((string)($options['period-start'] ?? '2026-06-16'));
$periodEnd = trim((string)($options['period-end'] ?? '2026-06-30'));
$payDate = trim((string)($options['pay-date'] ?? '2026-07-13'));
$fujiFile = (string)($options['fuji-file'] ?? '');
$fujiClient = (int)($options['fuji-client'] ?? 0);
$fujiTemplate = (int)($options['fuji-template'] ?? 0);

if ($fujiClient !== FujiPayrollSummaryAdapter::CLIENT_ID) {
    fwrite(STDERR, "The specialized Fuji adapter is bound to client 264.\n");
    exit(1);
}
if ($fujiTemplate <= 0 || !is_file($fujiFile)) {
    fwrite(STDERR, "Provide an existing Fuji workbook and its active local template.\n");
    exit(1);
}

$registry = new DtrAdapterRegistry();
$registry->db = $pdo;
$registry->allow_all_clients = true;

$adapterKey = 'FUJI_PAYROLL_SUMMARY';
$adapterVersion = 'validation-' . str_replace('-', '', $periodStart)
    . '-' . str_replace('-', '', $periodEnd);
$profileQuery = $pdo->prepare("
    SELECT id, profile_status
    FROM dtr_adapter_profiles
    WHERE client_id = :client_id
      AND adapter_key = :adapter_key
      AND adapter_version = :adapter_version
    LIMIT 1
");
$profileQuery->execute([
    ':client_id' => $fujiClient,
    ':adapter_key' => $adapterKey,
    ':adapter_version' => $adapterVersion,
]);
$profileRow = $profileQuery->fetch(PDO::FETCH_ASSOC);
if (!$profileRow) {
    $draft = $registry->createDraftFromTemplate([
        'template_id' => $fujiTemplate,
        'adapter_key' => $adapterKey,
        'adapter_version' => $adapterVersion,
        'display_name' => 'Fuji governed validation adapter',
        'parser_key' => 'fuji_payroll_summary_v1',
        'identity_policy' => 'approved_mapping_required',
        'effective_from' => $periodStart,
        'effective_to' => $periodEnd,
    ], $maker);
    if (empty($draft['success'])) {
        fwrite(STDERR, (string)($draft['error'] ?? 'Unable to create the Fuji validation adapter.') . "\n");
        exit(1);
    }
    $profileId = (int)$draft['profile_id'];
    $approval = $registry->approveProfile(
        $profileId,
        $checker,
        'Local shadow validation of the supplied source workbook and expected payslip evidence.'
    );
    if (empty($approval['success'])) {
        fwrite(STDERR, (string)($approval['error'] ?? 'Unable to approve the Fuji validation adapter.') . "\n");
        exit(1);
    }
} else {
    $profileId = (int)$profileRow['id'];
    if ((string)$profileRow['profile_status'] === 'draft') {
        $approval = $registry->approveProfile(
            $profileId,
            $checker,
            'Local shadow validation of the supplied source workbook and expected payslip evidence.'
        );
        if (empty($approval['success'])) {
            fwrite(STDERR, (string)($approval['error'] ?? 'Unable to approve the Fuji validation adapter.') . "\n");
            exit(1);
        }
    } elseif ((string)$profileRow['profile_status'] !== 'approved') {
        fwrite(STDERR, "The existing Fuji validation adapter is not approved.\n");
        exit(1);
    }
}

$fujiAdapter = new FujiPayrollSummaryAdapter();
$fujiAdapter->db = $pdo;
$tabular = new SyntheticUploadParser();
$tabular->db = $pdo;
$service = new GenericRealDtrUploadService();
$service->db = $pdo;
$service->registry = $registry;
$service->tabularParser = $tabular;
$service->fujiAdapter = $fujiAdapter;
$fujiResult = $service->stage([
    'client_id' => $fujiClient,
    'adapter_profile_id' => $profileId,
    'period_start' => $periodStart,
    'period_end' => $periodEnd,
    'pay_date' => $payDate,
], [
    'name' => basename($fujiFile),
    'tmp_name' => $fujiFile,
    'size' => filesize($fujiFile),
    'error' => UPLOAD_ERR_OK,
], $maker);
if (empty($fujiResult['success'])) {
    fwrite(STDERR, (string)($fujiResult['error'] ?? 'Fuji staging failed.') . "\n");
    exit(1);
}

$sampleBindings = [];
foreach (array_filter(array_map('trim', explode(',', (string)($options['samples'] ?? '')))) as $binding) {
    if (!preg_match('/^([A-Za-z0-9._-]+):([1-9][0-9]*)$/', $binding, $match)) {
        fwrite(STDERR, "Invalid --samples binding: {$binding}\n");
        exit(1);
    }
    $sampleBindings[strtoupper($match[1])] = (int)$match[2];
}
if (count($sampleBindings) !== 2) {
    fwrite(STDERR, "Provide exactly two non-Fuji sample bindings.\n");
    exit(1);
}

$sampleAdapter = new RealSampleAdapter();
$sampleAdapter->db = $pdo;
$sampleAdapter->profile_config_path = dirname(__DIR__)
    . '/dtr-format-engine/config/multiclient_validation_profiles.json';
$sampleResult = $sampleAdapter->runSelectedAdapters($sampleBindings, $maker);
if (empty($sampleResult['success'])) {
    fwrite(STDERR, (string)($sampleResult['error'] ?? 'Non-Fuji staging validation failed.') . "\n");
    exit(1);
}

$identityManager = new EmployeeIdentityNotificationManager();
$identityManager->db = $pdo;
$fujiIdentity = $identityManager->syncBatch((int)$fujiResult['batch_id'], $maker);
if (empty($fujiIdentity['success'])) {
    fwrite(STDERR, (string)($fujiIdentity['error'] ?? 'Fuji identity reconciliation failed.') . "\n");
    exit(1);
}
foreach ($sampleResult['results'] as &$sample) {
    $identity = $identityManager->syncBatch((int)$sample['batch_id'], $maker);
    if (empty($identity['success'])) {
        fwrite(STDERR, (string)($identity['error'] ?? 'Non-Fuji identity reconciliation failed.') . "\n");
        exit(1);
    }
    $sample['identity_gate'] = [
        'status' => (string)$identity['gate_status'],
        'open_p0_count' => (int)$identity['open_p0_count'],
        'counts' => (array)$identity['counts'],
        'notification_id' => (int)$identity['notification_id'],
    ];
}
unset($sample);

echo json_encode([
    'success' => 1,
    'database_host' => $host,
    'canonical_write' => 'blocked_staging_only',
    'fuji' => [
        'client_id' => $fujiClient,
        'profile_id' => $profileId,
        'batch_id' => (int)$fujiResult['batch_id'],
        'duplicate_upload' => !empty($fujiResult['duplicate_upload']),
        'row_count' => (int)($fujiResult['summary']['row_count'] ?? 0),
        'adapter_match' => $fujiResult['adapter_match'] ?? [],
        'identity_gate' => [
            'status' => (string)$fujiIdentity['gate_status'],
            'open_p0_count' => (int)$fujiIdentity['open_p0_count'],
            'counts' => (array)$fujiIdentity['counts'],
            'notification_id' => (int)$fujiIdentity['notification_id'],
        ],
    ],
    'non_fuji' => $sampleResult['results'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
