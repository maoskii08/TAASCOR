<?php

declare(strict_types=1);

require __DIR__ . '/../model/EmployeeResolutionEngine.php';

function resolution_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

function resolution_employee(int $employeeId, int $clientId, array $overrides = []): array
{
    return array_merge([
        'employee_id' => $employeeId,
        'client_id' => $clientId,
        'payroll_employee_id' => '',
        'old_employee_id' => '',
        'first_name' => 'Juan',
        'middle_name' => '',
        'last_name' => 'Dela Cruz',
        'full_name' => 'Juan Dela Cruz',
        'hire_date' => '2020-01-15',
        'status' => 'Active',
        'separation_date' => null,
    ], $overrides);
}

function resolution_context(array $overrides = []): array
{
    return array_merge([
        'client_id' => 10,
        'source_namespace' => 'template:fuji',
        'period_start' => '2026-06-16',
        'period_end' => '2026-06-30',
    ], $overrides);
}

function resolution_result(array $batch, string $sourceKey): array
{
    foreach ($batch['results'] as $result) {
        if (($result['source_key'] ?? '') === $sourceKey) {
            return $result;
        }
    }
    throw new RuntimeException("Missing resolution result for {$sourceKey}");
}

$engine = new EmployeeResolutionEngine();

resolution_check(
    $engine->normalizeName('  Dóe, João Jr. ') === 'DOE JOAO',
    'Unicode, case, punctuation, whitespace, and suffixes normalize safely'
);
resolution_check(
    $engine->canonicalNameKey('Dela Cruz, Juan P. Jr.') === $engine->canonicalNameKey('JUAN DELA CRUZ'),
    'name order, punctuation, suffix, and middle initial do not change the canonical key'
);
resolution_check(
    $engine->normalizeIdentifier('  fJi-001  ') === 'FJI-001',
    'identifier normalization preserves punctuation while normalizing case and whitespace'
);

$aliasBatch = $engine->resolveBatch(
    [[
        'source_key' => 'alias-source',
        'source_employee_id' => '  vendor-001 ',
        'employee_name' => 'Dela Cruz, Ana María Jr.',
        'hire_date' => '2020-01-15',
    ]],
    [resolution_employee(101, 10, [
        'first_name' => 'Ana Maria',
        'last_name' => 'Dela Cruz',
        'full_name' => 'Ana Maria Dela Cruz',
    ])],
    [[
        'client_id' => 10,
        'source_namespace' => 'template:fuji',
        'source_employee_id' => 'VENDOR-001',
        'employee_id' => 101,
        'status' => 'approved',
    ]],
    resolution_context()
);
$aliasResult = resolution_result($aliasBatch, 'alias-source');
resolution_check($aliasResult['match_basis'] === 'approved_alias', 'effective approved alias is the strongest match basis');
resolution_check($aliasResult['top_score'] === 100.0, 'approved alias receives deterministic score 100');
resolution_check($aliasResult['classification'] === 'auto_eligible_shadow', 'valid deterministic alias is shadow-auto-eligible');
resolution_check((int)$aliasResult['assigned_employee_id'] === 101, 'deterministic alias receives a one-to-one shadow assignment');
resolution_check($aliasBatch['cohort_preview']['contains_personal_data'] === false, 'safe cohort preview is explicitly PII-free');
resolution_check(
    !array_key_exists('results', $aliasBatch['cohort_preview'])
        && strpos((string)json_encode($aliasBatch['cohort_preview']), 'Ana') === false,
    'safe cohort preview excludes row-level candidates and names'
);
$approvedPreview = $engine->buildSafeCohortPreview([[
    'classification' => 'approved',
    'contradictions' => [],
]]);
resolution_check(
    (int)$approvedPreview['classification_counts']['approved'] === 1
        && (int)$approvedPreview['classification_counts']['block'] === 0,
    'owner-approved mappings remain distinct from unresolved hard blocks in cohort summaries'
);

$identifierBatch = $engine->resolveBatch(
    [[
        'source_key' => 'identifier-source',
        'employee_identifier' => 'PAY-200',
        'employee_name' => 'Name supplied differently',
    ]],
    [resolution_employee(200, 10, ['payroll_employee_id' => 'pay-200'])],
    [],
    resolution_context()
);
$identifierResult = resolution_result($identifierBatch, 'identifier-source');
resolution_check($identifierResult['match_basis'] === 'exact_identifier', 'client-scoped payroll identifier is deterministic evidence');
resolution_check($identifierResult['classification'] === 'auto_eligible_shadow', 'unique active exact identifier is shadow-auto-eligible');

