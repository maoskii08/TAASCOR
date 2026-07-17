<?php

class Client
{
    public $db = null;

    public $id = null;
    public $client_location = null;
    

    public function getClientList(){

        $response = [];

        try {

            $sql = "SELECT location_id, location_name FROM taascor_client_location";

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

    public function addClientLocation(){

        $response = [];

        try {

            $sql = "INSERT INTO taascor_client_location(location_name) values(:client_location)";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client_location', $this->client_location, PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }

    public function updateClientLocation(){

        $response = [];

        try {

            $sql = "UPDATE taascor_client_location set 
                        location_name = :client_location
                    where location_id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client_location', $this->client_location, PDO::PARAM_STR);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_STR);
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