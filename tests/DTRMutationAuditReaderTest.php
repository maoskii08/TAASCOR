<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/audit-log/model/AuditLog.php';

function dtr_audit_reader_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

function dtr_audit_canonical_json(array $payload): string
{
    $normalize = static function ($value) use (&$normalize) {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map($normalize, $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $child) {
            $value[$key] = $normalize($child);
        }
        return $value;
    };
    return json_encode(
        $normalize($payload),
        JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
    );
}

final class DtrAuditReaderFakeStatement
{
    private DtrAuditReaderFakeDb $db;
    private string $sql;
    private array $params = [];

    public function __construct(DtrAuditReaderFakeDb $db, string $sql)
    {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function bindValue(string $name, $value, int $type = PDO::PARAM_STR): bool
    {
        $this->params[$name] = $value;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        if (is_array($params)) {
            $this->params = array_replace($this->params, $params);
        }
        $this->db->queries[] = ['sql' => $this->sql, 'params' => $this->params];
        return true;
    }

    public function fetchColumn()
    {
        if (str_contains($this->sql, 'information_schema.tables')) {
            return $this->db->tableExists ? 1 : 0;
        }
        if (
            str_contains($this->sql, 'SELECT COUNT(*)')
            && str_contains($this->sql, 'FROM dtr_mutation_audit_events')
        ) {
            return count($this->db->listRows);
        }
        return 0;
    }

    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array
    {
        if (str_contains($this->sql, 'information_schema.columns')) {
            return $mode === PDO::FETCH_COLUMN
                ? $this->db->columns
                : array_map(static fn(string $column): array => ['COLUMN_NAME' => $column], $this->db->columns);
        }
        if (
            str_contains($this->sql, 'FROM dtr_mutation_audit_events')
            && str_contains($this->sql, 'ORDER BY created_at DESC')
        ) {
            $offset = (int)($this->params[':page_offset'] ?? 0);
            $limit = (int)($this->params[':page_size'] ?? 25);
            return array_slice($this->db->listRows, $offset, $limit);
        }
        return [];
    }

    public function fetch(int $mode = PDO::FETCH_ASSOC)
    {
        if (str_contains($this->sql, 'OCTET_LENGTH(change_reason)')) {
            if ($this->db->detailMeta === null) {
                return false;
            }
            $meta = $this->db->detailMeta;
            if (str_contains($this->sql, 'NULL AS scope_hash')) {
                $meta['scope_kind'] = null;
                $meta['branch_id'] = null;
                $meta['client_location_id'] = null;
                $meta['scope_hash'] = null;
                $meta['scope_payload_bytes'] = 0;
            }
            return $meta;
        }
        if (
            str_contains($this->sql, 'SELECT change_reason, evidence_reference')
            && str_contains($this->sql, 'before_payload')
        ) {
            if ($this->db->detailPayloads === null) {
                return false;
            }
            $payloads = $this->db->detailPayloads;
            if (str_contains($this->sql, 'NULL AS scope_payload')) {
                $payloads['scope_payload'] = null;
            }
            return $payloads;
        }
        return false;
    }
}

final class DtrAuditReaderFakeDb
{
    public bool $tableExists = true;
    public array $columns = [];
    public array $listRows = [];
    public ?array $detailMeta = null;
    public ?array $detailPayloads = null;
    public array $queries = [];

