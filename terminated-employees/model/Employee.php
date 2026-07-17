<?php

class Employee
{
    public $db = null;
    public $employee = null;
    public $client = null;
    public $branch = null;

    public $access_level = null;
    public $client_access = null;

    public function getEmployeeList(){

        $response = [];

        try {
            $where = "";
            if($this->employee != ''){
                $where .= "AND a.employee_id = '{$this->employee}' ";
            }

            if($this->client != 'null'){
                $where .= "AND client_name = '{$this->client}' ";
            }

            if($this->branch != 'null'){
                $where .= "AND a.branch_id = {$this->branch} ";
            }

            if($this->access_level != "1"){
                $where .= " AND e.client_name not in ('Taasc', 'Taasc - Parian', 'Taascor', 'Taascor-Foph')";
            }

            if($this->access_level == "4"){
                $where .= " AND a.client_id in ({$this->client_access})";
            }

            $sql = "SELECT a.employee_id
                            ,annual_leaves
                            ,first_name
                            ,last_name
                            ,DATE_FORMAT(hire_date, '%m/%d/%Y') as hire_date
                            ,middle_name
                            ,old_employee_id
                            ,DATE_FORMAT(separation_date, '%m/%d/%Y') as separation_date
                            ,DATE_FORMAT(birthday, '%m/%d/%Y') as birthday
                            ,birth_place
                            ,civil_status
                            ,contact_number
                            ,email_address
                            ,emergency_person
                            ,emergency_contact_number
                            ,gender
                            ,insurance
                            ,nationality
                            ,permanent_address
                            ,present_address
                            ,pag_ibig_number 
                            ,philhealth_number
                            ,sss_number
                            ,tin_number
                            ,atm_number
                            ,bank_name
                            ,daily_salary 
                            ,branch_name
                            ,client_name
                            ,position_name
                            ,department_name
                            ,a.branch_id
                            ,a.client_id
                            ,a.position_id
                            ,a.department_id
                            ,a.client_location_id
                            ,payroll_employee_id
                            ,full_name
                            ,employee_type
                            ,location_name as client_location
                            ,pay_type
                            ,DATE_FORMAT(client_date, '%m/%d/%Y') as client_date
                            FROM employee_list a 
                        inner join employee_details b on a.employee_id = b.employee_id
                        inner join employee_govt_account c on a.employee_id = c.employee_id
                        inner join employee_salary d on a.employee_id = d.employee_id
                        left join taascor_client e on a.client_id = e.client_id
                        left join taascor_branch f on a.branch_id = f.branch_id
                        left join taascor_department g on a.department_id = g.department_id
                        left join taascor_position h on a.position_id = h.position_id
                        left join taascor_client_location i on a.client_location_id = i.location_id
                        where status = 'Terminated' $where
                    ";

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


    
    public function restoreEmployee(){

        $response = [];

        try {
            $sql = "UPDATE employee_list set 
                        status = 'Active'
                    where employee_id = :employee";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee', $this->employee, PDO::PARAM_STR); 
            $stmt->execute();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }


    public function getClientFilter(){

        $response = [];

        try {
            $where = "";

            if($this->access_level != "1"){
                $where = "WHERE client_name not in ('No Client', 'Taasc', 'Taascor') ";
                if($this->access_level == "4"){
                    $where .= " AND client_id in ({$this->client_access})";
                }
            }else{
                $where = "WHERE client_name <> 'No Client' ";
            }

            $sql = "SELECT distinct client_name from taascor_client
                    $where order by client_name";

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

    public function getBranchFilter(){

        $response = [];

        try {

            $sql = "SELECT branch_id, branch_name from taascor_branch
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
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }    

    public function getClientBranch(){

        $response = [];

        try {
            $where = "";
            
            if($this->access_level != "1"){
                $where = " AND client_name not in ('No Client', 'Taasc', 'Taascor') ";
                if($this->access_level == "4"){
                    $where .= " AND a.client_id in ({$this->client_access})";
                }
            }else{
                $where = " AND client_name <> 'No Client' ";
            }

            $sql = "SELECT distinct client_name from employee_list a 
                    inner join taascor_client b on a.client_id = b.client_id
                    where branch_id = :branch_id $where
                    order by client_name";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':branch_id', $this->branch, PDO::PARAM_INT); 
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