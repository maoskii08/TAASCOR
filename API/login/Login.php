<?php

class LoginClass {
    public $db = null;
    public $userNT = '';
    public $password = '';

    public $username = null;
    public $firstname = null;
    public $lastname = null;
    public $email = null;
    public $access_level = null;
    public $access_description = null;
    public $client = null;

    public function login(){
         
        $userInfos = [];
        $login = false;
       
        if(isset($this->userNT) && $this->userNT !== ''){ 
            $userInfos = $this->getUser();
            if(count($userInfos) > 0){
                foreach ($userInfos as $key => $userData) {
                    $_SESSION['taascor_user_name']              = $userData['employee_user_name'];
                    $_SESSION['taascor_first_name']             = $userData['first_name'];
                    $_SESSION['taascor_employee_full_name']     = $userData['employee_full_name'];
                    $_SESSION['taascor_access_level']           = $userData['access_level'];
                    $_SESSION['taascor_access_description']     = $userData['access_description'];
                    $_SESSION['taascor_employee_email']         = $userData['employee_email'];
                    $_SESSION['taascor_client']                 = $userData['client'];
                    $user_password                              = $userData['password_hash'];
                }

                unset($_SESSION['error']);
            
                if(isset($_SESSION['taascor_user_name']) && password_verify($this->password, $user_password)){
                    $login = true;
                }else{
                    $_SESSION['error'] = 'Incorrect username or password.';
                }
            }else{
                $_SESSION['error'] = 'Incorrect username or password.';
            }
        } else{
            $_SESSION['error'] = 'Please provide your credentials.';
        }

        // session_write_close();
        
        return $login;

    }

    public function logoutUser(){ 

        $logout = false;

        session_start();


        session_destroy(); 

        $logout = true;

        return $logout;
    }


    public function signUpUser(){

        $response = [];

        try {

            $sql = "SELECT employee_user_name FROM taascor_user_access where employee_user_name = :employee_user_name";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_user_name', $this->username, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                $response['success'] = 2;   
                return $response;              
            } else {
                $date_now = date("Y-m-d H:i:s");
                $employee_full_name = $this->firstname . " " . $this->lastname;
                $hashedPassword = password_hash($this->password, PASSWORD_BCRYPT);

                $sql = "INSERT INTO taascor_user_access (
                            employee_user_name, employee_full_name, access_level, access_description, 
                            inserted_date_time_ph, password_hash, last_name, first_name, employee_email, is_active,
                            client
                        ) VALUES (
                            :employee_user_name, :employee_full_name, :access_level, :access_description, 
                            :inserted_date_time_ph, :password_hash, :last_name, :first_name, :email, 0
                            ,:client
                        )";
                
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':employee_user_name', $this->username, PDO::PARAM_STR);
                $stmt->bindParam(':employee_full_name', $employee_full_name, PDO::PARAM_STR);
                $stmt->bindParam(':access_level', $this->access_level, PDO::PARAM_INT);
                $stmt->bindParam(':access_description', $this->access_description, PDO::PARAM_STR);
                $stmt->bindParam(':inserted_date_time_ph', $date_now, PDO::PARAM_STR);
                $stmt->bindParam(':password_hash', $hashedPassword, PDO::PARAM_STR);
                $stmt->bindParam(':first_name', $this->firstname, PDO::PARAM_STR);
                $stmt->bindParam(':last_name', $this->lastname, PDO::PARAM_STR);
                $stmt->bindParam(':email', $this->email, PDO::PARAM_STR);
                $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
                $stmt->execute();

                $response['success'] = 1;
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


    private function getUser(): array
    {

        $data = [];
        try { 

            $userSql = "SELECT employee_user_name, employee_full_name, first_name, access_level, 
                                access_description, employee_email, password_hash, client
                            FROM taascor_user_access WHERE employee_user_name = :employee_user_name
                            AND is_active = 1";

            $stmt = $this->db->prepare($userSql);
            $stmt->bindParam(':employee_user_name', $this->userNT, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $data = $e->getMessage();
        }

        return $data;
    }    

}
?>