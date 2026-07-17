<?php
require_once __DIR__ . '/../../dtr-upload/model/PayrollLockGuard.php';
class Additional
{
    public $db = null;
    public $client = null;

    public $cutoffArray = array();
    public $id = null;

    public $employee_id = null;
    public $employee_name = null;
    public $amount = null;
    public $type_of_addition = null;
    public $client_name = null;
    public $cut_off = null;
    public $pay_day = null;
    public $start_date = null;
    public $end_date = null;
    public $client_location = null;
    public $branch = null;

    public function spDeleteAdditional(){
            
        try {
        $sql = "CALL sp_delete_additional_deduction(:client_name, :pay_day, :cut_off, :employee_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':client_name' => $this->client_name, ':pay_day' => $this->pay_day, ':cut_off' => $this->cut_off, ':employee_id' => $this->employee_id]);

        $response = array(
            "success" => 1
        );

        } catch (PDOException $e) {
            error_log('Additional::spDeleteAdditional failed: ' . $e->getMessage());
            $response = array(
                "success" => 0,
                "error" => "Unable to recalculate payroll additions."
            );
        }
        return $response;
    }


    public function spIndividualAdditional(){
            
        try {
        $sql = "CALL sp_payroll_additional_indv(:client_name, :pay_day, :cut_off, :employee_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':client_name' => $this->client_name, ':pay_day' => $this->pay_day, ':cut_off' => $this->cut_off, ':employee_id' => $this->employee_id]);

        $response = array(
            "success" => 1
        );

        } catch (PDOException $e) {
            error_log('Additional::spIndividualAdditional failed: ' . $e->getMessage());
            $response = array(
                "success" => 0,
                "error" => "Unable to recalculate payroll additions."
            );
        }
        return $response;
    }

