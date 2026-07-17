<?php

class Client
{
    public $db = null;

    public $id        = null;
    public $client_name  = null;
    public $is_active    = null;
    public $fd_code      = null;

    public function getClientList(){
        $response = [];
        try {
            $sql = "SELECT c.client_id, c.client_name,
                           COALESCE(c.is_active, 1) AS is_active,
                           c.fd_code,
                           COUNT(CASE WHEN e.status = 'Active' THEN 1 END) AS active_count
                    FROM taascor_client c
                    LEFT JOIN employee_list e ON c.client_id = e.client_id
                    WHERE c.client_name <> 'No Client'
                    GROUP BY c.client_id, c.client_name, c.is_active, c.fd_code
                    ORDER BY c.client_name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            $response['data']    = $data ?: [];
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = "An error occurred. Please contact your administrator.";
        }
        return $response;
    }

    public function getAlignment(){
        $response = [];
        try {
            $sql = "SELECT c.client_id, c.client_name,
                           COALESCE(c.is_active, 1)    AS is_active,
                           c.fd_code,
                           c.fd_canonical,
                           c.fd_group,
                           COUNT(CASE WHEN e.status = 'Active' THEN 1 END) AS active_count
                    FROM taascor_client c
                    LEFT JOIN employee_list e ON c.client_id = e.client_id
                    WHERE c.client_name <> 'No Client'
                    GROUP BY c.client_id, c.client_name, c.is_active, c.fd_code, c.fd_canonical, c.fd_group
                    ORDER BY c.fd_canonical ASC, c.client_name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            $response['data']    = $data ?: [];
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = "An error occurred. Please contact your administrator.";
        }
        return $response;
    }

    public function addClient(){
        $response = [];
        try {
            $sql  = "INSERT INTO taascor_client(client_name) VALUES(:client_name)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client_name', $this->client_name, PDO::PARAM_STR);
            $stmt->execute();
            $response['success'] = 1;
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = "An error occurred. Please contact your administrator.";
        }
        return $response;
    }

    public function updateClient(){
        $response = [];
        try {
            $sql  = "UPDATE taascor_client
                     SET client_name = :client_name,
                         fd_code     = :fd_code
                     WHERE client_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client_name', $this->client_name, PDO::PARAM_STR);
            $stmt->bindParam(':fd_code',     $this->fd_code,     PDO::PARAM_STR);
            $stmt->bindParam(':id',          $this->id,          PDO::PARAM_INT);
            $stmt->execute();
            $response['success'] = 1;
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = "An error occurred. Please contact your administrator.";
        }
        return $response;
    }

    public function updateStatus(){
        $response = [];
        try {
            $sql  = "UPDATE taascor_client SET is_active = :is_active WHERE client_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':is_active', $this->is_active, PDO::PARAM_INT);
            $stmt->bindParam(':id',        $this->id,        PDO::PARAM_INT);
            $stmt->execute();
            $response['success'] = 1;
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = "An error occurred. Please contact your administrator.";
        }
        return $response;
    }

    public function deleteClient(){
        $response = [];
        try {
            $sql  = "DELETE FROM taascor_client WHERE client_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->execute();
            $response['success'] = 1;
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = "An error occurred. Please contact your administrator.";
        }
        return $response;
    }
}


?>
