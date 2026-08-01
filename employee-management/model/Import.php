<?php

class Import{

  public $db = null;
  public $import_id = null;
  public $date_list = ['hire date','separation date','birthday', 'client date'];

  public function getClientById(int $clientId): ?array
  {
    $stmt = $this->db->prepare(
      "SELECT client_id, client_name
       FROM taascor_client
       WHERE client_id = :client_id
       LIMIT 1"
    );
    $stmt->bindValue(':client_id', $clientId, PDO::PARAM_INT);
    $stmt->execute();
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($client) ? $client : null;
  }

  public function countStagedRows(): int
  {
    $stmt = $this->db->prepare(
      "SELECT COUNT(*) FROM employee_tmp WHERE import_id = :import_id"
    );
    $stmt->bindValue(':import_id', (string)$this->import_id, PDO::PARAM_STR);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
  }


  public function blockedEmployeeTransferResponse(): array
  {
    return [
      'success' => 0,
      'error_code' => 'employee_transfer_v2_required',
      'message' => 'Employee import finalization is temporarily blocked because the installed transfer routine can commit outside the application rollback boundary. No employee master data was changed, and the staged upload was preserved.',
      'next_action' => 'An administrator must install and approve a transaction-neutral employee transfer v2 contract before this import can be finalized. You can continue using Employee Management for viewing and manual employee actions.',
      'route' => 'employee-management',
      'route_label' => 'Return to Employee Management',
      'staging_preserved' => true,
      'import_id' => (string)$this->import_id,
    ];
  }

