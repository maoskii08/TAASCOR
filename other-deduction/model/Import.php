<?php
require_once __DIR__ . '/../../dtr-upload/model/PayrollLockGuard.php';
require_once __DIR__ . '/../../includes/payroll_adjustment_guard.php';

class Import{

  public $db = null;
  public $payrollDetails = array();

  public $client_name = null;
  public $cut_off = null;
  public $pay_day = null;
  public $change_reason = null;
  public $evidence_reference = null;
  public $source_filename = null;

  public function spDeduction(){
    $scope = $this->validatedPayrollScope();
    if (($scope['success'] ?? 0) !== 1) {
      return $scope;
    }
    return PayrollAdjustmentGuard::recalculateScope($this->db, $scope);
  }


  public function add(array $data, $columnMap, $expectedRowCount = null){
    try {
      $scope = $this->validatedPayrollScope();
      if (($scope['success'] ?? 0) !== 1) {
        return $scope;
      }
      $this->payrollDetails = [[
        $scope['client_name'], $scope['cut_off'], $scope['pay_day'],
        $scope['start_date'], $scope['end_date'],
      ]];
      $audit = PayrollAdjustmentGuard::validateAuditContext([
        'change_reason' => $this->change_reason,
        'evidence_reference' => $this->evidence_reference,
      ]);
      if (($audit['success'] ?? 0) !== 1) {
        return $audit;
      }
      $rowLimit = PayrollAdjustmentGuard::validateAtomicWorkbookRows($data);
      if (($rowLimit['success'] ?? 0) !== 1) {
        return $rowLimit;
      }
      $expectedRows = filter_var(
        $expectedRowCount,
        FILTER_VALIDATE_INT,
        ['options' => [
          'min_range' => 1,
          'max_range' => PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_ROWS,
        ]]
      );
      if ($expectedRows === false || (int)$expectedRows !== count($data)) {
        return [
          'success' => 0,
          'code' => 'incomplete_atomic_workbook',
          'error' => 'The complete workbook row set must be submitted in one request.',
        ];
      }
      $sourceFilename = PayrollAdjustmentGuard::auditValue($this->source_filename);
      if ($sourceFilename === '') {
        return [
          'success' => 0,
          'code' => 'source_filename_required',
          'error' => 'The source workbook filename is required.',
        ];
      }
      $normalized = $this->normalizeImportRows($data, $columnMap);
      if (($normalized['success'] ?? 0) !== 1) {
        return $normalized;
      }

      $rows = $normalized['rows'];
      if (count($rows) !== (int)$expectedRows) {
        return [
          'success' => 0,
          'code' => 'incomplete_atomic_workbook',
          'error' => 'The validated workbook row count does not match the submitted row count.',
        ];
      }

      $this->db->beginTransaction();
      $employeeIds = array_column($rows, 'employee_id');
      $eligible = array_flip(PayrollAdjustmentGuard::eligibleEmployeeIds($this->db, $scope, $employeeIds));
      $existing = PayrollAdjustmentGuard::existingFingerprints(
        $this->db,
        'payroll_other_deduction',
        'type_of_deduction',
        $scope
      );
      $workbookFingerprints = [];
      foreach ($rows as $index => $row) {
        if (!isset($eligible[(int)$row['employee_id']])) {
          $this->db->rollBack();
          return [
            'success' => 0,
            'code' => 'employee_not_in_dtr_population',
            'error' => 'One or more employees are not active members of the selected DTR population.',
            'row' => $index + 1,
          ];
        }
        $fingerprint = PayrollAdjustmentGuard::fingerprint(
          (int)$row['employee_id'],
          (string)$row['amount'],
          (string)$row['type_of_deduction']
        );
        if (isset($existing[$fingerprint]) || isset($workbookFingerprints[$fingerprint])) {
          $this->db->rollBack();
          return [
            'success' => 0,
            'code' => 'duplicate_adjustment',
            'error' => 'The file contains an exact duplicate or a deduction that already exists.',
            'row' => $index + 1,
          ];
        }
        $workbookFingerprints[$fingerprint] = true;
      }

      $rowRecords = [];
      foreach ($rows as $row) {
        $insert = $this->insertNormalizedRow($scope, $row);
        if (($insert['success'] ?? 0) !== 1) {
          $this->db->rollBack();
          return $insert;
        }
        $rowRecords[] = [
          'source_row_number' => $row['source_row_number'],
          'adjustment_id' => $insert['inserted_id'],
          'employee_id' => $row['employee_id'],
          'amount' => $row['amount'],
          'type' => $row['type_of_deduction'],
        ];
      }

      $recalculation = PayrollAdjustmentGuard::recalculateScope(
        $this->db,
        $scope,
        array_values(array_unique(array_map('intval', $employeeIds)))
      );
      if (($recalculation['success'] ?? 0) !== 1) {
        $this->db->rollBack();
        return $recalculation;
      }

      $auditEventId = PayrollAdjustmentGuard::writeAuditRows(
        $this->db,
        'DEDUCTION_BULK',
        $scope + [
          'change_reason' => $audit['audit']['change_reason'],
          'evidence_reference' => $audit['audit']['evidence_reference'],
          'source_filename' => $sourceFilename,
          'row_records' => $rowRecords,
        ]
      );
      $this->db->commit();
      $response['success'] = 1;
      $response['message'] = 'Success';
      $response['rows_written'] = count($rows);
      $response['audit_event_id'] = $auditEventId;
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('Deduction bulk import failed: ' . $e->getMessage());
      $response['success'] = 0;
      $response['error'] = "An error occurred. Please contact your administrator.";
    }
    return $response;
  }

