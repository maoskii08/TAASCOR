<?php

class PayDay
{
    public $db = null;

    public $id = null;
    public $client_name = null;
    public $cut_off = null;
    public $pay_day = null;
    

    public function getPayDayList(){

        $response = [];

        try {

            $sql = "SELECT id, client_name, cut_off, pay_day FROM client_payday order by client_name, pay_day";

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

    public function addPayDay(){

        $response = [];

        try {
            $shiftMonth = 0;
            if($this->pay_day == 5 || $this->pay_day == 7){
                $shiftMonth = 1;
            }
            $sql = "INSERT INTO client_payday(client_name, cut_off, pay_day, shift_month) values(:client_name, :cut_off, :pay_day, {$shiftMonth})";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client_name', $this->client_name, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_INT);
            $stmt->execute();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }

    public function updatePayDay(){

        $response = [];

        try {
            $shiftMonth = 0;
            if($this->pay_day == 5 || $this->pay_day == 7){
                $shiftMonth = 1;
            }

            $sql = "UPDATE client_payday set 
                        cut_off = :cut_off
                        ,pay_day = :pay_day
                        ,shift_month = {$shiftMonth}
                    where id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_INT);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_STR);
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
            $sql = "SELECT distinct client_name from taascor_client 
                    where client_id not in (202, 204, 203) and client_name <> 'No Client'
                    order by client_name";

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