    public function prepare(string $sql): DtrAuditReaderFakeStatement
    {
        return new DtrAuditReaderFakeStatement($this, $sql);
    }
}

function dtr_audit_reader_fixture(): DtrAuditReaderFakeDb
{
    $before = [
        'payroll_summary' => ['net_pay' => '900.00', 'employee_id' => 42],
        'dtr_upload' => ['daily_worked' => 10.0, 'employee_id' => 42],
        'review_rows' => [
            ['row_key' => 'B', 'value' => 2],
            ['row_key' => 'A', 'value' => 1],
        ],
    ];
    $after = [
        'dtr_upload' => ['employee_id' => 42, 'daily_worked' => 11.0],
        'payroll_summary' => ['employee_id' => 42, 'net_pay' => '1000.00'],
        'review_rows' => [
            ['value' => 2, 'row_key' => 'B'],
            ['value' => 1, 'row_key' => 'A'],
        ],
    ];
    $scope = [
        'pay_day' => '2026-06-30',
        'employee_id' => 42,
        'client_name' => 'FUJIFILM',
        'cut_off' => 'Semi Monthly',
    ];
    $beforePayload = dtr_audit_canonical_json($before);
    $afterPayload = dtr_audit_canonical_json($after);
    $scopePayload = dtr_audit_canonical_json($scope);
    $eventUid = 'DTRM-' . str_repeat('A', 32);

    $db = new DtrAuditReaderFakeDb();
    $db->columns = [
        'id',
        'event_uid',
        'operation',
        'scope_kind',
        'employee_id',
        'client_name',
        'cut_off',
        'branch_id',
        'client_location_id',
        'period_start',
        'period_end',
        'pay_day',
        'actor',
        'change_reason',
        'evidence_reference',
        'before_payload',
        'after_payload',
        'before_hash',
        'after_hash',
        'scope_payload',
        'scope_hash',
        'calculator_routine',
        'calculator_hash',
        'created_at',
    ];
    $db->listRows = [
        [
            'event_uid' => $eventUid,
            'operation' => 'EDIT',
            'scope_kind' => 'EMPLOYEE',
            'employee_id' => 42,
            'client_name' => 'FUJIFILM',
            'cut_off' => 'Semi Monthly',
            'branch_id' => 1,
            'client_location_id' => 2,
            'period_start' => '2026-06-16',
            'period_end' => '2026-06-30',
            'pay_day' => '2026-06-30',
            'actor' => 'payroll.officer',
            'calculator_routine' => 'sp_calculate_indv_dtr_v2',
            'calculator_hash' => str_repeat('d', 64),
            'created_at' => '2026-07-27 10:00:00',
        ],
        [
            'event_uid' => 'DTRM-' . str_repeat('B', 32),
            'operation' => 'DELETE_BULK',
            'scope_kind' => 'PAYROLL_SCOPE',
            'employee_id' => null,
            'client_name' => 'FUJIFILM',
            'cut_off' => null,
            'branch_id' => null,
            'client_location_id' => null,
            'period_start' => null,
            'period_end' => null,
            'pay_day' => '2026-06-30',
            'actor' => 'payroll.officer',
            'calculator_routine' => null,
            'calculator_hash' => null,
            'created_at' => '2026-07-27 09:00:00',
        ],
    ];
    $db->detailMeta = array_merge($db->listRows[0], [
        'before_hash' => hash('sha256', $beforePayload),
        'after_hash' => hash('sha256', $afterPayload),
        'scope_hash' => hash('sha256', $scopePayload),
        'change_reason_bytes' => strlen('Approved DTR correction'),
        'evidence_reference_bytes' => strlen('TICKET-2026-0042'),
        'before_payload_bytes' => strlen($beforePayload),
        'after_payload_bytes' => strlen($afterPayload),
        'scope_payload_bytes' => strlen($scopePayload),
    ]);
    $db->detailPayloads = [
        'change_reason' => 'Approved DTR correction',
        'evidence_reference' => 'TICKET-2026-0042',
        'before_payload' => $beforePayload,
        'after_payload' => $afterPayload,
        'scope_payload' => $scopePayload,
    ];
    return $db;
}

$root = dirname(__DIR__);
$controllerSource = (string)file_get_contents($root . '/audit-log/controller/AuditLogController.php');
$modelSource = (string)file_get_contents($root . '/audit-log/model/AuditLog.php');
$pageSource = (string)file_get_contents($root . '/audit-log/index.php');
$scriptSource = (string)file_get_contents($root . '/audit-log/js/audit-log.js');

dtr_audit_reader_check(
    str_contains($controllerSource, "case 'get-dtr-mutation-audit-events':")
        && str_contains($controllerSource, "case 'get-dtr-mutation-audit-event':"),
    'controller exposes separate bounded DTR list and exact-event endpoints'
);
dtr_audit_reader_check(
    str_contains($modelSource, 'DTR_MAX_PAGE_SIZE = 100')
        && str_contains($modelSource, 'OCTET_LENGTH(before_payload)')
        && str_contains($modelSource, 'DTR_MAX_PAYLOAD_BYTES'),
    'reader bounds list pages and long-form evidence before decoding'
);
dtr_audit_reader_check(
    str_contains($modelSource, 'storedCanonicalHashIsValid')
        && str_contains($modelSource, "hash('sha256', \$storedPayload)")
        && str_contains($modelSource, "'before_hash_valid'")
        && str_contains($modelSource, "'scope_hash_valid'"),
    'server verifies exact stored canonical before, after, and scope JSON strings'
);
dtr_audit_reader_check(
    str_contains($pageSource, 'DTR Change Evidence')
        && str_contains($pageSource, 'id="dtrEvidenceModal"')
        && str_contains($scriptSource, "get('dtr_event')")
        && str_contains($scriptSource, 'HASH MISMATCH'),
    'UI provides searchable evidence, integrity detail, and dtr_event deep links'
);
dtr_audit_reader_check(
    strpos($scriptSource, 'loadActionTypes();') > strpos($scriptSource, '$(document).ready(function ()'),
    'action-type POST waits for DOM ready and the shared CSRF hook'
);
dtr_audit_reader_check(
    preg_match(
        '/public function getDtrMutationEvents\\(\\): array(.*?)private function tableExists/s',
        $modelSource,
        $readerMethods
    ) === 1
        && !preg_match('/\\bINSERT\\s+INTO\\b|\\bUPDATE\\s+[a-z_`]|\\bDELETE\\s+FROM\\b/i', $readerMethods[1]),
    'DTR evidence methods remain read-only'
);

$db = dtr_audit_reader_fixture();
$model = new AuditLog();
$model->db = $db;
$model->page = 1;
$model->page_size = 1;
$list = $model->getDtrMutationEvents();
dtr_audit_reader_check(
    ($list['success'] ?? 0) === 1
        && ($list['returned'] ?? 0) === 1
        && ($list['total'] ?? 0) === 2
        && ($list['has_more'] ?? false) === true,
    'fake list query returns one bounded page with deterministic continuation state'
);

$detail = $model->getDtrMutationEvent('dtrm-' . str_repeat('a', 32));
dtr_audit_reader_check(
    ($detail['success'] ?? 0) === 1
        && ($detail['data']['before_hash_valid'] ?? false) === true
        && ($detail['data']['after_hash_valid'] ?? false) === true
        && ($detail['data']['scope_hash_valid'] ?? false) === true
        && ($detail['data']['hash_valid'] ?? false) === true,
    'valid canonical fake evidence verifies all three hashes server-side'
);
dtr_audit_reader_check(
    ($detail['data']['before']['dtr_upload']['daily_worked'] ?? null) === 10.0
        && ($detail['data']['scope']['employee_id'] ?? null) === 42
        && ($detail['data']['before']['review_rows'][0]['row_key'] ?? '') === 'B'
        && ($detail['data']['before']['review_rows'][1]['row_key'] ?? '') === 'A',
    'verified detail reconstructs evidence and preserves the writer-sorted list order'
);

$mismatchDb = dtr_audit_reader_fixture();
$mismatchDb->detailMeta['before_hash'] = str_repeat('0', 64);
$mismatchModel = new AuditLog();
$mismatchModel->db = $mismatchDb;
$mismatch = $mismatchModel->getDtrMutationEvent('DTRM-' . str_repeat('A', 32));
dtr_audit_reader_check(
    ($mismatch['success'] ?? 0) === 1
        && ($mismatch['data']['before_hash_valid'] ?? true) === false
        && ($mismatch['data']['hash_valid'] ?? true) === false
        && ($mismatch['data']['integrity_status'] ?? '') === 'hash_mismatch',
    'hash mismatch is returned explicitly and never represented as verified'
);

$legacyDb = dtr_audit_reader_fixture();
$legacyDb->columns = array_values(array_diff(
    $legacyDb->columns,
    ['scope_kind', 'branch_id', 'client_location_id', 'scope_payload', 'scope_hash']
));
$legacyModel = new AuditLog();
$legacyModel->db = $legacyDb;
$legacy = $legacyModel->getDtrMutationEvent('DTRM-' . str_repeat('A', 32));
dtr_audit_reader_check(
    ($legacy['success'] ?? 0) === 1
        && ($legacy['data']['scope_evidence_supported'] ?? true) === false
        && array_key_exists('scope_hash_valid', $legacy['data'])
        && $legacy['data']['scope_hash_valid'] === null
        && ($legacy['data']['hash_valid'] ?? false) === true,
    'legacy audit schema remains readable while clearly marking scope evidence unavailable'
);

$nonCanonicalDb = dtr_audit_reader_fixture();
$nonCanonicalBefore = '{"payroll_summary":{"net_pay":"900.00","employee_id":42},"dtr_upload":{"daily_worked":10.0,"employee_id":42}}';
$nonCanonicalDb->detailPayloads['before_payload'] = $nonCanonicalBefore;
$nonCanonicalDb->detailMeta['before_payload_bytes'] = strlen($nonCanonicalBefore);
$nonCanonicalDb->detailMeta['before_hash'] = hash('sha256', $nonCanonicalBefore);
$nonCanonicalModel = new AuditLog();
$nonCanonicalModel->db = $nonCanonicalDb;
$nonCanonical = $nonCanonicalModel->getDtrMutationEvent('DTRM-' . str_repeat('A', 32));
dtr_audit_reader_check(
    ($nonCanonical['success'] ?? 0) === 1
        && ($nonCanonical['data']['before_hash_valid'] ?? true) === false
        && ($nonCanonical['data']['hash_valid'] ?? true) === false,
    'a matching hash cannot disguise a non-canonical stored JSON payload'
);

$oversizeDb = dtr_audit_reader_fixture();
$oversizeDb->detailMeta['before_payload_bytes'] = 524289;
$oversizeModel = new AuditLog();
$oversizeModel->db = $oversizeDb;
$oversize = $oversizeModel->getDtrMutationEvent('DTRM-' . str_repeat('A', 32));
dtr_audit_reader_check(
    ($oversize['success'] ?? 1) === 0
        && ($oversize['error_code'] ?? '') === 'dtr_mutation_audit_detail_too_large',
    'oversized detail fails closed before evidence payload retrieval'
);
dtr_audit_reader_check(
    count(array_filter(
        $oversizeDb->queries,
        static fn(array $query): bool => str_contains($query['sql'], 'SELECT change_reason, evidence_reference')
    )) === 0,
    'oversized metadata gate prevents the long-payload query'
);

$invalidPageDb = dtr_audit_reader_fixture();
$invalidPageModel = new AuditLog();
$invalidPageModel->db = $invalidPageDb;
$invalidPageModel->page_size = 101;
$invalidPage = $invalidPageModel->getDtrMutationEvents();
dtr_audit_reader_check(
    ($invalidPage['success'] ?? 1) === 0
        && str_contains((string)($invalidPage['error'] ?? ''), 'exceeds the allowed range'),
    'page sizes above the server maximum are rejected'
);

$missingDb = dtr_audit_reader_fixture();
$missingDb->tableExists = false;
$missingModel = new AuditLog();
$missingModel->db = $missingDb;
$missing = $missingModel->getDtrMutationEvents();
dtr_audit_reader_check(
    ($missing['success'] ?? 1) === 0
        && ($missing['error_code'] ?? '') === 'dtr_mutation_audit_schema_missing',
    'missing audit migration produces a clear read-only unavailable state'
);

echo "RESULT: DTR mutation audit reader checks passed.\n";
