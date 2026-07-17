<?php
class AuditLog
{
    public $db        = null;
    public $username  = null;
    public $action    = null;
    public $date_from = null;
    public $date_to   = null;

    // ── Read logs with filters ─────────────────────────────────────────────
    public function getLogs()
    {
        $response = [];
        try {
            $where  = [];
            $params = [];

            if ($this->username && $this->username !== '') {
                $where[]              = "username LIKE :username";
                $params[':username']  = '%' . $this->username . '%';
            }
            if ($this->action && $this->action !== '') {
                $where[]            = "log_action = :action";
                $params[':action']  = $this->action;
            }
            if ($this->date_from && $this->date_from !== '') {
                $where[]                = "DATE(inserted_date_time_ph) >= :date_from";
                $params[':date_from']   = $this->date_from;
            }
            if ($this->date_to && $this->date_to !== '') {
                $where[]              = "DATE(inserted_date_time_ph) <= :date_to";
                $params[':date_to']   = $this->date_to;
            }

            $whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

            $sql = "SELECT id, username, log_action, inserted_date_time_ph
                    FROM logs
                    $whereSQL
                    ORDER BY inserted_date_time_ph DESC
                    LIMIT 2000";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();

            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['total']   = count($response['data']);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    // ── Login activity summary per user ───────────────────────────────────
    public function getLoginSummary()
    {
        $response = [];
        try {
            $sql = "SELECT
                        username,
                        COUNT(*)                                AS total_logins,
                        MAX(inserted_date_time_ph)              AS last_login,
                        MIN(inserted_date_time_ph)              AS first_login,
                        SUM(CASE WHEN DATE(inserted_date_time_ph) = CURDATE() THEN 1 ELSE 0 END) AS logins_today
                    FROM logs
                    WHERE log_action = 'Login'
                    GROUP BY username
                    ORDER BY last_login DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    // ── Distinct action types for filter dropdown ──────────────────────────
    public function getActionTypes()
    {
        $response = [];
        try {
            $stmt = $this->db->query("SELECT DISTINCT log_action FROM logs ORDER BY log_action");
            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    // ── Write a new log entry (called by other controllers) ───────────────
    public static function write($db, string $username, string $action): void
    {
        try {
            $sql  = "INSERT INTO logs (username, log_action, inserted_date_time_ph)
                     VALUES (:username, :action, NOW())";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':action',   $action);
            $stmt->execute();
        } catch (\Throwable $ignored) {}
    }
}