    public function getAdditionalList(){

        $response = [];

        try {
            $where = "";
            $filterParams = [];

            if($this->client_location != 'null'){
                $where .= " AND b.client_location_id = :client_location_id";
                $filterParams[':client_location_id'] = (int)$this->client_location;
            }

            if($this->branch != 'null'){
                $where .= " AND b.branch_id = :branch_id";
                $filterParams[':branch_id'] = (int)$this->branch;
            }

            $sql = "SELECT c.id,
                        a.employee_id,
                        CONCAT(b.last_name, ', ', b.first_name) AS employee_full_name,
                        amount,
                        type_of_addition
                    FROM payroll_gross_variables a
                    INNER JOIN employee_list b ON a.employee_id = b.employee_id
                    LEFT JOIN payroll_other_additional c ON a.employee_id = c.employee_id
                                    AND c.client_name = a.client_name
                                    AND c.cut_off = a.cut_off
                                    AND c.pay_day = a.pay_day
                    WHERE b.status = 'Active' 
                        AND a.client_name = :client
                        AND a.cut_off = :cut_off
                        AND a.pay_day = :pay_day
                        $where";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            foreach ($filterParams as $name => $value) { $stmt->bindValue($name, $value, PDO::PARAM_INT); }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];       
                 
            }

                    } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; 
                    }

        return $response;
    }

    public function individualAdditional()
    {
        $response = [];
        
        try{   

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
            $stmt->bindParam(':client_name', $this->client_name, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':employee_id', $this->employee_id, PDO::PARAM_INT);
            $stmt->bindParam(':amount', $this->amount, PDO::PARAM_STR);
            $stmt->bindParam(':type_of_addition', $this->type_of_addition, PDO::PARAM_STR);
            $stmt->bindParam(':start_date', $this->start_date, PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $this->end_date, PDO::PARAM_STR);
            $stmt->execute();

            $response = array(
                "success" => 1
            );

        } catch (PDOException $e) {
            $response['success'] = 0;
                        $response['error'] = "An error occurred. Please contact your administrator.";
        }

        return $response;
    }

    public function deleteAdditional()
    {
        $response = [];
        
        try{   

            $sql = "DELETE FROM payroll_other_additional
                    WHERE id = :id AND client_name = :client_name AND pay_day = :pay_day";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->bindParam(':client_name', $this->client_name, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();

            $response = array(
                "success" => 1
            );

        } catch (PDOException $e) {
            $response['success'] = 0;
                        $response['error'] = "An error occurred. Please contact your administrator.";
        }

        return $response;
    }

    public function isLocked(){
        return (new PayrollLockGuard($this->db))->check((string)$this->client, (string)$this->pay_day);
    }

    public function getPayDay(){

        $response = [];

        try {
            $sql = "SELECT 1 FROM client_payday 
                        WHERE client_name = :client";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($stmt->rowCount() > 0) {    
                $response['success'] = 1;
            } else {
                $response['success'] = 2;
            }
    
                    } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator.";
                    }
    
        return $response;
    }

    public function getPayDayFilter(){

        $response = [];

        try {

            $sql = "SELECT DISTINCT start_date, end_date, pay_date, cut_off
                    FROM (
                        SELECT DISTINCT 
                            start_date,
                            end_date,
                            cut_off,
                            pay_day as pay_date
                        FROM dtr_upload 
                        WHERE client_name = :client1

                        UNION ALL
                        SELECT * FROM (
                        SELECT CASE 
                                    WHEN cut_off = 'Weekly' THEN DATE_SUB(pay_date, INTERVAL (WEEKDAY(pay_date) + 7) DAY)
                                    ELSE STR_TO_DATE(CONCAT(
                                        CASE 
                                            WHEN SUBSTRING_INDEX(cut_off, '-', 1) > pay_day
                                            THEN YEAR(DATE_SUB(pay_date, INTERVAL 1 MONTH))
                                            ELSE YEAR(pay_date)
                                        END, '-', 
                                        CASE 
                                            WHEN SUBSTRING_INDEX(cut_off, '-', 1) > pay_day
                                            THEN MONTH(DATE_SUB(pay_date, INTERVAL 1 MONTH))
                                            ELSE MONTH(pay_date)
                                        END, '-', 
                                        SUBSTRING_INDEX(cut_off, '-', 1)
                                    ), '%Y-%m-%d')
                                END AS start_date,
                                CASE WHEN cut_off = 'Weekly' THEN DATE_SUB(pay_date, INTERVAL (WEEKDAY(pay_date) + 1) DAY)
                                    ELSE STR_TO_DATE(CONCAT(
                                        CASE 
                                            WHEN SUBSTRING_INDEX(cut_off, '-', -1) > pay_day
                                            THEN YEAR(DATE_SUB(pay_date, INTERVAL 1 MONTH))
                                            ELSE YEAR(pay_date)
                                        END, '-', 
                                        CASE 
                                            WHEN SUBSTRING_INDEX(cut_off, '-', -1) > pay_day
                                            THEN MONTH(DATE_SUB(pay_date, INTERVAL 1 MONTH))
                                            ELSE MONTH(pay_date)
                                        END, '-', 
                                        SUBSTRING_INDEX(cut_off, '-', -1)
                                    ), '%Y-%m-%d')
                                END AS end_date,
                                cut_off,
                                pay_date
                        FROM (
                            SELECT DISTINCT cut_off, pay_day,
                                -- Compute pay day 
                                CASE 
                                    WHEN cut_off = 'Weekly' THEN 
                                        -- Next Friday
                                        DATE_ADD(curdate(), INTERVAL (CASE 
                                            WHEN WEEKDAY(curdate()) = 4 THEN 0  
                                            WHEN WEEKDAY(curdate()) < 4 THEN (4 - WEEKDAY(curdate())) 
                                            ELSE (11 - WEEKDAY(curdate()))  
                                        END) DAY)
                                    ELSE 
                                        -- payday calculation
                                        STR_TO_DATE(CONCAT(
                                            CASE 
                                                WHEN pay_day < DAY(curdate()) THEN YEAR(DATE_ADD(curdate(), INTERVAL 1 MONTH))
                                                ELSE YEAR(curdate())
                                            END, '-', 
                                            CASE 
                                                WHEN pay_day < DAY(curdate()) THEN MONTH(DATE_ADD(curdate(), INTERVAL 1 MONTH))
                                                ELSE MONTH(curdate())
                                            END, '-', 
                                            CASE 
                                                -- Feb
                                                WHEN MONTH(DATE_ADD(curdate(), INTERVAL 1 MONTH)) = 2 AND pay_day > 28 THEN 
                                                    CASE 
                                                        WHEN YEAR(DATE_ADD(curdate(), INTERVAL 1 MONTH)) % 4 = 0 
                                                            AND (YEAR(DATE_ADD(curdate(), INTERVAL 1 MONTH)) % 100 <> 0 
                                                            OR YEAR(DATE_ADD(curdate(), INTERVAL 1 MONTH)) % 400 = 0) 
                                                        THEN 29 
                                                        ELSE 28
                                                    END
                                                ELSE pay_day 
                                            END
                                        ), '%Y-%m-%d')
                                END AS pay_date
                            FROM client_payday 
                            WHERE client_name = :client1
                        ) AS adjusted_paydays
                        WHERE pay_date >= curdate()  -- Only future pay days
                        ORDER BY pay_date ASC
                        LIMIT 1) as future_paydays
                    ) AS final_result ORDER BY pay_date DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':client1', $this->client, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];       
                 
            }

            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }
    

    public function getClientFilter(){

        $response = [];

        try {
            $sql = "SELECT distinct client_name from taascor_client
                    order by client_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];       
                 
            }

            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }

    public function getClientLocation(){

        $response = [];

        try {
            $sql = "SELECT distinct location_id, location_name from employee_list a 
                    inner join taascor_client b on a.client_id = b.client_id
                    inner join taascor_client_location c on a.client_location_id = c.location_id
                    WHERE client_name = :client
                    order by location_name";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];       
                 
            }

            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }

    public function getBranch(){

        $response = [];

        try {
            $sql = "SELECT distinct a.branch_id, branch_name from employee_list a 
                    inner join taascor_branch b on a.branch_id = b.branch_id
                    inner join taascor_client c on a.client_id = c.client_id
                    WHERE client_name = :client
                    order by branch_name";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];       
                 
            }

            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }
    
}


?>
