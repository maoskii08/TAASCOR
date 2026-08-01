<?php

declare(strict_types=1);

/**
 * TASCA's allowlisted Employee Management reader.
 *
 * This file intentionally selects only directory and organisational fields from
 * employee_list and its lookup tables. It never queries employee_details,
 * employee_govt_account, or employee_salary, and it exposes no mutation method.
 */

/** @return array<string, mixed>|null */
function tasca_employee_intent(string $question): ?array
{
    $clean = trim(preg_replace('/\s+/u', ' ', $question) ?: '');
    $lower = mb_strtolower($clean);

    $mentionsEmployees = preg_match('/\b(employee|employees|staff|workforce|headcount)\b/u', $lower) === 1;
    if (!$mentionsEmployees) {
        return null;
    }

    if (preg_match('/\b(how many|headcount|employee count|count of employees|number of employees)\b/u', $lower) === 1) {
        $status = 'active';
        if (preg_match('/\bterminated\b/u', $lower) === 1) {
            $status = 'terminated';
        } elseif (preg_match('/\bremoved\b/u', $lower) === 1) {
            $status = 'removed';
        } elseif (preg_match('/\b(inactive|non-active|not active)\b/u', $lower) === 1) {
            $status = 'non_active';
        } elseif (preg_match('/\b(all statuses|regardless of status)\b/u', $lower) === 1) {
            $status = 'all';
        }

        $clientName = '';
        if (preg_match(
            '/\bclient\s+["\']?(.+?)["\']?(?=\s+(?:have|has|with|who|that|are|is)\b|[?.,]|$)/iu',
            $clean,
            $match
        ) === 1) {
            $clientName = trim((string)$match[1], " \t\n\r\0\x0B\"'");
        }

        return [
            'type' => 'employee_count',
            'status' => $status,
            'client_name' => mb_substr($clientName, 0, 100),
        ];
    }

    if (preg_match('/\b(list|show|display|give me)\s+(?:all\s+)?(?:the\s+)?employees\b/u', $lower) === 1) {
        return ['type' => 'broad_employee_list'];
    }

    if (preg_match('/\bpayroll\s+employee\s+(?:id|number|#)\s*[:#-]?\s*([A-Za-z0-9_-]{2,40})\b/iu', $clean, $match) === 1) {
        return [
            'type' => 'employee_lookup',
            'lookup_kind' => 'payroll_employee_id',
            'term' => (string)$match[1],
        ];
    }

    if (preg_match('/\bemployee\s+(?:id|number|#)\s*[:#-]?\s*(\d{1,12})\b/iu', $clean, $match) === 1) {
        return [
            'type' => 'employee_lookup',
            'lookup_kind' => 'employee_id',
            'term' => (string)$match[1],
        ];
    }

    if (preg_match(
        '/\b(?:find|show|look\s+up|lookup|search\s+for|locate)\s+(?:the\s+)?(?:employee|staff(?:\s+member)?)\s+(?:named\s+)?["\']?(.+?)["\']?(?:\?|$)/iu',
        $clean,
        $match
    ) === 1) {
        $name = preg_replace('/\s+(?:details|record|profile)$/iu', '', trim((string)$match[1])) ?: '';
        if ($name !== '' && mb_strlen($name) <= 100 && preg_match('/^[\p{L}\p{M} .\'-]+$/u', $name) === 1) {
            return [
                'type' => 'employee_lookup',
                'lookup_kind' => 'name',
                'term' => $name,
            ];
        }
        return ['type' => 'invalid_employee_lookup'];
    }

    return null;
}

function tasca_employee_requests_forbidden_fields(string $question): bool
{
    $lower = mb_strtolower($question);
    foreach ([
        'address', 'atm', 'bank', 'birthday', 'birth place', 'civil status',
        'contact', 'daily salary', 'email', 'emergency', 'government id',
        'pag-ibig', 'pagibig', 'password', 'pay amount', 'payslip amount',
        'philhealth', 'salary', 'sss', 'tin', 'wage',
    ] as $term) {
        if (str_contains($lower, $term)) {
            return true;
        }
    }
    return false;
}

/**
 * @param int[] $clientIds
 * @return array{sql:string,params:array<string, mixed>,scope:string}
 */
function tasca_employee_scope(int $role, array $clientIds): array
{
    $clauses = [];
    $params = [];
    $scope = 'global';

    if (in_array($role, [2, 3, 4], true)) {
        $clauses[] = "(c.client_name IS NULL OR c.client_name NOT IN ('Taasc', 'Taascor'))";
    }

    if ($role === 4) {
        $scope = 'assigned-clients';
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $clientIds),
            static fn(int $value): bool => $value > 0
        )));
        if ($ids === []) {
            $clauses[] = '1 = 0';
        } else {
            $placeholders = [];
            foreach ($ids as $index => $id) {
                $placeholder = ':scope_client_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $id;
            }
            $clauses[] = 'e.client_id IN (' . implode(', ', $placeholders) . ')';
        }
    }

    return [
        'sql' => $clauses === [] ? '' : ' AND ' . implode(' AND ', $clauses),
        'params' => $params,
        'scope' => $scope,
    ];
}

