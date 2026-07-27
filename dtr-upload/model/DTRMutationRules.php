<?php

declare(strict_types=1);

/**
 * Pure validation rules for manual DTR mutations.
 *
 * Keeping request validation outside the controller makes the fail-closed
 * behavior independently testable and prevents invalid values from reaching
 * payroll calculations.
 */
final class DTRMutationRules
{
    private const DELETE_REVIEW_TTL_SECONDS = 600;

    private const DELETE_COUNT_FIELDS = [
        'dtr_upload',
        'payroll_gross_variables',
        'payroll_other_additional',
        'payroll_other_deduction',
        'payroll_summary',
    ];

    private const DATE_FIELDS = ['pay_day', 'start_date', 'end_date'];

    private const NUMERIC_FIELDS = [
        'daily_salary',
        'days_worked',
        'absent',
        'lates',
        'undertime',
        'vacation_leave',
        'sick_leave',
        'overtime',
        'night_diff',
        'night_diff_ot',
        'regular_holiday',
        'regular_holiday_ot',
        'regular_holiday_night_diff',
        'regular_holiday_nd_ot',
        'special_holiday',
        'special_holiday_ot',
        'special_holiday_night_diff',
        'special_holiday_nd_ot',
        'rest_day',
        'rest_day_ot',
        'rest_day_night_diff',
        'rest_day_nd_ot',
        'rd_regular_holiday',
        'rd_regular_holiday_ot',
        'rd_regular_holiday_night_diff',
        'rd_regular_holiday_nd_ot',
        'rd_special_holiday',
        'rd_special_holiday_ot',
        'rd_special_holiday_night_diff',
        'rd_special_holiday_nd_ot',
    ];

    private const DAY_FIELDS = [
        'days_worked',
        'absent',
        'vacation_leave',
        'sick_leave',
    ];

