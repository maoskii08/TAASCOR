<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1, 2, 3]);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');
ini_set('memory_limit', '256M');

require('../../config/db_connect.php');
require('../model/TemplateManager.php');
require('../model/SyntheticUploadParser.php');
require('../model/RealSampleAdapter.php');
require('../model/PayrollBasisPreview.php');
require('../model/EmployeeIdentityNotificationManager.php');
require('../model/FujiPayrollSummaryAdapter.php');
require('../model/DtrAdapterRegistry.php');
require('../model/GenericRealDtrUploadService.php');
require('../model/SmartEmployeeResolutionService.php');
require('../model/PayrollImportRunManager.php');
require('../model/PayrollPopulationExceptionManager.php');

$model = new TemplateManager;
$model->db = $pdoConn;
$model->allow_all_clients = auth_has_global_client_access();
$model->allowed_client_ids = auth_client_ids();
$parser = new SyntheticUploadParser;
$parser->db = $pdoConn;
$adapter = new RealSampleAdapter;
$adapter->db = $pdoConn;
$payrollBasisPreview = new PayrollBasisPreview;
$payrollBasisPreview->db = $pdoConn;
$identityManager = new EmployeeIdentityNotificationManager;
$identityManager->db = $pdoConn;
$fujiAdapter = new FujiPayrollSummaryAdapter;
$fujiAdapter->db = $pdoConn;
$adapterRegistry = new DtrAdapterRegistry;
$adapterRegistry->db = $pdoConn;
$adapterRegistry->allow_all_clients = auth_has_global_client_access();
$adapterRegistry->allowed_client_ids = auth_client_ids();
$realDtrUpload = new GenericRealDtrUploadService;
$realDtrUpload->db = $pdoConn;
$realDtrUpload->registry = $adapterRegistry;
$realDtrUpload->tabularParser = $parser;
$realDtrUpload->fujiAdapter = $fujiAdapter;
$smartResolution = new SmartEmployeeResolutionService;
$smartResolution->db = $pdoConn;
$payrollImportRuns = new PayrollImportRunManager;
$payrollImportRuns->db = $pdoConn;
$populationExceptions = new PayrollPopulationExceptionManager;
$populationExceptions->db = $pdoConn;
$request = $_POST['request'] ?? $_GET['request'] ?? '';
$user = auth_user() ?: 'local_admin';

$mutatingRequests = [
    'save-template', 'deactivate-template', 'upload-synthetic', 'upload-real-dtr',
    'upload-fuji-summary', 'create-adapter-profile', 'approve-adapter-profile',
    'clear-synthetic-batches', 'run-real-sample-adapters', 'clear-real-sample-adapters',
    'save-adapter-approval', 'run-payroll-basis-preview', 'sync-employee-identities',
    'approve-smart-employee-cohort', 'create-payroll-import-run',
    'approve-payroll-import-run', 'cancel-payroll-import-run',
    'mark-notification-read', 'resolve-employee-exception', 'set-smart-payroll-enrollment',
    'save-payroll-rule-set', 'import-population-exceptions', 'resolve-population-exception',
    'sync-population-notification',
];
if (in_array($request, $mutatingRequests, true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => 0, 'error' => 'This action requires POST.']);
    exit;
}

$adminOnlyRequests = [
    'save-template',
    'deactivate-template',
    'clear-synthetic-batches',
    'run-real-sample-adapters',
    'clear-real-sample-adapters',
    'save-adapter-approval',
    'create-adapter-profile',
    'approve-adapter-profile',
    'set-smart-payroll-enrollment',
    'save-payroll-rule-set',
];
if (in_array($request, $adminOnlyRequests, true) && auth_level() !== 1) {
    http_response_code(403);
    echo json_encode([
        'success' => 0,
        'error' => 'Administrator approval is required for template and adapter configuration changes.',
    ]);
    exit;
}

$identityOwnerRequests = ['approve-smart-employee-cohort', 'resolve-employee-exception'];
if (in_array($request, $identityOwnerRequests, true) && !in_array(auth_level(), [1, 2], true)) {
    http_response_code(403);
    echo json_encode(['success' => 0, 'error' => 'Admin or HR identity-owner approval is required.']);
    exit;
}