$crossClientBatch = $engine->resolveBatch(
    [[
        'source_key' => 'cross-client',
        'client_id' => 10,
        'employee_identifier' => 'OTHER-CLIENT-1',
    ]],
    [resolution_employee(210, 20, ['payroll_employee_id' => 'OTHER-CLIENT-1'])],
    [],
    resolution_context()
);
$crossClient = resolution_result($crossClientBatch, 'cross-client');
resolution_check($crossClient['classification'] === 'block', 'identifier found only under another client is blocked');
resolution_check(
    in_array('cross_client_identifier_match', $crossClient['contradictions'], true),
    'cross-client identifier produces an explicit contradiction flag'
);
resolution_check($crossClient['assigned_employee_id'] === null, 'cross-client candidate is never assigned');

$inactiveBatch = $engine->resolveBatch(
    [[
        'source_key' => 'inactive',
        'employee_identifier' => 'INACTIVE-1',
        'employee_name' => 'Juan Dela Cruz',
        'hire_date' => '2020-01-15',
    ]],
    [resolution_employee(220, 10, [
        'payroll_employee_id' => 'INACTIVE-1',
        'status' => 'Terminated',
        'separation_date' => '2026-05-31',
    ])],
    [],
    resolution_context()
);
$inactive = resolution_result($inactiveBatch, 'inactive');
resolution_check($inactive['classification'] === 'block', 'employee separated before the payroll period is blocked');
resolution_check(
    in_array('inactive_during_payroll_period', $inactive['contradictions'], true),
    'inactive payroll-period status produces an explicit contradiction'
);

$terminatedDuringPeriodBatch = $engine->resolveBatch(
    [[
        'source_key' => 'terminated-during-period',
        'employee_identifier' => 'TERM-230',
    ]],
    [resolution_employee(230, 10, [
        'payroll_employee_id' => 'TERM-230',
        'status' => 'Terminated',
        'separation_date' => '2026-06-20',
    ])],
    [],
    resolution_context()
);
$terminatedDuringPeriod = resolution_result($terminatedDuringPeriodBatch, 'terminated-during-period');
resolution_check(
    $terminatedDuringPeriod['classification'] === 'auto_eligible_shadow',
    'employee terminated during the payroll period remains period-active for identity resolution'
);

$hireConflictBatch = $engine->resolveBatch(
    [[
        'source_key' => 'hire-conflict',
        'employee_name' => 'Juan Dela Cruz',
        'hire_date' => '2021-02-01',
    ]],
    [resolution_employee(240, 10, ['hire_date' => '2020-01-15'])],
    [],
    resolution_context()
);
$hireConflict = resolution_result($hireConflictBatch, 'hire-conflict');
resolution_check($hireConflict['classification'] === 'block', 'name match with conflicting hire date is blocked');
resolution_check(
    in_array('hire_date_conflict', $hireConflict['contradictions'], true),
    'hire-date disagreement is explainable as a hard contradiction'
);

$ambiguousBatch = $engine->resolveBatch(
    [[
        'source_key' => 'ambiguous-name',
        'employee_name' => 'Maria Santos',
    ]],
    [
        resolution_employee(250, 10, [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'full_name' => 'Maria Santos',
        ]),
        resolution_employee(251, 10, [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'full_name' => 'Santos, Maria',
        ]),
    ],
    [],
    resolution_context()
);
$ambiguous = resolution_result($ambiguousBatch, 'ambiguous-name');
resolution_check($ambiguous['classification'] === 'review', 'equal name candidates remain in owner review');
resolution_check($ambiguous['margin'] === 0.0, 'ambiguous candidates expose a zero top-versus-second margin');
resolution_check($ambiguous['classification_reason'] === 'ambiguous_top_candidate', 'ambiguity has an explicit classification reason');
resolution_check($ambiguous['assigned_employee_id'] === null, 'review candidates are not emitted as shadow assignments');

$collisionBatch = $engine->resolveBatch(
    [
        [
            'source_key' => 'collision-a',
            'employee_name' => 'Roberto Reyes',
            'hire_date' => '2020-01-15',
        ],
        [
            'source_key' => 'collision-b',
            'employee_name' => 'Reyes, Roberto',
            'hire_date' => '2020-01-15',
        ],
    ],
    [resolution_employee(260, 10, [
        'first_name' => 'Roberto',
        'last_name' => 'Reyes',
        'full_name' => 'Roberto Reyes',
    ])],
    [],
    resolution_context()
);
foreach (['collision-a', 'collision-b'] as $sourceKey) {
    $collision = resolution_result($collisionBatch, $sourceKey);
    resolution_check($collision['classification'] === 'block', "{$sourceKey} is blocked by equal-priority collision");
    resolution_check(
        in_array('employee_assignment_collision', $collision['contradictions'], true),
        "{$sourceKey} exposes the global target collision"
    );
    resolution_check($collision['assigned_employee_id'] === null, "{$sourceKey} has no duplicate shadow assignment");
}
resolution_check(
    $collisionBatch['cohort_preview']['collision_group_count'] === 1,
    'batch-wide collision preview counts the shared target once'
);

