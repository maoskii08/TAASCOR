<?php

class Data
{
    public $db = null;
    

    public function getIncompleteDetails(){

        $response = [];

        try {

            $sql = "WITH duplicate_pagibig AS (
                        SELECT 
                            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(pag_ibig_number, '-', ''), ' ', ''), '.', ''), '(', ''), ')', ''), '/', '') AS cleaned_pagibig,
                            COUNT(*) AS duplicate_count
                        FROM employee_govt_account w
                        INNER JOIN employee_list a ON a.employee_id = w.employee_id
                        WHERE w.pag_ibig_number IS NOT NULL 
                            AND TRIM(w.pag_ibig_number) <> '' 
                            AND a.status = 'Active'  
                        GROUP BY cleaned_pagibig
                        HAVING COUNT(*) > 1
                    )
                    SELECT 
                        a.employee_id, 
                        a.full_name, 
                        c.branch_name, 
                        b.client_name, 
                        location_name as client_location, 
                        w.pag_ibig_number,
                        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(w.pag_ibig_number, '-', ''), ' ', ''), '.', ''), '(', ''), ')', ''), '/', '') AS cleaned_pagibig
                    FROM employee_list a
                    INNER JOIN employee_govt_account w ON a.employee_id = w.employee_id
                    LEFT JOIN taascor_client b ON a.client_id = b.client_id
                    LEFT JOIN taascor_branch c ON a.branch_id = c.branch_id
                    left join taascor_client_location i on a.client_location_id = i.location_id
                    INNER JOIN duplicate_pagibig d ON d.cleaned_pagibig = 
                        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(w.pag_ibig_number, '-', ''), ' ', ''), '.', ''), '(', ''), ')', ''), '/', '')
                    where a.status = 'Active'
                    ORDER BY c.branch_name, a.full_name;";

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