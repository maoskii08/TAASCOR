<?php
class Loan
{
    public $db = null;
    public $loan_date = null;
    public $loan_type = null;


    public function getLoanList(){

        $response = [];

        try {
            $this->loan_date .= "-01";

            $sql = "WITH ranked_payments AS (
                        SELECT 
                            a.employee_id,
                            a.payment_amount,
                            a.pay_day,
                            c.loan_date,
                            b.first_name,
                            b.last_name,
                            ROW_NUMBER() OVER (
                                PARTITION BY a.employee_id ORDER BY a.payment_number
                            ) AS rn
                        FROM loans_payment a
                        INNER JOIN employee_list b 
                            ON a.employee_id = b.employee_id
                        INNER JOIN employee_loans c 
                            ON a.employee_id = c.employee_id 
                            AND a.loan_type = c.loan_type
                        WHERE MONTH(a.pay_day) = MONTH(:loan_date_month)
                            AND YEAR(a.pay_day) = YEAR(:loan_date_year)
                            AND a.loan_type = :loan_type
                    )

                    SELECT 
                        CONCAT(last_name, ', ', first_name) AS employee_full_name,
                        DATE_FORMAT(loan_date, '%m/%d/%Y') AS date_awarded,
                        MAX(CASE WHEN rn = 1 THEN payment_amount ELSE NULL END) AS first_cutoff,
                        MAX(CASE WHEN rn = 2 THEN payment_amount ELSE NULL END) AS second_cutoff,
                        SUM(payment_amount) AS total_collected
                    FROM ranked_payments
                    GROUP BY employee_id, loan_date";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':loan_date_month', $this->loan_date, PDO::PARAM_STR);
            $stmt->bindParam(':loan_date_year', $this->loan_date, PDO::PARAM_STR);
            $stmt->bindParam(':loan_type', $this->loan_type, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];       
            }
            $response['success'] = 1;
                    } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; 
                    }

        return $response;
    }

    public function checkIfUserExists(){

        $response = [];

        try {

            $sql = "SELECT 1 from employee_list where employee_id = :employee_id ";

            $stmt = $this->db ->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR); 
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if($stmt->rowCount() > 0){
                $response['exists'] = true;                 
            } else {
                $response['exists'] = false;       
            }

            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }


    public function checkEmployeePayDay(){

        $response = [];

        try {

            $sql = "SELECT cut_off FROM (
                        SELECT employee_id, client_name from employee_list a 
                        inner join taascor_client b on a.client_id = b.client_id
                        where employee_id = :employee_id 
                    ) as x inner join client_payday c on c.client_name = x.client_name";

            $stmt = $this->db ->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR); 
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if($stmt->rowCount() > 0){
                $response['exists'] = true;    
                $response['monthly'] = true;
                foreach ($data as $key => $row) {
                    if($row['cut_off'] == 'Weekly'){
                        $response['monthly'] = false;
                    }
                }             
            } else {
                $response['exists'] = false;       
            }

            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }


    public function searchEmployee(){

        $response = [];

        try {

            $sql = "SELECT concat(last_name,', ',first_name) as employee_full_name
                     from employee_list where employee_id = :employee_id ";

            $stmt = $this->db ->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR); 
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            $response['data'] = $data;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }

    public function addLoan(){

        $response = [];

        try {
            $sql = "INSERT INTO employee_loans (
                        employee_id,
                        employee_full_name,
                        loan_type,
                        loan_date,
                        start_payment_date,
                        loan_amount,
                        interest_amount,
                        monthly_amortization,
                        beginning_payment,
                        in_system_payment,
                        loan_running_balance,
                        stop_payment,
                        reactivation_date,
                        reactivation_remarks,
                        week1_amortization,
                        week2_amortization,
                        week3_amortization,
                        week4_amortization
                    ) VALUES (
                        :employee_id,
                        :employee_full_name,
                        :loan_type,
                        :loan_date,
                        :start_payment_date,
                        :loan_amount,
                        :interest_amount,
                        :monthly_amortization,
                        :beginning_payment,
                        :in_system_payment,
                        :loan_running_balance,
                        :stop_payment,
                        :reactivation_date,
                        :remarks,
                        :week1_amortization,
                        :week2_amortization,
                        :week3_amortization,
                        :week4_amortization
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':employee_full_name', $this->employee_full_name, PDO::PARAM_STR);
            $stmt->bindParam(':loan_type', $this->loan_type, PDO::PARAM_STR);
            $stmt->bindParam(':loan_date', $this->loan_date, PDO::PARAM_STR);
            $stmt->bindParam(':start_payment_date', $this->start_payment, PDO::PARAM_STR);
            $stmt->bindParam(':loan_amount', $this->loan_amount, PDO::PARAM_STR);
            $stmt->bindParam(':interest_amount', $this->interest_amount, PDO::PARAM_STR);
            $stmt->bindParam(':beginning_payment', $this->beginning_payment, PDO::PARAM_STR);
            $stmt->bindParam(':in_system_payment', $this->system_payment, PDO::PARAM_STR);
            $stmt->bindParam(':loan_running_balance', $this->loan_balance, PDO::PARAM_STR);
            $stmt->bindParam(':stop_payment', $this->stop_payment, PDO::PARAM_STR);
            $stmt->bindParam(':reactivation_date', $this->reactivation_date, PDO::PARAM_STR);
            $stmt->bindParam(':remarks', $this->remarks, PDO::PARAM_STR);
            $stmt->bindParam(':monthly_amortization', $this->monthly_amortization, PDO::PARAM_STR);
            $stmt->bindParam(':week1_amortization', $this->week1_amortization, PDO::PARAM_STR);
            $stmt->bindParam(':week2_amortization', $this->week2_amortization, PDO::PARAM_STR);
            $stmt->bindParam(':week3_amortization', $this->week3_amortization, PDO::PARAM_STR);
            $stmt->bindParam(':week4_amortization', $this->week4_amortization, PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }


    public function updateLoan(){

        $response = [];

        try {
            $sql = "UPDATE employee_loans SET
                        loan_type = :loan_type,
                        loan_date = :loan_date,
                        start_payment_date = :start_payment_date,
                        loan_amount = :loan_amount,
                        interest_amount = :interest_amount,
                        monthly_amortization = :monthly_amortization,
                        beginning_payment = :beginning_payment,
                        in_system_payment = :in_system_payment,
                        loan_running_balance = :loan_running_balance,
                        stop_payment = :stop_payment,
                        reactivation_date = :reactivation_date,
                        reactivation_remarks = :remarks,
                        week1_amortization = :week1_amortization,
                        week2_amortization = :week2_amortization,
                        week3_amortization = :week3_amortization,
                        week4_amortization = :week4_amortization
                    WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_STR);
            $stmt->bindParam(':loan_type', $this->loan_type, PDO::PARAM_STR);
            $stmt->bindParam(':loan_date', $this->loan_date, PDO::PARAM_STR);
            $stmt->bindParam(':start_payment_date', $this->start_payment, PDO::PARAM_STR);
            $stmt->bindParam(':loan_amount', $this->loan_amount, PDO::PARAM_STR);
            $stmt->bindParam(':interest_amount', $this->interest_amount, PDO::PARAM_STR);
            $stmt->bindParam(':monthly_amortization', $this->monthly_amortization, PDO::PARAM_STR);
            $stmt->bindParam(':beginning_payment', $this->beginning_payment, PDO::PARAM_STR);
            $stmt->bindParam(':in_system_payment', $this->system_payment, PDO::PARAM_STR);
            $stmt->bindParam(':loan_running_balance', $this->loan_balance, PDO::PARAM_STR);
            $stmt->bindParam(':stop_payment', $this->stop_payment, PDO::PARAM_INT);
            $stmt->bindParam(':reactivation_date', $this->reactivation_date, PDO::PARAM_STR);
            $stmt->bindParam(':remarks', $this->remarks, PDO::PARAM_STR);
            $stmt->bindParam(':week1_amortization', $this->week1_amortization, PDO::PARAM_STR);
            $stmt->bindParam(':week2_amortization', $this->week2_amortization, PDO::PARAM_STR);
            $stmt->bindParam(':week3_amortization', $this->week3_amortization, PDO::PARAM_STR);
            $stmt->bindParam(':week4_amortization', $this->week4_amortization, PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }


    public function getLoanType(){

        $response = [];

        try {
            $sql = "SELECT distinct loan_type from employee_loans
                    order by loan_type";

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

    
}


?>