  public function validatedPayrollScope(): array
  {
    $lockScope = PayrollLockGuard::normalizePayrollDetails($this->payrollDetails);
    if (($lockScope['success'] ?? 0) !== 1) {
      return $lockScope;
    }
    $scope = PayrollAdjustmentGuard::validateScope($lockScope);
    return ($scope['success'] ?? 0) === 1
      ? ['success' => 1, 'locked' => false] + $scope['scope']
      : $scope;
  }

  private function normalizeImportRows(array $data, $columnMap): array
  {
    if (!is_array($columnMap)) {
      return ['success' => 0, 'code' => 'invalid_column_map', 'error' => 'The upload column mapping is invalid.'];
    }
    $required = ['employee id', 'amount', 'type of deduction'];
    $requiredIndexes = [];
    foreach ($required as $header) {
      $index = array_key_exists($header, $columnMap)
        ? filter_var($columnMap[$header], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
        : false;
      if ($index === false) {
        return ['success' => 0, 'code' => 'missing_required_column', 'error' => "Map the required column: {$header}."];
      }
      if (isset($requiredIndexes[(int)$index])) {
        return [
          'success' => 0,
          'code' => 'duplicate_column_mapping',
          'error' => 'Each required payroll field must map to a different workbook column.',
        ];
      }
      $requiredIndexes[(int)$index] = true;
      $columnMap[$header] = (int)$index;
    }

    $rows = [];
    foreach ($data as $index => $columnData) {
      if (!is_array($columnData)) {
        return ['success' => 0, 'code' => 'invalid_import_row', 'error' => 'The upload contains an invalid row.', 'row' => $index + 1];
      }
      $input = [
        'employee_id' => $columnData[(int)$columnMap['employee id']] ?? null,
        'amount' => $columnData[(int)$columnMap['amount']] ?? null,
        'type_of_deduction' => $columnData[(int)$columnMap['type of deduction']] ?? null,
      ];
      $validation = PayrollAdjustmentGuard::validateAdjustment($input, 'type_of_deduction');
      if (($validation['success'] ?? 0) !== 1) {
        $validation['row'] = $index + 1;
        return $validation;
      }
      $rows[] = $validation['adjustment'] + ['source_row_number' => $index + 2];
    }
    if (count($rows) === 0) {
      return ['success' => 0, 'code' => 'empty_import', 'error' => 'The upload contains no payroll deduction rows.'];
    }
    return ['success' => 1, 'rows' => $rows];
  }

  private function insertNormalizedRow(array $scope, array $row): array
  {
    try {
      $sql = "INSERT INTO payroll_other_deduction
              (
                  client_name,
                  pay_day,
                  cut_off,
                  employee_id,
                  amount,
                  type_of_deduction,
                  start_date,
                  end_date
              ) 
              VALUES (
                  :client_name,
                  :pay_day,
                  :cut_off,
                  :employee_id,
                  :amount,
                  :type_of_deduction,
                  :start_date,
                  :end_date
              )";

      $stmt = $this->db->prepare($sql);
      $stmt->execute([
        ':client_name' => $scope['client_name'],
        ':pay_day' => $scope['pay_day'],
        ':cut_off' => $scope['cut_off'],
        ':employee_id' => $row['employee_id'],
        ':amount' => $row['amount'],
        ':type_of_deduction' => $row['type_of_deduction'],
        ':start_date' => $scope['start_date'],
        ':end_date' => $scope['end_date'],
      ]);
      $insertedId = (int)$this->db->lastInsertId();
      if ($stmt->rowCount() !== 1 || $insertedId < 1) {
        throw new RuntimeException('The inserted deduction could not be identified.');
      }

      $response = array(
        "success" => 1,
        "message" => 'Successfully imported',
        "inserted_id" => $insertedId,
      );

    } catch (Throwable $e) {
      error_log('Deduction import row failed: ' . $e->getMessage());
      $response = array(
        "success" => 0,
        "error" => "Unable to import payroll deduction."
      );
 
    }
    return $response;
  }
  
}

?>
