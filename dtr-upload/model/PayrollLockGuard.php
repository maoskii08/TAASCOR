<?php

declare(strict_types=1);

/**
 * Central server-side guard for every legacy DTR/payroll mutation.
 *
 * The UI lock is advisory only. Controllers must call this guard immediately
 * before a write so a crafted request cannot alter an already-posted payroll.
 */
final class PayrollLockGuard
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function check(string $client, string $payDay): array
    {
        $client = trim($client);
        $payDay = trim($payDay);
        if ($client === '' || !$this->validDate($payDay)) {
            return $this->failure(
                'payroll_lock_scope_invalid',
                'A valid client and pay date are required before changing payroll data.'
            );
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM locked_payroll '
                . 'WHERE client_name = :client AND pay_day = :pay_day LIMIT 1'
            );
            $stmt->execute([
                ':client' => $client,
                ':pay_day' => $payDay,
            ]);
            $locked = $stmt->fetchColumn() !== false;

            if ($locked) {
                return [
                    'success' => 0,
                    'locked' => true,
                    'code' => 'payroll_locked',
                    'error' => 'Payroll is already posted and locked. DTR and payroll data cannot be changed.',
                    'lock' => [
                        'client' => $client,
                        'pay_day' => $payDay,
                    ],
                ];
            }

            return [
                'success' => 1,
                'locked' => false,
                'lock' => [
                    'client' => $client,
                    'pay_day' => $payDay,
                ],
            ];
        } catch (Throwable $error) {
            error_log('PayrollLockGuard::check failed: ' . $error->getMessage());
            return $this->failure(
                'payroll_lock_check_failed',
                'Unable to verify the payroll lock. No data was changed.'
            );
        }
    }

    /**
     * Serialize every cooperating payroll mutation and the posting lock on one
     * database advisory-lock key. This closes the controller-check/write race.
     */
    public function acquireMutationLease(string $client, string $payDay, int $timeoutSeconds = 5): array
    {
        $client = trim($client);
        $payDay = trim($payDay);
        if ($client === '' || !$this->validDate($payDay)) {
            return $this->failure(
                'payroll_lock_scope_invalid',
                'A valid client and pay date are required before changing payroll data.'
            );
        }

        try {
            $canonicalScope = $this->canonicalScope($client, $payDay);
            if (($canonicalScope['success'] ?? 0) !== 1) {
                return $canonicalScope;
            }
            $client = (string)$canonicalScope['client'];
            $payDay = (string)$canonicalScope['pay_day'];
            $leaseName = $this->leaseName((int)$canonicalScope['client_id'], $payDay);
            $stmt = $this->db->prepare('SELECT GET_LOCK(:lease_name, :timeout_seconds)');
            $stmt->bindValue(':lease_name', $leaseName, PDO::PARAM_STR);
            $stmt->bindValue(':timeout_seconds', max(0, min(30, $timeoutSeconds)), PDO::PARAM_INT);
            $stmt->execute();
            if ((int)$stmt->fetchColumn() !== 1) {
                return $this->failure(
                    'payroll_mutation_busy',
                    'Another payroll change is in progress. Please retry after it completes.'
                );
            }

            $gate = $this->check($client, $payDay);
            if (($gate['success'] ?? 0) !== 1) {
                $this->releaseMutationLease($leaseName);
                return $gate;
            }
            return [
                'success' => 1,
                'locked' => false,
                'lease_name' => $leaseName,
                'lock' => [
                    'client_id' => (int)$canonicalScope['client_id'],
                    'client' => $client,
                    'pay_day' => $payDay,
                ],
            ];
        } catch (Throwable $error) {
            error_log('PayrollLockGuard::acquireMutationLease failed: ' . $error->getMessage());
            return $this->failure(
                'payroll_mutation_lease_failed',
                'Unable to reserve the payroll mutation scope. No data was changed.'
            );
        }
    }

    public function releaseMutationLease(string $leaseName): void
    {
        if ($leaseName === '') {
            return;
        }
        try {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(:lease_name)');
            $stmt->execute([':lease_name' => $leaseName]);
        } catch (Throwable $error) {
            error_log('PayrollLockGuard::releaseMutationLease failed: ' . $error->getMessage());
        }
    }

    public function runUnlockedMutation(string $client, string $payDay, callable $mutation): array
    {
        $lease = $this->acquireMutationLease($client, $payDay);
        if (($lease['success'] ?? 0) !== 1) {
            return $lease;
        }
        try {
            $result = $mutation();
            return is_array($result)
                ? $result
                : $this->failure('payroll_mutation_invalid_result', 'The payroll change did not return a valid result.');
        } catch (Throwable $error) {
            error_log('PayrollLockGuard::runUnlockedMutation failed: ' . $error->getMessage());
            return $this->failure('payroll_mutation_failed', 'The payroll change failed. No further changes were made.');
        } finally {
            $this->releaseMutationLease((string)$lease['lease_name']);
        }
    }

    public static function normalizePayrollDetails(array $payrollDetails): array
    {
        if (count($payrollDetails) !== 1 || !is_array($payrollDetails[0])) {
            return [
                'success' => 0,
                'locked' => true,
                'code' => 'payroll_lock_scope_invalid',
                'error' => 'Exactly one payroll client, cutoff, pay date, and period are required.',
            ];
        }
        $row = $payrollDetails[0];
        $scope = [
            'client_name' => trim((string)($row[0] ?? '')),
            'cut_off' => trim((string)($row[1] ?? '')),
            'pay_day' => trim((string)($row[2] ?? '')),
            'start_date' => trim((string)($row[3] ?? '')),
            'end_date' => trim((string)($row[4] ?? '')),
        ];
        if ($scope['client_name'] === '' || $scope['cut_off'] === ''
            || !self::validDateString($scope['pay_day'])
            || !self::validDateString($scope['start_date'])
            || !self::validDateString($scope['end_date'])
            || $scope['end_date'] < $scope['start_date']) {
            return [
                'success' => 0,
                'locked' => true,
                'code' => 'payroll_lock_scope_invalid',
                'error' => 'The payroll mutation scope is incomplete or invalid.',
            ];
        }
        return ['success' => 1, 'locked' => false] + $scope;
    }

    /**
     * Backward-compatible scope recovery for cached clients that did not yet
     * submit client_name on employee-level actions. Ambiguous scope fails shut.
     */
    public function resolveEmployeeClient($employeeId, string $payDay): array
    {
        $employeeId = filter_var(
            $employeeId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $payDay = trim($payDay);
        if ($employeeId === false || !$this->validDate($payDay)) {
            return $this->failure(
                'payroll_lock_scope_invalid',
                'Unable to determine the payroll lock scope. No data was changed.'
            );
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT client_name FROM ('
                . ' SELECT client_name FROM dtr_upload'
                . ' WHERE employee_id = :employee_id_dtr AND pay_day = :pay_day_dtr'
                . ' UNION '
                . ' SELECT client_name FROM payroll_summary'
                . ' WHERE employee_id = :employee_id_payroll AND pay_day = :pay_day_payroll'
                . ') payroll_scope ORDER BY client_name LIMIT 2'
            );
            $stmt->execute([
                ':employee_id_dtr' => (int)$employeeId,
                ':pay_day_dtr' => $payDay,
                ':employee_id_payroll' => (int)$employeeId,
                ':pay_day_payroll' => $payDay,
            ]);
            $clients = array_values(array_unique(array_filter(array_map(
                static fn($value): string => trim((string)$value),
                $stmt->fetchAll(PDO::FETCH_COLUMN)
            ))));

            if (count($clients) !== 1) {
                return $this->failure(
                    'payroll_lock_scope_ambiguous',
                    'Unable to determine one client for this employee and pay date. No data was changed.'
                );
            }

            return ['success' => 1, 'client' => $clients[0]];
        } catch (Throwable $error) {
            error_log('PayrollLockGuard::resolveEmployeeClient failed: ' . $error->getMessage());
            return $this->failure(
                'payroll_lock_scope_failed',
                'Unable to determine the payroll lock scope. No data was changed.'
            );
        }
    }

    private function validDate(string $value): bool
    {
        return self::validDateString($value);
    }

    private static function validDateString(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        return $date instanceof DateTimeImmutable
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }

    private function canonicalScope(string $client, string $payDay): array
    {
        $stmt = $this->db->prepare(
            'SELECT client_id, client_name FROM taascor_client '
            . 'WHERE client_name = :client ORDER BY client_id LIMIT 2'
        );
        $stmt->execute([':client' => $client]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($matches) !== 1 || (int)($matches[0]['client_id'] ?? 0) <= 0) {
            return $this->failure(
                'payroll_lock_scope_ambiguous',
                'The payroll client is unknown or ambiguous. No data was changed.'
            );
        }
        return [
            'success' => 1,
            'client_id' => (int)$matches[0]['client_id'],
            'client' => (string)$matches[0]['client_name'],
            'pay_day' => $payDay,
        ];
    }

    private function leaseName(int $clientId, string $payDay): string
    {
        return 'taascor:payroll:' . substr(hash('sha256', $clientId . '|' . $payDay), 0, 40);
    }

    private function failure(string $code, string $message): array
    {
        return [
            'success' => 0,
            'locked' => true,
            'code' => $code,
            'error' => $message,
        ];
    }
}
