<?php

/**
 * Session-bound containment for the legacy employee staging table.
 *
 * employee_tmp has no owner/session columns, so every application path must
 * require a short-lived, server-issued context before reading or appending an
 * import_id. This does not make employee_tmp a durable workflow store; it
 * prevents callers from choosing or co-mingling import references while the
 * transaction-neutral employee-transfer v2 contract is still unavailable.
 */
final class EmployeeImportStagingContext
{
    public const MAX_BATCH_ROWS = 100;
    public const MAX_TOTAL_ROWS = 1000;
    public const MAX_FILE_BYTES = 5242880; // 5 MiB
    public const MAX_REQUEST_BYTES = 2097152; // 2 MiB per JSON batch request
    public const CONTEXT_TTL_SECONDS = 14400; // 4 hours
    public const MAX_ACTIVE_CONTEXTS = 10;

    private const SESSION_KEY = 'employee_import_staging_contexts';
    private const REQUIRED_COLUMNS = [
        'employee ident',
        'old employee ident',
        'payroll employee id',
        'full name',
        'last name',
        'first name',
        'middle name',
        'hire date',
        'separation date',
        'present address',
        'permanent address',
        'contact number',
        'email address',
        'birthday',
        'birth place',
        'gender',
        'civil status',
        'nationality',
        'emergency person',
        'emergency contact number',
        'client date',
        'position',
        'client',
        'branch',
        'client location',
        'department',
        'tin',
        'sss',
        'philhealth',
        'pag-ibig',
        'bank account number',
        'daily salary',
        'bank name',
        'insurance',
        'annual leaves',
        'employee type',
        'pay type',
    ];

    /**
     * @return array<string,mixed>
     */
    public static function create(
        array &$session,
        string $importId,
        string $actor,
        int $clientId,
        string $clientName,
        string $fileName,
        int $fileSize,
        int $totalRows,
        ?int $now = null
    ): array {
        $now = $now ?? time();
        self::prune($session, $now);

        if (preg_match('/^\d{9}$/', $importId) !== 1) {
            throw new InvalidArgumentException('The server-issued employee import reference is invalid.');
        }
        if ($actor === '' || $clientId <= 0 || trim($clientName) === '') {
            throw new InvalidArgumentException('The authenticated employee import scope is invalid.');
        }
        if ($totalRows < 1 || $totalRows > self::MAX_TOTAL_ROWS) {
            throw new LengthException('Employee imports are limited to 1,000 rows per workbook.');
        }
        if ($fileSize < 1 || $fileSize > self::MAX_FILE_BYTES) {
            throw new LengthException('Employee import workbooks are limited to 5 MiB.');
        }

        $safeFileName = trim(basename(str_replace('\\', '/', $fileName)));
        if ($safeFileName === '' || strlen($safeFileName) > 200) {
            throw new InvalidArgumentException('The employee import file name is invalid.');
        }

        $contexts = $session[self::SESSION_KEY] ?? [];
        if (!is_array($contexts)) {
            $contexts = [];
        }
        if (isset($contexts[$importId])) {
            throw new RuntimeException('The server-issued employee import reference is already active.');
        }
        if (count($contexts) >= self::MAX_ACTIVE_CONTEXTS) {
            throw new LengthException(
                'Too many employee import contexts are active. Review the existing references before starting another upload.'
            );
        }

        $context = [
            'import_id' => $importId,
            'actor' => $actor,
            'client_id' => $clientId,
            'client_name' => trim($clientName),
            'file_name' => $safeFileName,
            'file_size' => $fileSize,
            'total_rows' => $totalRows,
            'staged_rows' => 0,
            'next_batch' => 0,
            'status' => 'initialized',
            'created_at' => $now,
            'expires_at' => $now + self::CONTEXT_TTL_SECONDS,
        ];
        $contexts[$importId] = $context;
        $session[self::SESSION_KEY] = $contexts;

        return $context;
    }

    /**
     * @return array<string,mixed>
     */
    public static function requireForBatch(
        array &$session,
        string $importId,
        string $actor,
        int $clientId,
        int $batch,
        int $declaredTotalRows,
        int $declaredFileSize,
        string $declaredFileName,
        array $rows,
        array $columnMap,
        ?int $now = null
    ): array {
        $context = self::requireOwned($session, $importId, $actor, $now);

        if (!in_array((string)($context['status'] ?? ''), ['initialized', 'staging'], true)) {
            throw new RuntimeException('The employee import context is no longer open for staging.');
        }
        if ($clientId !== (int)$context['client_id']) {
            throw new RuntimeException('The employee import client scope changed after initialization.');
        }
        if ($declaredTotalRows !== (int)$context['total_rows']
            || $declaredFileSize !== (int)$context['file_size']
            || trim(basename(str_replace('\\', '/', $declaredFileName)))
                !== (string)$context['file_name']) {
            throw new RuntimeException('The employee import workbook metadata changed after initialization.');
        }
        if ($batch !== (int)$context['next_batch']) {
            throw new RuntimeException('The employee import batch is missing, duplicated, or out of sequence.');
        }
        if (count($rows) < 1 || count($rows) > self::MAX_BATCH_ROWS) {
            throw new LengthException('Each employee staging request must contain between 1 and 100 rows.');
        }
        if ((int)$context['staged_rows'] + count($rows) > (int)$context['total_rows']) {
            throw new LengthException('The employee import exceeds its declared workbook row count.');
        }

        self::assertColumnMap($columnMap);
        self::assertRowsMatchClient($rows, $columnMap, (string)$context['client_name']);
        self::assertRowsAreBounded($rows);

        return $context;
    }

