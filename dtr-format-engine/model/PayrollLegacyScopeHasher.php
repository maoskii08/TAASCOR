<?php

declare(strict_types=1);

/**
 * Builds a deterministic seal of every legacy row that can affect a payslip.
 * The seal is captured after calculation/reconciliation and recomputed while
 * holding the same payroll-scope mutex immediately before posting.
 */
final class PayrollLegacyScopeHasher
{
    private const TABLES = [
        'dtr_upload',
        'payroll_gross_variables',
        'payroll_summary',
        'payroll_other_additional',
        'payroll_other_deduction',
        'loans_payment',
    ];

    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function snapshot(string $clientName, string $payDay): array
    {
        $clientName = trim($clientName);
        $payDay = trim($payDay);
        if ($clientName === '' || !$this->validDate($payDay)) {
            throw new InvalidArgumentException('A valid client and pay date are required for a payroll seal.');
        }

        $tables = [];
        $employeeIds = [];
        $payrollRowCount = 0;
        foreach (self::TABLES as $table) {
            if (!$this->tableHasScope($table)) {
                $tables[$table] = ['available' => false, 'rows' => []];
                continue;
            }
            $stmt = $this->db->prepare(
                "SELECT * FROM `{$table}` WHERE client_name = :client_name AND pay_day = :pay_day"
            );
            $stmt->execute([':client_name' => $clientName, ':pay_day' => $payDay]);
            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                ksort($row, SORT_STRING);
                foreach ($row as $key => $value) {
                    $row[$key] = $value === null ? null : (string)$value;
                }
                $rows[] = $row;
                if ($table === 'payroll_summary') {
                    $payrollRowCount++;
                    $employeeId = (int)($row['employee_id'] ?? 0);
                    if ($employeeId > 0) {
                        $employeeIds[$employeeId] = true;
                    }
                }
            }
            usort($rows, static function (array $left, array $right): int {
                return strcmp(self::canonicalJson($left), self::canonicalJson($right));
            });
            $tables[$table] = ['available' => true, 'rows' => $rows];
        }

        $employeeIds = array_map('intval', array_keys($employeeIds));
        sort($employeeIds, SORT_NUMERIC);
        $payload = [
            'schema' => 'legacy_payroll_scope_v1',
            'client_name' => $clientName,
            'pay_day' => $payDay,
            'employee_ids' => $employeeIds,
            'tables' => $tables,
        ];
        $payloadJson = self::canonicalJson($payload);
        return [
            'snapshot_payload' => $payloadJson,
            'live_snapshot_hash' => hash('sha256', $payloadJson),
            'employee_ids' => $employeeIds,
            'employee_count' => count($employeeIds),
            'payroll_row_count' => $payrollRowCount,
        ];
    }

    public static function canonicalJson(array $value): string
    {
        return (string)json_encode(
            self::canonicalValue($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    private static function canonicalValue($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        $keys = array_keys($value);
        if ($keys !== range(0, count($value) - 1)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalValue($item);
        }
        return $value;
    }

    private function tableHasScope(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(DISTINCT column_name) FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = :table_name '
            . "AND column_name IN ('client_name', 'pay_day')"
        );
        $stmt->execute([':table_name' => $table]);
        return (int)$stmt->fetchColumn() === 2;
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        return $date instanceof DateTimeImmutable
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }
}
