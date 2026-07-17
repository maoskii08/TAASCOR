<?php

class Billing
{
    public $db       = null;
    public $client   = null;
    public $pay_day  = null;
    public $cut_off  = null;

    // ── Per-period billing summary ────────────────────────────────────────────
    public function getBillingSummary()
    {
        $response = [];
        try {
            $where = '';
            if ($this->client  && $this->client  !== 'null') $where .= " AND a.client_name = :client";
            if ($this->pay_day && $this->pay_day !== 'null') $where .= " AND a.pay_day = :pay_day";
            if ($this->cut_off && $this->cut_off !== 'null') $where .= " AND a.cut_off = :cut_off";

            $sql = "SELECT
                        a.client_name,
                        a.cut_off,
                        a.pay_day,
                        COUNT(DISTINCT a.employee_id)          AS headcount,
                        SUM(r.daily_salary * r.daily_worked)   AS basic_pay,
                        SUM(a.total_ot)                        AS total_ot,
                        SUM(g.vacation_leave + g.sick_leave)   AS total_leaves,
                        SUM(a.total_additional)                AS total_additional,
                        SUM(a.gross_income)                    AS gross_income,
                        SUM(a.employee_tax)                    AS employee_tax,
                        SUM(a.total_tardy)                     AS total_tardy,
                        SUM(a.employee_sss)                    AS employee_sss,
                        SUM(a.employee_sss_mpf)                AS employee_sss_mpf,
                        SUM(a.employee_philhealth)             AS employee_philhealth,
                        SUM(a.employee_pagibig)                AS employee_pagibig,
                        SUM(a.employee_loan)                   AS employee_loan,
                        SUM(a.total_deduction)                 AS total_deduction,
                        SUM(a.net_pay)                         AS net_pay,
                        SUM(a.annual_bonus)                    AS annual_bonus,
                        SUM(a.employer_sss)                    AS employer_sss,
                        SUM(a.employer_sss_mpf)                AS employer_sss_mpf,
                        SUM(a.employer_sss_ec)                 AS employer_sss_ec,
                        SUM(a.employer_philhealth)             AS employer_philhealth,
                        SUM(a.employer_pagibig)                AS employer_pagibig,
                        SUM(a.employer_sss + a.employer_sss_mpf + a.employer_sss_ec
                            + a.employer_philhealth + a.employer_pagibig)
                                                               AS total_employer_contributions
                    FROM payroll_summary a
                    INNER JOIN dtr_upload r
                        ON  a.employee_id  = r.employee_id
                        AND a.client_name  = r.client_name
                        AND a.cut_off      = r.cut_off
                        AND a.pay_day      = r.pay_day
                    INNER JOIN payroll_gross_variables g
                        ON  a.employee_id  = g.employee_id
                        AND a.client_name  = g.client_name
                        AND a.cut_off      = g.cut_off
                        AND a.pay_day      = g.pay_day
                    WHERE 1=1 $where
                    GROUP BY a.client_name, a.cut_off, a.pay_day
                    ORDER BY a.pay_day DESC, a.client_name ASC";

            $stmt = $this->db->prepare($sql);
            if ($this->client  && $this->client  !== 'null') $stmt->bindParam(':client',  $this->client,  PDO::PARAM_STR);
            if ($this->pay_day && $this->pay_day !== 'null') $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            if ($this->cut_off && $this->cut_off !== 'null') $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    // ── Per-employee billing detail for one client+period ────────────────────
    public function getBillingDetail()
    {
        $response = [];
        try {
            $sql = "SELECT
                        a.employee_id,
                        CONCAT(b.last_name, ', ', b.first_name) AS employee_name,
                        r.daily_salary,
                        r.daily_worked,
                        r.daily_salary * r.daily_worked         AS basic_pay,
                        a.total_ot,
                        a.total_additional,
                        a.gross_income,
                        a.employee_tax,
                        a.total_tardy,
                        a.employee_sss,
                        a.employee_sss_mpf,
                        a.employee_philhealth,
                        a.employee_pagibig,
                        a.employee_loan,
                        a.total_deduction,
                        a.net_pay,
                        a.annual_bonus,
                        a.employer_sss,
                        a.employer_sss_mpf,
                        a.employer_sss_ec,
                        a.employer_philhealth,
                        a.employer_pagibig
                    FROM payroll_summary a
                    INNER JOIN employee_list b  ON a.employee_id = b.employee_id
                    INNER JOIN dtr_upload r
                        ON  a.employee_id = r.employee_id
                        AND a.client_name = r.client_name
                        AND a.cut_off     = r.cut_off
                        AND a.pay_day     = r.pay_day
                    WHERE a.client_name = :client
                      AND a.cut_off     = :cut_off
                      AND a.pay_day     = :pay_day
                    ORDER BY b.last_name, b.first_name";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client',  $this->client,  PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    // ── Filter helpers ────────────────────────────────────────────────────────
    public function getClientFilter()
    {
        $response = [];
        try {
            $stmt = $this->db->query(
                "SELECT DISTINCT client_name FROM payroll_summary ORDER BY client_name"
            );
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
            $where = '';
            if ($this->client && $this->client !== 'null') $where = "WHERE client_name = :client";
            $sql  = "SELECT DISTINCT pay_day, cut_off FROM payroll_summary $where ORDER BY pay_day DESC";
            $stmt = $this->db->prepare($sql);
            if ($this->client && $this->client !== 'null') $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
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
