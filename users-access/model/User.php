<?php

class User
{
    public $db = null;

    public $id = null;
    public $is_active = null;
    public $user_name = null;
    public $full_name = null;
    public $email = null;
    public $user_role = null;
    public $user_role_txt = null;
    public $client = null;

    public function getUserList(){

        $response = [];

        try {

            $sql = "SELECT id, employee_user_name, employee_full_name, access_level, access_description, 
                        employee_email, is_active, client
                            FROM taascor_user_access";

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

    public function getClientLocation(){

        $response = [];

        try {

            $sql = "SELECT client_id, client_name from taascor_client
                    where client_name <> 'No Client' order by client_name";

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


    public function updateUser(){

        $response = [];

        try {
            $activate = "";

            if($this->is_active == "0"){
                $activate = ",is_active = 1";
            }

            $sql = "UPDATE taascor_user_access set 
                        employee_full_name = :employee_full_name
                        ,access_level = :access_level
                        ,access_description = :access_description
                        ,employee_email = :employee_email 
                        ,client = :client 
                        $activate
                    where id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_full_name', $this->full_name, PDO::PARAM_STR);
            $stmt->bindParam(':access_level', $this->user_role, PDO::PARAM_INT);
            $stmt->bindParam(':access_description', $this->user_role_txt, PDO::PARAM_STR);
            $stmt->bindParam(':employee_email', $this->email, PDO::PARAM_STR);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_STR);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }


    public function deleteUser(){

        $response = [];

        try {
            $sql = "DELETE FROM taascor_user_access where id = :id";

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