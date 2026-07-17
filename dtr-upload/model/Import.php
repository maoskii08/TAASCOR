<?php

class Import{

  public $db = null;
  public $payrollDetails = array();

  public $client_name = null;
  public $cut_off = null;
  public $pay_day = null;

  public function spCalculateDTR(){
        
    try {
      $sql = "CALL sp_calculate_dtr(
                    '{$this->client_name}'
                    ,'{$this->pay_day}'
                    ,'{$this->cut_off}'
              )";
      if($this->cut_off == 'Weekly'){
        $sql = "CALL sp_calculate_dtr_weekly(
          '{$this->client_name}'
          ,'{$this->pay_day}'
          ,'{$this->cut_off}'
        )";
      }
      $stmt = $this->db->prepare($sql);
      $stmt->execute();

      $response = array(
        "success" => 1,
        "sql" => $sql 
      );

    } catch (PDOException $e) {
      $response = array(
        "success" => 0,
        "message" => $e->getMessage(), 
        "sql" => $sql 
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
      $this->db->beginTransaction();

      $columnMap = $columnMap;
      foreach ($data as $value) {
        
        $insert= $this->insertToDatabase($columnMap, $value); 
          if($insert['success'] == 0){
            $this->db->rollBack();
            $response['success'] = 0;
            $response['message'] = 'Insert Syntax Error!';
            $response['error'] = $insert['message'];
            $response['sql'] = $insert['sql'];
            return $response;
            exit();
          }

      }
        
      $this->db->commit();
      $response['success'] = 1;
      // $response['sql'] = $insert['sql'];
      $response['message'] = 'Success';
    } catch (PDOException $e) {
      $this->db->rollBack();
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

      foreach($this->payrollDetails as $row){
        $client_name = $row[0];
        $cut_off = $row[1];
        $pay_day = $row[2];
        $start_date = $row[3];
        $end_date = $row[4];
      }
   
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
        "sql" => $sql,
        "message" => 'Succesfully Imported',
      );

    } catch (PDOException $e) {
      $response = array(
        "success" => 0,
        "sql" => $sql,
        "message" => $e->getMessage() 
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
