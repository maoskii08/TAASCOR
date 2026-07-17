<?php

class Import{

  public $db = null;
  public $payrollDetails = array();

  public $client_name = null;
  public $cut_off = null;
  public $pay_day = null;

  public function spDeduction(){
        
    try {
      $sql = "CALL sp_payroll_deduction(
                    '{$this->client_name}'
                    ,'{$this->pay_day}'
                    ,'{$this->cut_off}'
              )";
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
              $sql_values[$columnHeader] = $singleQoute;
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

      $stmt->bindParam(':client_name', $client_name, PDO::PARAM_STR);
      $stmt->bindParam(':pay_day', $pay_day, PDO::PARAM_STR);
      $stmt->bindParam(':cut_off', $cut_off, PDO::PARAM_STR);
      $stmt->bindParam(':employee_id', $sql_values["employee id"], PDO::PARAM_INT);
      $stmt->bindParam(':amount', $sql_values["amount"], PDO::PARAM_STR);
      $stmt->bindParam(':type_of_deduction', $sql_values["type of deduction"], PDO::PARAM_STR);
      $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
      $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);

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
