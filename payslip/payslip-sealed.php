<?php

declare(strict_types=1);

require_once('../includes/auth_guard.php');
auth_require_role([1, 3]);
require('../config/db_connect.php');
require_once('../dtr-format-engine/model/PayslipArtifactStore.php');

$runId = filter_input(INPUT_GET, 'run_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$employeeId = filter_input(INPUT_GET, 'employee_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($runId === false || $runId === null) {
    http_response_code(400);
    exit('A released payroll run is required.');
}

$scopeSql = '';
$params = [
    ':run_id' => (int)$runId,
    ':employee_id' => $employeeId === false || $employeeId === null ? null : (int)$employeeId,
    ':employee_id_match' => $employeeId === false || $employeeId === null ? null : (int)$employeeId,
];
if (!auth_has_global_client_access()) {
    $clientIds = auth_client_ids();
    if ($clientIds === []) {
        http_response_code(403);
        exit('No client access is assigned to this account.');
    }
    $placeholders = [];
    foreach ($clientIds as $index => $clientId) {
        $name = ':client_scope_' . $index;
        $placeholders[] = $name;
        $params[$name] = $clientId;
    }
    $scopeSql = ' AND r.client_id IN (' . implode(',', $placeholders) . ')';
}

$stmt = $pdoConn->prepare("\n    SELECT a.employee_id, a.storage_path, a.content_hash, a.byte_size,
           COALESCE(NULLIF(e.full_name, ''), CONCAT(e.last_name, ', ', e.first_name)) AS employee_name,
           r.run_uid, r.pay_date, l.client_name
    FROM payroll_import_payslip_artifacts a
    INNER JOIN payroll_import_runs r ON r.id = a.run_id
    INNER JOIN payroll_import_release_locks l ON l.run_id = r.id
    INNER JOIN employee_list e ON e.employee_id = a.employee_id
    WHERE a.run_id = :run_id
      AND a.artifact_type = 'payslip_pdf'
      AND a.artifact_status = 'published'
      AND r.status = 'released'
      AND r.release_status = 'released'
      AND (:employee_id IS NULL OR a.employee_id = :employee_id_match)
      $scopeSql
    ORDER BY employee_name, a.employee_id
");
$stmt->execute($params);
$artifacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$artifacts) {
    http_response_code(404);
    exit('No published, sealed payslip artifact was found for this released run.');
}

function sealed_artifact_path(array $artifact): ?string
{
    $verified = (new PayslipArtifactStore())->verifyPdf(
        (string)$artifact['storage_path'],
        (string)$artifact['content_hash']
    );
    return $verified === null ? null : (string)$verified['absolute_path'];
}

if ($employeeId !== false && $employeeId !== null) {
    $artifact = $artifacts[0];
    $path = sealed_artifact_path($artifact);
    if ($path === null) {
        http_response_code(409);
        exit('The sealed payslip artifact is missing or its content hash no longer matches.');
    }
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="payslip-' . (int)$artifact['employee_id'] . '.pdf"');
    header('Cache-Control: private, no-store, max-age=0');
    readfile($path);
    exit;
}

$first = $artifacts[0];
header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sealed Payslips</title>
  <style>
    body{font:15px/1.45 system-ui,sans-serif;background:#f5f6f8;color:#18202b;margin:0;padding:32px}
    main{max-width:920px;margin:auto;background:#fff;border:1px solid #dde1e7;border-radius:12px;padding:28px}
    h1{margin:0 0 8px;font-size:24px}.meta{color:#5d6877;margin-bottom:22px}
    a{color:#8b1455;text-decoration:none;font-weight:650}.row{display:flex;justify-content:space-between;gap:16px;padding:12px 0;border-top:1px solid #edf0f3}
    .seal{font-size:12px;color:#647184}
  </style>
</head>
<body><main>
  <h1>Published sealed payslips</h1>
  <div class="meta"><?php echo htmlspecialchars((string)$first['client_name']); ?> · <?php echo htmlspecialchars((string)$first['pay_date']); ?> · <?php echo htmlspecialchars((string)$first['run_uid']); ?></div>
  <?php foreach ($artifacts as $artifact):
      $path = sealed_artifact_path($artifact);
      $valid = $path !== null;
  ?>
    <div class="row">
      <div><?php echo htmlspecialchars((string)$artifact['employee_name']); ?><div class="seal">Employee <?php echo (int)$artifact['employee_id']; ?> · SHA-256 <?php echo htmlspecialchars(substr((string)$artifact['content_hash'], 0, 16)); ?>…</div></div>
      <div><?php if ($valid): ?><a href="?run_id=<?php echo (int)$runId; ?>&amp;employee_id=<?php echo (int)$artifact['employee_id']; ?>">Open sealed PDF</a><?php else: ?><span class="seal">Artifact integrity error</span><?php endif; ?></div>
    </div>
  <?php endforeach; ?>
</main></body></html>
