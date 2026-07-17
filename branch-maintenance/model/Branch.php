<?php

class Branch
{
    public $db = null;

    public $id = null;
    public $branch_name = null;
    

    public function getBranchList(){

        $response = [];

        try {

            $sql = "SELECT branch_id, branch_name FROM taascor_branch";

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

    public function addBranch(){

        $response = [];

        try {

            $sql = "INSERT INTO taascor_branch(branch_name) values(:branch_name)";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':branch_name', $this->branch_name, PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }

    public function updateBranch(){

        $response = [];

        try {

            $sql = "UPDATE taascor_branch set 
                        branch_name = :branch_name
                    where branch_id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':branch_name', $this->branch_name, PDO::PARAM_STR);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }


    public function deleteBranch(){

        $response = [];

        try {
            $sql = "DELETE FROM taascor_branch where branch_id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT); 
            $stmt->execute();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }
    
}


?>