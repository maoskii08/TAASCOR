<?php

class Position
{
    public $db = null;

    public $id = null;
    public $position_name = null;
    

    public function getPositionList(){

        $response = [];

        try {

            $sql = "SELECT position_id, position_name FROM taascor_position";

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

    public function addPosition(){

        $response = [];

        try {

            $sql = "INSERT INTO taascor_position(position_name) values(:position_name)";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':position_name', $this->position_name, PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }

    public function updatePosition(){

        $response = [];

        try {

            $sql = "UPDATE taascor_position set 
                        position_name = :position_name
                    where position_id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':position_name', $this->position_name, PDO::PARAM_STR);
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