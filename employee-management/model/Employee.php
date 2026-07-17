<?php

class Employee
{
    public $db = null;
    public $employee = null;
    public $client = null;
    public $branch = null;

    public $employee_ident = null;
    public $old_employee_ident = null;
    public $last_name = null;
    public $first_name = null;
    public $middle_name = null;
    public $hire_date = null;
    public $present_address = null;
    public $permanent_address = null;
    public $contact_number = null;
    public $email_address = null;
    public $birthday = null;
    public $birth_place = null;
    public $gender = null;
    public $civil_status = null;
    public $nationality = null;
    public $emergency_person = null;
    public $emergency_contact_number = null;
    public $department = null;
    public $position = null;
    public $insurance = null;
    public $tin = null;
    public $sss = null;
    public $pag_ibig = null;
    public $philhealth = null;
    public $daily_salary = null;
    public $bank_name = null;
    public $bank_account_number = null;
    public $annual_leaves = null;

    public $payroll_employee_ident = null;
    public $full_name = null;
    public $employee_type = null;
    public $client_location = null;

    public $access_level = null;
    public $client_access = null;

    public $termination_date = null;

    public $employee_id_array = array();

    private function normalizeUniqueValue($value){
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string) $value)));
    }

    private function getActiveIdentifierDuplicates($excludeEmployeeId = null){
        $duplicates = [];
        $incoming = [
            'TIN' => $this->normalizeUniqueValue($this->tin),
            'SSS' => $this->normalizeUniqueValue($this->sss),
            'PhilHealth' => $this->normalizeUniqueValue($this->philhealth),
            'Pag-IBIG' => $this->normalizeUniqueValue($this->pag_ibig),
        ];

        $where = "WHERE a.status = 'Active'";
        $params = [];
        if($excludeEmployeeId !== null){
            $where .= " AND a.employee_id <> :employee_id";
            $params[':employee_id'] = $excludeEmployeeId;
        }

        $sql = "SELECT
                    b.tin_number,
                    b.sss_number,
                    b.philhealth_number,
                    b.pag_ibig_number
                FROM employee_list a
                LEFT JOIN employee_govt_account b ON a.employee_id = b.employee_id
                $where";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $existing = [
                'TIN' => $this->normalizeUniqueValue($row['tin_number']),
                'SSS' => $this->normalizeUniqueValue($row['sss_number']),
                'PhilHealth' => $this->normalizeUniqueValue($row['philhealth_number']),
                'Pag-IBIG' => $this->normalizeUniqueValue($row['pag_ibig_number']),
            ];

            foreach($incoming as $label => $value){
                if($value !== '' && $value === $existing[$label]){
                    $duplicates[$label] = "$label already belongs to another active employee";
                }
            }
        }

        return array_values($duplicates);
    }

    public function getEmployeeList(){

        $response = [];

        try {
            $where = "";
            if($this->employee != ''){
                $where .= " AND a.employee_id = '{$this->employee}' ";
            }

            if($this->client != 'null'){
                $where .= " AND client_name = '{$this->client}' ";
            }

            if($this->client_location != 'null'){
                $where .= " AND location_name = '{$this->client_location}' ";
            }

            if($this->branch != 'null'){
                $where .= " AND a.branch_id = {$this->branch} ";
            }

            if($this->access_level != "1"){
                $where .= " AND e.client_name not in ('Taasc', 'Taascor')";
            }

            if($this->access_level == "4"){
                $where .= " AND a.client_id in ({$this->client_access})";
            }

            $sql = "SELECT a.employee_id
                            ,annual_leaves
                            ,first_name
                            ,last_name
                            ,ifnull(DATE_FORMAT(hire_date, '%m/%d/%Y'),'MM/DD/YYYY') as hire_date
                            ,middle_name
                            ,old_employee_id
                            ,separation_date
                            ,ifnull(DATE_FORMAT(birthday, '%m/%d/%Y'),'MM/DD/YYYY') as birthday
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
                            ,ifnull(DATE_FORMAT(client_date, '%m/%d/%Y'),'MM/DD/YYYY') as client_date
                            ,pay_type
                            FROM employee_list a 
                        inner join employee_details b on a.employee_id = b.employee_id
                        inner join employee_govt_account c on a.employee_id = c.employee_id
                        inner join employee_salary d on a.employee_id = d.employee_id
                        left join taascor_client e on a.client_id = e.client_id
                        left join taascor_branch f on a.branch_id = f.branch_id
                        left join taascor_department g on a.department_id = g.department_id
                        left join taascor_position h on a.position_id = h.position_id
                        left join taascor_client_location i on a.client_location_id = i.location_id
                        where status = 'Active' $where
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


    public function validate(){

        $response = [];

        try {
            $dup = [];

            $sql = "SELECT 1 FROM employee_list 
                        WHERE first_name = :first_name 
                        and middle_name = :middle_name 
                        and last_name = :last_name
                        and status = 'Active'";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':first_name', $this->first_name, PDO::PARAM_STR);
            $stmt->bindParam(':middle_name', $this->middle_name, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $this->last_name, PDO::PARAM_STR);
            $stmt->execute();

            if($stmt->rowCount() > 0){
                $dup [] = "Employee Already Exists";       
            } 

            $dup = array_merge($dup, $this->getActiveIdentifierDuplicates());

            if(count($dup) == 0){
                $response['success'] = 1;
            }else{
                $response['success'] = 2;
                $response['dup'] = $dup;
            }

                    } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; 
                    }

        return $response;
    }


    public function validateUpdate(){

        $response = [];

        try {
            $dup = [];

            $sql = "SELECT 1 FROM employee_list 
                        WHERE first_name = :first_name 
                        and middle_name = :middle_name 
                        and last_name = :last_name
                        and employee_id <> :employee_id
                        and status = 'Active'";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':first_name', $this->first_name, PDO::PARAM_STR);
            $stmt->bindParam(':middle_name', $this->middle_name, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $this->last_name, PDO::PARAM_STR);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->execute();

            if($stmt->rowCount() > 0){
                $dup [] = "Employee Already Exists";       
            } 

            $dup = array_merge($dup, $this->getActiveIdentifierDuplicates($this->employee_ident));

            if(count($dup) == 0){
                $response['success'] = 1;
            }else{
                $response['success'] = 2;
                $response['dup'] = $dup;
            }

                    } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; 
                    }

        return $response;
    }

    public function deleteEmployees()
    {
        $response = [];

        try {
            $this->db->beginTransaction();
            $placeholders = str_repeat('?,', count($this->employee_id_array) - 1) . '?';
            $sql = "DELETE FROM employee_details
                    WHERE employee_id in ($placeholders)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($this->employee_id_array);

            $sql = "DELETE FROM employee_salary
                    WHERE employee_id in ($placeholders)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($this->employee_id_array);

            $sql = "DELETE FROM employee_govt_account
                    WHERE employee_id in ($placeholders)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($this->employee_id_array);

            $sql = "DELETE FROM employee_list
                    WHERE employee_id in ($placeholders)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($this->employee_id_array);

            $response['success'] = 1;
                        $this->db->commit(); 
        } catch (\Throwable $th) {
            $this->db->rollBack();
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }   


    public function updateEmployee(){

        $response = [];

        try {
            $this->db->beginTransaction();

            $sql = "UPDATE employee_list set 
                        annual_leaves = :annual_leaves
                        ,branch_id = (SELECT branch_id FROM taascor_branch where branch_name = :branch_name)
                        ,client_id = (SELECT client_id FROM taascor_client where client_name = :client_name)
                        ,department_id = (SELECT department_id FROM taascor_department where department_name = :department_name)
                        ,first_name = :first_name 
                        ,hire_date = :hire_date
                        ,last_name = :last_name
                        ,middle_name = :middle_name
                        ,old_employee_id = :old_employee_id
                        ,position_id = (SELECT position_id FROM taascor_position where position_name = :position_name)
                        ,payroll_employee_id = :payroll_employee_id
                        ,full_name = :full_name
                        ,employee_type = :employee_type
                        ,client_date = :client_date
                        ,client_location_id = (SELECT location_id FROM taascor_client_location where location_name = :client_location)
                    where employee_id = :employee_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':annual_leaves', $this->annual_leaves, PDO::PARAM_STR);
            $stmt->bindParam(':branch_name', $this->branch, PDO::PARAM_STR);
            $stmt->bindParam(':client_name', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':client_location', $this->client_location, PDO::PARAM_STR);
            $stmt->bindParam(':department_name', $this->department, PDO::PARAM_STR);
            $stmt->bindParam(':first_name', $this->first_name, PDO::PARAM_STR);
            $stmt->bindParam(':hire_date', $this->hire_date, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $this->last_name, PDO::PARAM_STR);
            $stmt->bindParam(':middle_name', $this->middle_name, PDO::PARAM_STR);
            $stmt->bindParam(':old_employee_id', $this->old_employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':position_name', $this->position, PDO::PARAM_STR);
            $stmt->bindParam(':payroll_employee_id', $this->payroll_employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':full_name', $this->full_name, PDO::PARAM_STR);
            $stmt->bindParam(':employee_type', $this->employee_type, PDO::PARAM_STR);
            $stmt->bindParam(':client_date', $this->client_date, PDO::PARAM_STR);
            $stmt->execute();


            $sql = "UPDATE employee_details set 
                        birthday = :birthday
                        ,birth_place = :birth_place
                        ,civil_status = :civil_status
                        ,contact_number = :contact_number 
                        ,email_address = :email_address
                        ,emergency_contact_number = :emergency_contact_number
                        ,emergency_person = :emergency_person
                        ,gender = :gender
                        ,insurance = :insurance
                        ,nationality = :nationality
                        ,permanent_address = :permanent_address
                        ,present_address = :present_address
                    where employee_id = :employee_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':birthday', $this->birthday, PDO::PARAM_STR);
            $stmt->bindParam(':birth_place', $this->birth_place, PDO::PARAM_STR);
            $stmt->bindParam(':civil_status', $this->civil_status, PDO::PARAM_STR);
            $stmt->bindParam(':contact_number', $this->contact_number, PDO::PARAM_STR);
            $stmt->bindParam(':email_address', $this->email_address, PDO::PARAM_STR);
            $stmt->bindParam(':emergency_contact_number', $this->emergency_contact_number, PDO::PARAM_STR);
            $stmt->bindParam(':emergency_person', $this->emergency_person, PDO::PARAM_STR);
            $stmt->bindParam(':gender', $this->gender, PDO::PARAM_STR);
            $stmt->bindParam(':insurance', $this->insurance, PDO::PARAM_STR);
            $stmt->bindParam(':nationality', $this->nationality, PDO::PARAM_STR);
            $stmt->bindParam(':permanent_address', $this->permanent_address, PDO::PARAM_STR);
            $stmt->bindParam(':present_address', $this->present_address, PDO::PARAM_STR);
            $stmt->execute();


            $sql = "UPDATE employee_govt_account set 
                        pag_ibig_number = :pag_ibig
                        ,philhealth_number = :philhealth
                        ,sss_number = :sss
                        ,tin_number = :tin 
                    where employee_id = :employee_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':pag_ibig', $this->pag_ibig, PDO::PARAM_STR);
            $stmt->bindParam(':philhealth', $this->philhealth, PDO::PARAM_STR);
            $stmt->bindParam(':sss', $this->sss, PDO::PARAM_STR);
            $stmt->bindParam(':tin', $this->tin, PDO::PARAM_STR);
            $stmt->execute();


            $sql = "UPDATE employee_salary set 
                        atm_number = :bank_account_number
                        ,bank_name = :bank_name
                        ,daily_salary = :daily_salary
                        ,pay_type = :pay_type
                    where employee_id = :employee_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':bank_account_number', $this->bank_account_number, PDO::PARAM_STR);
            $stmt->bindParam(':bank_name', $this->bank_name, PDO::PARAM_STR);
            $stmt->bindParam(':daily_salary', $this->daily_salary, PDO::PARAM_STR);
            $stmt->bindParam(':pay_type', $this->pay_type, PDO::PARAM_STR);
            $stmt->execute();



            $this->db->commit();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $this->db->rollBack();
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }



    public function addEmployee(){

        $response = [];

        try {
            $this->db->beginTransaction();

            $sql = "SELECT COALESCE(MAX(employee_id), 1000) as employee_id FROM employee_list";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($data as $key => $row) {
                $this->employee_ident = $row['employee_id'] + 1;
            }

            $sql = "INSERT INTO employee_list (
                        employee_id, annual_leaves, branch_id, client_id, department_id, 
                        first_name, hire_date, last_name, middle_name, old_employee_id, 
                        position_id, payroll_employee_id, full_name, employee_type
                        ,status, client_date, client_location_id
                    ) VALUES (
                        :employee_id, :annual_leaves 
                        ,(SELECT branch_id FROM taascor_branch where branch_name = :branch_name)
                        ,(SELECT client_id FROM taascor_client where client_name = :client_name)
                        ,(SELECT department_id FROM taascor_department where department_name = :department_name)
                        ,:first_name, :hire_date, :last_name, :middle_name, :old_employee_id 
                        ,(SELECT position_id FROM taascor_position where position_name = :position_name)
                        ,:payroll_employee_id, :full_name, :employee_type
                        ,'Active', :client_date
                        ,(SELECT location_id FROM taascor_client_location where location_name = :client_location) 
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':annual_leaves', $this->annual_leaves, PDO::PARAM_STR);
            $stmt->bindParam(':branch_name', $this->branch, PDO::PARAM_STR);
            $stmt->bindParam(':client_name', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':client_location', $this->client_location, PDO::PARAM_STR);
            $stmt->bindParam(':department_name', $this->department, PDO::PARAM_STR);
            $stmt->bindParam(':first_name', $this->first_name, PDO::PARAM_STR);
            $stmt->bindParam(':hire_date', $this->hire_date, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $this->last_name, PDO::PARAM_STR);
            $stmt->bindParam(':middle_name', $this->middle_name, PDO::PARAM_STR);
            $stmt->bindParam(':old_employee_id', $this->old_employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':position_name', $this->position, PDO::PARAM_STR);
            $stmt->bindParam(':payroll_employee_id', $this->payroll_employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':full_name', $this->full_name, PDO::PARAM_STR);
            $stmt->bindParam(':employee_type', $this->employee_type, PDO::PARAM_STR);
            $stmt->bindParam(':client_date', $this->client_date, PDO::PARAM_STR);
            $stmt->execute();
            
            // Insert into employee_details
            $sql = "INSERT INTO employee_details (
                        employee_id, birthday, birth_place, civil_status, contact_number, 
                        email_address, emergency_contact_number, emergency_person, gender, 
                        insurance, nationality, permanent_address, present_address
                    ) VALUES (
                        :employee_id, :birthday, :birth_place, :civil_status, :contact_number, 
                        :email_address, :emergency_contact_number, :emergency_person, :gender, 
                        :insurance, :nationality, :permanent_address, :present_address
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':birthday', $this->birthday, PDO::PARAM_STR);
            $stmt->bindParam(':birth_place', $this->birth_place, PDO::PARAM_STR);
            $stmt->bindParam(':civil_status', $this->civil_status, PDO::PARAM_STR);
            $stmt->bindParam(':contact_number', $this->contact_number, PDO::PARAM_STR);
            $stmt->bindParam(':email_address', $this->email_address, PDO::PARAM_STR);
            $stmt->bindParam(':emergency_contact_number', $this->emergency_contact_number, PDO::PARAM_STR);
            $stmt->bindParam(':emergency_person', $this->emergency_person, PDO::PARAM_STR);
            $stmt->bindParam(':gender', $this->gender, PDO::PARAM_STR);
            $stmt->bindParam(':insurance', $this->insurance, PDO::PARAM_STR);
            $stmt->bindParam(':nationality', $this->nationality, PDO::PARAM_STR);
            $stmt->bindParam(':permanent_address', $this->permanent_address, PDO::PARAM_STR);
            $stmt->bindParam(':present_address', $this->present_address, PDO::PARAM_STR);
            $stmt->execute();
            
            // Insert into employee_govt_account
            $sql = "INSERT INTO employee_govt_account (
                        employee_id, pag_ibig_number, philhealth_number, sss_number, tin_number
                    ) VALUES (
                        :employee_id, :pag_ibig, :philhealth, :sss, :tin
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':pag_ibig', $this->pag_ibig, PDO::PARAM_STR);
            $stmt->bindParam(':philhealth', $this->philhealth, PDO::PARAM_STR);
            $stmt->bindParam(':sss', $this->sss, PDO::PARAM_STR);
            $stmt->bindParam(':tin', $this->tin, PDO::PARAM_STR);
            $stmt->execute();
            
            // Insert into employee_salary
            $sql = "INSERT INTO employee_salary (
                        employee_id, atm_number, bank_name, daily_salary, pay_type
                    ) VALUES (
                        :employee_id, :bank_account_number, :bank_name, :daily_salary, :pay_type
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':bank_account_number', $this->bank_account_number, PDO::PARAM_STR);
            $stmt->bindParam(':bank_name', $this->bank_name, PDO::PARAM_STR);
            $stmt->bindParam(':daily_salary', $this->daily_salary, PDO::PARAM_STR);
            $stmt->bindParam(':pay_type', $this->pay_type, PDO::PARAM_STR);
            $stmt->execute();




            $this->db->commit();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $this->db->rollBack();
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }

    public function terminateEmployee(){

        $response = [];

        try {
            $date_now = date("Y-m-d");

            $sql = "UPDATE employee_list set 
                        status = 'Terminated'
                        ,separation_date = :termination_date
                    where employee_id = :employee";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee', $this->employee, PDO::PARAM_STR); 
            $stmt->bindParam(':termination_date', $this->termination_date, PDO::PARAM_STR); 
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


    public function getClientLocation(){

        $response = [];

        try {

            $sql = "SELECT distinct location_name from taascor_client_location order by location_name";

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
                    where branch_id = :branch_id 
                    $where
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

    public function getDepartmentFilter(){

        $response = [];

        try {

            $sql = "SELECT department_id, department_name from taascor_department
                    order by department_name";

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

    public function getPositionFilter(){

        $response = [];

        try {

            $sql = "SELECT position_id, position_name from taascor_position
                    order by position_name";

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
