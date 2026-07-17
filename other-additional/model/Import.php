<?php
require_once __DIR__ . '/../../dtr-upload/model/PayrollLockGuard.php';

class Import{

  public $db = null;
  public $payrollDetails = array();

  public $client_name = null;
  public $cut_off = null;
  public $pay_day = null;

  public function spAdditional(){
        
    try {
      $sql = "CALL sp_payroll_additional(:client_name, :pay_day, :cut_off)";
      $stmt = $this->db->prepare($sql);
      $stmt->execute([
        ':client_name' => $this->client_name,
        ':pay_day' => $this->pay_day,
        ':cut_off' => $this->cut_off,
      ]);

      $response = array(
        "success" => 1
      );

    } catch (PDOException $e) {
      $response = array(
        "success" => 0,
        "message" => "Unable to calculate payroll additions."
      );
 
    }
    return $response;
  }

  public function add(array $data, $columnMap){
    try {
      $scope = $this->validatedPayrollScope();
      if (($scope['success'] ?? 0) !== 1) {
        return $scope;
      }
      $this->payrollDetails = [[
        $scope['client_name'], $scope['cut_off'], $scope['pay_day'],
        $scope['start_date'], $scope['end_date'],
      ]];
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
      // $response['sql'] = $insert['sql'];
      $response['message'] = 'Success';
    } catch (PDOException $e) {
      $this->db->rollBack();
      $response['success'] = 0;
      $response['error'] = "An error occurred. Please contact your administrator.";
    }
    return $response;
  }

  public function validatedPayrollScope(): array
  {
    return PayrollLockGuard::normalizePayrollDetails($this->payrollDetails);
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
              $sql_values[$columnHeader] = $singleQoute;
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
   
      $sql = "INSERT INTO payroll_other_additional
              (
                  client_name,
                  pay_day,
                  cut_off,
                  employee_id,
                  amount,
                  type_of_addition,
                  start_date,
                  end_date
              ) 
              VALUES (
                  :client_name,
                  :pay_day,
                  :cut_off,
                  :employee_id,
                  :amount,
                  :type_of_addition,
                  :start_date,
                  :end_date
              )";

      $stmt = $this->db->prepare($sql);

      $stmt->bindParam(':client_name', $client_name, PDO::PARAM_STR);
      $stmt->bindParam(':pay_day', $pay_day, PDO::PARAM_STR);
      $stmt->bindParam(':cut_off', $cut_off, PDO::PARAM_STR);
      $stmt->bindParam(':employee_id', $sql_values["employee id"], PDO::PARAM_INT);
      $stmt->bindParam(':amount', $sql_values["amount"], PDO::PARAM_STR);
      $stmt->bindParam(':type_of_addition', $sql_values["type of addition"], PDO::PARAM_STR);
      $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
      $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);

      $stmt->execute();

      $response = array(
        "success" => 1,
        "message" => 'Succesfully Imported',
      );

    } catch (PDOException $e) {
      $response = array(
        "success" => 0,
        "message" => "Unable to import payroll addition."
      );
 
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
