<?php

class Department
{
    public $db = null;

    public $id = null;
    public $department_name = null;
    

    public function getDepartmentList(){

        $response = [];

        try {

            $sql = "SELECT department_id, department_name FROM taascor_department";

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

    public function addDepartment(){

        $response = [];

        try {

            $sql = "INSERT INTO taascor_department(department_name) values(:department_name)";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':department_name', $this->department_name, PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }

    public function updateDepartment(){

        $response = [];

        try {

            $sql = "UPDATE taascor_department set 
                        department_name = :department_name
                    where department_id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':department_name', $this->department_name, PDO::PARAM_STR);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }


    // public function deleteBranch(){

    //     $response = [];

    //     try {
    //         $sql = "DELETE FROM taascor_branch where branch_id = :id";

    //         $stmt = $this->db->prepare($sql);
    //         $stmt->bindParam(':id', $this->id, PDO::PARAM_INT); 
    //         $stmt->execute();

    //         $response['success'] = 1;
    //         
    //     } catch (\Throwable $th) {
    //         $response['success'] = 0;
    //         $response['error'] = "An error occurred. Please contact your administrator."; //dev
    //             //     }

    //     return $response;
    // }
    
}


?>