/**
 * @param array<string, mixed> $intent
 * @param int[] $clientIds
 * @return array{sql:string,params:array<string, mixed>,scope:string}
 */
function tasca_employee_count_query(array $intent, int $role, array $clientIds): array
{
    $scope = tasca_employee_scope($role, $clientIds);
    $where = ' WHERE 1 = 1' . $scope['sql'];
    $params = $scope['params'];

    $status = (string)($intent['status'] ?? 'active');
    if ($status === 'non_active') {
        $where .= ' AND e.status <> :active_status';
        $params[':active_status'] = 'Active';
    } elseif ($status !== 'all') {
        $statusValues = [
            'active' => 'Active',
            'terminated' => 'Terminated',
            'removed' => 'Removed',
        ];
        $where .= ' AND e.status = :employee_status';
        $params[':employee_status'] = $statusValues[$status] ?? 'Active';
    }

    $clientName = trim((string)($intent['client_name'] ?? ''));
    if ($clientName !== '') {
        $where .= ' AND LOWER(c.client_name) = LOWER(:client_name)';
        $params[':client_name'] = $clientName;
    }

    return [
        'sql' => 'SELECT COUNT(*)'
            . ' FROM employee_list e'
            . ' LEFT JOIN taascor_client c ON c.client_id = e.client_id'
            . $where,
        'params' => $params,
        'scope' => $scope['scope'],
    ];
}

/**
 * @param array<string, mixed> $intent
 * @param int[] $clientIds
 * @return array{sql:string,params:array<string, mixed>,scope:string}
 */
function tasca_employee_lookup_query(array $intent, int $role, array $clientIds): array
{
    $scope = tasca_employee_scope($role, $clientIds);
    $where = ' WHERE 1 = 1' . $scope['sql'];
    $params = $scope['params'];
    $kind = (string)($intent['lookup_kind'] ?? '');
    $term = trim((string)($intent['term'] ?? ''));

    if ($kind === 'employee_id') {
        $where .= ' AND e.employee_id = :employee_id';
        $params[':employee_id'] = (int)$term;
    } elseif ($kind === 'payroll_employee_id') {
        $where .= ' AND e.payroll_employee_id = :payroll_employee_id';
        $params[':payroll_employee_id'] = $term;
    } else {
        $where .= ' AND LOWER(e.full_name) LIKE LOWER(:employee_name)';
        $params[':employee_name'] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
    }

    return [
        'sql' => 'SELECT e.employee_id, e.payroll_employee_id, e.full_name, e.status,'
            . ' e.hire_date, e.separation_date, e.employee_type,'
            . ' c.client_name, b.branch_name, d.department_name, p.position_name,'
            . ' l.location_name AS client_location'
            . ' FROM employee_list e'
            . ' LEFT JOIN taascor_client c ON c.client_id = e.client_id'
            . ' LEFT JOIN taascor_branch b ON b.branch_id = e.branch_id'
            . ' LEFT JOIN taascor_department d ON d.department_id = e.department_id'
            . ' LEFT JOIN taascor_position p ON p.position_id = e.position_id'
            . ' LEFT JOIN taascor_client_location l ON l.location_id = e.client_location_id'
            . $where
            . ' ORDER BY e.full_name ASC LIMIT 5',
        'params' => $params,
        'scope' => $scope['scope'],
    ];
}

function tasca_employee_display(mixed $value, string $fallback = 'Not recorded'): string
{
    $text = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$value) ?: '');
    return $text === '' ? $fallback : mb_substr($text, 0, 160);
}

/** @param array<string, mixed> $row */
function tasca_employee_format_record(array $row, int $role): string
{
    $parts = [
        tasca_employee_display($row['full_name'] ?? null, 'Unnamed employee'),
        'Employee ID: ' . tasca_employee_display($row['employee_id'] ?? null),
        'Status: ' . tasca_employee_display($row['status'] ?? null),
        'Client: ' . tasca_employee_display($row['client_name'] ?? null),
        'Location: ' . tasca_employee_display($row['client_location'] ?? null),
        'Branch: ' . tasca_employee_display($row['branch_name'] ?? null),
        'Department: ' . tasca_employee_display($row['department_name'] ?? null),
        'Position: ' . tasca_employee_display($row['position_name'] ?? null),
        'Hire date: ' . tasca_employee_display($row['hire_date'] ?? null),
        'Separation date: ' . tasca_employee_display($row['separation_date'] ?? null),
        'Employee type: ' . tasca_employee_display($row['employee_type'] ?? null),
    ];
    if ($role === 3) {
        $parts[] = 'Payroll employee ID: ' . tasca_employee_display($row['payroll_employee_id'] ?? null);
    }
    return implode("\n", $parts);
}

