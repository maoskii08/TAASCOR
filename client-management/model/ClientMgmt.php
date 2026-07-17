<?php
class ClientMgmt
{
    public $db          = null;
    public $client_id   = null;
    public $client_name = null;
    public $address     = null;
    public $contact_person = null;
    public $contact_number = null;
    public $email       = null;
    public $industry    = null;
    public $notes       = null;

    // ── Full client overview with live stats ──────────────────────────────
    public function getClientOverview()
    {
        $response = [];
        try {
            $sql = "SELECT
                        c.client_id,
                        c.client_name,
                        COALESCE(c.address,        '')          AS address,
                        COALESCE(c.contact_person, '')          AS contact_person,
                        COALESCE(c.contact_number, '')          AS contact_number,
                        COALESCE(c.email,          '')          AS email,
                        COALESCE(c.industry,       '')          AS industry,
                        COALESCE(c.notes,          '')          AS notes,
                        COUNT(DISTINCT e.employee_id)           AS active_headcount,
                        COALESCE(MAX(p.pay_day), 'No payroll')  AS last_payroll,
                        COALESCE(SUM(p.net_pay), 0)             AS last_net_pay
                    FROM taascor_client c
                    LEFT JOIN employee_list e
                        ON  c.client_id  = e.client_id
                        AND e.status     = 'Active'
                    LEFT JOIN (
                        SELECT client_name, pay_day, SUM(net_pay) AS net_pay
                        FROM payroll_summary
                        WHERE (client_name, pay_day) IN (
                            SELECT client_name, MAX(pay_day)
                            FROM payroll_summary
                            GROUP BY client_name
                        )
                        GROUP BY client_name, pay_day
                    ) p ON c.client_name = p.client_name
                    WHERE c.client_name <> 'No Client'
                    GROUP BY c.client_id, c.client_name, c.address,
                             c.contact_person, c.contact_number, c.email, c.industry, c.notes
                    ORDER BY c.client_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            // Try without extended columns (in case they don't exist yet)
            try {
                $sql2 = "SELECT
                            c.client_id,
                            c.client_name,
                            '' AS address, '' AS contact_person,
                            '' AS contact_number, '' AS email,
                            '' AS industry, '' AS notes,
                            COUNT(DISTINCT e.employee_id) AS active_headcount,
                            'N/A' AS last_payroll, 0 AS last_net_pay
                         FROM taascor_client c
                         LEFT JOIN employee_list e ON c.client_id = e.client_id AND e.status = 'Active'
                         WHERE c.client_name <> 'No Client'
                         GROUP BY c.client_id, c.client_name
                         ORDER BY c.client_name";
                $stmt2 = $this->db->prepare($sql2);
                $stmt2->execute();
                $response['success'] = 1;
                $response['data']    = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                $response['notice']  = 'Extended client fields not yet available. Run the schema update.';
            } catch (\Throwable $th2) {
                $response['success'] = 0;
                $response['error']   = 'An error occurred. Please contact your administrator.';
            }
        }
        return $response;
    }

    // ── Per-client employee list ───────────────────────────────────────────
    public function getClientEmployees()
    {
        $response = [];
        try {
            $sql = "SELECT
                        e.employee_id,
                        CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                        e.employee_type,
                        e.hire_date,
                        e.status,
                        p.position_name,
                        d.department_name,
                        b.branch_name,
                        l.location_name,
                        s.daily_salary,
                        s.bank_name
                    FROM employee_list e
                    LEFT JOIN taascor_position p ON e.position_id = p.position_id
                    LEFT JOIN taascor_department d ON e.department_id = d.department_id
                    LEFT JOIN taascor_branch b ON e.branch_id = b.branch_id
                    LEFT JOIN taascor_client_location l ON e.client_location_id = l.location_id
                    LEFT JOIN employee_salary s ON e.employee_id = s.employee_id
                    INNER JOIN taascor_client c ON e.client_id = c.client_id
                    WHERE c.client_id = :client_id
                    ORDER BY e.status DESC, e.last_name, e.first_name";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client_id', $this->client_id, PDO::PARAM_INT);
            $stmt->execute();
            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    // ── Per-client payroll history ─────────────────────────────────────────
    public function getClientPayrollHistory()
    {
        $response = [];
        try {
            $sql = "SELECT
                        a.pay_day,
                        a.cut_off,
                        COUNT(DISTINCT a.employee_id) AS headcount,
                        SUM(a.gross_income)           AS total_gross,
                        SUM(a.employee_tax)           AS total_tax,
                        SUM(a.employee_sss + a.employee_sss_mpf
                            + a.employer_sss + a.employer_sss_mpf + a.employer_sss_ec) AS total_sss,
                        SUM(a.employee_philhealth + a.employer_philhealth) AS total_philhealth,
                        SUM(a.employee_pagibig + a.employer_pagibig)       AS total_pagibig,
                        SUM(a.net_pay)                AS total_net,
                        CASE WHEN lp.pay_day IS NOT NULL THEN 'Locked' ELSE 'Open' END AS status
                    FROM payroll_summary a
                    INNER JOIN taascor_client c ON a.client_name = c.client_name
                    LEFT JOIN locked_payroll lp ON a.client_name = lp.client_name AND a.pay_day = lp.pay_day
                    WHERE c.client_id = :client_id
                    GROUP BY a.pay_day, a.cut_off, lp.pay_day
                    ORDER BY a.pay_day DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client_id', $this->client_id, PDO::PARAM_INT);
            $stmt->execute();
            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    // ── Update extended client profile ────────────────────────────────────
    public function updateClientProfile()
    {
        $response = [];
        try {
            // Add columns if they don't exist (safe ALTER IGNORE equivalent)
            $cols = ['address VARCHAR(255)', 'contact_person VARCHAR(100)',
                     'contact_number VARCHAR(50)', 'email VARCHAR(100)',
                     'industry VARCHAR(100)', 'notes TEXT'];
            foreach ($cols as $col) {
                try {
                    $colName = explode(' ', $col)[0];
                    $this->db->exec("ALTER TABLE taascor_client ADD COLUMN $col");
                } catch (\Throwable $ignored) {}
            }

            $sql = "UPDATE taascor_client SET
                        address        = :address,
                        contact_person = :contact_person,
                        contact_number = :contact_number,
                        email          = :email,
                        industry       = :industry,
                        notes          = :notes
                    WHERE client_id = :client_id";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client_id',      $this->client_id,      PDO::PARAM_INT);
            $stmt->bindParam(':address',         $this->address,        PDO::PARAM_STR);
            $stmt->bindParam(':contact_person',  $this->contact_person, PDO::PARAM_STR);
            $stmt->bindParam(':contact_number',  $this->contact_number, PDO::PARAM_STR);
            $stmt->bindParam(':email',           $this->email,          PDO::PARAM_STR);
            $stmt->bindParam(':industry',        $this->industry,       PDO::PARAM_STR);
            $stmt->bindParam(':notes',           $this->notes,          PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }
}