    /**
     * Mark a successfully committed batch. Call only after the staged row count
     * has been verified in the database.
     *
     * @return array<string,mixed>
     */
    public static function markBatchCommitted(
        array &$session,
        string $importId,
        string $actor,
        int $batch,
        int $addedRows,
        ?int $now = null
    ): array {
        $context = self::requireOwned($session, $importId, $actor, $now);
        if ($batch !== (int)$context['next_batch'] || $addedRows < 1) {
            throw new RuntimeException('The committed employee import batch does not match its server context.');
        }

        $context['staged_rows'] = (int)$context['staged_rows'] + $addedRows;
        $context['next_batch'] = (int)$context['next_batch'] + 1;
        $context['status'] = ((int)$context['staged_rows'] === (int)$context['total_rows'])
            ? 'staged'
            : 'staging';
        $context['expires_at'] = ($now ?? time()) + self::CONTEXT_TTL_SECONDS;
        $session[self::SESSION_KEY][$importId] = $context;

        return $context;
    }

    /**
     * @return array<string,mixed>
     */
    public static function requireComplete(
        array &$session,
        string $importId,
        string $actor,
        ?int $now = null
    ): array {
        $context = self::requireOwned($session, $importId, $actor, $now);
        if ((int)$context['staged_rows'] !== (int)$context['total_rows']
            || (string)$context['status'] !== 'staged') {
            throw new RuntimeException('The employee import is incomplete and cannot be finalized.');
        }
        return $context;
    }

    public static function markFinalizationBlocked(
        array &$session,
        string $importId,
        string $actor,
        ?int $now = null
    ): void {
        $context = self::requireComplete($session, $importId, $actor, $now);
        $context['status'] = 'staged_finalization_blocked';
        $context['expires_at'] = ($now ?? time()) + self::CONTEXT_TTL_SECONDS;
        $session[self::SESSION_KEY][$importId] = $context;
    }

    /**
     * @return array<string,mixed>
     */
    private static function requireOwned(
        array &$session,
        string $importId,
        string $actor,
        ?int $now = null
    ): array {
        self::prune($session, $now ?? time());
        $context = $session[self::SESSION_KEY][$importId] ?? null;
        if (!is_array($context)) {
            throw new RuntimeException('The employee import context is missing or expired. Start a new upload.');
        }
        if (!hash_equals((string)($context['actor'] ?? ''), $actor)) {
            throw new RuntimeException('The employee import belongs to a different authenticated user.');
        }
        return $context;
    }

    private static function assertRowsMatchClient(array $rows, array $columnMap, string $clientName): void
    {
        if (!array_key_exists('client', $columnMap)
            || filter_var($columnMap['client'], FILTER_VALIDATE_INT) === false
            || (int)$columnMap['client'] < 0) {
            throw new InvalidArgumentException('The Client column must be mapped before employee staging.');
        }

        $clientIndex = (int)$columnMap['client'];
        $expected = self::normalizeName($clientName);
        foreach ($rows as $offset => $row) {
            if (!is_array($row) || !array_key_exists($clientIndex, $row)) {
                throw new InvalidArgumentException('Employee import row ' . ($offset + 1) . ' has no mapped Client value.');
            }
            if (self::normalizeName((string)$row[$clientIndex]) !== $expected) {
                throw new RuntimeException(
                    'Employee import row ' . ($offset + 1) . ' does not match the selected client.'
                );
            }
        }
    }

    private static function assertColumnMap(array $columnMap): void
    {
        $keys = array_map(
            static fn(mixed $key): string => strtolower(trim((string)$key)),
            array_keys($columnMap)
        );
        sort($keys);
        $required = self::REQUIRED_COLUMNS;
        sort($required);
        if ($keys !== $required) {
            throw new InvalidArgumentException(
                'Every required employee workbook column must be mapped exactly once.'
            );
        }

        $indexes = [];
        foreach ($columnMap as $index) {
            if (filter_var($index, FILTER_VALIDATE_INT) === false
                || (int)$index < 0
                || (int)$index > 63
                || isset($indexes[(int)$index])) {
                throw new InvalidArgumentException(
                    'Employee workbook column indexes must be unique integers between 0 and 63.'
                );
            }
            $indexes[(int)$index] = true;
        }
    }

    private static function assertRowsAreBounded(array $rows): void
    {
        foreach ($rows as $offset => $row) {
            if (!is_array($row) || count($row) > 64) {
                throw new LengthException('Employee import row ' . ($offset + 1) . ' has too many columns.');
            }
            foreach ($row as $value) {
                if (is_array($value) || is_object($value) || is_resource($value)) {
                    throw new InvalidArgumentException('Employee import cells must contain scalar values.');
                }
                if (is_string($value) && strlen($value) > 5000) {
                    throw new LengthException('An employee import cell exceeds the 5,000-character limit.');
                }
            }
        }
    }

    private static function normalizeName(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function prune(array &$session, int $now): void
    {
        $contexts = $session[self::SESSION_KEY] ?? [];
        if (!is_array($contexts)) {
            $session[self::SESSION_KEY] = [];
            return;
        }

        foreach ($contexts as $id => $context) {
            if (!is_array($context) || (int)($context['expires_at'] ?? 0) < $now) {
                unset($contexts[$id]);
            }
        }
        $session[self::SESSION_KEY] = $contexts;
    }
}
