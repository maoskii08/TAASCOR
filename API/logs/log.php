<?php

class Logs
{
    public $db = null;

    public $username = null;
    public $log_action = null;

    public function insertLog(){
        $response = false;
        $date_now = date("Y-m-d H:i:s");
        try {

            $sql = "INSERT INTO logs
                            (
                                username, log_action, inserted_date_time_ph
                            )
                        VALUES   
                            (
                                :username, :log_action, :inserted_date_time_ph
                            )";          
            
            $stmt = $this -> db -> prepare($sql);
            
            $stmt->bindParam(':username', $this->username, PDO::PARAM_STR);
            $stmt->bindParam(':log_action', $this->log_action, PDO::PARAM_STR);
            $stmt->bindParam(':inserted_date_time_ph', $date_now, PDO::PARAM_STR);
            $stmt->execute();

            $response = true;

        } catch (\Throwable $th) {
            error_log('Logs::insertLog failed: ' . $th->getMessage());
            $response = array(
                'success' => 0,
                'message' => 'Unable to save log entry.'
            );
        }

        return $response;
    }

}

?>
