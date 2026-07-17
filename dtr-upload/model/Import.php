<?php
require_once __DIR__ . '/PayrollLockGuard.php';

class Import{

  public $db = null;
  public $payrollDetails = array();

  public $client_name = null;
  public $cut_off = null;
  public $pay_day = null;

  public function spCalculateDTR(){
        
    try {
      $sql = "CALL sp_calculate_dtr(
                    :client_name
                    ,:pay_day
                    ,:cut_off
              )";
      if($this->cut_off == 'Weekly'){
        $sql = "CALL sp_calculate_dtr_weekly(
          :client_name
          ,:pay_day
          ,:cut_off
        )";
      }
      $stmt = $this->db->prepare($sql);
      $stmt->bindParam(':client_name', $this->client_name, PDO::PARAM_STR);
      $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
      $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
      $stmt->execute();

      $response = array(
        "success" => 1
      );

    } catch (PDOException $e) {
      error_log('Import::spCalculateDTR failed: ' . $e->getMessage());
      $response = array(
        "success" => 0,
        "message" => "Unable to calculate DTR."
      );
 
    }

    return $response;
  }

  public function validate(){
    
    try {      
      $success = [];
      $blanks = [];
      
      $blanks = $this->blanks(); 
      $invalidWorkDays = $this->invalidWorkDays(); 

      if($blanks['success'] == 1){
        if(count($blanks['data']) > 0){ 
            $this->errors['blanks'] = $blanks['data']; 
            $this->error_message['blanks'] = 'Salary or Days Work is blank or 0';
            $this->error_count['blanks'] = count($blanks['data']);
            $success[] = 'blanks'; 
        }
      }

      if($invalidWorkDays['success'] == 1){
        if(count($invalidWorkDays['data']) > 0){ 
            $this->errors['invalidWorkDays'] = $invalidWorkDays['data']; 
            $this->error_message['invalidWorkDays'] = 'Worked Days is more than 16';
            $this->error_count['invalidWorkDays'] = count($invalidWorkDays['data']);
            $success[] = 'invalidWorkDays'; 
        }
      }

      if(count($success) == 0){
        $response['success'] = 1;
      } else {
          $response['success'] = 0;
          $response['errors'] = $this->errors;
          $response['error_message'] = $this->error_message;
          $response['error_count'] = $this->error_count;
          $response['validation'] = $success;      
      }
    } catch (PDOException $e) {
      $response['success'] = 0;
      $response['message'] = "Error on validating data.";
      $response['error'] = "An error occurred. Please contact your administrator.";
    }
    return $response;
    
  }


  public function blanks()
  {
      $response = [];
      
      try{   
        
          $sql = "SELECT 
                      a.payroll_employee_id,
                      c.employee_id,
                      CONCAT(a.first_name, ' ', a.last_name) AS employee_full_name,
                      c.daily_salary,
                      c.daily_worked,
                      c.absent,
                      c.lates,
                      c.undertime,
                      c.vacation_leave,
                      c.sick_leave,
                      c.overtime,
                      c.night_diff,
                      c.night_diff_ot,
                      c.regular_holiday,
                      c.regular_holiday_ot,
                      c.regular_holiday_night_diff,
                      c.regular_holiday_nd_ot,
                      c.special_holiday,
                      c.special_holiday_ot,
                      c.special_holiday_night_diff,
                      c.special_holiday_nd_ot,
                      c.rest_day,
                      c.rest_day_ot,
                      c.rest_day_night_diff,
                      c.rest_day_nd_ot,
                      c.rest_day_regular_holiday,
                      c.rest_day_regular_holiday_ot,
                      c.rest_day_regular_holiday_night_diff,
                      c.rest_day_regular_holiday_nd_ot,
                      c.rest_day_special_holiday,
                      c.rest_day_special_holiday_ot,
                      c.rest_day_special_holiday_night_diff,
                      c.rest_day_special_holiday_nd_ot
                  FROM dtr_upload c
                  LEFT JOIN employee_list a ON c.employee_id = a.employee_id 
                  WHERE c.client_name = :client
                        AND c.cut_off = :cut_off
                        AND c.pay_day = :pay_day
                        AND (daily_salary is null OR daily_salary <= 0
                            OR daily_worked is null OR daily_worked <= 0)";
              
          $stmt = $this->db->prepare($sql);
          $stmt->bindParam(':client', $this->client_name, PDO::PARAM_STR);
          $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
          $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
          $stmt->execute();
          $data = $stmt->fetchAll();

          if($stmt->rowCount() > 0){
              $response['data'] = $data;                 
          } else {
              $response['data'] = [];       
          }

          $response['success'] = 1;

      } catch (PDOException $e) {
          $response['success'] = 0;
          $response['message'] = 'Something went wrong.';
          $response['error'] = "An error occurred. Please contact your administrator.";
      }

      return $response;
  }


  public function invalidWorkDays()
  {
      $response = [];
      
      try{   
          $sql = "SELECT 
                      a.payroll_employee_id,
                      c.employee_id,
                      CONCAT(a.first_name, ' ', a.last_name) AS employee_full_name,
                      c.daily_salary,
                      c.daily_worked,
                      c.absent,
                      c.lates,
                      c.undertime,
                      c.vacation_leave,
                      c.sick_leave,
                      c.overtime,
                      c.night_diff,
                      c.night_diff_ot,
                      c.regular_holiday,
                      c.regular_holiday_ot,
                      c.regular_holiday_night_diff,
                      c.regular_holiday_nd_ot,
                      c.special_holiday,
                      c.special_holiday_ot,
                      c.special_holiday_night_diff,
                      c.special_holiday_nd_ot,
                      c.rest_day,
                      c.rest_day_ot,
                      c.rest_day_night_diff,
                      c.rest_day_nd_ot,
                      c.rest_day_regular_holiday,
                      c.rest_day_regular_holiday_ot,
                      c.rest_day_regular_holiday_night_diff,
                      c.rest_day_regular_holiday_nd_ot,
                      c.rest_day_special_holiday,
                      c.rest_day_special_holiday_ot,
                      c.rest_day_special_holiday_night_diff,
                      c.rest_day_special_holiday_nd_ot
                  FROM dtr_upload c
                  LEFT JOIN employee_list a ON c.employee_id = a.employee_id 
                  WHERE c.client_name = :client
                        AND c.cut_off = :cut_off
                        AND c.pay_day = :pay_day
                        AND daily_worked > 16";
              
          $stmt = $this->db->prepare($sql);
          $stmt->bindParam(':client', $this->client_name, PDO::PARAM_STR);
          $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
          $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
          $stmt->execute();
          $data = $stmt->fetchAll();
          
          if($stmt->rowCount() > 0){
              $response['data'] = $data;                 
          } else {
              $response['data'] = [];       
          }

          $response['success'] = 1;

      } catch (PDOException $e) {
          $response['success'] = 0;
          $response['message'] = 'Something went wrong.';
          $response['error'] = "An error occurred. Please contact your administrator.";
      }

      return $response;
  }

  public function add(array $data, $columnMap){
    try {
      $scope = $this->validatedPayrollScope();
      if(($scope['success'] ?? 0) !== 1){
        return $scope;
      }
      $this->payrollDetails = [[
        $scope['client_name'],
        $scope['cut_off'],
        $scope['pay_day'],
        $scope['start_date'],
        $scope['end_date'],
      ]];
      $identity = $this->resolveEmployeeIdentitiesForImport($data, $columnMap);
      if(($identity['success'] ?? 0) !== 1){
        return $identity;
      }
      $data = $identity['data'];
      $this->db->beginTransaction();

      $columnMap = $columnMap;
      foreach ($data as $value) {
        
        $insert= $this->insertToDatabase($columnMap, $value); 
          if($insert['success'] == 0){
            $this->db->rollBack();
            $response['success'] = 0;
            $response['message'] = 'Insert Syntax Error!';
            $response['error'] = $insert['message'];
            return $response;
            exit();
          }

      }
        
      $this->db->commit();
      $response['success'] = 1;
      $response['message'] = 'Success';
    } catch (Throwable $e) {
      if($this->db->inTransaction()){
        $this->db->rollBack();
      }
      error_log('Import::add failed: ' . $e->getMessage());
      $response['success'] = 0;
      $response['error'] = "An error occurred. Please contact your administrator.";
    }
    return $response;
  }

  public function validatedPayrollScope(): array
  {
    $scope = PayrollLockGuard::normalizePayrollDetails($this->payrollDetails);
    if (($scope['success'] ?? 0) !== 1) {
      $scope['error_code'] = 'INVALID_PAYROLL_SCOPE';
    }
    return $scope;
  }

  public function validateEmployeeIdentityScope(){
    try {
      $client = $this->clientRecord();
      if(!$client){
        return [
          'success' => 0,
          'validation' => ['employeeIdentity'],
          'error_message' => ['employeeIdentity' => 'Client is not configured in HRIS'],
          'error_count' => ['employeeIdentity' => 1],
          'errors' => ['employeeIdentity' => []],
        ];
      }

      $open = $this->db->prepare("\n        SELECT COUNT(DISTINCT e.id)\n        FROM dtr_employee_exceptions e\n        INNER JOIN dtr_upload_batches b ON b.id = e.batch_id\n        INNER JOIN dtr_format_templates t ON t.id = b.template_id\n        INNER JOIN dtr_upload_staging_rows r ON r.id = e.staging_row_id\n        WHERE t.client_id = :client_id\n          AND e.status = 'open'\n          AND e.severity = 'P0'\n          AND JSON_UNQUOTE(JSON_EXTRACT(r.parsed_payload, '$.pay_date')) = :pay_day\n      ");
      $open->execute([
        ':client_id' => (int)$client['client_id'],
        ':pay_day' => $this->pay_day,
      ]);
      $openCount = (int)$open->fetchColumn();
      if($openCount > 0){
        return [
          'success' => 0,
          'validation' => ['employeeIdentity'],
          'error_message' => ['employeeIdentity' => 'Payroll is blocked by unresolved staged employee identities'],
          'error_count' => ['employeeIdentity' => $openCount],
          'errors' => ['employeeIdentity' => []],
          'identity_gate' => [
            'gate_status' => 'blocked',
            'open_p0_count' => $openCount,
            'resolution_url' => '../dtr-format-engine/#employee-identity-review',
          ],
        ];
      }

      $orphans = $this->db->prepare("\n        SELECT d.employee_id, COUNT(*) AS affected_rows\n        FROM dtr_upload d\n        LEFT JOIN employee_list e\n          ON e.employee_id = d.employee_id\n         AND e.client_id = :client_id_a\n         AND e.status = 'Active'\n        WHERE d.client_name = :client_name\n          AND d.cut_off = :cut_off\n          AND d.pay_day = :pay_day\n          AND e.employee_id IS NULL\n        GROUP BY d.employee_id\n        ORDER BY d.employee_id\n        LIMIT 100\n      ");
      $orphans->execute([
        ':client_id_a' => (int)$client['client_id'],
        ':client_name' => $this->client_name,
        ':cut_off' => $this->cut_off,
        ':pay_day' => $this->pay_day,
      ]);
      $rows = $orphans->fetchAll(PDO::FETCH_ASSOC);
      if(count($rows) > 0){
        return [
          'success' => 0,
          'validation' => ['employeeIdentity'],
          'error_message' => ['employeeIdentity' => 'Imported DTR contains missing, inactive, or wrong-client employees'],
          'error_count' => ['employeeIdentity' => count($rows)],
          'errors' => ['employeeIdentity' => $rows],
          'identity_gate' => ['gate_status' => 'blocked', 'open_p0_count' => count($rows)],
        ];
      }

      return ['success' => 1, 'identity_gate' => ['gate_status' => 'ready', 'open_p0_count' => 0]];
    } catch (Throwable $error) {
      error_log('Import::validateEmployeeIdentityScope failed: ' . $error->getMessage());
      return [
        'success' => 0,
        'validation' => ['employeeIdentity'],
        'error_message' => ['employeeIdentity' => 'Unable to verify employee identities'],
        'error_count' => ['employeeIdentity' => 1],
        'errors' => ['employeeIdentity' => []],
      ];
    }
  }

  private function resolveEmployeeIdentitiesForImport(array $data, array $columnMap): array
  {
    $employeeColumn = $this->employeeColumnIndex($columnMap);
    if($employeeColumn < 0){
      return $this->identityImportError(['Employee ID column is not mapped.']);
    }
    $client = $this->clientRecord();
    if(!$client){
      return $this->identityImportError(['Selected client is not configured in HRIS.']);
    }
    $clientId = (int)$client['client_id'];
    $isFuji = $clientId === 264 || stripos((string)$client['client_name'], 'FUJI') !== false;
    $sourceNamespace = $isFuji ? $this->fujiSourceNamespace($clientId) : null;
    if($isFuji && $sourceNamespace === null){
      return $this->identityImportError(['Fuji identity template is not configured.']);
    }
    $unresolved = [];
    $resolvedData = $data;
    $mappingDate = $this->identityReferenceDate();

    $map = $this->db->prepare("\n      SELECT DISTINCT m.employee_id\n      FROM employee_identity_map m\n      INNER JOIN employee_list e ON e.employee_id = m.employee_id\n      WHERE m.client_id = :client_id\n        AND m.source_namespace = :source_namespace\n        AND m.source_employee_id = :source_employee_id\n        AND m.status = 'approved'\n        AND e.client_id = :employee_client_id\n        AND e.status = 'Active'\n        AND (m.effective_from IS NULL OR m.effective_from <= :mapping_date_from)\n        AND (m.effective_to IS NULL OR m.effective_to >= :mapping_date_to)\n      ORDER BY m.employee_id\n    ");
    $direct = $this->db->prepare("\n      SELECT employee_id\n      FROM employee_list\n      WHERE client_id = :client_id\n        AND status = 'Active'\n        AND (\n             CAST(employee_id AS CHAR) = :identifier_a\n          OR payroll_employee_id = :identifier_b\n          OR old_employee_id = :identifier_c\n        )\n      ORDER BY employee_id\n      LIMIT 2\n    ");

    foreach($data as $index => $row){
      $sourceId = trim((string)($row[$employeeColumn] ?? ''));
      if($sourceId === ''){
        $unresolved[] = ['row' => $index + 1, 'source_employee_id' => '', 'reason' => 'missing_source_employee_id'];
        continue;
      }
      $mapped = [];
      if($sourceNamespace !== null){
        $map->execute([
          ':client_id' => $clientId,
          ':source_namespace' => $sourceNamespace,
          ':source_employee_id' => $sourceId,
          ':employee_client_id' => $clientId,
          ':mapping_date_from' => $mappingDate,
          ':mapping_date_to' => $mappingDate,
        ]);
        $mapped = array_values(array_unique(array_map('intval', $map->fetchAll(PDO::FETCH_COLUMN))));
      }
      if(count($mapped) === 1){
        $resolvedData[$index][$employeeColumn] = $mapped[0];
        continue;
      }
      if(count($mapped) > 1){
        $unresolved[] = ['row' => $index + 1, 'source_employee_id' => $sourceId, 'reason' => 'conflicting_approved_mappings'];
        continue;
      }
      if($isFuji){
        $unresolved[] = ['row' => $index + 1, 'source_employee_id' => $sourceId, 'reason' => 'fuji_mapping_not_approved'];
        continue;
      }
      $direct->execute([
        ':client_id' => $clientId,
        ':identifier_a' => $sourceId,
        ':identifier_b' => $sourceId,
        ':identifier_c' => $sourceId,
      ]);
      $directMatches = array_values(array_unique(array_map('intval', $direct->fetchAll(PDO::FETCH_COLUMN))));
      if(count($directMatches) === 1){
        $resolvedData[$index][$employeeColumn] = $directMatches[0];
      }else{
        $unresolved[] = [
          'row' => $index + 1,
          'source_employee_id' => $sourceId,
          'reason' => count($directMatches) > 1 ? 'ambiguous_hris_identifier' : 'employee_not_found',
        ];
      }
    }

    if(count($unresolved) > 0){
      return $this->identityImportError($unresolved);
    }
    return ['success' => 1, 'data' => $resolvedData];
  }

  private function clientRecord(): ?array
  {
    $name = '';
    if(count($this->payrollDetails) > 0){
      $name = trim((string)($this->payrollDetails[0][0] ?? ''));
    }
    if($name === ''){
      $name = trim((string)$this->client_name);
    }
    if($name === ''){
      return null;
    }
    $stmt = $this->db->prepare('SELECT client_id, client_name FROM taascor_client WHERE client_name = :client_name LIMIT 1');
    $stmt->execute([':client_name' => $name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  }

  private function fujiSourceNamespace(int $clientId): ?string
  {
    $stmt = $this->db->prepare("\n      SELECT id\n      FROM dtr_format_templates\n      WHERE client_id = :client_id\n        AND source_type = 'fuji_payroll_summary'\n        AND is_active = 1\n      ORDER BY id DESC\n      LIMIT 1\n    ");
    $stmt->execute([':client_id' => $clientId]);
    $templateId = (int)$stmt->fetchColumn();
    return $templateId > 0 ? 'template:' . $templateId : null;
  }

  private function identityImportError(array $unresolved): array
  {
    return [
      'success' => 0,
      'message' => 'DTR upload blocked by unresolved employee identities.',
      'error' => [
        'message' => 'Resolve the employee mapping queue in DTR Format Engine before uploading payroll data.',
        'code' => 'employee_identity_blocked',
      ],
      'identity_gate' => [
        'gate_status' => 'blocked',
        'open_p0_count' => count($unresolved),
        'unresolved' => array_slice($unresolved, 0, 100),
        'resolution_url' => '../dtr-format-engine/#employee-identity-review',
      ],
    ];
  }

  private function employeeColumnIndex(array $columnMap): int
  {
    foreach($columnMap as $field => $column){
      $normalizedField = strtolower((string)preg_replace('/[^a-z0-9]+/i', '_', trim((string)$field)));
      if($normalizedField === 'employee_id'){
        return (int)$column;
      }
    }
    return -1;
  }

  private function identityReferenceDate(): string
  {
    $candidate = count($this->payrollDetails) > 0
      ? trim((string)($this->payrollDetails[0][2] ?? ''))
      : trim((string)$this->pay_day);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $candidate);
    $errors = DateTimeImmutable::getLastErrors();
    if($date instanceof DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))){
      return $date->format('Y-m-d');
    }
    return date('Y-m-d');
  }

  public function insertToDatabase($columnMap, $column_data){
    try {
      $date_now = date("Y-m-d H:i:s", time());
      $columnMap_key = array_keys($columnMap);
      $sql_values = [];
      
      foreach ($columnMap as $colIndex) {
        
        $columnHeader = trim($columnMap_key[$colIndex]);
        if(array_key_exists($colIndex, $column_data)){
          $columnValue =  $column_data[$colIndex];
        }else{
          $columnValue =  NULL;
        }

        if(in_array($columnHeader, $columnMap_key)){
          if($columnValue !== NULL && trim($columnValue) !== ""){
              $singleQoute = $this->singleQoute($columnValue);
              $singleQoute = preg_replace('/[^A-Za-z0-9. ]/', '', $singleQoute);
              $singleQoute = preg_replace('/\s+/', ' ', $singleQoute);
              $sql_values[$columnHeader] = trim($singleQoute);
          }else{
              $sql_values[$columnHeader] = NULL;
          }
        } else {
          $sql_values[$columnHeader] = NULL;
        }
      }

      $row = $this->payrollDetails[0];
      $client_name = $row[0];
      $cut_off = $row[1];
      $pay_day = $row[2];
      $start_date = $row[3];
      $end_date = $row[4];
   
      $sql = "INSERT INTO dtr_upload
              (
                  client_name,
                  pay_day,
                  cut_off,
                  employee_id,
                  daily_salary,
                  daily_worked,
                  absent,
                  lates,
                  undertime,
                  vacation_leave,
                  sick_leave,
                  overtime,
                  night_diff,
                  night_diff_ot,
                  regular_holiday,
                  regular_holiday_ot,
                  regular_holiday_night_diff,
                  special_holiday,
                  special_holiday_ot,
                  special_holiday_night_diff,
                  rest_day,
                  rest_day_ot,
                  rest_day_night_diff,
                  rest_day_regular_holiday,
                  rest_day_regular_holiday_ot,
                  rest_day_regular_holiday_night_diff,
                  rest_day_special_holiday,
                  rest_day_special_holiday_ot,
                  rest_day_special_holiday_night_diff,
                  start_date,
                  end_date,
                  regular_holiday_nd_ot,
                  special_holiday_nd_ot,
                  rest_day_nd_ot,
                  rest_day_regular_holiday_nd_ot,
                  rest_day_special_holiday_nd_ot
              ) 
              VALUES (
                  :client_name,
                  :pay_day,
                  :cut_off,
                  :employee_id,
                  :daily_salary,
                  :daily_worked,
                  :absent,
                  :lates,
                  :undertime,
                  :vacation_leave,
                  :sick_leave,
                  :overtime,
                  :night_diff,
                  :night_diff_ot,
                  :regular_holiday,
                  :regular_holiday_ot,
                  :regular_holiday_night_diff,
                  :special_holiday,
                  :special_holiday_ot,
                  :special_holiday_night_diff,
                  :rest_day,
                  :rest_day_ot,
                  :rest_day_night_diff,
                  :rest_day_regular_holiday,
                  :rest_day_regular_holiday_ot,
                  :rest_day_regular_holiday_night_diff,
                  :rest_day_special_holiday,
                  :rest_day_special_holiday_ot,
                  :rest_day_special_holiday_night_diff,
                  :start_date,
                  :end_date,
                  :regular_holiday_nd_ot,
                  :special_holiday_nd_ot,
                  :rest_day_nd_ot,
                  :rest_day_regular_holiday_nd_ot,
                  :rest_day_special_holiday_nd_ot
              )ON DUPLICATE KEY UPDATE 
                  daily_salary = VALUES(daily_salary),
                  daily_worked = VALUES(daily_worked),
                  absent = VALUES(absent),
                  lates = VALUES(lates),
                  undertime = VALUES(undertime),
                  vacation_leave = VALUES(vacation_leave),
                  sick_leave = VALUES(sick_leave),
                  overtime = VALUES(overtime),
                  night_diff = VALUES(night_diff),
                  night_diff_ot = VALUES(night_diff_ot),
                  regular_holiday = VALUES(regular_holiday),
                  regular_holiday_ot = VALUES(regular_holiday_ot),
                  regular_holiday_night_diff = VALUES(regular_holiday_night_diff),
                  special_holiday = VALUES(special_holiday),
                  special_holiday_ot = VALUES(special_holiday_ot),
                  special_holiday_night_diff = VALUES(special_holiday_night_diff),
                  rest_day = VALUES(rest_day),
                  rest_day_ot = VALUES(rest_day_ot),
                  rest_day_night_diff = VALUES(rest_day_night_diff),
                  rest_day_regular_holiday = VALUES(rest_day_regular_holiday),
                  rest_day_regular_holiday_ot = VALUES(rest_day_regular_holiday_ot),
                  rest_day_regular_holiday_night_diff = VALUES(rest_day_regular_holiday_night_diff),
                  rest_day_special_holiday = VALUES(rest_day_special_holiday),
                  rest_day_special_holiday_ot = VALUES(rest_day_special_holiday_ot),
                  rest_day_special_holiday_night_diff = VALUES(rest_day_special_holiday_night_diff),
                  start_date = VALUES(start_date),
                  end_date = VALUES(end_date),
                  regular_holiday_nd_ot = VALUES(regular_holiday_nd_ot),
                  special_holiday_nd_ot = VALUES(special_holiday_nd_ot),
                  rest_day_nd_ot = VALUES(rest_day_nd_ot),
                  rest_day_regular_holiday_nd_ot = VALUES(rest_day_regular_holiday_nd_ot),
                  rest_day_special_holiday_nd_ot = VALUES(rest_day_special_holiday_nd_ot)";

      $stmt = $this->db->prepare($sql);
      $stmt->bindParam(':client_name', $client_name, PDO::PARAM_STR);
      $stmt->bindParam(':pay_day', $pay_day, PDO::PARAM_STR);
      $stmt->bindParam(':cut_off', $cut_off, PDO::PARAM_STR);
      $stmt->bindParam(':employee_id', $sql_values["employee id"], PDO::PARAM_INT);
      $stmt->bindParam(':daily_salary', $sql_values["daily salary"], PDO::PARAM_STR);
      $stmt->bindParam(':daily_worked', $sql_values["days worked"], PDO::PARAM_STR);
      $stmt->bindParam(':absent', $sql_values["absent"], PDO::PARAM_STR);
      $stmt->bindParam(':lates', $sql_values["lates (min)"], PDO::PARAM_STR);
      $stmt->bindParam(':undertime', $sql_values["undertime"], PDO::PARAM_STR);
      $stmt->bindParam(':vacation_leave', $sql_values["vacation leave"], PDO::PARAM_STR);
      $stmt->bindParam(':sick_leave', $sql_values["sick leave"], PDO::PARAM_STR);
      $stmt->bindParam(':overtime', $sql_values["overtime"], PDO::PARAM_STR);
      $stmt->bindParam(':night_diff', $sql_values["night differential"], PDO::PARAM_STR);
      $stmt->bindParam(':night_diff_ot', $sql_values["night differential ot"], PDO::PARAM_STR);
      $stmt->bindParam(':regular_holiday', $sql_values["regular holiday"], PDO::PARAM_STR);
      $stmt->bindParam(':regular_holiday_ot', $sql_values["regular holiday ot"], PDO::PARAM_STR);
      $stmt->bindParam(':regular_holiday_night_diff', $sql_values["regular holiday night diff"], PDO::PARAM_STR);
      $stmt->bindParam(':special_holiday', $sql_values["special holiday"], PDO::PARAM_STR);
      $stmt->bindParam(':special_holiday_ot', $sql_values["special holiday ot"], PDO::PARAM_STR);
      $stmt->bindParam(':special_holiday_night_diff', $sql_values["special holiday night diff"], PDO::PARAM_STR);
      $stmt->bindParam(':rest_day', $sql_values["rest day"], PDO::PARAM_STR);
      $stmt->bindParam(':rest_day_ot', $sql_values["rest day ot"], PDO::PARAM_STR);
      $stmt->bindParam(':rest_day_night_diff', $sql_values["rest day night diff"], PDO::PARAM_STR);
      $stmt->bindParam(':rest_day_regular_holiday', $sql_values["rest day - regular holiday"], PDO::PARAM_STR);
      $stmt->bindParam(':rest_day_regular_holiday_ot', $sql_values["rest day - regular holiday ot"], PDO::PARAM_STR);
      $stmt->bindParam(':rest_day_regular_holiday_night_diff', $sql_values["rest day - regular holiday night diff"], PDO::PARAM_STR);
      $stmt->bindParam(':rest_day_special_holiday', $sql_values["rest day - special holiday"], PDO::PARAM_STR);
      $stmt->bindParam(':rest_day_special_holiday_ot', $sql_values["rest day - special holiday ot"], PDO::PARAM_STR);
      $stmt->bindParam(':rest_day_special_holiday_night_diff', $sql_values["rest day - special holiday night diff"], PDO::PARAM_STR);
      $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
      $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
      $stmt->bindParam(':regular_holiday_nd_ot', $sql_values["regular holiday nd ot"], PDO::PARAM_STR);
      $stmt->bindParam(':special_holiday_nd_ot', $sql_values["special holiday nd ot"], PDO::PARAM_STR);
      $stmt->bindParam(':rest_day_nd_ot', $sql_values["rest day nd ot"], PDO::PARAM_STR);
      $stmt->bindParam(':rest_day_regular_holiday_nd_ot', $sql_values["rest day - regular holiday nd ot"], PDO::PARAM_STR);
      $stmt->bindParam(':rest_day_special_holiday_nd_ot', $sql_values["rest day - special holiday nd ot"], PDO::PARAM_STR);
      $stmt->execute();

      $response = array(
        "success" => 1,
        "message" => 'Succesfully Imported',
      );

    } catch (PDOException $e) {
      error_log('Import::insertToDatabase failed: ' . $e->getMessage());
      $response = array(
        "success" => 0,
        "message" => "Unable to import DTR data."
      );
 
    }
    return $response;
  }


  public function deleteInvalid()
  {
      $response = [];
      
      try{   

          $sql = "DELETE FROM dtr_upload
                  WHERE client_name = :client
                        AND cut_off = :cut_off
                        AND pay_day = :pay_day
                        AND (daily_salary is null OR daily_salary <= 0
                            OR daily_worked is null OR daily_worked <= 0
                            OR daily_worked > 16)"; 
          $stmt = $this->db->prepare($sql);
          $stmt->bindParam(':client', $this->client_name, PDO::PARAM_STR);
          $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
          $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
          $stmt->execute();

          $response['success'] = 1;

      } catch (PDOException $e) {
          $response['success'] = 0;
                    $response['error'] = "An error occurred. Please contact your administrator.";
      }

      return $response;
  }

  public function cleanString($string) {
    $white_space = str_replace(" ","",trim($string)); // Remove White.
    return  $white_space;
  }

  public function singleQoute($string) {
    $qoute = str_replace("'","''",trim($string)); // Remove White.
    return  $qoute;
  }
  
}

?>
