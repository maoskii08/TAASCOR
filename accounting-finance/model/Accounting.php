<?php
class Accounting
{
    public $db      = null;
    public $client  = null;
    public $pay_day = null;
    public $cut_off = null;
    public $year    = null;

    // ── Government remittance summary per period ──────────────────────────
    public function getRemittanceSummary()
    {
        $response = [];
        try {
            $where = '';
            $params = [];
            if ($this->client  && $this->client  !== 'null') { $where .= " AND a.client_name = :client";  $params[':client']  = $this->client; }
            if ($this->pay_day && $this->pay_day !== 'null') { $where .= " AND a.pay_day = :pay_day";     $params[':pay_day'] = $this->pay_day; }
            if ($this->cut_off && $this->cut_off !== 'null') { $where .= " AND a.cut_off = :cut_off";     $params[':cut_off'] = $this->cut_off; }

            $sql = "SELECT
                        a.client_name,
                        a.cut_off,
                        a.pay_day,
                        COUNT(DISTINCT a.employee_id)                AS headcount,
                        -- Employee contributions
                        SUM(a.employee_sss)                          AS ee_sss,
                        SUM(a.employee_sss_mpf)                      AS ee_sss_mpf,
                        SUM(a.employee_philhealth)                   AS ee_philhealth,
                        SUM(a.employee_pagibig)                      AS ee_pagibig,
                        SUM(a.employee_tax)                          AS ee_tax,
                        -- Employer contributions
                        SUM(a.employer_sss)                          AS er_sss,
                        SUM(a.employer_sss_mpf)                      AS er_sss_mpf,
                        SUM(a.employer_sss_ec)                       AS er_sss_ec,
                        SUM(a.employer_philhealth)                   AS er_philhealth,
                        SUM(a.employer_pagibig)                      AS er_pagibig,
                        -- Totals
                        SUM(a.employee_sss + a.employee_sss_mpf)     AS total_ee_sss,
                        SUM(a.employer_sss + a.employer_sss_mpf + a.employer_sss_ec) AS total_er_sss,
                        SUM(a.employee_philhealth + a.employer_philhealth) AS total_philhealth,
                        SUM(a.employee_pagibig   + a.employer_pagibig)    AS total_pagibig,
                        SUM(a.employee_sss + a.employee_sss_mpf
                            + a.employer_sss + a.employer_sss_mpf + a.employer_sss_ec) AS grand_sss,
                        SUM(a.employee_philhealth + a.employer_philhealth)              AS grand_philhealth,
                        SUM(a.employee_pagibig   + a.employer_pagibig)                 AS grand_pagibig,
                        SUM(a.employee_tax)                                             AS grand_tax,
                        SUM(a.net_pay)                                                  AS total_net_pay,
                        SUM(a.gross_income)                                             AS total_gross
                    FROM payroll_summary a
                    WHERE 1=1 $where
                    GROUP BY a.client_name, a.cut_off, a.pay_day
                    ORDER BY a.pay_day DESC, a.client_name";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();

            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    // ── Annual government contributions summary (for alphalist / year-end) ─
    public function getAnnualSummary()
    {
        $response = [];
        try {
            $where  = '';
            $params = [];
            if ($this->year   && $this->year   !== 'null') { $where .= " AND YEAR(a.pay_day) = :year";   $params[':year']   = $this->year; }
            if ($this->client && $this->client !== 'null') { $where .= " AND a.client_name = :client";   $params[':client'] = $this->client; }

            $sql = "SELECT
                        a.client_name,
                        YEAR(a.pay_day)                                                       AS year,
                        COUNT(DISTINCT a.employee_id)                                         AS headcount,
                        SUM(a.gross_income)                                                   AS total_gross,
                        SUM(a.taxable_income)                                                 AS total_taxable,
                        SUM(a.employee_tax)                                                   AS total_tax,
                        SUM(a.employee_sss + a.employee_sss_mpf)                             AS total_ee_sss,
                        SUM(a.employer_sss + a.employer_sss_mpf + a.employer_sss_ec)         AS total_er_sss,
                        SUM(a.employee_sss + a.employee_sss_mpf
                            + a.employer_sss + a.employer_sss_mpf + a.employer_sss_ec)       AS grand_sss,
                        SUM(a.employee_philhealth + a.employer_philhealth)                    AS grand_philhealth,
                        SUM(a.employee_pagibig   + a.employer_pagibig)                       AS grand_pagibig,
                        SUM(a.net_pay)                                                        AS total_net_pay,
                        SUM(a.annual_bonus)                                                   AS total_13th_month
                    FROM payroll_summary a
                    WHERE 1=1 $where
                    GROUP BY a.client_name, YEAR(a.pay_day)
                    ORDER BY year DESC, a.client_name";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();

            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    // ── Per-employee annual summary (for BIR 2316 / alphalist) ────────────
    public function getEmployeeAnnual()
    {
        $response = [];
        try {
            $where  = '';
            $params = [];
            if ($this->year   && $this->year   !== 'null') { $where .= " AND YEAR(a.pay_day) = :year";   $params[':year']   = $this->year; }
            if ($this->client && $this->client !== 'null') { $where .= " AND a.client_name = :client";   $params[':client'] = $this->client; }

            $sql = "SELECT
                        a.employee_id,
                        CONCAT(e.last_name, ', ', e.first_name) AS employee_name,
                        g.tin_number,
                        a.client_name,
                        YEAR(a.pay_day)                        AS year,
                        SUM(a.gross_income)                    AS total_gross,
                        SUM(a.taxable_income)                  AS total_taxable,
                        SUM(a.employee_tax)                    AS total_tax,
                        SUM(a.employee_sss + a.employee_sss_mpf) AS total_sss,
                        SUM(a.employee_philhealth)             AS total_philhealth,
                        SUM(a.employee_pagibig)                AS total_pagibig,
                        SUM(a.net_pay)                         AS total_net,
                        SUM(a.annual_bonus)                    AS total_13th
                    FROM payroll_summary a
                    INNER JOIN employee_list e   ON a.employee_id = e.employee_id
                    INNER JOIN employee_govt_account g ON a.employee_id = g.employee_id
                    WHERE 1=1 $where
                    GROUP BY a.employee_id, a.client_name, YEAR(a.pay_day),
                             e.last_name, e.first_name, g.tin_number
                    ORDER BY e.last_name, e.first_name";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();

            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    // ── Filter helpers ─────────────────────────────────────────────────────
    public function getClientFilter()
    {
        $response = [];
        try {
            $stmt = $this->db->query("SELECT DISTINCT client_name FROM payroll_summary ORDER BY client_name");
            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    public function getYearFilter()
    {
        $response = [];
        try {
            $stmt = $this->db->query("SELECT DISTINCT YEAR(pay_day) AS yr FROM payroll_summary ORDER BY yr DESC");
            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    public function getPayDayFilter()
    {
        $response = [];
        try {
            $where = $this->client && $this->client !== 'null' ? "WHERE client_name = :client" : "";
            $sql   = "SELECT DISTINCT pay_day, cut_off FROM payroll_summary $where ORDER BY pay_day DESC";
            $stmt  = $this->db->prepare($sql);
            if ($this->client && $this->client !== 'null') $stmt->bindValue(':client', $this->client);
            $stmt->execute();
            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }
}