$payrollOwnerRequests = [
    'create-payroll-import-run', 'approve-payroll-import-run', 'cancel-payroll-import-run',
    'import-population-exceptions',
];
if (in_array($request, $payrollOwnerRequests, true) && !in_array(auth_level(), [1, 3], true)) {
    http_response_code(403);
    echo json_encode(['success' => 0, 'error' => 'Admin or Payroll access is required for payroll-run actions.']);
    exit;
}

if ($request === 'resolve-population-exception' && !in_array(auth_level(), [1, 2, 3], true)) {
    http_response_code(403);
    echo json_encode(['success' => 0, 'error' => 'Admin, HR, or Payroll ownership is required for a population disposition.']);
    exit;
}

function approved_payroll_ruleset($db, int $batchId): array
{
    $stmt = $db->prepare("\n        SELECT COALESCE(b.client_id, t.client_id) AS client_id, r.parsed_payload
        FROM dtr_upload_batches b
        INNER JOIN dtr_format_templates t ON t.id = b.template_id
        INNER JOIN dtr_upload_staging_rows r ON r.batch_id = b.id
        WHERE b.id = :batch_id AND r.validation_status <> 'excluded'
        ORDER BY r.id
    ");
    $stmt->execute([':batch_id' => $batchId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return ['success' => 0, 'error' => 'The staged batch has no eligible rows.'];
    }
    $clientId = (int)$rows[0]['client_id'];
    $payDates = [];
    foreach ($rows as $row) {
        $parsed = json_decode((string)$row['parsed_payload'], true);
        $payDate = trim((string)($parsed['pay_date'] ?? ''));
        if ($payDate !== '') {
            $payDates[$payDate] = true;
        }
    }
    if (count($payDates) !== 1) {
        return ['success' => 0, 'error' => 'The staged batch needs one unambiguous pay date before rule selection.'];
    }
    $payDate = array_key_first($payDates);
    $ruleset = $db->prepare("\n        SELECT * FROM payroll_import_rule_sets
        WHERE client_id = :client_id AND ruleset_status = 'approved'
          AND effective_from <= :pay_date
          AND (effective_to IS NULL OR effective_to >= :pay_date)
        ORDER BY effective_from DESC, id DESC
        LIMIT 2
    ");
    $ruleset->execute([':client_id' => $clientId, ':pay_date' => $payDate]);
    $matches = $ruleset->fetchAll(PDO::FETCH_ASSOC);
    if (count($matches) !== 1) {
        return [
            'success' => 0,
            'error_code' => 'APPROVED_RULESET_REQUIRED',
            'error' => count($matches) === 0
                ? 'No approved, effective-dated payroll ruleset is configured for this client and pay date.'
                : 'More than one approved payroll ruleset overlaps this pay date. Resolve the governance conflict first.',
        ];
    }
    $rules = json_decode((string)$matches[0]['rules_payload'], true);
    if (!is_array($rules)
        || !hash_equals((string)$matches[0]['rules_hash'], hash('sha256', PayrollImportRunManager::canonicalJson($rules)))) {
        return ['success' => 0, 'error_code' => 'RULESET_INTEGRITY_FAILED', 'error' => 'The approved payroll ruleset hash is invalid.'];
    }
    return [
        'success' => 1,
        'pay_date' => $payDate,
        'ruleset_key' => (string)$matches[0]['ruleset_key'],
        'ruleset_version' => (string)$matches[0]['ruleset_version'],
        'rules' => $rules,
    ];
}

function requireTemplateClientScope($db, int $templateId): void
{
    $stmt = $db->prepare('SELECT client_id FROM dtr_format_templates WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $templateId]);
    $clientId = $stmt->fetchColumn();
    if ($clientId === false) {
        http_response_code(404);
        echo json_encode(['success' => 0, 'error' => 'The requested template was not found.']);
        exit;
    }
    if ($clientId === null || (int)$clientId <= 0) {
        if (!auth_has_global_client_access()) {
            http_response_code(403);
            echo json_encode(['success' => 0, 'error' => 'Access denied for this shared DTR template.']);
            exit;
        }
        return;
    }
    auth_require_client_id((int)$clientId);
}

function requireBatchClientScope($db, int $batchId): void
{
    $stmt = $db->prepare("\n        SELECT COALESCE(b.client_id, t.client_id) AS client_id
        FROM dtr_upload_batches b
        INNER JOIN dtr_format_templates t ON t.id = b.template_id
        WHERE b.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $batchId]);
    $clientId = $stmt->fetchColumn();
    if ($clientId === false) {
        http_response_code(404);
        echo json_encode(['success' => 0, 'error' => 'The requested DTR batch was not found.']);
        exit;
    }
    auth_require_client_id((int)$clientId);
}

function requirePayrollRunClientScope($db, int $runId): void
{
    $stmt = $db->prepare('SELECT client_id FROM payroll_import_runs WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $runId]);
    $clientId = $stmt->fetchColumn();
    if ($clientId === false) {
        http_response_code(404);
        echo json_encode(['success' => 0, 'error' => 'The requested payroll run was not found.']);
        exit;
    }
    auth_require_client_id((int)$clientId);
}

function requireExceptionClientScope($db, int $exceptionId): void
{
    $stmt = $db->prepare("\n        SELECT COALESCE(b.client_id, t.client_id) AS client_id
        FROM dtr_employee_exceptions e
        INNER JOIN dtr_upload_batches b ON b.id = e.batch_id
        INNER JOIN dtr_format_templates t ON t.id = b.template_id
        WHERE e.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $exceptionId]);
    $clientId = $stmt->fetchColumn();
    if ($clientId === false) {
        http_response_code(404);
        echo json_encode(['success' => 0, 'error' => 'The requested employee exception was not found.']);
        exit;
    }
    auth_require_client_id((int)$clientId);
}

function requirePopulationExceptionClientScope($db, int $exceptionId): void
{
    $stmt = $db->prepare(
        'SELECT client_id FROM payroll_population_exceptions WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $exceptionId]);
    $clientId = $stmt->fetchColumn();
    if ($clientId === false) {
        http_response_code(404);
        echo json_encode(['success' => 0, 'error' => 'The requested population exception was not found.']);
        exit;
    }
    auth_require_client_id((int)$clientId);
}

$directClientRequests = ['save-payroll-rule-set', 'smart-payroll-enrollment', 'set-smart-payroll-enrollment'];
if (in_array($request, $directClientRequests, true)) {
    auth_require_client_id((int)($_GET['client_id'] ?? $_POST['client_id'] ?? 0));
}

$batchScopedRequests = [
    'batch-rows', 'sync-employee-identities', 'employee-identity-exceptions',
    'employee-identity-gate', 'smart-employee-resolution-preview',
    'approve-smart-employee-cohort', 'create-payroll-import-run',
    'latest-payroll-import-run', 'payroll-population-exceptions',
    'import-population-exceptions', 'sync-population-notification',
];
if (in_array($request, $batchScopedRequests, true)) {
    $scopedBatchId = (int)($_GET['batch_id'] ?? $_POST['batch_id'] ?? 0);
    if ($scopedBatchId > 0) {
        requireBatchClientScope($pdoConn, $scopedBatchId);
    } else {
        http_response_code(400);
        echo json_encode(['success' => 0, 'error' => 'Select one staged DTR batch.']);
        exit;
    }
}

$runScopedRequests = ['payroll-import-run-status', 'approve-payroll-import-run', 'cancel-payroll-import-run'];
if (in_array($request, $runScopedRequests, true)) {
    requirePayrollRunClientScope($pdoConn, (int)($_GET['run_id'] ?? $_POST['run_id'] ?? 0));
}

if ($request === 'resolve-employee-exception') {
    requireExceptionClientScope($pdoConn, (int)($_POST['exception_id'] ?? 0));
}
if ($request === 'resolve-population-exception') {
    requirePopulationExceptionClientScope($pdoConn, (int)($_POST['exception_id'] ?? 0));
}
if ($request === 'get-template') {
    requireTemplateClientScope($pdoConn, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
}
if ($request === 'upload-synthetic') {
    requireTemplateClientScope($pdoConn, (int)($_POST['template_id'] ?? 0));
}
if ($request === 'upload-real-dtr') {
    auth_require_client_id((int)($_POST['client_id'] ?? 0));
}
if ($request === 'upload-fuji-summary') {
    auth_require_client_id(FujiPayrollSummaryAdapter::CLIENT_ID);
}

switch ($request) {
    case 'save-payroll-rule-set':
        $clientId = (int)($_POST['client_id'] ?? 0);
        $key = trim((string)($_POST['ruleset_key'] ?? ''));
        $version = trim((string)($_POST['ruleset_version'] ?? ''));
        $effectiveFrom = trim((string)($_POST['effective_from'] ?? ''));
        $effectiveTo = trim((string)($_POST['effective_to'] ?? '')) ?: null;
        $rules = json_decode((string)($_POST['rules_payload'] ?? ''), true);
        $dateValid = static function (?string $value): bool {
            if ($value === null) { return true; }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $errors = DateTimeImmutable::getLastErrors();
            return $date instanceof DateTimeImmutable
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
        };
        if ($clientId <= 0 || $key === '' || $version === '' || !is_array($rules) || !$rules
            || !$dateValid($effectiveFrom) || !$dateValid($effectiveTo)
            || ($effectiveTo !== null && $effectiveTo < $effectiveFrom)) {
            echo json_encode(['success' => 0, 'error' => 'A valid client, version, effective window, and non-empty rule manifest are required.']);
            break;
        }
        foreach ($rules as $rule) {
            if (!is_array($rule) || trim((string)($rule['rule_type'] ?? '')) === ''
                || trim((string)($rule['rule_key'] ?? '')) === ''
                || trim((string)($rule['rule_version'] ?? '')) === ''
                || !array_key_exists('snapshot', $rule)) {
                echo json_encode(['success' => 0, 'error' => 'Every rule needs type, key, version, and an immutable snapshot.']);
                break 2;
            }
        }
        $payload = PayrollImportRunManager::canonicalJson($rules);
        try {
            $stmt = $pdoConn->prepare("\n                INSERT INTO payroll_import_rule_sets (
                    client_id, ruleset_key, ruleset_version, effective_from, effective_to,
                    rules_payload, rules_hash, ruleset_status, approved_by, approved_at
                ) VALUES (
                    :client_id, :ruleset_key, :ruleset_version, :effective_from, :effective_to,
                    :rules_payload, :rules_hash, 'approved', :approved_by, NOW()
                )
            ");
            $stmt->execute([
                ':client_id' => $clientId,
                ':ruleset_key' => $key,
                ':ruleset_version' => $version,
                ':effective_from' => $effectiveFrom,
                ':effective_to' => $effectiveTo,
                ':rules_payload' => $payload,
                ':rules_hash' => hash('sha256', $payload),
                ':approved_by' => $user,
            ]);
            echo json_encode(['success' => 1, 'ruleset_id' => (int)$pdoConn->lastInsertId(), 'rules_hash' => hash('sha256', $payload)]);
        } catch (Throwable $error) {
            error_log('Payroll ruleset save failed: ' . $error->getMessage());
            echo json_encode(['success' => 0, 'error' => 'The ruleset version already exists or could not be saved.']);
        }
        break;
    case 'smart-payroll-enrollment':
        $clientId = (int)($_GET['client_id'] ?? $_POST['client_id'] ?? 0);
        $stmt = $pdoConn->prepare("\n            SELECT c.client_id, c.client_name, COALESCE(s.smart_flow_enabled, 0) AS smart_flow_enabled,
                   s.enabled_by, s.enabled_at, s.rollout_notes
            FROM taascor_client c
            LEFT JOIN payroll_import_client_settings s ON s.client_id = c.client_id
            WHERE c.client_id = :client_id
        ");
        $stmt->execute([':client_id' => $clientId]);
        echo json_encode(['success' => 1, 'enrollment' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null]);
        break;
    case 'set-smart-payroll-enrollment':
        $clientId = (int)($_POST['client_id'] ?? 0);
        $enabled = (string)($_POST['enabled'] ?? '0') === '1';
        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($clientId <= 0 || $notes === '') {
            echo json_encode(['success' => 0, 'error' => 'Client and rollout decision notes are required.']);
            break;
        }
        if (!$enabled) {
            echo json_encode([
                'success' => 0,
                'error_code' => 'SMART_PAYROLL_DOWNGRADE_BLOCKED',
                'error' => 'Smart payroll enrollment is a one-way governed cutover. Rollback requires an audited migration, not an application toggle.',
            ]);
            break;
        }
        if ($enabled) {
            $rules = $pdoConn->prepare("\n                SELECT COUNT(*) FROM payroll_import_rule_sets
                WHERE client_id = :client_id
                  AND ruleset_status = 'approved'
                  AND effective_from <= CURDATE()
                  AND (effective_to IS NULL OR effective_to >= CURDATE())
            ");
            $rules->execute([':client_id' => $clientId]);
            if ((int)$rules->fetchColumn() === 0) {
                echo json_encode(['success' => 0, 'error' => 'Approve an effective-dated payroll ruleset before enrollment.']);
                break;
            }
        }
        $stmt = $pdoConn->prepare("\n            INSERT INTO payroll_import_client_settings (
                client_id, smart_flow_enabled, enabled_by, enabled_at, rollout_notes
            ) VALUES (:client_id, :enabled, :enabled_by, :enabled_at, :rollout_notes)
            ON DUPLICATE KEY UPDATE
                smart_flow_enabled = VALUES(smart_flow_enabled),
                enabled_by = VALUES(enabled_by), enabled_at = VALUES(enabled_at),
                rollout_notes = VALUES(rollout_notes)
        ");
        $stmt->execute([
            ':client_id' => $clientId,
            ':enabled' => 1,
            ':enabled_by' => $user,
            ':enabled_at' => date('Y-m-d H:i:s'),
            ':rollout_notes' => $notes,
        ]);
        if (function_exists('log_action')) {
            log_action('Smart payroll one-way enrollment enabled for client ' . $clientId, $pdoConn);
        }
        echo json_encode(['success' => 1, 'client_id' => $clientId, 'smart_flow_enabled' => true]);
        break;
    case 'lookups':
        echo json_encode($model->getLookups());
        break;
    case 'list-templates':
        echo json_encode($model->listTemplates());
        break;
    case 'get-template':
        echo json_encode($model->getTemplate((int)($_GET['id'] ?? $_POST['id'] ?? 0)));
        break;
    case 'save-template':
        echo json_encode($model->saveTemplate($_POST, $user));
        break;
    case 'deactivate-template':
        echo json_encode($model->deactivateTemplate((int)($_POST['id'] ?? 0), $user));
        break;
    case 'list-batches':
        echo json_encode($model->listBatches());
        break;
    case 'adapter-profiles':
        echo json_encode($adapterRegistry->listProfiles(false));
        break;
    case 'approved-adapter-profiles':
        echo json_encode($adapterRegistry->listProfiles(true));
        break;
    case 'create-adapter-profile':
        echo json_encode($adapterRegistry->createDraftFromTemplate($_POST, $user));
        break;
    case 'approve-adapter-profile':
        echo json_encode($adapterRegistry->approveProfile(
            (int)($_POST['profile_id'] ?? 0),
            $user,
            (string)($_POST['approval_reason'] ?? '')
        ));
        break;
    case 'upload-synthetic':
        $upload = $parser->uploadSynthetic($_POST, $_FILES['synthetic_file'] ?? [], $user);
        if (!empty($upload['success']) && !empty($upload['batch_id'])) {
            $upload['identity_gate'] = $identityManager->syncBatch((int)$upload['batch_id'], $user);
        }
        echo json_encode($upload);
        break;
    case 'upload-real-dtr':
        @set_time_limit(180);
        $upload = $realDtrUpload->stage($_POST, $_FILES['dtr_file'] ?? [], $user);
        if (!empty($upload['success']) && !empty($upload['batch_id'])) {
            $upload['identity_gate'] = $identityManager->syncBatch((int)$upload['batch_id'], $user);
        }
        echo json_encode($upload);
        break;
    case 'upload-fuji-summary':
        @set_time_limit(180);
        $upload = $fujiAdapter->stageUpload($_POST, $_FILES['fuji_file'] ?? [], $user);
        if (!empty($upload['success']) && !empty($upload['batch_id'])) {
            $upload['identity_gate'] = $identityManager->syncBatch((int)$upload['batch_id'], $user);
        }
        echo json_encode($upload);
        break;
    case 'clear-synthetic-batches':
        echo json_encode($parser->clearSyntheticBatches());
        break;
    case 'batch-rows':
        echo json_encode($parser->getBatchRows((int)($_GET['batch_id'] ?? $_POST['batch_id'] ?? 0)));
        break;
    case 'normalization-preview':
        echo json_encode($parser->getNormalizationPreview((string)($_GET['source_context'] ?? $_POST['source_context'] ?? '')));
        break;
    case 'timekeeping-preview':
        echo json_encode($parser->getTimekeepingPreview((string)($_GET['source_context'] ?? $_POST['source_context'] ?? '')));
        break;
    case 'real-sample-profile':
        echo json_encode($adapter->profileSamples());
        break;
    case 'run-real-sample-adapters':
        @set_time_limit(180);
        echo json_encode($adapter->runAdapters($user));
        break;
    case 'clear-real-sample-adapters':
        echo json_encode($adapter->clearAdapterBatches());
        break;
    case 'real-sample-adapter-summary':
        echo json_encode($adapter->adapterSummary());
        break;
    case 'adapter-approval-workflow':
        echo json_encode($adapter->approvalWorkflow());
        break;
    case 'adapter-preview-workflow':
        @set_time_limit(180);
        echo json_encode([
            'success' => 1,
            'mode' => 'local_read_only_preview_workflow',
            'source_context' => 'real_sample_batch10_profile_adapter',
            'sample_intake' => $adapter->adapterSummary(),
            'workbook_profile' => $adapter->profileSamples(),
            'validation_summary' => $adapter->adapterSummary(),
            'normalization_preview' => $parser->getNormalizationPreview('real_sample_batch10_profile_adapter'),
            'timekeeping_preview' => $parser->getTimekeepingPreview('real_sample_batch10_profile_adapter'),
            'adapter_approval_status' => $adapter->approvalWorkflow(),
            'safety' => [
                'canonical_dtr_write' => 'blocked',
                'payroll_write' => 'blocked',
                'payroll_generation' => 'blocked',
                'payroll_handoff' => 'blocked',
            ],
        ]);
        break;
    case 'save-adapter-approval':
        echo json_encode($adapter->saveApprovalReview($_POST, $user));
        break;
    case 'run-payroll-basis-preview':
        $gate = $identityManager->getGateSummary();
        if (($gate['gate_status'] ?? 'blocked') !== 'ready') {
            echo json_encode([
                'success' => 0,
                'error' => 'Payroll basis preview is blocked by unresolved employee identity exceptions.',
                'identity_gate' => $gate,
            ]);
            break;
        }
        echo json_encode($payrollBasisPreview->buildFromCurrentPreview($user));
        break;
    case 'payroll-basis-preview':
        echo json_encode($payrollBasisPreview->listPreview());
        break;
    case 'sync-employee-identities':
        echo json_encode($identityManager->syncBatch((int)($_POST['batch_id'] ?? 0), $user));
        break;
    case 'employee-identity-exceptions':
        echo json_encode(
            $identityManager->listExceptions(
                (int)($_GET['batch_id'] ?? $_POST['batch_id'] ?? 0),
                (string)($_GET['status'] ?? $_POST['status'] ?? 'open')
            ),
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        break;
    case 'employee-identity-gate':
        echo json_encode([
            'success' => 1,
            'summary' => $identityManager->getGateSummary(
                (int)($_GET['batch_id'] ?? $_POST['batch_id'] ?? 0)
            ),
        ]);
        break;
    case 'payroll-population-exceptions':
        echo json_encode(
            $populationExceptions->listForBatch(
                (int)($_GET['batch_id'] ?? $_POST['batch_id'] ?? 0),
                (string)($_GET['status'] ?? $_POST['status'] ?? 'all')
            ),
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        break;
    case 'import-population-exceptions':
        $records = json_decode((string)($_POST['records'] ?? ''), true);
        $result = $populationExceptions->importForBatch(
            (int)($_POST['batch_id'] ?? 0),
            is_array($records) ? $records : [],
            $user
        );
        if (!empty($result['success'])) {
            $result['notification'] = $identityManager->syncPopulationNotification(
                (int)($_POST['batch_id'] ?? 0),
                $user
            );
        }
        echo json_encode($result);
        break;
    case 'resolve-population-exception':
        $exceptionId = (int)($_POST['exception_id'] ?? 0);
        $batchLookup = $pdoConn->prepare(
            'SELECT batch_id FROM payroll_population_exceptions WHERE id = :id LIMIT 1'
        );
        $batchLookup->execute([':id' => $exceptionId]);
        $populationBatchId = (int)$batchLookup->fetchColumn();
        $result = $populationExceptions->resolve(
            $exceptionId,
            (string)($_POST['disposition'] ?? ''),
            (string)($_POST['reason'] ?? ''),
            $user
        );
        if (!empty($result['success']) && $populationBatchId > 0) {
            $result['notification'] = $identityManager->syncPopulationNotification(
                $populationBatchId,
                $user
            );
        }
        echo json_encode($result);
        break;
    case 'sync-population-notification':
        echo json_encode($identityManager->syncPopulationNotification(
            (int)($_POST['batch_id'] ?? 0),
            $user
        ));
        break;
    case 'smart-employee-resolution-preview':
        @set_time_limit(180);
        echo json_encode($smartResolution->previewBatch(
            (int)($_GET['batch_id'] ?? $_POST['batch_id'] ?? 0)
        ));
        break;
    case 'approve-smart-employee-cohort':
        @set_time_limit(180);
        $selectedSourceKeys = json_decode((string)($_POST['source_keys'] ?? '[]'), true);
        if (!is_array($selectedSourceKeys)) {
            $selectedSourceKeys = [];
        }
        $approval = $smartResolution->approveSafeCohort(
            (int)($_POST['batch_id'] ?? 0),
            trim((string)($_POST['reason'] ?? '')),
            $user,
            $selectedSourceKeys
        );
        if (!empty($approval['success'])) {
            $approval['identity_gate'] = $identityManager->syncBatch(
                (int)($_POST['batch_id'] ?? 0),
                $user
            );
        }
        echo json_encode($approval);
        break;
    case 'create-payroll-import-run':
        @set_time_limit(180);
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $ruleset = approved_payroll_ruleset($pdoConn, $batchId);
        if (($ruleset['success'] ?? 0) !== 1) {
            echo json_encode($ruleset);
            break;
        }
        echo json_encode($payrollImportRuns->createAndCanonicalizeFromStagedBatch(
            $batchId,
            [
                'run_type' => 'guarded_dtr_import',
                'pay_date' => (string)$ruleset['pay_date'],
                'ruleset_key' => (string)$ruleset['ruleset_key'],
                'ruleset_version' => (string)$ruleset['ruleset_version'],
                'rules' => (array)$ruleset['rules'],
            ],
            $user
        ));
        break;
    case 'latest-payroll-import-run':
        echo json_encode($payrollImportRuns->getLatestRunForBatch(
            (int)($_GET['batch_id'] ?? $_POST['batch_id'] ?? 0)
        ));
        break;
    case 'payroll-import-run-status':
        echo json_encode($payrollImportRuns->getReleaseStatus(
            (int)($_GET['run_id'] ?? $_POST['run_id'] ?? 0)
        ));
        break;
    case 'approve-payroll-import-run':
        echo json_encode($payrollImportRuns->approveRun(
            (int)($_POST['run_id'] ?? 0),
            $user
        ));
        break;
    case 'cancel-payroll-import-run':
        echo json_encode($payrollImportRuns->transitionRun(
            (int)($_POST['run_id'] ?? 0),
            'cancelled',
            $user,
            trim((string)($_POST['reason'] ?? ''))
        ));
        break;
    case 'notifications':
        echo json_encode($identityManager->listNotifications(
            $user,
            (int)($_GET['limit'] ?? 20)
        ));
        break;
    case 'mark-notification-read':
        echo json_encode($identityManager->markNotificationRead(
            (int)($_POST['notification_id'] ?? 0),
            $user
        ));
        break;
    case 'resolve-employee-exception':
        echo json_encode($identityManager->resolveException(
            (int)($_POST['exception_id'] ?? 0),
            trim((string)($_POST['action'] ?? '')),
            (int)($_POST['employee_id'] ?? 0),
            trim((string)($_POST['reason'] ?? '')),
            $user
        ));
        break;
    default:
        echo json_encode([
            'success' => 0,
            'error' => 'Unknown DTR Format Engine request.',
        ]);
        break;
}

?>
