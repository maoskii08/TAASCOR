<?php

declare(strict_types=1);

require_once __DIR__ . '/EmployeeConversionAdapter.php';
require_once __DIR__ . '/RecruitmentContentPolicy.php';

final class HrisEmployeeConversionAdapter implements EmployeeConversionAdapter
{
    private const REQUIRED_FIELDS = [
        'first_name', 'last_name', 'full_name', 'hire_date', 'client_date',
        'branch_name', 'client_name', 'client_location_name', 'department_name',
        'position_name', 'payroll_employee_id', 'employee_type', 'email_address',
        'contact_number', 'birthday', 'birth_place', 'civil_status', 'gender',
        'nationality', 'present_address', 'permanent_address', 'emergency_person',
        'emergency_contact_number', 'pay_type', 'daily_salary', 'annual_leaves',
    ];

    private const OPTIONAL_FIELDS = [
        'middle_name', 'old_employee_id', 'insurance', 'pag_ibig_number',
        'philhealth_number', 'sss_number', 'tin_number', 'bank_account_number', 'bank_name',
    ];

    public function __construct(private PDO $db)
    {
    }

    public function duplicateCheck(array $employeePayload): array
    {
        $payload = $this->normalizePayload($employeePayload);
        $statement = $this->db->prepare(
            'SELECT e.employee_id,
                    e.payroll_employee_id = :payroll_employee_id AS payroll_match,
                    LOWER(TRIM(d.email_address)) = LOWER(TRIM(:email_address)) AS email_match,
                    (LOWER(TRIM(e.full_name)) = LOWER(TRIM(:full_name)) AND d.birthday = :birthday) AS identity_match
               FROM employee_list e
               LEFT JOIN employee_details d ON d.employee_id = e.employee_id
              WHERE e.payroll_employee_id = :payroll_employee_id_lookup
                 OR LOWER(TRIM(d.email_address)) = LOWER(TRIM(:email_address_lookup))
                 OR (LOWER(TRIM(e.full_name)) = LOWER(TRIM(:full_name_lookup)) AND d.birthday = :birthday_lookup)
              ORDER BY e.employee_id
              LIMIT 20'
        );
        $statement->execute([
            'payroll_employee_id' => $payload['payroll_employee_id'],
            'email_address' => $payload['email_address'],
            'full_name' => $payload['full_name'],
            'birthday' => $payload['birthday'],
            'payroll_employee_id_lookup' => $payload['payroll_employee_id'],
            'email_address_lookup' => $payload['email_address'],
            'full_name_lookup' => $payload['full_name'],
            'birthday_lookup' => $payload['birthday'],
        ]);
        $matches = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $matches[] = [
                'employee_reference' => (string)$row['employee_id'],
                'payroll_id_match' => (bool)$row['payroll_match'],
                'email_match' => (bool)$row['email_match'],
                'name_birthdate_match' => (bool)$row['identity_match'],
            ];
        }
        $governmentFields=['pag_ibig_number','philhealth_number','sss_number','tin_number'];
        foreach($governmentFields as $field){
            $value=trim((string)($payload[$field]??''));
            if($value==='') continue;
            $government=$this->db->prepare("SELECT employee_id FROM employee_govt_account WHERE {$field}=:value LIMIT 20");
            $government->execute(['value'=>$value]);
            foreach($government->fetchAll(PDO::FETCH_COLUMN)?:[] as $employeeId){ $matches[]=['employee_reference'=>(string)$employeeId,'government_id_match'=>$field]; }
        }
        return [
            'clear' => $matches === [],
            'evidence' => [
                'match_count' => count($matches),
                'matches' => $matches,
                'checked_fields' => ['payroll_employee_id', 'email_address', 'full_name_and_birthday', 'pag_ibig_number', 'philhealth_number', 'sss_number', 'tin_number'],
            ],
        ];
    }

    public function createEmployee(array $employeePayload): string
    {
        $payload = $this->normalizePayload($employeePayload);
        $this->assertReferenceData($payload);
        $lock = $this->db->query("SELECT GET_LOCK('taascor_employee_id_allocation', 10)");
        if ((int)$lock->fetchColumn() !== 1) {
            throw new RuntimeException('Employee ID allocation is currently busy.');
        }

        try {
            $duplicate = $this->duplicateCheck($payload);
            if (!$duplicate['clear']) {
                throw new DomainException('Employee creation stopped because duplicate evidence was found.');
            }
            $employeeId = (int)$this->db->query('SELECT COALESCE(MAX(employee_id), 1000) + 1 FROM employee_list FOR UPDATE')->fetchColumn();
            if ($employeeId <= 1000) {
                throw new RuntimeException('A valid employee ID could not be allocated.');
            }

            $this->db->prepare(
                "INSERT INTO employee_list
                    (employee_id, annual_leaves, branch_id, client_id, department_id, first_name,
                     hire_date, last_name, middle_name, old_employee_id, position_id,
                     payroll_employee_id, full_name, employee_type, status, client_date, client_location_id)
                 VALUES
                    (:employee_id, :annual_leaves,
                     (SELECT branch_id FROM taascor_branch WHERE branch_name = :branch_name LIMIT 1),
                     (SELECT client_id FROM taascor_client WHERE client_name = :client_name LIMIT 1),
                     (SELECT department_id FROM taascor_department WHERE department_name = :department_name LIMIT 1),
                     :first_name, :hire_date, :last_name, :middle_name, :old_employee_id,
                     (SELECT position_id FROM taascor_position WHERE position_name = :position_name LIMIT 1),
                     :payroll_employee_id, :full_name, :employee_type, 'Active', :client_date,
                     (SELECT location_id FROM taascor_client_location WHERE location_name = :client_location_name LIMIT 1))"
            )->execute([
                'employee_id' => $employeeId,
                'annual_leaves' => $payload['annual_leaves'],
                'branch_name' => $payload['branch_name'],
                'client_name' => $payload['client_name'],
                'department_name' => $payload['department_name'],
                'first_name' => $payload['first_name'],
                'hire_date' => $payload['hire_date'],
                'last_name' => $payload['last_name'],
                'middle_name' => $payload['middle_name'],
                'old_employee_id' => $payload['old_employee_id'],
                'position_name' => $payload['position_name'],
                'payroll_employee_id' => $payload['payroll_employee_id'],
                'full_name' => $payload['full_name'],
                'employee_type' => $payload['employee_type'],
                'client_date' => $payload['client_date'],
                'client_location_name' => $payload['client_location_name'],
            ]);

            $this->db->prepare(
                'INSERT INTO employee_details
                    (employee_id, birthday, birth_place, civil_status, contact_number, email_address,
                     emergency_contact_number, emergency_person, gender, insurance, nationality,
                     permanent_address, present_address)
                 VALUES
                    (:employee_id, :birthday, :birth_place, :civil_status, :contact_number, :email_address,
                     :emergency_contact_number, :emergency_person, :gender, :insurance, :nationality,
                     :permanent_address, :present_address)'
            )->execute([
                'employee_id' => $employeeId,
                'birthday' => $payload['birthday'],
                'birth_place' => $payload['birth_place'],
                'civil_status' => $payload['civil_status'],
                'contact_number' => $payload['contact_number'],
                'email_address' => $payload['email_address'],
                'emergency_contact_number' => $payload['emergency_contact_number'],
                'emergency_person' => $payload['emergency_person'],
                'gender' => $payload['gender'],
                'insurance' => $payload['insurance'],
                'nationality' => $payload['nationality'],
                'permanent_address' => $payload['permanent_address'],
                'present_address' => $payload['present_address'],
            ]);

            $this->db->prepare(
                'INSERT INTO employee_govt_account
                    (employee_id, pag_ibig_number, philhealth_number, sss_number, tin_number)
                 VALUES (:employee_id, :pag_ibig, :philhealth, :sss, :tin)'
            )->execute([
                'employee_id' => $employeeId,
                'pag_ibig' => $payload['pag_ibig_number'],
                'philhealth' => $payload['philhealth_number'],
                'sss' => $payload['sss_number'],
                'tin' => $payload['tin_number'],
            ]);

            $this->db->prepare(
                'INSERT INTO employee_salary
                    (employee_id, atm_number, bank_name, daily_salary, pay_type)
                 VALUES (:employee_id, :atm_number, :bank_name, :daily_salary, :pay_type)'
            )->execute([
                'employee_id' => $employeeId,
                'atm_number' => $payload['bank_account_number'],
                'bank_name' => $payload['bank_name'],
                'daily_salary' => $payload['daily_salary'],
                'pay_type' => $payload['pay_type'],
            ]);

            return (string)$employeeId;
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('taascor_employee_id_allocation')");
        }
    }

    public function employeeSnapshot(string $employeeReference): ?array
    {
        if (!ctype_digit($employeeReference) || (int)$employeeReference <= 0) {
            return null;
        }
        $statement = $this->db->prepare(
            'SELECT e.first_name, e.last_name, e.middle_name, e.full_name, e.hire_date, e.client_date,
                    e.payroll_employee_id, e.employee_type, e.annual_leaves, e.old_employee_id,
                    b.branch_name, c.client_name, l.location_name AS client_location_name,
                    dep.department_name, p.position_name, d.email_address, d.contact_number,
                    d.birthday, d.birth_place, d.civil_status, d.gender, d.nationality,
                    d.present_address, d.permanent_address, d.emergency_person,
                    d.emergency_contact_number, d.insurance, g.pag_ibig_number,
                    g.philhealth_number, g.sss_number, g.tin_number, s.atm_number AS bank_account_number,
                    s.bank_name, s.daily_salary, s.pay_type
               FROM employee_list e
               LEFT JOIN taascor_branch b ON b.branch_id = e.branch_id
               LEFT JOIN taascor_client c ON c.client_id = e.client_id
               LEFT JOIN taascor_client_location l ON l.location_id = e.client_location_id
               LEFT JOIN taascor_department dep ON dep.department_id = e.department_id
               LEFT JOIN taascor_position p ON p.position_id = e.position_id
               LEFT JOIN employee_details d ON d.employee_id = e.employee_id
               LEFT JOIN employee_govt_account g ON g.employee_id = e.employee_id
               LEFT JOIN employee_salary s ON s.employee_id = e.employee_id
              WHERE e.employee_id = :employee_id LIMIT 1'
        );
        $statement->execute(['employee_id' => (int)$employeeReference]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->normalizePayload($row) : null;
    }

    /** @return array<string, mixed> */
    public function normalizePayload(array $payload): array
    {
        $normalized = [];
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $payload) || trim((string)$payload[$field]) === '') {
                throw new InvalidArgumentException("Employee conversion field {$field} is required.");
            }
            $normalized[$field] = trim((string)$payload[$field]);
        }
        foreach (self::OPTIONAL_FIELDS as $field) {
            $normalized[$field] = trim((string)($payload[$field] ?? ''));
        }
        foreach (['hire_date', 'client_date', 'birthday'] as $field) {
            $normalized[$field] = RecruitmentContentPolicy::date($normalized[$field], str_replace('_', ' ', $field), true);
        }
        $normalized['first_name'] = RecruitmentContentPolicy::requiredText($normalized['first_name'], 'First name', 1, 190);
        $normalized['last_name'] = RecruitmentContentPolicy::requiredText($normalized['last_name'], 'Last name', 1, 190);
        $normalized['full_name'] = RecruitmentContentPolicy::requiredText($normalized['full_name'], 'Full name', 3, 300);
        $normalized['email_address'] = strtolower(RecruitmentContentPolicy::requiredText($normalized['email_address'], 'Email address', 5, 190));
        if (!filter_var($normalized['email_address'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Employee conversion email address is invalid.');
        }
        return $normalized;
    }

    private function assertReferenceData(array $payload): void
    {
        $statement = $this->db->prepare(
            'SELECT
                (SELECT COUNT(*) FROM taascor_branch WHERE branch_name = :branch_name) AS branch_count,
                (SELECT COUNT(*) FROM taascor_client WHERE client_name = :client_name) AS client_count,
                (SELECT COUNT(*) FROM taascor_department WHERE department_name = :department_name) AS department_count,
                (SELECT COUNT(*) FROM taascor_position WHERE position_name = :position_name) AS position_count,
                (SELECT COUNT(*) FROM taascor_client_location WHERE location_name = :location_name) AS location_count'
        );
        $statement->execute([
            'branch_name' => $payload['branch_name'],
            'client_name' => $payload['client_name'],
            'department_name' => $payload['department_name'],
            'position_name' => $payload['position_name'],
            'location_name' => $payload['client_location_name'],
        ]);
        $counts = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        $missing = [];
        foreach (['branch', 'client', 'department', 'position', 'location'] as $reference) {
            if ((int)($counts[$reference . '_count'] ?? 0) !== 1) {
                $missing[] = $reference;
            }
        }
        if ($missing !== []) {
            throw new DomainException('Employee conversion reference data is missing or ambiguous: ' . implode(', ', $missing) . '.');
        }
    }
}
