<?php

class Data
{
    public $db = null;
    

    public function getIncompleteDetails(){

        $response = [];

        try {

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
                            ,payroll_employee_id
                            ,full_name
                            ,employee_type
                            ,location_name as client_location
                            FROM employee_list a 
                        inner join employee_details b on a.employee_id = b.employee_id
                        inner join employee_govt_account c on a.employee_id = c.employee_id
                        inner join employee_salary d on a.employee_id = d.employee_id
                        left join taascor_client e on a.client_id = e.client_id
                        left join taascor_branch f on a.branch_id = f.branch_id
                        left join taascor_department g on a.department_id = g.department_id
                        left join taascor_position h on a.position_id = h.position_id
                        left join taascor_client_location i on a.client_location_id = i.location_id
                        where status = 'Active'AND (
                            (employee_type <> 'Seasonal' OR employee_type IS NULL)
                          AND (
                              TRIM(full_name) = '' OR full_name IS NULL OR
                              TRIM(last_name) = '' OR last_name IS NULL OR
                              TRIM(first_name) = '' OR first_name IS NULL OR
                              TRIM(sss_number) = '' OR sss_number IS NULL OR
                              TRIM(philhealth_number) = '' OR philhealth_number IS NULL OR
                              TRIM(pag_ibig_number) = '' OR pag_ibig_number IS NULL OR
                              TRIM(tin_number) = '' OR tin_number IS NULL OR
                              TRIM(atm_number) = '' OR atm_number IS NULL OR
                              TRIM(bank_name) = '' OR bank_name IS NULL OR
                              TRIM(birthday) = '' OR birthday IS NULL OR
                              TRIM(hire_date) = '' OR hire_date IS NULL OR
                              TRIM(client_name) = '' OR client_name IS NULL OR
                              TRIM(branch_name) = '' OR branch_name IS NULL OR
                              TRIM(location_name) = '' OR location_name IS NULL OR
                              TRIM(position_name) = '' OR position_name IS NULL OR
                              TRIM(present_address) = '' OR present_address IS NULL OR
                              TRIM(contact_number) = '' OR contact_number IS NULL OR
                              TRIM(birth_place) = '' OR birth_place IS NULL OR
                              TRIM(gender) = '' OR gender IS NULL OR
                              TRIM(civil_status) = '' OR civil_status IS NULL OR
                              daily_salary IS NULL OR
                              annual_leaves IS NULL OR
                              TRIM(employee_type) = '' OR employee_type IS NULL
                              OR birthday = '0000-00-00' OR hire_date = '0000-00-00'
                              )
                        )
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