/**
 * @param object $db PDO-compatible connection exposing prepare().
 * @param array<string, mixed> $intent
 * @param int[] $clientIds
 * @return array{text:string,source:string,provider:string,model:string,audit:array<string, mixed>}
 */
function tasca_employee_answer(object $db, string $question, array $intent, int $role, array $clientIds): array
{
    $source = 'Employee Management live read-only data';
    $type = (string)($intent['type'] ?? 'unknown');
    $scope = $role === 4 ? 'assigned-clients' : 'global';
    $audit = ['intent' => $type, 'decision' => 'denied', 'row_count' => 0, 'scope' => $scope];

    $respond = static function (string $text, array $auditData) use ($source): array {
        return [
            'text' => $text,
            'source' => $source,
            'provider' => 'TAASCOR HRIS',
            'model' => 'local-read-policy-v1',
            'audit' => $auditData,
        ];
    };

    if ($role === 5) {
        return $respond(
            'Your C&B role does not include Employee Management record access. TASCA can still provide general HRIS and C&B process guidance.',
            $audit
        );
    }
    if (!in_array($role, [1, 2, 3, 4], true)) {
        return $respond('Employee data access is not available for this account.', $audit);
    }
    if (tasca_employee_requests_forbidden_fields($question)) {
        return $respond(
            'TASCA cannot return salary, banking, government ID, address, contact, credential, or other restricted employee fields. Ask for an approved directory field or an aggregate count.',
            $audit
        );
    }
    if (in_array($type, ['broad_employee_list', 'invalid_employee_lookup'], true)) {
        return $respond(
            'For privacy, TASCA does not return an unrestricted employee list. Ask for an aggregate count or search for one employee by exact name or employee ID.',
            $audit
        );
    }
    if ($type === 'employee_lookup' && $role === 4) {
        return $respond(
            'Coordinator access is limited to read-only employee counts for assigned clients. Ask HR or an Administrator for a named employee lookup.',
            $audit
        );
    }

    if ($type === 'employee_count') {
        $query = tasca_employee_count_query($intent, $role, $clientIds);
        $statement = $db->prepare($query['sql']);
        $statement->execute($query['params']);
        $count = max(0, (int)$statement->fetchColumn());
        $statusLabels = [
            'active' => 'active',
            'terminated' => 'terminated',
            'removed' => 'removed',
            'non_active' => 'non-active',
            'all' => 'all-status',
        ];
        $status = $statusLabels[(string)($intent['status'] ?? 'active')] ?? 'active';
        $client = trim((string)($intent['client_name'] ?? ''));
        $scopeText = $client !== '' ? ' for client ' . tasca_employee_display($client) : '';
        if ($role === 4 && $client === '') {
            $scopeText = ' across your assigned clients';
        }
        $audit = ['intent' => $type, 'decision' => 'allowed', 'row_count' => $count, 'scope' => $query['scope']];
        return $respond(
            'TAASCOR currently shows ' . number_format($count) . ' ' . $status . ' employee' . ($count === 1 ? '' : 's') . $scopeText . '. This is a live, read-only count.',
            $audit
        );
    }

    if ($type === 'employee_lookup') {
        $query = tasca_employee_lookup_query($intent, $role, $clientIds);
        $statement = $db->prepare($query['sql']);
        $statement->execute($query['params']);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $rows = is_array($rows) ? array_slice($rows, 0, 5) : [];
        $audit = ['intent' => $type, 'decision' => 'allowed', 'row_count' => count($rows), 'scope' => $query['scope']];
        if ($rows === []) {
            return $respond('No matching employee was found within your authorized HRIS scope.', $audit);
        }
        if (count($rows) === 1) {
            return $respond(tasca_employee_format_record($rows[0], $role) . "\n\nRead-only result. Restricted personal, banking, government ID, contact, and salary fields were not queried.", $audit);
        }

        $items = [];
        foreach ($rows as $index => $row) {
            $items[] = ($index + 1) . '. '
                . tasca_employee_display($row['full_name'] ?? null, 'Unnamed employee')
                . ' | Employee ID: ' . tasca_employee_display($row['employee_id'] ?? null)
                . ' | Status: ' . tasca_employee_display($row['status'] ?? null)
                . ' | Client: ' . tasca_employee_display($row['client_name'] ?? null);
        }
        return $respond(
            "Multiple authorized matches were found. Refine the search using an exact employee ID:\n" . implode("\n", $items),
            $audit
        );
    }

    return $respond('That Employee Management data request is not supported in TASCA read mode.', $audit);
}
