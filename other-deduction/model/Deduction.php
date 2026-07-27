<?php
require_once __DIR__ . '/../../dtr-upload/model/PayrollLockGuard.php';
require_once __DIR__ . '/../../includes/payroll_adjustment_guard.php';
class Deduction
{
    public $db = null;
    public $client = null;

    public $cutoffArray = array();
    public $id = null;

    public $employee_id = null;
    public $employee_name = null;
    public $amount = null;
    public $type_of_deduction = null;
    public $client_name = null;
    public $cut_off = null;
    public $pay_day = null;
    public $start_date = null;
    public $end_date = null;
    public $client_location = null;
    public $branch = null;

    public function spDeleteDeduction(){
        return PayrollAdjustmentGuard::recalculateScope(
            $this->db,
            $this->adjustmentScope(),
            [(int)$this->employee_id]
        );
    }

    public function spIndividualDeduction(){
        return PayrollAdjustmentGuard::recalculateScope(
            $this->db,
            $this->adjustmentScope(),
            [(int)$this->employee_id]
        );
    }

    private function adjustmentScope(): array
    {
        return [
            'client_name' => $this->client_name,
            'cut_off' => $this->cut_off,
            'pay_day' => $this->pay_day,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ];
    }

    public function getDeductionList(){

        $response = [];

        try {
            $where = "";
            $filterParams = [];

            $locationId = PayrollAdjustmentGuard::filterIdOrNull($this->client_location, 'client location');
            $branchId = PayrollAdjustmentGuard::filterIdOrNull($this->branch, 'branch');

            if($locationId !== null){
                $where .= " AND b.client_location_id = :client_location_id";
                $filterParams[':client_location_id'] = $locationId;
            }

            if($branchId !== null){
                $where .= " AND b.branch_id = :branch_id";
                $filterParams[':branch_id'] = $branchId;
            }

            $sql = "SELECT c.id,
                        a.employee_id,
                        COALESCE(NULLIF(b.full_name, ''), CONCAT(b.last_name, ', ', b.first_name)) AS employee_full_name,
                        c.amount,
                        c.type_of_deduction
                    FROM (
                        SELECT DISTINCT client_name, pay_day, cut_off, employee_id, start_date, end_date
                        FROM dtr_upload
                        WHERE client_name = :client
                          AND cut_off = :cut_off
                          AND pay_day = :pay_day
                          AND start_date = :start_date
                          AND end_date = :end_date
                    ) a
                    INNER JOIN employee_list b ON a.employee_id = b.employee_id
                    INNER JOIN taascor_client tc
                        ON tc.client_id = b.client_id
                       AND tc.client_name = a.client_name
                       AND tc.is_active = 1
                    LEFT JOIN payroll_other_deduction c ON a.employee_id = c.employee_id
                                    AND c.client_name = a.client_name
                                    AND c.cut_off = a.cut_off
                                    AND c.pay_day = a.pay_day
                                    AND c.start_date = a.start_date
                                    AND c.end_date = a.end_date
                    WHERE b.status = 'Active' 
                        $where
                    ORDER BY employee_full_name, c.id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->bindParam(':start_date', $this->start_date, PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $this->end_date, PDO::PARAM_STR);
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


    public function individualDeduction()
    {
        $response = [];
        
        try{   

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
            $stmt->bindParam(':client_name', $this->client_name, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':employee_id', $this->employee_id, PDO::PARAM_INT);
            $stmt->bindParam(':amount', $this->amount, PDO::PARAM_STR);
            $stmt->bindParam(':type_of_deduction', $this->type_of_deduction, PDO::PARAM_STR);
            $stmt->bindParam(':start_date', $this->start_date, PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $this->end_date, PDO::PARAM_STR);
            $stmt->execute();
            $insertedId = (int)$this->db->lastInsertId();
            if ($stmt->rowCount() !== 1 || $insertedId < 1) {
                throw new RuntimeException('The inserted deduction could not be identified.');
            }

            $response = array(
                "success" => 1,
                "inserted_id" => $insertedId
            );

        } catch (Throwable $e) {
            $response['success'] = 0;
                        $response['error'] = "An error occurred. Please contact your administrator.";
        }

        return $response;
    }

    public function getDeductionSnapshotForUpdate(): array
    {
        try {
            $sql = "SELECT id,
                           employee_id,
                           amount,
                           type_of_deduction AS type
                    FROM payroll_other_deduction
                    WHERE id = :id
                      AND employee_id = :employee_id
                      AND client_name = :client_name
                      AND cut_off = :cut_off
                      AND pay_day = :pay_day
                      AND start_date = :start_date
                      AND end_date = :end_date
                    FOR UPDATE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $this->id,
                ':employee_id' => $this->employee_id,
                ':client_name' => $this->client_name,
                ':cut_off' => $this->cut_off,
                ':pay_day' => $this->pay_day,
                ':start_date' => $this->start_date,
                ':end_date' => $this->end_date,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return [
                    'success' => 0,
                    'code' => 'adjustment_not_found',
                    'error' => 'The selected deduction no longer exists in this payroll scope.',
                ];
            }

            return [
                'success' => 1,
                'row_record' => [
                    'adjustment_id' => (int)$row['id'],
                    'employee_id' => (int)$row['employee_id'],
                    'amount' => number_format((float)$row['amount'], 2, '.', ''),
                    'type' => (string)$row['type'],
                ],
            ];
        } catch (Throwable $error) {
            error_log('Deduction::getDeductionSnapshotForUpdate failed: ' . $error->getMessage());
            return [
                'success' => 0,
                'code' => 'deduction_snapshot_failed',
                'error' => 'The deduction could not be verified for deletion.',
            ];
        }
    }

    public function deleteDeduction()
    {
        $response = [];
        
        try{   

            $sql = "DELETE FROM payroll_other_deduction
                    WHERE id = :id
                      AND employee_id = :employee_id
                      AND client_name = :client_name
                      AND cut_off = :cut_off
                      AND pay_day = :pay_day
                      AND start_date = :start_date
                      AND end_date = :end_date";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->bindParam(':employee_id', $this->employee_id, PDO::PARAM_INT);
            $stmt->bindParam(':client_name', $this->client_name, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->bindParam(':start_date', $this->start_date, PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $this->end_date, PDO::PARAM_STR);
            $stmt->execute();

            $response = $stmt->rowCount() === 1
                ? ["success" => 1]
                : ["success" => 0, "code" => "adjustment_not_found", "error" => "The selected deduction no longer exists in this payroll scope."];

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
                        WHERE client_name = :client_dtr

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
                            WHERE client_name = :client_schedule
                        ) AS adjusted_paydays
                        WHERE pay_date >= curdate()  -- Only future pay days
                        ORDER BY pay_date ASC
                        LIMIT 1) as future_paydays
                    ) AS final_result ORDER BY pay_date DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client_dtr', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':client_schedule', $this->client, PDO::PARAM_STR);
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
            $sql = "SELECT DISTINCT client_name FROM taascor_client
                    WHERE is_active = 1
                      AND client_name <> 'No Client'
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
            $sql = "SELECT DISTINCT c.location_id, c.location_name
                    FROM employee_list a
                    INNER JOIN taascor_client b ON a.client_id = b.client_id
                    INNER JOIN taascor_client_location c ON a.client_location_id = c.location_id
                    WHERE b.client_name = :client
                      AND b.is_active = 1
                      AND a.status = 'Active'
                    ORDER BY c.location_name";

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
            $sql = "SELECT DISTINCT a.branch_id, b.branch_name
                    FROM employee_list a
                    INNER JOIN taascor_branch b ON a.branch_id = b.branch_id
                    INNER JOIN taascor_client c ON a.client_id = c.client_id
                    WHERE c.client_name = :client
                      AND c.is_active = 1
                      AND a.status = 'Active'
                    ORDER BY b.branch_name";

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
