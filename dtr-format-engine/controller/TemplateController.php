<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1]);
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

$model = new TemplateManager;
$model->db = $pdoConn;
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
$request = $_GET['request'] ?? $_POST['request'] ?? '';
$user = auth_user() ?: 'local_admin';

switch ($request) {
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
    case 'upload-synthetic':
        $upload = $parser->uploadSynthetic($_POST, $_FILES['synthetic_file'] ?? [], $user);
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
        echo json_encode($identityManager->listExceptions(
            (int)($_GET['batch_id'] ?? $_POST['batch_id'] ?? 0),
            (string)($_GET['status'] ?? $_POST['status'] ?? 'open')
        ));
        break;
    case 'employee-identity-gate':
        echo json_encode([
            'success' => 1,
            'summary' => $identityManager->getGateSummary(
                (int)($_GET['batch_id'] ?? $_POST['batch_id'] ?? 0)
            ),
        ]);
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
