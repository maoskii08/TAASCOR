<?php

class Restriction
{
    public $db = null;
    public $user_name = '';
    
    private $database = 'dbo';


    public function getAccess(){

        $response = [];

        try {

            $sql = "SELECT access_level from taascor_user_access 
                    WHERE employee_user_name = :user_name";

            $stmt = $this->db ->prepare($sql);
            $stmt->bindParam(':user_name', $this->user_name, PDO::PARAM_STR); 
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($data as $key => $row) {
                $response['access_level'] = $row['access_level'];
            }  

        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }


}


?>