<?php

class Dashboard
{
    public $db = null;

    public $year = null;
    public $branch = null;


    public function getNewHires(){

        $response = [];

        try {

            $sql = "SELECT count(employee_id) as cnt from employee_list 
                    where MONTH(hire_date) = MONTH(curdate()) AND YEAR(hire_date) = YEAR(curdate())
                    and status = 'Active'";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                foreach ($data as $row) {
                    $response['data']['new_hire'] = $row['cnt'];
                }      
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
    

    public function getMaleFemale(){

        $response = [];

        try {

            $sql = "SELECT count(a.employee_id) as cnt, lower(gender) as gender from employee_details a 
                    inner join employee_list b on a.employee_id = b.employee_id 
                    where gender is not null and trim(gender) <> '' and status = 'Active' 
                    group by gender";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                foreach ($data as $row) {
                    $response['data'][$row['gender']] = $row['cnt'];
                }          
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


    public function getCivilStatus(){

        $response = [];

        try {

            $sql = "SELECT count(a.employee_id) as cnt, civil_status 
                    from employee_details a 
                    inner join employee_list b on a.employee_id = b.employee_id 
                        where civil_status in ('Single', 'Married', 'Separated', 'Widow')
                        and status = 'Active'
                    group by civil_status";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                foreach ($data as $row) {
                    $response['data']['labels'][] = $row['civil_status'];  
                    $response['data']['series'][] = (int) $row['cnt']; 
                }          
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

    public function getQuantities(){

        $response = [];

        try {

            $sql = "SELECT 'Employees' AS category, COUNT(employee_id) AS total FROM employee_list 
                    where status = 'Active' and client_id 
                    UNION ALL
                    SELECT 'Clients' AS category, COUNT(DISTINCT client_name) AS total FROM taascor_client
                    where client_name <> 'No Client'
                    UNION ALL
                    SELECT 'Branches' AS category, COUNT(branch_id) AS total FROM taascor_branch
                    UNION ALL
                    SELECT 'Locations' AS category, COUNT(DISTINCT location_id) AS total FROM taascor_client_location";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                foreach ($data as $row) {
                    $response['data'][$row['category']][] = $row['total'];  
                }          
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

    public function getBranchEmployees(){

        $response = [];

        try {

            $sql = "SELECT count(employee_id) as cnt, branch_name
                    from employee_list a inner join taascor_branch b 
                    on a.branch_id = b.branch_id 
                    where status = 'Active' 
                    group by branch_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                foreach ($data as $row) {
                    $response['data']['labels'][] = $row['branch_name'];  
                    $response['data']['series'][] = (int) $row['cnt']; 
                }          
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

    public function getEmployeeType(){

        $response = [];

        try {

            $sql = "SELECT count(employee_id) as cnt, employee_type
                        from employee_list 
                    where employee_type in ('Seasonal', 'Long Term') 
                    and status = 'Active' group by employee_type";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                foreach ($data as $row) {
                    $response['data']['labels'][] = $row['employee_type'];  
                    $response['data']['series'][] = (int) $row['cnt']; 
                }          
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


    public function getClientEmployees(){

        $response = [];

        try {

            $sql = "SELECT count(a.employee_id) as cnt,
                           b.client_name,
                           COALESCE(b.fd_canonical, '') AS fd_canonical
                    FROM employee_list a
                    INNER JOIN taascor_client b ON a.client_id = b.client_id
                    WHERE b.client_name <> 'No Client' AND a.status = 'Active'
                    GROUP BY b.client_id, b.client_name, b.fd_canonical
                    ORDER BY b.client_name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if($stmt->rowCount() > 0){
                foreach ($data as $row) {
                    $response['data']['categories'][] = $row['client_name'];
                    $response['data']['series'][]     = (int) $row['cnt'];
                    $response['data']['canonical'][]  = $row['fd_canonical'];
                }
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

    public function getAgeBracket(){

        $response = [];

        try {

            $sql = "SELECT 
                        CASE 
                            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 18 AND 25 THEN 'Age 18-25'
                            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 26 AND 35 THEN 'Age 26-35'
                            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 36 AND 45 THEN 'Age 36-45'
                            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 46 AND 55 THEN 'Age 46-55'
                            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 56 AND 65 THEN 'Age 56-65'
                            WHEN TIMESTAMPDIFF(YEAR, birthday, CURDATE()) BETWEEN 66 AND 75 THEN 'Age 66-75'
                            ELSE 'Other' 
                        END AS age_bracket, 
                        COUNT(a.employee_id) AS total
                    FROM employee_details a 
                    inner join employee_list b on a.employee_id = b.employee_id 
                    where birthday is not null and birthday <> '0000-00-00' and status = 'Active'
                    GROUP BY age_bracket
                    ORDER BY age_bracket";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                foreach ($data as $row) {
                    $response['data']['categories'][] = $row['age_bracket'];  
                    $response['data']['series'][] = (int) $row['total']; 
                }          
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


    public function getLocationEmployees(){

        $response = [];

        try {

            $sql = "SELECT count(employee_id) as cnt, location_name
                    from employee_list a inner join taascor_client_location b 
                    on a.client_location_id = b.location_id where client_location_id is not null
                    and status = 'Active' 
                    group by location_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                foreach ($data as $row) {
                    $response['data']['categories'][] = $row['location_name'];  
                    $response['data']['series'][] = (int) $row['cnt']; 
                }          
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

    public function getMonthlyHires(){

        $response = [];

        try {
            $where = "";
            if($this->year != 'null'){
                $where .= " AND YEAR(hire_date) = '{$this->year}'";
            }else{
                $where .= " AND YEAR(hire_date) = YEAR(CURDATE())";
            }

            if($this->branch != 'null'){
                $where .= " AND branch_id = {$this->branch}";
            }

            $sql = "SELECT DATE_FORMAT(hire_date, '%Y-%b') as mnth, count(employee_id) as cnt from employee_list 
                        where hire_date is not null and hire_date <> '0000-00-00' and status = 'Active' 
                        $where
                    group by DATE_FORMAT(hire_date, '%Y-%b') order by hire_date";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                foreach ($data as $row) {
                    $response['data']['categories'][] = $row['mnth'];  
                    $response['data']['series'][] = (int) $row['cnt']; 
                }          
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

    public function getYearlyHires(){

        $response = [];

        try {
            $where = "";
            if($this->branch != 'null'){
                $where .= " AND branch_id = {$this->branch}";
            }

            $sql = "SELECT YEAR(hire_date) as yr, count(employee_id) as cnt from employee_list 
                        where hire_date is not null and hire_date <> '0000-00-00' and status = 'Active'
                        $where
                    group by YEAR(hire_date) order by hire_date";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                foreach ($data as $row) {
                    $response['data']['categories'][] = $row['yr'];  
                    $response['data']['series'][] = (int) $row['cnt']; 
                }          
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

    public function getYearFilter(){

        $response = [];

        try {
            $sql = "SELECT distinct YEAR(hire_date) as yr from employee_list 
                        where hire_date is not null and hire_date <> '0000-00-00' and status = 'Active'
                    group by yr order by yr desc";

            $stmt = $this->db->prepare($sql);
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

    public function getBranchFilter(){

        $response = [];

        try {
            $sql = "SELECT distinct branch_id, branch_name from taascor_branch 
                    group by branch_id, branch_name order by branch_name";

            $stmt = $this->db->prepare($sql);
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
    
}


?>