$priorityBatch = $engine->resolveBatch(
    [
        [
            'source_key' => 'alias-winner',
            'source_employee_id' => 'OWNER-APPROVED',
            'employee_name' => 'Roberto Reyes',
            'hire_date' => '2020-01-15',
        ],
        [
            'source_key' => 'fuzzy-loser',
            'employee_name' => 'Reyes, Roberto',
            'hire_date' => '2020-01-15',
        ],
    ],
    [resolution_employee(270, 10, [
        'first_name' => 'Roberto',
        'last_name' => 'Reyes',
        'full_name' => 'Roberto Reyes',
    ])],
    [[
        'client_id' => 10,
        'source_namespace' => 'template:fuji',
        'source_employee_id' => 'OWNER-APPROVED',
        'employee_id' => 270,
        'status' => 'approved',
    ]],
    resolution_context()
);
resolution_check(
    resolution_result($priorityBatch, 'alias-winner')['classification'] === 'auto_eligible_shadow',
    'owner-approved alias retains the unique target over weaker name evidence'
);
resolution_check(
    resolution_result($priorityBatch, 'fuzzy-loser')['classification'] === 'block',
    'weaker source contesting an approved target is blocked'
);

$compositePriorityBatch = $engine->resolveBatch(
    [
        [
            'source_key' => 'composite-winner',
            'employee_name' => 'Santos, Andrea',
            'hire_date' => '2024-03-18',
        ],
        [
            'source_key' => 'similarity-loser',
            'employee_name' => 'Santos, Angela',
            'hire_date' => '2026-06-22',
        ],
    ],
    [resolution_employee(271, 10, [
        'first_name' => 'Andrea',
        'last_name' => 'Santos',
        'full_name' => 'Andrea Santos',
        'hire_date' => '2024-03-18',
    ])],
    [],
    resolution_context()
);
resolution_check(
    resolution_result($compositePriorityBatch, 'composite-winner')['classification'] === 'auto_eligible_shadow',
    'exact-name and exact-hire-date evidence retains the target over weaker name similarity'
);
resolution_check(
    resolution_result($compositePriorityBatch, 'similarity-loser')['classification'] === 'block',
    'weaker name similarity remains blocked after the stronger composite reservation wins'
);

$financialFieldsA = [
    resolution_employee(280, 10, [
        'first_name' => 'Lina',
        'last_name' => 'Garcia',
        'full_name' => 'Lina Garcia',
        'monthly_salary' => 1,
    ]),
    resolution_employee(281, 10, [
        'first_name' => 'Lina',
        'last_name' => 'Garcia',
        'full_name' => 'Lina Garcia',
        'monthly_salary' => 999999,
    ]),
];
$financialFieldsB = $financialFieldsA;
$financialFieldsB[0]['monthly_salary'] = 999999;
$financialFieldsB[1]['monthly_salary'] = 1;
$financialSource = [[
    'source_key' => 'financial-fields-ignored',
    'employee_name' => 'Garcia, Lina',
]];
$financialFirst = resolution_result(
    $engine->resolveBatch($financialSource, $financialFieldsA, [], resolution_context()),
    'financial-fields-ignored'
);
$financialSecond = resolution_result(
    $engine->resolveBatch($financialSource, $financialFieldsB, [], resolution_context()),
    'financial-fields-ignored'
);
resolution_check(
    $financialFirst['proposed_employee_id'] === $financialSecond['proposed_employee_id']
        && $financialFirst['top_score'] === $financialSecond['top_score']
        && $financialFirst['second_score'] === $financialSecond['second_score'],
    'financial fields cannot affect candidate identity, score, or ordering'
);

$untrustedIdentifierBatch = $engine->resolveBatch(
    [[
        'source_key' => 'vendor-identifier-policy',
        'source_employee_id' => '777',
        'employee_name' => 'Santos, Maria',
        'hire_date' => '2020-01-15',
    ]],
    [
        resolution_employee(290, 10, [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'full_name' => 'Maria Santos',
        ]),
        resolution_employee(777, 20, [
            'first_name' => 'Different',
            'last_name' => 'Employee',
            'full_name' => 'Different Employee',
        ]),
    ],
    [],
    resolution_context(),
    ['direct_identifier_matching' => false]
);
$untrustedIdentifier = resolution_result($untrustedIdentifierBatch, 'vendor-identifier-policy');
resolution_check(
    $untrustedIdentifier['classification'] === 'auto_eligible_shadow'
        && (int)$untrustedIdentifier['assigned_employee_id'] === 290,
    'a governed vendor identifier can be excluded from direct HRIS matching while name and hire-date evidence remains usable'
);
resolution_check(
    !in_array('cross_client_identifier_match', $untrustedIdentifier['contradictions'], true),
    'untrusted vendor identifiers do not create false cross-client contradictions'
);

echo "RESULT: Employee resolution engine pure unit tests passed.\n";