    public static function validateUpdate(array $payload): array
    {
        $client = trim((string)($payload['client_name'] ?? ''));
        $cutOff = trim((string)($payload['cut_off'] ?? ''));
        $employeeId = filter_var(
            $payload['employee_ident'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($client === '' || $cutOff === '' || $employeeId === false) {
            return self::failure(
                'dtr_scope_invalid',
                'A valid employee, client, cutoff, and payroll period are required.'
            );
        }
        $evidence = self::validateBusinessEvidence(
            (string)($payload['change_reason'] ?? ''),
            (string)($payload['change_evidence'] ?? '')
        );
        if (($evidence['success'] ?? 0) !== 1) {
            return $evidence;
        }

        $dates = [];
        foreach (self::DATE_FIELDS as $field) {
            $value = trim((string)($payload[$field] ?? ''));
            $date = self::parseDate($value);
            if (!$date) {
                return self::failure(
                    'dtr_period_invalid',
                    'The DTR start date, end date, and pay date must be valid dates.'
                );
            }
            $dates[$field] = $date;
        }

        if (
            $dates['end_date'] < $dates['start_date']
            || $dates['pay_day'] < $dates['end_date']
        ) {
            return self::failure(
                'dtr_period_invalid',
                'The payroll period must follow start date, end date, then pay date.'
            );
        }

        $periodSpan = (int)$dates['start_date']->diff($dates['end_date'])->days;
        if ($periodSpan >= 30) {
            return self::failure(
                'dtr_period_invalid',
                'The payroll period cannot span 30 days or more.'
            );
        }
        $periodDays = $periodSpan + 1;

        $normalized = [
            'employee_ident' => (int)$employeeId,
            'client_name' => $client,
            'cut_off' => $cutOff,
            'pay_day' => $dates['pay_day']->format('Y-m-d'),
            'start_date' => $dates['start_date']->format('Y-m-d'),
            'end_date' => $dates['end_date']->format('Y-m-d'),
            'change_reason' => $evidence['reason'],
            'change_evidence' => $evidence['evidence'],
        ];

        foreach (self::NUMERIC_FIELDS as $field) {
            $raw = $payload[$field] ?? '';
            $raw = $raw === null || trim((string)$raw) === '' ? '0' : trim((string)$raw);
            if (!is_numeric($raw)) {
                return self::failure(
                    'dtr_numeric_invalid',
                    self::label($field) . ' must be a number.'
                );
            }

            $value = (float)$raw;
            if (!is_finite($value) || $value < 0 || $value > 100000000) {
                return self::failure(
                    'dtr_numeric_invalid',
                    self::label($field) . ' must be a non-negative payroll value.'
                );
            }

            if (in_array($field, self::DAY_FIELDS, true) && $value > $periodDays) {
                return self::failure(
                    'dtr_numeric_invalid',
                    self::label($field) . ' cannot exceed the ' . $periodDays . '-day payroll period.'
                );
            }
            $normalized[$field] = $value;
        }

        return [
            'success' => 1,
            'code' => 'dtr_update_valid',
            'data' => $normalized,
            'period_days' => $periodDays,
        ];
    }

    public static function validateBusinessEvidence(string $reason, string $evidence): array
    {
        $reason = trim($reason);
        $reasonLength = function_exists('mb_strlen') ? mb_strlen($reason) : strlen($reason);
        if ($reasonLength < 10 || $reasonLength > 500) {
            return self::failure(
                'dtr_business_reason_invalid',
                'Enter a business reason between 10 and 500 characters.'
            );
        }

        $evidence = trim($evidence);
        $evidenceLength = function_exists('mb_strlen') ? mb_strlen($evidence) : strlen($evidence);
        if ($evidenceLength < 3 || $evidenceLength > 500) {
            return self::failure(
                'dtr_evidence_invalid',
                'Enter an approval, ticket, source file, or other evidence reference between 3 and 500 characters.'
            );
        }

        return [
            'success' => 1,
            'code' => 'dtr_business_evidence_valid',
            'reason' => $reason,
            'evidence' => $evidence,
        ];
    }

    public static function expectedGovernmentBenefitsConfirmation(
        int $employeeId,
        string $client,
        string $payDay,
        string $cutOff
    ): string {
        return 'REMOVE BENEFITS ' . $employeeId
            . ' ' . trim($client)
            . ' ' . trim($payDay)
            . ' ' . trim($cutOff);
    }

    public static function validateGovernmentBenefitsRemoval(
        $employeeId,
        string $client,
        string $payDay,
        string $cutOff,
        string $confirmation,
        string $reason,
        string $evidence
    ): array {
        $employeeId = filter_var(
            $employeeId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $client = trim($client);
        $payDay = trim($payDay);
        $cutOff = trim($cutOff);
        if ($employeeId === false || $client === '' || $cutOff === '' || !self::parseDate($payDay)) {
            return self::failure(
                'dtr_benefits_scope_invalid',
                'A valid employee, client, pay date, and cutoff are required.'
            );
        }

        $expected = self::expectedGovernmentBenefitsConfirmation(
            (int)$employeeId,
            $client,
            $payDay,
            $cutOff
        );
        if (!hash_equals($expected, trim($confirmation))) {
            return self::failure(
                'dtr_benefits_confirmation_invalid',
                'The typed confirmation does not match the selected employee payroll scope.'
            ) + ['confirmation_phrase' => $expected];
        }

        $businessEvidence = self::validateBusinessEvidence($reason, $evidence);
        if (($businessEvidence['success'] ?? 0) !== 1) {
            return $businessEvidence + ['confirmation_phrase' => $expected];
        }

        return [
            'success' => 1,
            'code' => 'dtr_benefits_removal_authorized',
            'employee_id' => (int)$employeeId,
            'reason' => $businessEvidence['reason'],
            'evidence' => $businessEvidence['evidence'],
            'confirmation_phrase' => $expected,
        ];
    }

    public static function expectedBulkDeleteConfirmation(
        string $client,
        string $payDay,
        ?int $branch = null,
        ?int $clientLocation = null
    ): string
    {
        $phrase = 'DELETE ' . trim($client) . ' ' . trim($payDay);
        if ($branch !== null) {
            $phrase .= ' BRANCH ' . $branch;
        }
        if ($clientLocation !== null) {
            $phrase .= ' LOCATION ' . $clientLocation;
        }
        return $phrase;
    }

    public static function validateBulkDeleteAuthorization(
        string $client,
        string $payDay,
        string $confirmation,
        string $reason,
        string $evidence,
        ?int $branch = null,
        ?int $clientLocation = null
    ): array {
        if ($client === '' || !self::parseDate($payDay)) {
            return self::failure(
                'dtr_delete_scope_invalid',
                'A valid client and pay date are required.'
            );
        }

        $expected = self::expectedBulkDeleteConfirmation(
            $client,
            $payDay,
            $branch,
            $clientLocation
        );
        if (!hash_equals($expected, trim($confirmation))) {
            return self::failure(
                'dtr_delete_confirmation_invalid',
                'The typed confirmation does not match the selected payroll scope.'
            ) + ['confirmation_phrase' => $expected];
        }

        $businessEvidence = self::validateBusinessEvidence($reason, $evidence);
        if (($businessEvidence['success'] ?? 0) !== 1) {
            return $businessEvidence + ['confirmation_phrase' => $expected];
        }

        return [
            'success' => 1,
            'code' => 'dtr_delete_authorized',
            'reason' => $businessEvidence['reason'],
            'evidence' => $businessEvidence['evidence'],
            'confirmation_phrase' => $expected,
        ];
    }

    public static function issueBulkDeleteReviewToken(
        array $scope,
        array $counts,
        string $actor,
        string $secret,
        ?int $issuedAt = null
    ): string {
        $actor = trim($actor);
        $secret = trim($secret);
        if ($actor === '' || $secret === '') {
            throw new InvalidArgumentException('A signed-in actor and review secret are required.');
        }

        $issuedAt = $issuedAt ?? time();
        $claims = [
            'v' => 1,
            'kind' => 'dtr_bulk_delete_review',
            'actor' => $actor,
            'scope' => self::normalizeBulkDeleteReviewScope($scope),
            'counts' => self::normalizeDeleteCounts($counts),
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt + self::DELETE_REVIEW_TTL_SECONDS,
            'nonce' => bin2hex(random_bytes(16)),
        ];
        $payload = self::base64UrlEncode(self::canonicalJson($claims));
        $signature = hash_hmac('sha256', $payload, $secret);
        return $payload . '.' . $signature;
    }

    public static function validateBulkDeleteReviewToken(
        string $token,
        array $scope,
        array $counts,
        string $actor,
        string $secret,
        ?int $now = null
    ): array {
        $token = trim($token);
        if ($token === '') {
            return self::failure(
                'dtr_delete_review_required',
                'Review the exact deletion impact again before deleting records.'
            );
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2 || !preg_match('/^[a-f0-9]{64}$/', $parts[1])) {
            return self::failure(
                'dtr_delete_review_invalid',
                'The deletion review is invalid. Review the records again.'
            );
        }
        [$payload, $submittedSignature] = $parts;
        $expectedSignature = hash_hmac('sha256', $payload, trim($secret));
        if ($secret === '' || !hash_equals($expectedSignature, $submittedSignature)) {
            return self::failure(
                'dtr_delete_review_invalid',
                'The deletion review is invalid. Review the records again.'
            );
        }

        $decoded = self::base64UrlDecode($payload);
        $claims = $decoded === null ? null : json_decode($decoded, true);
        if (
            !is_array($claims)
            || (int)($claims['v'] ?? 0) !== 1
            || ($claims['kind'] ?? '') !== 'dtr_bulk_delete_review'
        ) {
            return self::failure(
                'dtr_delete_review_invalid',
                'The deletion review is invalid. Review the records again.'
            );
        }

        if (!hash_equals(trim((string)($claims['actor'] ?? '')), trim($actor))) {
            return self::failure(
                'dtr_delete_review_actor_changed',
                'The deletion review belongs to a different user. Review the records again.'
            );
        }

        $now = $now ?? time();
        if ((int)($claims['expires_at'] ?? 0) < $now) {
            return self::failure(
                'dtr_delete_review_expired',
                'The deletion review expired. Review the records again.'
            );
        }

        $reviewedScope = self::normalizeBulkDeleteReviewScope(
            is_array($claims['scope'] ?? null) ? $claims['scope'] : []
        );
        $currentScope = self::normalizeBulkDeleteReviewScope($scope);
        if (!hash_equals(self::canonicalJson($reviewedScope), self::canonicalJson($currentScope))) {
            return self::failure(
                'dtr_delete_scope_drift',
                'The selected client, pay date, branch, or location changed. Review the records again.'
            );
        }

        $reviewedCounts = self::normalizeDeleteCounts(
            is_array($claims['counts'] ?? null) ? $claims['counts'] : []
        );
        $currentCounts = self::normalizeDeleteCounts($counts);
        if (!hash_equals(self::canonicalJson($reviewedCounts), self::canonicalJson($currentCounts))) {
            return self::failure(
                'dtr_delete_count_drift',
                'The reviewed payroll population changed. Review the records again.'
            );
        }

        return [
            'success' => 1,
            'code' => 'dtr_delete_review_verified',
            'scope' => $currentScope,
            'counts' => $currentCounts,
        ];
    }

    public static function expectedEmployeeDeleteConfirmation(
        int $employeeId,
        string $client,
        string $payDay,
        string $cutOff
    ): string {
        return 'DELETE EMPLOYEE ' . $employeeId
            . ' ' . trim($client)
            . ' ' . trim($payDay)
            . ' ' . trim($cutOff);
    }

    public static function validateEmployeeDeleteAuthorization(
        $employeeId,
        string $client,
        string $payDay,
        string $cutOff,
        string $confirmation,
        string $reason,
        string $evidence
    ): array {
        $employeeId = filter_var(
            $employeeId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $client = trim($client);
        $payDay = trim($payDay);
        $cutOff = trim($cutOff);
        if ($employeeId === false || $client === '' || $cutOff === '' || !self::parseDate($payDay)) {
            return self::failure(
                'dtr_employee_delete_scope_invalid',
                'A valid employee, client, pay date, and cutoff are required.'
            );
        }

        $expected = self::expectedEmployeeDeleteConfirmation(
            (int)$employeeId,
            $client,
            $payDay,
            $cutOff
        );
        if (!hash_equals($expected, trim($confirmation))) {
            return self::failure(
                'dtr_employee_delete_confirmation_invalid',
                'The typed confirmation does not match the selected employee payroll scope.'
            ) + ['confirmation_phrase' => $expected];
        }

        $businessEvidence = self::validateBusinessEvidence($reason, $evidence);
        if (($businessEvidence['success'] ?? 0) !== 1) {
            return $businessEvidence + ['confirmation_phrase' => $expected];
        }

        return [
            'success' => 1,
            'code' => 'dtr_employee_delete_authorized',
            'employee_id' => (int)$employeeId,
            'reason' => $businessEvidence['reason'],
            'evidence' => $businessEvidence['evidence'],
            'confirmation_phrase' => $expected,
        ];
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            !$date
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            return null;
        }
        return $date;
    }

    private static function label(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }

    private static function normalizeBulkDeleteReviewScope(array $scope): array
    {
        return [
            'client' => trim((string)($scope['client'] ?? '')),
            'pay_day' => trim((string)($scope['pay_day'] ?? '')),
            'branch' => isset($scope['branch']) && $scope['branch'] !== ''
                ? (int)$scope['branch']
                : null,
            'client_location' => isset($scope['client_location'])
                && $scope['client_location'] !== ''
                ? (int)$scope['client_location']
                : null,
        ];
    }

    private static function normalizeDeleteCounts(array $counts): array
    {
        $normalized = [];
        foreach (self::DELETE_COUNT_FIELDS as $field) {
            $normalized[$field] = max(0, (int)($counts[$field] ?? 0));
        }
        return $normalized;
    }

    private static function canonicalJson(array $value): string
    {
        $normalize = static function ($item) use (&$normalize) {
            if (!is_array($item)) {
                return $item;
            }
            if (array_is_list($item)) {
                return array_map($normalize, $item);
            }
            ksort($item, SORT_STRING);
            foreach ($item as $key => $child) {
                $item[$key] = $normalize($child);
            }
            return $item;
        };
        $json = json_encode(
            $normalize($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode the deletion review.');
        }
        return $json;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return null;
        }
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }

    private static function failure(string $code, string $message): array
    {
        return [
            'success' => 0,
            'code' => $code,
            'error' => $message,
        ];
    }
}