  public function validate(){
    
    try {      
      $success = [];
      $dupEmployee = [];

      $dupEmployee = $this->dupEmployee(); 

      if($dupEmployee['success'] == 1){
        if(count($dupEmployee['data']) > 0){ 
            $this->errors['dupEmployee'] = $dupEmployee['data']; 
            $this->error_message['dupEmployee'] = 'Duplicate Employee with different Employee ID';
            $this->error_count['dupEmployee'] = count($dupEmployee['data']);
            $success[] = 'dupEmployee'; 
        }
      }

      // $blanks = [];
      // $sssFormat = [];
      // $dailySalary = [];
      // $contactNumber = [];
      // $dupSSSNumber = [];
      // $dupPhilhealthNumber = [];
      // $dupPagIbigNumber = [];
      // $dupTinNumber = [];
      // $dupBankNumber = [];
      // $invalidEmployeeType = [];
      // $success = [];

      // $blanks = $this->blanks(); 
      // $dupSSSNumber = $this->dupSSSNumber();
      // $dupPhilhealthNumber = $this->dupPhilhealthNumber();
      // $dupPagIbigNumber = $this->dupPagIbigNumber();
      // $dupTinNumber = $this->dupTinNumber();
      // $dupBankNumber = $this->dupBankNumber();
      // $sssFormat = $this->sssFormat();
      // $dailySalary = $this->dailySalary();
      // $contactNumber = $this->contactNumber();
      // $invalidEmployeeType = $this->invalidEmployeeType();

      // if($blanks['success'] == 1){
      //     if(count($blanks['data']) > 0){ 
      //         $this->errors['blanks'] = $blanks['data']; 
      //         $this->error_message['blanks'] = 'Blanks or Incomplete Details';
      //         $this->error_count['blanks'] = count($blanks['data']);
      //         $success[] = 'blanks'; 
      //     }
      // }

      // if($dupSSSNumber['success'] == 1){
      //   if(count($dupSSSNumber['data']) > 0){ 
      //       $this->errors['dupSSSNumber'] = $dupSSSNumber['data']; 
      //       $this->error_message['dupSSSNumber'] = 'Duplicate SSS Number';
      //       $this->error_count['dupSSSNumber'] = count($dupSSSNumber['data']);
      //       $success[] = 'dupSSSNumber'; 
      //   }
      // }

      // if($dupPhilhealthNumber['success'] == 1){
      //   if(count($dupPhilhealthNumber['data']) > 0){ 
      //       $this->errors['dupPhilhealthNumber'] = $dupPhilhealthNumber['data']; 
      //       $this->error_message['dupPhilhealthNumber'] = 'Duplicate Philhealth Number';
      //       $this->error_count['dupPhilhealthNumber'] = count($dupPhilhealthNumber['data']);
      //       $success[] = 'dupPhilhealthNumber'; 
      //   }
      // }

      // if($dupPagIbigNumber['success'] == 1){
      //   if(count($dupPagIbigNumber['data']) > 0){ 
      //       $this->errors['dupPagIbigNumber'] = $dupPagIbigNumber['data']; 
      //       $this->error_message['dupPagIbigNumber'] = 'Duplicate Pag-Ibig Number';
      //       $this->error_count['dupPagIbigNumber'] = count($dupPagIbigNumber['data']);
      //       $success[] = 'dupPagIbigNumber'; 
      //   }
      // }

      // if($dupTinNumber['success'] == 1){
      //   if(count($dupTinNumber['data']) > 0){ 
      //       $this->errors['dupTinNumber'] = $dupPagIbigNumber['data']; 
      //       $this->error_message['dupTinNumber'] = 'Duplicate TIN Number';
      //       $this->error_count['dupTinNumber'] = count($dupTinNumber['data']);
      //       $success[] = 'dupTinNumber'; 
      //   }
      // }

      // if($dupBankNumber['success'] == 1){
      //   if(count($dupBankNumber['data']) > 0){ 
      //       $this->errors['dupBankNumber'] = $dupBankNumber['data']; 
      //       $this->error_message['dupBankNumber'] = 'Duplicate Bank Account Number';
      //       $this->error_count['dupBankNumber'] = count($dupBankNumber['data']);
      //       $success[] = 'dupBankNumber'; 
      //   }
      // }

      // if($sssFormat['success'] == 1){
      //   if(count($sssFormat['data']) > 0){ 
      //       $this->errors['sssFormat'] = $sssFormat['data']; 
      //       $this->error_message['sssFormat'] = 'Incorrect SSS Format';
      //       $this->error_count['sssFormat'] = count($sssFormat['data']);
      //       $success[] = 'sssFormat'; 
      //   }
      // }


      // if($dailySalary['success'] == 1){
      //   if(count($dailySalary['data']) > 0){ 
      //       $this->errors['dailySalary'] = $dailySalary['data']; 
      //       $this->error_message['dailySalary'] = 'Daily Salary is less than 100 or greater than 10,000';
      //       $this->error_count['dailySalary'] = count($dailySalary['data']);
      //       $success[] = 'dailySalary'; 
      //   }
      // }

      // if($contactNumber['success'] == 1){
      //   if(count($contactNumber['data']) > 0){ 
      //       $this->errors['contactNumber'] = $contactNumber['data']; 
      //       $this->error_message['contactNumber'] = 'Invalid contact number';
      //       $this->error_count['contactNumber'] = count($contactNumber['data']);
      //       $success[] = 'contactNumber'; 
      //   }
      // }

      // if($invalidEmployeeType['success'] == 1){
      //   if(count($invalidEmployeeType['data']) > 0){ 
      //       $this->errors['invalidEmployeeType'] = $invalidEmployeeType['data']; 
      //       $this->error_message['invalidEmployeeType'] = 'Invalid Employee Type';
      //       $this->error_count['invalidEmployeeType'] = count($invalidEmployeeType['data']);
      //       $success[] = 'invalidEmployeeType'; 
      //   }
      // }

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

  public function dupEmployee()
  {
      $response = [];
      
      try{   
          $sql = "SELECT DISTINCT 
                      a.employee_id,
                      a.old_employee_id,
                      a.payroll_employee_id,
                      a.full_name,
                      a.last_name,
                      a.first_name,
                      a.middle_name,
                      DATE_FORMAT(a.hire_date, '%m/%d/%Y') AS hire_date,
                      a.separation_date,
                      a.present_address,
                      a.permanent_address,
                      a.contact_number,
                      a.email_address,
                      DATE_FORMAT(a.birthday, '%m/%d/%Y') AS birthday,
                      a.birth_place,
                      a.gender,
                      a.civil_status,
                      a.nationality,
                      a.emergency_person,
                      a.emergency_contact_number,
                      DATE_FORMAT(a.client_date, '%m/%d/%Y') AS client_date,
                      a.position_name,
                      a.client_name,
                      a.branch_name,
                      a.client_location,
                      a.department_name,
                      a.tin_number,
                      a.sss_number,
                      a.philhealth_number,
                      a.pag_ibig_number,
                      a.atm_number,
                      a.daily_salary,
                      a.bank_name,
                      a.insurance,
                      a.annual_leaves,
                      a.employee_type,
                      a.pay_type
                  FROM employee_tmp a
                  INNER JOIN employee_list b 
                      ON TRIM(a.first_name) = TRIM(b.first_name)
                      AND TRIM(a.last_name) = TRIM(b.last_name)
                      AND COALESCE(TRIM(a.middle_name), '') = COALESCE(TRIM(b.middle_name), '')
                      AND COALESCE(a.employee_id,0) <> b.employee_id
                  WHERE a.import_id = :import_id";
              
          $stmt = $this->db->prepare($sql);
          $stmt->bindParam(':import_id', $this->import_id, PDO::PARAM_STR);
          $stmt->execute();
          $data = $stmt->fetchAll();
          
          if($stmt->rowCount() > 0){
              $response['data'] = $data;                 
          } else {
              $response['data'] = [];       
          }

          // 
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
      $response['import_id'] = $this->import_id;
    } catch (Throwable $e) {
      if (method_exists($this->db, 'inTransaction') && $this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('Import::add failed: ' . $e->getMessage());
      $response['success'] = 0;
      $response['error'] = "An error occurred. Please contact your administrator.";
    }
    return $response;
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

        if (in_array($columnHeader, $this->date_list)) {
          // Date Only
          if($columnValue !== NULL && trim($columnValue) !== ""){
            $date = $columnValue;
            if(is_numeric($date)){
              $unix_date = ($date - 25569) * 86400;
              $date = 25569 + ($unix_date / 86400);
              $unix_date = ($date - 25569) * 86400;
              $sql_values[$columnHeader] =  gmdate("Y-m-d", $unix_date);
            } else {
              if(strtotime($date)){
                $date = date_create($date);
                $date = date_format($date,"Y-m-d");
              }
              $sql_values[$columnHeader] = $date;
            }
          } else {
            $sql_values[$columnHeader] = NULL;
          }
        } else {

          if(in_array($columnHeader, $columnMap_key)){
              if($columnValue !== NULL && trim($columnValue) !== ""){
                  $singleQoute = $this->singleQoute($columnValue);
                  $sql_values[$columnHeader] = $singleQoute;
              }else{
                  $sql_values[$columnHeader] = NULL;
              }
          } else {
            $sql_values[$columnHeader] = NULL;
          }
        }
      }
   
      $sql = "INSERT INTO employee_tmp
        (
            import_id
            ,employee_id
            ,old_employee_id
            ,payroll_employee_id
            ,full_name
            ,last_name
            ,first_name
            ,middle_name
            ,hire_date
            ,separation_date
            ,present_address
            ,permanent_address
            ,contact_number
            ,email_address
            ,birthday
            ,birth_place
            ,gender
            ,civil_status
            ,nationality
            ,emergency_person
            ,emergency_contact_number
            ,position_name
            ,client_name
            ,department_name
            ,branch_name
            ,insurance
            ,bank_name
            ,annual_leaves
            ,tin_number
            ,sss_number
            ,philhealth_number
            ,pag_ibig_number
            ,atm_number
            ,daily_salary
            ,employee_type
            ,is_validated
            ,client_location
            ,client_date
            ,pay_type            
        ) 
        VALUES (
            :import_id
            ,:employee_id
            ,:old_employee_id
            ,:payroll_employee_id
            ,:full_name
            ,:last_name
            ,:first_name
            ,:middle_name 
            ,:hire_date
            ,:separation_date
            ,:present_address
            ,:permanent_address
            ,:contact_number 
            ,:email_address
            ,:birthday
            ,:birth_place
            ,:gender
            ,:civil_status
            ,:nationality 
            ,:emergency_person
            ,:emergency_contact_number
            ,:position_name
            ,:client_name 
            ,:department_name
            ,:branch_name
            ,:insurance
            ,:bank_name
            ,:annual_leaves
            ,:tin_number
            ,:sss_number 
            ,:philhealth_number
            ,:pag_ibig_number
            ,:atm_number
            ,:daily_salary
            ,:employee_type
            ,0
            ,:client_location
            ,:client_date
            ,:pay_type
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':import_id', $this->import_id, PDO::PARAM_INT);
        $stmt->bindParam(':employee_id', $sql_values["employee ident"], PDO::PARAM_STR);
        $stmt->bindParam(':old_employee_id', $sql_values["old employee ident"], PDO::PARAM_STR);
        $stmt->bindParam(':payroll_employee_id', $sql_values["payroll employee id"], PDO::PARAM_STR);
        $stmt->bindParam(':full_name', $sql_values["full name"], PDO::PARAM_STR);
        $stmt->bindParam(':last_name', $sql_values["last name"], PDO::PARAM_STR);
        $stmt->bindParam(':first_name', $sql_values["first name"], PDO::PARAM_STR);
        $stmt->bindParam(':middle_name', $sql_values["middle name"], PDO::PARAM_STR);
        $stmt->bindParam(':hire_date', $sql_values["hire date"], PDO::PARAM_STR);
        $stmt->bindParam(':separation_date', $sql_values["separation date"], PDO::PARAM_STR);
        $stmt->bindParam(':present_address', $sql_values["present address"], PDO::PARAM_STR);
        $stmt->bindParam(':permanent_address', $sql_values["permanent address"], PDO::PARAM_STR);
        $stmt->bindParam(':contact_number', $sql_values["contact number"], PDO::PARAM_STR);
        $stmt->bindParam(':email_address', $sql_values["email address"], PDO::PARAM_STR);
        $stmt->bindParam(':birthday', $sql_values["birthday"], PDO::PARAM_STR);
        $stmt->bindParam(':birth_place', $sql_values["birth place"], PDO::PARAM_STR);
        $stmt->bindParam(':gender', $sql_values["gender"], PDO::PARAM_STR);
        $stmt->bindParam(':civil_status', $sql_values["civil status"], PDO::PARAM_STR);
        $stmt->bindParam(':nationality', $sql_values["nationality"], PDO::PARAM_STR);
        $stmt->bindParam(':emergency_person', $sql_values["emergency person"], PDO::PARAM_STR);
        $stmt->bindParam(':emergency_contact_number', $sql_values["emergency contact number"], PDO::PARAM_STR);
        $stmt->bindParam(':position_name', $sql_values["position"], PDO::PARAM_STR);
        $stmt->bindParam(':client_name', $sql_values["client"], PDO::PARAM_STR);
        $stmt->bindParam(':department_name', $sql_values["department"], PDO::PARAM_STR);
        $stmt->bindParam(':branch_name', $sql_values["branch"], PDO::PARAM_STR);
        $stmt->bindParam(':insurance', $sql_values["insurance"], PDO::PARAM_STR);
        $stmt->bindParam(':bank_name', $sql_values["bank name"], PDO::PARAM_STR);
        $stmt->bindParam(':annual_leaves', $sql_values["annual leaves"], PDO::PARAM_INT);
        $stmt->bindParam(':tin_number', $sql_values["tin"], PDO::PARAM_STR);
        $stmt->bindParam(':sss_number', $sql_values["sss"], PDO::PARAM_STR);
        $stmt->bindParam(':philhealth_number', $sql_values["philhealth"], PDO::PARAM_STR);
        $stmt->bindParam(':pag_ibig_number', $sql_values["pag-ibig"], PDO::PARAM_STR);
        $stmt->bindParam(':atm_number', $sql_values["bank account number"], PDO::PARAM_STR);
        $stmt->bindParam(':daily_salary', $sql_values["daily salary"], PDO::PARAM_STR);
        $stmt->bindParam(':employee_type', $sql_values["employee type"], PDO::PARAM_STR);
        $stmt->bindParam(':client_location', $sql_values["client location"], PDO::PARAM_STR);
        $stmt->bindParam(':client_date', $sql_values["client date"], PDO::PARAM_STR);
        $stmt->bindParam(':pay_type', $sql_values["pay type"], PDO::PARAM_STR);
        $stmt->execute();

      $response = array(
        "success" => 1,
        "message" => 'Succesfully Imported',
      );

    } catch (PDOException $e) {
      error_log('Import::insertToDatabase failed: ' . $e->getMessage());
      $response = array(
        "success" => 0,
        "message" => "Unable to import employee data."
      );
 
    }
    return $response;
  }


  // public function blanks()
  // {
  //     $response = [];
      
  //     try{   

  //         $sql = "SELECT employee_id
  //                         ,old_employee_id
  //                         ,payroll_employee_id
  //                         ,full_name
  //                         ,last_name
  //                         ,first_name
  //                         ,middle_name
  //                         ,DATE_FORMAT(hire_date, '%m/%d/%Y') as hire_date
  //                         ,separation_date
  //                         ,present_address
  //                         ,permanent_address
  //                         ,contact_number
  //                         ,email_address
  //                         ,DATE_FORMAT(birthday, '%m/%d/%Y') as birthday
  //                         ,birth_place
  //                         ,gender
  //                         ,civil_status
  //                         ,nationality
  //                         ,emergency_person
  //                         ,emergency_contact_number
  //                         ,position_name
  //                         ,client_name
  //                         ,branch_name
  //                         ,client_location
  //                         ,department_name
  //                         ,tin_number
  //                         ,sss_number
  //                         ,philhealth_number
  //                         ,pag_ibig_number
  //                         ,atm_number
  //                         ,daily_salary
  //                         ,bank_name
  //                         ,insurance
  //                         ,annual_leaves
  //                         ,employee_type
  //                   FROM employee_tmp
  //                   where import_id = :import_id 
  //                   AND (
  //                       (employee_type <> 'Seasonal' OR employee_type IS NULL)
  //                         AND (
  //                             TRIM(full_name) = '' OR full_name IS NULL OR
  //                             TRIM(last_name) = '' OR last_name IS NULL OR
  //                             TRIM(first_name) = '' OR first_name IS NULL OR
  //                             TRIM(sss_number) = '' OR sss_number IS NULL OR
  //                             TRIM(philhealth_number) = '' OR philhealth_number IS NULL OR
  //                             TRIM(pag_ibig_number) = '' OR pag_ibig_number IS NULL OR
  //                             TRIM(tin_number) = '' OR tin_number IS NULL OR
  //                             TRIM(atm_number) = '' OR atm_number IS NULL OR
  //                             TRIM(bank_name) = '' OR bank_name IS NULL OR
  //                             TRIM(birthday) = '' OR birthday IS NULL OR
  //                             TRIM(hire_date) = '' OR hire_date IS NULL OR
  //                             TRIM(client_name) = '' OR client_name IS NULL OR
  //                             TRIM(branch_name) = '' OR branch_name IS NULL OR
  //                             TRIM(client_location) = '' OR client_location IS NULL OR
  //                             TRIM(position_name) = '' OR position_name IS NULL OR
  //                             TRIM(present_address) = '' OR present_address IS NULL OR
  //                             TRIM(contact_number) = '' OR contact_number IS NULL OR
  //                             TRIM(birth_place) = '' OR birth_place IS NULL OR
  //                             TRIM(gender) = '' OR gender IS NULL OR
  //                             TRIM(civil_status) = '' OR civil_status IS NULL OR
  //                             daily_salary IS NULL OR
  //                             annual_leaves IS NULL OR
  //                             TRIM(employee_type) = '' OR employee_type IS NULL
  //                             OR birthday = '0000-00-00' OR hire_date = '0000-00-00'
  //                             )
  //                       )";
              
  //         $stmt = $this->db->prepare($sql);
  //         $stmt->bindParam(':import_id', $this->import_id, PDO::PARAM_STR);
  //         $stmt->execute();
  //         $data = $stmt->fetchAll();

  //         if($stmt->rowCount() > 0){
  //             $response['data'] = $data;                 
  //         } else {
  //             $response['data'] = [];       
  //         }

  //         $response['success'] = 1;

  //     } catch (PDOException $e) {
  //         $response['success'] = 0;
  //         $response['message'] = 'Something went wrong.';
  //         $response['error'] = "An error occurred. Please contact your administrator.";
  //     }

  //     return $response;
  // }


  // public function sssFormat()
  // {
  //     $response = [];
      
  //     try{   

  //         $sql = "SELECT employee_id
  //                         ,old_employee_id
  //                         ,payroll_employee_id
  //                         ,full_name
  //                         ,last_name
  //                         ,first_name
  //                         ,middle_name
  //                         ,DATE_FORMAT(hire_date, '%m/%d/%Y') as hire_date
  //                         ,separation_date
  //                         ,present_address
  //                         ,permanent_address
  //                         ,contact_number
  //                         ,email_address
  //                         ,DATE_FORMAT(birthday, '%m/%d/%Y') as birthday
  //                         ,birth_place
  //                         ,gender
  //                         ,civil_status
  //                         ,nationality
  //                         ,emergency_person
  //                         ,emergency_contact_number
  //                         ,position_name
  //                         ,client_name
  //                         ,branch_name
  //                         ,client_location
  //                         ,department_name
  //                         ,tin_number
  //                         ,sss_number
  //                         ,philhealth_number
  //                         ,pag_ibig_number
  //                         ,atm_number
  //                         ,daily_salary
  //                         ,bank_name
  //                         ,insurance
  //                         ,annual_leaves
  //                         ,employee_type
  //                   FROM employee_tmp
  //                   where import_id = :import_id 
  //                         AND LENGTH(REGEXP_REPLACE(sss_number, '[^0-9]', '')) != 10";
              
  //         $stmt = $this->db->prepare($sql);
  //         $stmt->bindParam(':import_id', $this->import_id, PDO::PARAM_STR);
  //         $stmt->execute();
  //         $data = $stmt->fetchAll();

  //         if($stmt->rowCount() > 0){
  //             $response['data'] = $data;                 
  //         } else {
  //             $response['data'] = [];       
  //         }

  //         $response['success'] = 1;

  //     } catch (PDOException $e) {
  //         $response['success'] = 0;
  //         $response['message'] = 'Something went wrong.';
  //         $response['error'] = "An error occurred. Please contact your administrator.";
  //     }

  //     return $response;
  // }


  // public function dupSSSNumber()
  // {
  //     $response = [];
      
  //     try{   

  //         $sql = "SELECT a.employee_id
  //                         ,old_employee_id
  //                         ,payroll_employee_id
  //                         ,full_name
  //                         ,last_name
  //                         ,first_name
  //                         ,middle_name
  //                         ,DATE_FORMAT(hire_date, '%m/%d/%Y') as hire_date
  //                         ,separation_date
  //                         ,present_address
  //                         ,permanent_address
  //                         ,contact_number
  //                         ,email_address
  //                         ,DATE_FORMAT(birthday, '%m/%d/%Y') as birthday
  //                         ,birth_place
  //                         ,gender
  //                         ,civil_status
  //                         ,nationality
  //                         ,emergency_person
  //                         ,emergency_contact_number
  //                         ,position_name
  //                         ,client_name
  //                         ,branch_name
  //                         ,client_location
  //                         ,department_name
  //                         ,a.tin_number
  //                         ,a.sss_number
  //                         ,a.philhealth_number
  //                         ,a.pag_ibig_number
  //                         ,atm_number
  //                         ,daily_salary
  //                         ,bank_name
  //                         ,insurance
  //                         ,annual_leaves
  //                         ,employee_type
  //                   FROM employee_tmp a 
  //                   INNER JOIN employee_govt_account b 
  //                   ON REPLACE(a.sss_number, '-', '') = REPLACE(b.sss_number, '-', '')
  //                   AND a.employee_id <> b.employee_id
  //                   where import_id = :import_id";
              
  //         $stmt = $this->db->prepare($sql);
  //         $stmt->bindParam(':import_id', $this->import_id, PDO::PARAM_STR);
  //         $stmt->execute();
  //         $data = $stmt->fetchAll();

  //         if($stmt->rowCount() > 0){
  //             $response['data'] = $data;                 
  //         } else {
  //             $response['data'] = [];       
  //         }

  //         $response['success'] = 1;

  //     } catch (PDOException $e) {
  //         $response['success'] = 0;
  //         $response['message'] = 'Something went wrong.';
  //         $response['error'] = "An error occurred. Please contact your administrator.";
  //     }

  //     return $response;
  // }

  // public function dupPhilhealthNumber()
  // {
  //     $response = [];
      
  //     try{   

  //         $sql = "SELECT a.employee_id
  //                         ,old_employee_id
  //                         ,payroll_employee_id
  //                         ,full_name
  //                         ,last_name
  //                         ,first_name
  //                         ,middle_name
  //                         ,DATE_FORMAT(hire_date, '%m/%d/%Y') as hire_date
  //                         ,separation_date
  //                         ,present_address
  //                         ,permanent_address
  //                         ,contact_number
  //                         ,email_address
  //                         ,DATE_FORMAT(birthday, '%m/%d/%Y') as birthday
  //                         ,birth_place
  //                         ,gender
  //                         ,civil_status
  //                         ,nationality
  //                         ,emergency_person
  //                         ,emergency_contact_number
  //                         ,position_name
  //                         ,client_name
  //                         ,branch_name
  //                         ,client_location
  //                         ,department_name
  //                         ,a.tin_number
  //                         ,a.sss_number
  //                         ,a.philhealth_number
  //                         ,a.pag_ibig_number
  //                         ,atm_number
  //                         ,daily_salary
  //                         ,bank_name
  //                         ,insurance
  //                         ,annual_leaves
  //                         ,employee_type
  //                   FROM employee_tmp a 
  //                   INNER JOIN employee_govt_account b 
  //                   ON REPLACE(a.philhealth_number, '-', '') = REPLACE(b.philhealth_number, '-', '')
  //                   AND a.employee_id <> b.employee_id
  //                   where import_id = :import_id";
              
  //         $stmt = $this->db->prepare($sql);
  //         $stmt->bindParam(':import_id', $this->import_id, PDO::PARAM_STR);
  //         $stmt->execute();
  //         $data = $stmt->fetchAll();

  //         if($stmt->rowCount() > 0){
  //             $response['data'] = $data;                 
  //         } else {
  //             $response['data'] = [];       
  //         }

  //         $response['success'] = 1;

  //     } catch (PDOException $e) {
  //         $response['success'] = 0;
  //         $response['message'] = 'Something went wrong.';
  //         $response['error'] = "An error occurred. Please contact your administrator.";
  //     }

  //     return $response;
  // }


  // public function dupPagIbigNumber()
  // {
  //     $response = [];
      
  //     try{   

  //         $sql = "SELECT a.employee_id
  //                         ,old_employee_id
  //                         ,payroll_employee_id
  //                         ,full_name
  //                         ,last_name
  //                         ,first_name
  //                         ,middle_name
  //                         ,DATE_FORMAT(hire_date, '%m/%d/%Y') as hire_date
  //                         ,separation_date
  //                         ,present_address
  //                         ,permanent_address
  //                         ,contact_number
  //                         ,email_address
  //                         ,DATE_FORMAT(birthday, '%m/%d/%Y') as birthday
  //                         ,birth_place
  //                         ,gender
  //                         ,civil_status
  //                         ,nationality
  //                         ,emergency_person
  //                         ,emergency_contact_number
  //                         ,position_name
  //                         ,client_name
  //                         ,branch_name
  //                         ,client_location
  //                         ,department_name
  //                         ,a.tin_number
  //                         ,a.sss_number
  //                         ,a.philhealth_number
  //                         ,a.pag_ibig_number
  //                         ,atm_number
  //                         ,daily_salary
  //                         ,bank_name
  //                         ,insurance
  //                         ,annual_leaves
  //                         ,employee_type
  //                   FROM employee_tmp a 
  //                   INNER JOIN employee_govt_account b 
  //                   ON REPLACE(a.pag_ibig_number, '-', '') = REPLACE(b.pag_ibig_number, '-', '')
  //                   AND a.employee_id <> b.employee_id
  //                   where import_id = :import_id";
              
  //         $stmt = $this->db->prepare($sql);
  //         $stmt->bindParam(':import_id', $this->import_id, PDO::PARAM_STR);
  //         $stmt->execute();
  //         $data = $stmt->fetchAll();

  //         if($stmt->rowCount() > 0){
  //             $response['data'] = $data;                 
  //         } else {
  //             $response['data'] = [];       
  //         }

  //         $response['success'] = 1;

  //     } catch (PDOException $e) {
  //         $response['success'] = 0;
  //         $response['message'] = 'Something went wrong.';
  //         $response['error'] = "An error occurred. Please contact your administrator.";
  //     }

  //     return $response;
  // }

  // public function dupBankNumber()
  // {
  //     $response = [];
      
  //     try{   

  //         $sql = "SELECT a.employee_id
  //                         ,old_employee_id
  //                         ,payroll_employee_id
  //                         ,full_name
  //                         ,last_name
  //                         ,first_name
  //                         ,middle_name
  //                         ,DATE_FORMAT(hire_date, '%m/%d/%Y') as hire_date
  //                         ,separation_date
  //                         ,present_address
  //                         ,permanent_address
  //                         ,contact_number
  //                         ,email_address
  //                         ,DATE_FORMAT(birthday, '%m/%d/%Y') as birthday
  //                         ,birth_place
  //                         ,gender
  //                         ,civil_status
  //                         ,nationality
  //                         ,emergency_person
  //                         ,emergency_contact_number
  //                         ,position_name
  //                         ,client_name
  //                         ,branch_name
  //                         ,client_location
  //                         ,department_name
  //                         ,tin_number
  //                         ,sss_number
  //                         ,philhealth_number
  //                         ,pag_ibig_number
  //                         ,a.atm_number
  //                         ,a.daily_salary
  //                         ,a.bank_name
  //                         ,insurance
  //                         ,annual_leaves
  //                         ,employee_type
  //                   FROM employee_tmp a 
  //                   INNER JOIN employee_salary b 
  //                   ON REPLACE(a.atm_number, '-', '') = REPLACE(b.atm_number, '-', '')
  //                   AND a.employee_id <> b.employee_id
  //                   where import_id = :import_id";
              
  //         $stmt = $this->db->prepare($sql);
  //         $stmt->bindParam(':import_id', $this->import_id, PDO::PARAM_STR);
  //         $stmt->execute();
  //         $data = $stmt->fetchAll();

  //         if($stmt->rowCount() > 0){
  //             $response['data'] = $data;                 
  //         } else {
  //             $response['data'] = [];       
  //         }

  //         $response['success'] = 1;

  //     } catch (PDOException $e) {
  //         $response['success'] = 0;
  //         $response['message'] = 'Something went wrong.';
  //         $response['error'] = "An error occurred. Please contact your administrator.";
  //     }

  //     return $response;
  // }


  // public function dupTinNumber()
  // {
  //     $response = [];
      
  //     try{   

  //         $sql = "SELECT a.employee_id
  //                         ,old_employee_id
  //                         ,payroll_employee_id
  //                         ,full_name
  //                         ,last_name
  //                         ,first_name
  //                         ,middle_name
  //                         ,DATE_FORMAT(hire_date, '%m/%d/%Y') as hire_date
  //                         ,separation_date
  //                         ,present_address
  //                         ,permanent_address
  //                         ,contact_number
  //                         ,email_address
  //                         ,DATE_FORMAT(birthday, '%m/%d/%Y') as birthday
  //                         ,birth_place
  //                         ,gender
  //                         ,civil_status
  //                         ,nationality
  //                         ,emergency_person
  //                         ,emergency_contact_number
  //                         ,position_name
  //                         ,client_name
  //                         ,branch_name
  //                         ,client_location
  //                         ,department_name
  //                         ,a.tin_number
  //                         ,a.sss_number
  //                         ,a.philhealth_number
  //                         ,a.pag_ibig_number
  //                         ,atm_number
  //                         ,daily_salary
  //                         ,bank_name
  //                         ,insurance
  //                         ,annual_leaves
  //                         ,employee_type
  //                   FROM employee_tmp a 
  //                   INNER JOIN employee_govt_account b
  //                   ON REPLACE(a.tin_number, '-', '') = REPLACE(b.tin_number, '-', '')
  //                   AND a.employee_id <> b.employee_id 
  //                   where import_id = :import_id";
              
  //         $stmt = $this->db->prepare($sql);
  //         $stmt->bindParam(':import_id', $this->import_id, PDO::PARAM_STR);
  //         $stmt->execute();
  //         $data = $stmt->fetchAll();

  //         if($stmt->rowCount() > 0){
  //             $response['data'] = $data;                 
  //         } else {
  //             $response['data'] = [];       
  //         }

  //         $response['success'] = 1;

  //     } catch (PDOException $e) {
  //         $response['success'] = 0;
  //         $response['message'] = 'Something went wrong.';
  //         $response['error'] = "An error occurred. Please contact your administrator.";
  //     }

  //     return $response;
  // }

  // public function dailySalary()
  // {
  //     $response = [];
      
  //     try{   

  //         $sql = "SELECT employee_id
  //                         ,old_employee_id
  //                         ,payroll_employee_id
  //                         ,full_name
  //                         ,last_name
  //                         ,first_name
  //                         ,middle_name
  //                         ,DATE_FORMAT(hire_date, '%m/%d/%Y') as hire_date
  //                         ,separation_date
  //                         ,present_address
  //                         ,permanent_address
  //                         ,contact_number
  //                         ,email_address
  //                         ,DATE_FORMAT(birthday, '%m/%d/%Y') as birthday
  //                         ,birth_place
  //                         ,gender
  //                         ,civil_status
  //                         ,nationality
  //                         ,emergency_person
  //                         ,emergency_contact_number
  //                         ,position_name
  //                         ,client_name
  //                         ,branch_name
  //                         ,client_location
  //                         ,department_name
  //                         ,tin_number
  //                         ,sss_number
  //                         ,philhealth_number
  //                         ,pag_ibig_number
  //                         ,atm_number
  //                         ,daily_salary
  //                         ,bank_name
  //                         ,insurance
  //                         ,annual_leaves
  //                         ,employee_type
  //                   FROM employee_tmp
  //                   where import_id = :import_id 
  //                         AND (daily_salary < 100 OR daily_salary > 10000)";
              
  //         $stmt = $this->db->prepare($sql);
  //         $stmt->bindParam(':import_id', $this->import_id, PDO::PARAM_STR);
  //         $stmt->execute();
  //         $data = $stmt->fetchAll();

  //         if($stmt->rowCount() > 0){
  //             $response['data'] = $data;                 
  //         } else {
  //             $response['data'] = [];       
  //         }

  //         $response['success'] = 1;

  //     } catch (PDOException $e) {
  //         $response['success'] = 0;
  //         $response['message'] = 'Something went wrong.';
  //         $response['error'] = "An error occurred. Please contact your administrator.";
  //     }

  //     return $response;
  // }


  // public function contactNumber()
  // {
  //     $response = [];
      
  //     try{   

  //         $sql = "SELECT employee_id
  //                         ,old_employee_id
  //                         ,payroll_employee_id
  //                         ,full_name
  //                         ,last_name
  //                         ,first_name
  //                         ,middle_name
  //                         ,DATE_FORMAT(hire_date, '%m/%d/%Y') as hire_date
  //                         ,separation_date
  //                         ,present_address
  //                         ,permanent_address
  //                         ,contact_number
  //                         ,email_address
  //                         ,DATE_FORMAT(birthday, '%m/%d/%Y') as birthday
  //                         ,birth_place
  //                         ,gender
  //                         ,civil_status
  //                         ,nationality
  //                         ,emergency_person
  //                         ,emergency_contact_number
  //                         ,position_name
  //                         ,client_name
  //                         ,branch_name
  //                         ,client_location
  //                         ,department_name
  //                         ,tin_number
  //                         ,sss_number
  //                         ,philhealth_number
  //                         ,pag_ibig_number
  //                         ,atm_number
  //                         ,daily_salary
  //                         ,bank_name
  //                         ,insurance
  //                         ,annual_leaves
  //                         ,employee_type
  //                   FROM employee_tmp
  //                   where import_id = :import_id 
  //                         AND contact_number NOT REGEXP '^[0-9]{11}$'";
              
  //         $stmt = $this->db->prepare($sql);
  //         $stmt->bindParam(':import_id', $this->import_id, PDO::PARAM_STR);
  //         $stmt->execute();
  //         $data = $stmt->fetchAll();

  //         if($stmt->rowCount() > 0){
  //             $response['data'] = $data;                 
  //         } else {
  //             $response['data'] = [];       
  //         }

  //         $response['success'] = 1;

  //     } catch (PDOException $e) {
  //         $response['success'] = 0;
  //         $response['message'] = 'Something went wrong.';
  //         $response['error'] = "An error occurred. Please contact your administrator.";
  //     }

  //     return $response;
  // }


  // public function invalidEmployeeType()
  // {
  //     $response = [];
      
  //     try{   

  //         $sql = "SELECT employee_id
  //                         ,old_employee_id
  //                         ,payroll_employee_id
  //                         ,full_name
  //                         ,last_name
  //                         ,first_name
  //                         ,middle_name
  //                         ,DATE_FORMAT(hire_date, '%m/%d/%Y') as hire_date
  //                         ,separation_date
  //                         ,present_address
  //                         ,permanent_address
  //                         ,contact_number
  //                         ,email_address
  //                         ,DATE_FORMAT(birthday, '%m/%d/%Y') as birthday
  //                         ,birth_place
  //                         ,gender
  //                         ,civil_status
  //                         ,nationality
  //                         ,emergency_person
  //                         ,emergency_contact_number
  //                         ,position_name
  //                         ,client_name
  //                         ,branch_name
  //                         ,client_location
  //                         ,department_name
  //                         ,tin_number
  //                         ,sss_number
  //                         ,philhealth_number
  //                         ,pag_ibig_number
  //                         ,atm_number
  //                         ,daily_salary
  //                         ,bank_name
  //                         ,insurance
  //                         ,annual_leaves
  //                         ,employee_type
  //                   FROM employee_tmp
  //                   where import_id = :import_id 
  //                         AND employee_type NOT IN ('Seasonal', 'Long Term')
  //                         AND employee_type IS NOT NULL";
              
  //         $stmt = $this->db->prepare($sql);
  //         $stmt->bindParam(':import_id', $this->import_id, PDO::PARAM_STR);
  //         $stmt->execute();
  //         $data = $stmt->fetchAll();

  //         if($stmt->rowCount() > 0){
  //             $response['data'] = $data;                 
  //         } else {
  //             $response['data'] = [];       
  //         }

  //         $response['success'] = 1;

  //     } catch (PDOException $e) {
  //         $response['success'] = 0;
  //         $response['message'] = 'Something went wrong.';
  //         $response['error'] = "An error occurred. Please contact your administrator.";
  //     }

  //     return $response;
  // }

  public function deleteTemp()
  {
      $response = [];
      
      try{   

          $sql = "DELETE FROM employee_tmp
                  WHERE import_id = :import_id"; 
          $stmt = $this->db->prepare($sql);
          $stmt->bindParam(':import_id', $this->import_id, PDO::PARAM_STR);
          $stmt->execute();

          $response = array(
              "success" => 1,
              "import_id" => $this->import_id
          );

      } catch (PDOException $e) {
          error_log('Import::deleteInvalid failed: ' . $e->getMessage());
          $response['success'] = 0;
                    $response['message'] = "Unable to delete invalid employee import rows.";
      }

      return $response;
  }

  public function generateId(){
    $uniqueRefFound = 1;
    while ($uniqueRefFound == 1) {
      $uniqueRef = (string)random_int(100000000, 999999999);
        
      $sql = "SELECT import_id
                FROM employee_tmp
                WHERE import_id = :import_id";
      $stmt = $this->db->prepare($sql);
      $stmt->bindParam(':import_id', $uniqueRef, PDO::PARAM_STR);
      $stmt->execute();
      if($stmt->rowCount() == 0){
        $uniqueRefFound = 0;
      }
    }
    return $uniqueRef;
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
