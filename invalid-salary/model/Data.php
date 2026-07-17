<?php

class Data
{
    public $db = null;
    

    public function getIncompleteDetails(){

        $response = [];

        try {

            $sql = "SELECT a.employee_id, a.full_name, branch_name, client_name, location_name as client_location, daily_salary
                    FROM employee_list a
                    INNER JOIN employee_salary w on a.employee_id = w.employee_id
                    LEFT JOIN taascor_client b on a.client_id = b.client_id
                    LEFT JOIN taascor_branch c on a.branch_id = c.branch_id
                    left join taascor_client_location i on a.client_location_id = i.location_id
                    where (daily_salary < 100 OR daily_salary > 10000) and a.status = 'Active'
                    order by branch_name";

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
            $response['error'] = "An error occurred. Please contact your administrator."; 
                    }

        return $response;
    }

    
}


?>