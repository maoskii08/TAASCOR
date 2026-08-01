<?php

declare(strict_types=1);

/**
 * Pure, explainable employee resolver for staged DTR batches.
 *
 * The engine performs no database writes and never turns a shadow decision into
 * an approved identity mapping. Callers provide source identities, HRIS
 * employees, approved aliases, and payroll-period context as arrays.
 */
final class EmployeeResolutionEngine
{
    public const VERSION = '1.0.0';
    public const MODE = 'shadow_only';

    private const DEFAULT_POLICY = [
        'auto_score_min' => 98.0,
        'auto_margin_min' => 10.0,
        'review_score_min' => 80.0,
        'candidate_name_score_min' => 55.0,
        'candidate_limit' => 5,
        'require_payroll_period' => true,
        'direct_identifier_matching' => true,
    ];

    private const SUFFIXES = [
        'JR', 'JNR', 'SR', 'SNR', 'II', 'III', 'IV', 'V', 'VI',
    ];

    /**
     * Resolve a complete client batch without persisting any decision.
     *
     * Source fields accepted:
     * - source_key, client_id, source_namespace
     * - source_employee_id or employee_identifier
     * - employee_name or source_employee_name
     * - hire_date, period_start, period_end
     *
     * Employee fields accepted:
     * - employee_id, client_id, status
     * - payroll_employee_id, old_employee_id
     * - first_name, middle_name, last_name, full_name
     * - hire_date, separation_date or termination_date
     *
     * Alias fields accepted:
     * - client_id, source_namespace, source_employee_id, employee_id, status
     * - effective_from, effective_to
     */
    public function resolveBatch(
        array $sources,
        array $employees,
        array $approvedAliases = [],
        array $context = [],
        array $policyOverrides = []
    ): array {
        $policy = $this->policy($policyOverrides);
        $preparedEmployees = $this->prepareEmployees($employees);
        $preparedAliases = $this->prepareAliases($approvedAliases);
        $evaluations = [];

        foreach (array_values($sources) as $index => $source) {
            if (!is_array($source)) {
                $source = [];
            }
            $evaluations[] = $this->evaluateSource(
                $source,
                $index,
                $preparedEmployees,
                $preparedAliases,
                $context,
                $policy
            );
        }

        $collisionGroupCount = $this->applyGlobalOneToOneAssignment($evaluations);
        foreach ($evaluations as &$evaluation) {
            $this->classify($evaluation, $policy);
        }
        unset($evaluation);

        $preview = $this->buildSafeCohortPreview($evaluations, $policy);
        $preview['collision_group_count'] = $collisionGroupCount;

        return [
            'engine_version' => self::VERSION,
            'mode' => self::MODE,
            'policy' => $policy,
            'results' => $evaluations,
            'cohort_preview' => $preview,
        ];
    }

    /**
     * Normalize Unicode, case, punctuation, whitespace, and common suffixes
     * while retaining the source token order.
     */
    public function normalizeName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = $normalized;
                $value = (string)preg_replace('/\p{Mn}+/u', '', $value);
            }
        }

        // Windows iconv can split precomposed Latin characters into separate
        // tokens when ext-intl is unavailable. Normalize the common employee
        // name characters deterministically before the iconv fallback.
        $value = strtr($value, [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'Ç' => 'C', 'ç' => 'c',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ñ' => 'N', 'ñ' => 'n',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ý' => 'Y', 'Ÿ' => 'Y', 'ý' => 'y', 'ÿ' => 'y',
            'Æ' => 'AE', 'æ' => 'ae', 'Œ' => 'OE', 'œ' => 'oe',
        ]);

        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($ascii !== false) {
                $value = $ascii;
            }
        }
        $value = function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
        $value = (string)preg_replace('/[^A-Z0-9]+/', ' ', $value);
        $tokens = array_values(array_filter(explode(' ', trim($value)), static function ($token) {
            return $token !== '' && !in_array($token, self::SUFFIXES, true);
        }));
        return implode(' ', $tokens);
    }

    /**
     * Return an order-independent key. Middle initials are ignored only when
     * at least two substantive name tokens remain.
     */
    public function canonicalNameKey(string $value): string
    {
        $tokens = array_values(array_filter(explode(' ', $this->normalizeName($value))));
        $substantive = array_values(array_filter($tokens, static function ($token) {
            return strlen($token) > 1;
        }));
        if (count($substantive) >= 2) {
            $tokens = $substantive;
        }
        sort($tokens, SORT_STRING);
        return implode(' ', $tokens);
    }

    /**
     * Identifiers remain punctuation-sensitive and zero-sensitive. Only
     * Unicode/case/outer whitespace and repeated internal whitespace change.
     */
    public function normalizeIdentifier(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_KC);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }
        $value = function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
        return trim((string)preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Build an aggregate preview that contains no employee names, source
     * identifiers, employee IDs, or candidate details.
     */
    public function buildSafeCohortPreview(array $results, array $policyOverrides = []): array
    {
        $policy = $this->policy($policyOverrides);
        $counts = [
            'auto_eligible_shadow' => 0,
            'approved' => 0,
            'review' => 0,
            'block' => 0,
        ];
        $contradictionCounts = [];
        $collisionSources = 0;
        $hardContradictionSources = 0;

        foreach ($results as $result) {
            $classification = (string)($result['classification'] ?? 'block');
            if (!array_key_exists($classification, $counts)) {
                $classification = 'block';
            }
            $counts[$classification]++;

            $codes = array_values(array_unique(array_map('strval', $result['contradictions'] ?? [])));
            if ($codes) {
                $hardContradictionSources++;
            }
            foreach ($codes as $code) {
                $contradictionCounts[$code] = ($contradictionCounts[$code] ?? 0) + 1;
            }
            if (in_array('employee_assignment_collision', $codes, true)) {
                $collisionSources++;
            }
        }

        ksort($contradictionCounts, SORT_STRING);
        $total = count($results);
        $autoRate = $total > 0 ? round(($counts['auto_eligible_shadow'] / $total) * 100, 2) : 0.0;

        return [
            'contains_personal_data' => false,
            'mode' => self::MODE,
            'total_sources' => $total,
            'classification_counts' => $counts,
            'auto_eligible_shadow_rate' => $autoRate,
            'collision_source_count' => $collisionSources,
            'hard_contradiction_source_count' => $hardContradictionSources,
            'contradiction_counts' => $contradictionCounts,
            'release_gate' => ($counts['review'] + $counts['block']) > 0
                ? 'blocked_owner_review_required'
                : 'shadow_cohort_ready_for_validation',
            'thresholds' => [
                'auto_score_min' => $policy['auto_score_min'],
                'auto_margin_min' => $policy['auto_margin_min'],
                'review_score_min' => $policy['review_score_min'],
            ],
        ];
    }

    private function policy(array $overrides): array
    {
        $policy = array_merge(self::DEFAULT_POLICY, array_intersect_key($overrides, self::DEFAULT_POLICY));
        $policy['auto_score_min'] = max(0.0, min(100.0, (float)$policy['auto_score_min']));
        $policy['auto_margin_min'] = max(0.0, min(100.0, (float)$policy['auto_margin_min']));
        $policy['review_score_min'] = max(0.0, min(100.0, (float)$policy['review_score_min']));
        $policy['candidate_name_score_min'] = max(0.0, min(100.0, (float)$policy['candidate_name_score_min']));
        $policy['candidate_limit'] = max(2, min(20, (int)$policy['candidate_limit']));
        $policy['require_payroll_period'] = (bool)$policy['require_payroll_period'];
        $policy['direct_identifier_matching'] = (bool)$policy['direct_identifier_matching'];
        return $policy;
    }

    private function prepareEmployees(array $employees): array
    {
        $prepared = [];
        foreach (array_values($employees) as $employee) {
            if (!is_array($employee)) {
                continue;
            }
            $employeeId = (int)($employee['employee_id'] ?? 0);
            $clientId = (int)($employee['client_id'] ?? 0);
            if ($employeeId <= 0 || $clientId <= 0) {
                continue;
            }

            $first = trim((string)($employee['first_name'] ?? ''));
            $middle = trim((string)($employee['middle_name'] ?? ''));
            $last = trim((string)($employee['last_name'] ?? ''));
            $full = trim((string)($employee['full_name'] ?? ''));
            $variants = array_values(array_unique(array_filter([
                $full,
                trim($first . ' ' . $middle . ' ' . $last),
                trim($first . ' ' . $last),
                trim($last . ' ' . $first . ' ' . $middle),
                trim($last . ' ' . $first),
            ])));

            $identifierFields = [];
            foreach (['employee_id', 'payroll_employee_id', 'old_employee_id'] as $field) {
                $value = $field === 'employee_id'
                    ? (string)$employeeId
                    : (string)($employee[$field] ?? '');
                $value = $this->normalizeIdentifier($value);
                if ($value !== '') {
                    $identifierFields[$field] = $value;
                }
            }

            $displayName = trim($first . ' ' . $middle . ' ' . $last);
            if ($displayName === '') {
                $displayName = $full;
            }

            $prepared[] = [
                'employee_id' => $employeeId,
                'client_id' => $clientId,
                'status' => trim((string)($employee['status'] ?? '')),
                'display_name' => preg_replace('/\s+/', ' ', $displayName),
                'name_variants' => $variants,
                'identifier_fields' => $identifierFields,
                'hire_date' => $this->normalizeDate((string)($employee['hire_date'] ?? '')),
                'separation_date' => $this->normalizeDate((string)(
                    $employee['separation_date']
                    ?? $employee['termination_date']
                    ?? $employee['terminated_at']
                    ?? ''
                )),
            ];
        }
        return $prepared;
    }

    private function prepareAliases(array $aliases): array
    {
        $prepared = [];
        foreach (array_values($aliases) as $alias) {
            if (!is_array($alias)) {
                continue;
            }
            if (strcasecmp(trim((string)($alias['status'] ?? 'approved')), 'approved') !== 0) {
                continue;
            }
            $sourceIdentifier = $this->normalizeIdentifier((string)(
                $alias['source_employee_id']
                ?? $alias['source_identifier']
                ?? ''
            ));
            $employeeId = (int)($alias['employee_id'] ?? 0);
            $clientId = (int)($alias['client_id'] ?? 0);
            if ($sourceIdentifier === '' || $employeeId <= 0 || $clientId <= 0) {
                continue;
            }
            $prepared[] = [
                'client_id' => $clientId,
                'source_namespace' => trim((string)($alias['source_namespace'] ?? '')),
                'source_identifier' => $sourceIdentifier,
                'employee_id' => $employeeId,
                'effective_from' => $this->normalizeDate((string)($alias['effective_from'] ?? '')),
                'effective_to' => $this->normalizeDate((string)($alias['effective_to'] ?? '')),
            ];
        }
        return $prepared;
    }

    private function evaluateSource(
        array $source,
        int $index,
        array $employees,
        array $aliases,
        array $context,
        array $policy
    ): array {
        $sourceKey = trim((string)($source['source_key'] ?? ''));
        if ($sourceKey === '') {
            $sourceKey = 'source:' . ($index + 1);
        }
        $clientId = (int)($source['client_id'] ?? $context['client_id'] ?? 0);
        $namespace = trim((string)($source['source_namespace'] ?? $context['source_namespace'] ?? ''));
        $identifier = $this->normalizeIdentifier((string)(
            $source['source_employee_id']
            ?? $source['employee_identifier']
            ?? ''
        ));
        $sourceName = trim((string)(
            $source['employee_name']
            ?? $source['source_employee_name']
            ?? ''
        ));
        $sourceHireDate = $this->normalizeDate((string)($source['hire_date'] ?? ''));
        $periodStart = $this->normalizeDate((string)($source['period_start'] ?? $context['period_start'] ?? ''));
        $periodEnd = $this->normalizeDate((string)($source['period_end'] ?? $context['period_end'] ?? ''));

        $result = [
            'source_key' => $sourceKey,
            'classification' => 'block',
            'classification_reason' => 'not_evaluated',
            'proposed_employee_id' => null,
            'one_to_one_candidate_id' => null,
            'assigned_employee_id' => null,
            'top_score' => 0.0,
            'second_score' => 0.0,
            'margin' => 0.0,
            'match_basis' => 'none',
            'explanations' => [],
            'contradictions' => [],
            'contradiction_details' => [],
            'warnings' => [],
            'candidates' => [],
            'collision_group_size' => 0,
        ];

        if ($clientId <= 0) {
            $this->addContradiction($result, 'missing_client_scope', 'A client scope is required before matching employees.');
        }
        if ($identifier === '' && $this->canonicalNameKey($sourceName) === '') {
            $this->addContradiction($result, 'missing_source_identity', 'The DTR source has neither an employee identifier nor a usable name.');
        }
        if ($identifier !== '' && !$policy['direct_identifier_matching']) {
            $result['warnings'][] = $this->detail(
                'direct_identifier_disabled_by_source_policy',
                'This source format uses its employee identifier only for approved aliases, not as an HRIS identifier.',
                'warning'
            );
        }
        if ($policy['require_payroll_period'] && ($periodStart === '' || $periodEnd === '')) {
            $this->addContradiction($result, 'missing_payroll_period', 'A complete payroll period is required to verify employment status.');
        } elseif ($periodStart !== '' && $periodEnd !== '' && $periodStart > $periodEnd) {
            $this->addContradiction($result, 'invalid_payroll_period', 'The payroll period start is after its end.');
        }

        $referenceDate = $periodEnd !== ''
            ? $periodEnd
            : $this->normalizeDate((string)($context['as_of_date'] ?? ''));
        $matchingAliases = [];
        $crossClientAliases = [];
        foreach ($aliases as $alias) {
            if ($identifier === '' || $alias['source_identifier'] !== $identifier) {
                continue;
            }
            if ($alias['source_namespace'] !== $namespace) {
                continue;
            }
            if (!$this->aliasIsEffective($alias, $periodStart, $periodEnd, $referenceDate)) {
                continue;
            }
            if ($alias['client_id'] === $clientId) {
                $matchingAliases[] = $alias;
            } else {
                $crossClientAliases[] = $alias;
            }
        }
        if (!$matchingAliases && $crossClientAliases) {
            $this->addContradiction($result, 'cross_client_approved_alias', 'The approved alias exists only under another client.');
        }

        $aliasTargetIds = array_values(array_unique(array_map(static function ($alias) {
            return (int)$alias['employee_id'];
        }, $matchingAliases)));
        if (count($aliasTargetIds) > 1) {
            $this->addContradiction($result, 'ambiguous_approved_alias', 'The same approved source alias points to multiple HRIS employees.');
        }

        $scopedEmployees = [];
        $employeeById = [];
        $sameClientIdentifierMatches = [];
        $otherClientIdentifierMatches = [];
        foreach ($employees as $employee) {
            $employeeById[$employee['employee_id']][] = $employee;
            if ($employee['client_id'] === $clientId) {
                $scopedEmployees[] = $employee;
            }
            if (!$policy['direct_identifier_matching']
                || $identifier === ''
                || !in_array($identifier, $employee['identifier_fields'], true)) {
                continue;
            }
            if ($employee['client_id'] === $clientId) {
                $sameClientIdentifierMatches[] = $employee;
            } else {
                $otherClientIdentifierMatches[] = $employee;
            }
        }
        if (!$sameClientIdentifierMatches && $otherClientIdentifierMatches) {
            $this->addContradiction($result, 'cross_client_identifier_match', 'The source identifier matches an employee under another client only.');
        }
        if (count($sameClientIdentifierMatches) > 1) {
            $this->addContradiction($result, 'duplicate_client_identifier', 'The source identifier matches multiple employees within the client.');
        }

        foreach ($aliasTargetIds as $aliasTargetId) {
            $targets = $employeeById[$aliasTargetId] ?? [];
            if (!$targets) {
                $this->addContradiction($result, 'approved_alias_target_missing', 'An approved alias points to an HRIS employee that is not available to the resolver.');
                continue;
            }
            $hasScopedTarget = false;
            foreach ($targets as $target) {
                if ($target['client_id'] === $clientId) {
                    $hasScopedTarget = true;
                    break;
                }
            }
            if (!$hasScopedTarget) {
                $this->addContradiction($result, 'approved_alias_target_cross_client', 'An approved alias points to an employee under another client.');
            }
        }

        $directTargetIds = array_values(array_unique(array_map(static function ($employee) {
            return (int)$employee['employee_id'];
        }, $sameClientIdentifierMatches)));
        if ($aliasTargetIds && $directTargetIds && !array_intersect($aliasTargetIds, $directTargetIds)) {
            $this->addContradiction($result, 'alias_identifier_conflict', 'The approved alias and the direct HRIS identifier point to different employees.');
        }

        $sourceNameKey = $this->canonicalNameKey($sourceName);
        $sourceTokenCount = $sourceNameKey === '' ? 0 : count(explode(' ', $sourceNameKey));
        foreach ($scopedEmployees as $employee) {
            $isAlias = in_array($employee['employee_id'], $aliasTargetIds, true);
            $identifierFields = [];
            if ($policy['direct_identifier_matching'] && $identifier !== '') {
                foreach ($employee['identifier_fields'] as $field => $value) {
                    if ($value === $identifier) {
                        $identifierFields[] = $field;
                    }
                }
            }
            $isIdentifier = (bool)$identifierFields;
            $nameEvidence = $this->bestNameEvidence($sourceName, $employee['name_variants']);

            if (!$isAlias && !$isIdentifier && $nameEvidence['score'] < $policy['candidate_name_score_min']) {
                continue;
            }

            $employment = $this->employmentDuringPeriod($employee, $periodStart, $periodEnd);
            $hireEvidence = 'missing';
            if ($sourceHireDate !== '' && $employee['hire_date'] !== '') {
                $hireEvidence = $sourceHireDate === $employee['hire_date'] ? 'exact' : 'conflict';
            }

            if ($isAlias) {
                $score = 100.0;
                $basis = 'approved_alias';
                $priority = 4;
            } elseif ($isIdentifier) {
                $score = 99.0;
                $basis = 'exact_identifier';
                $priority = 3;
            } else {
                $score = $nameEvidence['score'] * 0.90;
                if ($hireEvidence === 'exact') {
                    $score += 6.0;
                } elseif ($hireEvidence === 'conflict') {
                    $score -= 8.0;
                }
                if ($employment['active']) {
                    $score += 2.0;
                } else {
                    $score -= 15.0;
                }
                $score = max(0.0, min(100.0, $score));
                $basis = $hireEvidence === 'exact' ? 'name_and_exact_hire_date' : 'name_similarity';
                $priority = $hireEvidence === 'exact' ? 2 : 1;
            }

            $candidate = [
                'employee_id' => $employee['employee_id'],
                'employee_name' => $employee['display_name'],
                'score' => round($score, 2),
                'match_basis' => $basis,
                'priority' => $priority,
                'signals' => [
                    'client_scope_exact' => true,
                    'approved_alias' => $isAlias,
                    'identifier_fields' => $identifierFields,
                    'name_score' => $nameEvidence['score'],
                    'name_order_independent_exact' => $nameEvidence['exact'],
                    'hire_date_evidence' => $hireEvidence,
                    'active_during_payroll_period' => $employment['active'],
                    'employment_reason' => $employment['reason'],
                ],
                'explanations' => [],
                'contradictions' => [],
                'warnings' => [],
            ];
            $candidate['explanations'][] = $this->detail('client_scope_exact', 'Candidate belongs to the source client.', 'evidence');
            if ($isAlias) {
                $candidate['explanations'][] = $this->detail('approved_alias', 'An effective owner-approved alias points to this employee.', 'deterministic');
            }
            if ($isIdentifier) {
                $candidate['explanations'][] = $this->detail('exact_identifier', 'The source identifier exactly matches: ' . implode(', ', $identifierFields) . '.', 'deterministic');
            }
            if ($nameEvidence['score'] > 0.0) {
                $candidate['explanations'][] = $this->detail('name_similarity', 'Order-independent normalized name score: ' . number_format($nameEvidence['score'], 2) . '.', 'evidence');
            }
            if ($hireEvidence === 'exact') {
                $candidate['explanations'][] = $this->detail('exact_hire_date', 'The source and HRIS hire dates are equal.', 'evidence');
            } elseif ($hireEvidence === 'conflict') {
                $candidate['contradictions'][] = $this->detail('hire_date_conflict', 'The source and HRIS hire dates disagree.', 'hard');
            }
            if (!$employment['active']) {
                $candidate['contradictions'][] = $this->detail('inactive_during_payroll_period', 'The employee is not active during the payroll period.', 'hard');
            } else {
                $candidate['explanations'][] = $this->detail('active_during_payroll_period', 'Employment dates and status cover the payroll period.', 'evidence');
            }
            if (($isAlias || $isIdentifier) && $sourceNameKey !== '' && $nameEvidence['score'] < 55.0) {
                $candidate['warnings'][] = $this->detail('name_disagreement', 'The deterministic identity evidence is stronger than the supplied name, which should still be reviewed during pilot validation.', 'warning');
            }
            if (!$isAlias && !$isIdentifier && $sourceTokenCount < 2) {
                $candidate['warnings'][] = $this->detail('insufficient_name_detail', 'Name-only evidence has fewer than two substantive tokens.', 'warning');
            }
            $result['candidates'][] = $candidate;
        }

        usort($result['candidates'], static function ($left, $right) {
            $scoreOrder = $right['score'] <=> $left['score'];
            if ($scoreOrder !== 0) {
                return $scoreOrder;
            }
            $priorityOrder = $right['priority'] <=> $left['priority'];
            if ($priorityOrder !== 0) {
                return $priorityOrder;
            }
            return $left['employee_id'] <=> $right['employee_id'];
        });
        $result['candidates'] = array_slice($result['candidates'], 0, $policy['candidate_limit']);

        if (!$result['candidates']) {
            $this->addContradiction($result, 'no_client_scoped_candidate', 'No sufficiently similar candidate exists within the source client.');
            return $result;
        }

        $top = $result['candidates'][0];
        $second = $result['candidates'][1] ?? null;
        $result['proposed_employee_id'] = $top['employee_id'];
        $result['top_score'] = $top['score'];
        $result['second_score'] = $second ? $second['score'] : 0.0;
        $result['margin'] = round($result['top_score'] - $result['second_score'], 2);
        $result['match_basis'] = $top['match_basis'];
        $result['proposal_priority'] = $top['priority'];
        $result['source_name_token_count'] = $sourceTokenCount;
        foreach ($top['explanations'] as $detail) {
            $result['explanations'][] = $detail;
        }
        foreach ($top['warnings'] as $detail) {
            $result['warnings'][] = $detail;
        }
        foreach ($top['contradictions'] as $detail) {
            $this->addContradiction($result, $detail['code'], $detail['message']);
        }
        $result['explanations'][] = $this->detail(
            'top_vs_second_margin',
            'Top score ' . number_format($result['top_score'], 2)
                . '; second score ' . number_format($result['second_score'], 2)
                . '; margin ' . number_format($result['margin'], 2) . '.',
            'evidence'
        );

        return $result;
    }

    /**
     * Reserve at most one source per target. Deterministic evidence wins over
     * weaker proposals; equal-priority collisions are withheld for every source.
     */
    private function applyGlobalOneToOneAssignment(array &$evaluations): int
    {
        $groups = [];
        foreach ($evaluations as $index => $evaluation) {
            $employeeId = (int)($evaluation['proposed_employee_id'] ?? 0);
            if ($employeeId > 0) {
                $groups[$employeeId][] = $index;
            }
        }

        $collisionGroups = 0;
        foreach ($groups as $indexes) {
            if (count($indexes) === 1) {
                $index = $indexes[0];
                $evaluations[$index]['one_to_one_candidate_id'] = $evaluations[$index]['proposed_employee_id'];
                continue;
            }

            $collisionGroups++;
            $maxPriority = max(array_map(static function ($index) use (&$evaluations) {
                return (int)($evaluations[$index]['proposal_priority'] ?? 0);
            }, $indexes));
            $winners = array_values(array_filter($indexes, static function ($index) use (&$evaluations, $maxPriority) {
                return (int)($evaluations[$index]['proposal_priority'] ?? 0) === $maxPriority;
            }));

            if (count($winners) === 1) {
                $winner = $winners[0];
                $evaluations[$winner]['one_to_one_candidate_id'] = $evaluations[$winner]['proposed_employee_id'];
                $evaluations[$winner]['collision_group_size'] = count($indexes);
                $evaluations[$winner]['warnings'][] = $this->detail(
                    'target_contested_by_weaker_match',
                    'Another source proposed this target using weaker evidence; deterministic evidence retained the shadow reservation.',
                    'warning'
                );
                foreach ($indexes as $index) {
                    if ($index === $winner) {
                        continue;
                    }
                    $evaluations[$index]['collision_group_size'] = count($indexes);
                    $this->addContradiction(
                        $evaluations[$index],
                        'employee_assignment_collision',
                        'Another source has stronger evidence for the same HRIS employee.'
                    );
                }
                continue;
            }

            foreach ($indexes as $index) {
                $evaluations[$index]['collision_group_size'] = count($indexes);
                $this->addContradiction(
                    $evaluations[$index],
                    'employee_assignment_collision',
                    'Multiple sources have equal-priority claims on the same HRIS employee.'
                );
            }
        }
        return $collisionGroups;
    }

    private function classify(array &$result, array $policy): void
    {
        if ($result['contradictions']) {
            $result['classification'] = 'block';
            $result['classification_reason'] = $result['contradictions'][0];
            $result['assigned_employee_id'] = null;
            return;
        }
        if ((int)($result['one_to_one_candidate_id'] ?? 0) <= 0) {
            $this->addContradiction($result, 'no_unique_batch_assignment', 'The batch did not produce a unique one-to-one candidate.');
            $result['classification'] = 'block';
            $result['classification_reason'] = 'no_unique_batch_assignment';
            return;
        }

        $deterministic = in_array($result['match_basis'], ['approved_alias', 'exact_identifier'], true);
        $strongComposite = $result['top_score'] >= $policy['auto_score_min']
            && $result['margin'] >= $policy['auto_margin_min']
            && (int)($result['source_name_token_count'] ?? 0) >= 2;

        if ($deterministic || $strongComposite) {
            $result['classification'] = 'auto_eligible_shadow';
            $result['classification_reason'] = $deterministic
                ? 'deterministic_identity_evidence'
                : 'strong_composite_evidence';
            $result['assigned_employee_id'] = $result['one_to_one_candidate_id'];
            return;
        }
        if ($result['top_score'] >= $policy['review_score_min']) {
            $result['classification'] = 'review';
            $result['classification_reason'] = $result['margin'] < $policy['auto_margin_min']
                ? 'ambiguous_top_candidate'
                : 'evidence_below_auto_threshold';
            $result['assigned_employee_id'] = null;
            return;
        }

        $this->addContradiction($result, 'insufficient_match_evidence', 'Candidate evidence is below the owner-review threshold.');
        $result['classification'] = 'block';
        $result['classification_reason'] = 'insufficient_match_evidence';
        $result['assigned_employee_id'] = null;
    }

    private function bestNameEvidence(string $sourceName, array $candidateNames): array
    {
        $bestScore = 0.0;
        $exact = false;
        foreach ($candidateNames as $candidateName) {
            $score = $this->nameSimilarity($sourceName, (string)$candidateName);
            if ($score > $bestScore) {
                $bestScore = $score;
            }
            if ($score >= 100.0) {
                $exact = true;
            }
        }
        return ['score' => round($bestScore, 2), 'exact' => $exact];
    }

    private function nameSimilarity(string $left, string $right): float
    {
        $leftKey = $this->canonicalNameKey($left);
        $rightKey = $this->canonicalNameKey($right);
        if ($leftKey === '' || $rightKey === '') {
            return 0.0;
        }
        if ($leftKey === $rightKey) {
            return 100.0;
        }

        $leftTokens = array_values(array_unique(explode(' ', $leftKey)));
        $rightTokens = array_values(array_unique(explode(' ', $rightKey)));
        $intersection = count(array_intersect($leftTokens, $rightTokens));
        $union = count(array_unique(array_merge($leftTokens, $rightTokens)));
        $minimum = min(count($leftTokens), count($rightTokens));
        $jaccard = $union > 0 ? ($intersection / $union) * 100.0 : 0.0;
        $containment = $minimum > 0 ? ($intersection / $minimum) * 96.0 : 0.0;
        $maxLength = max(strlen($leftKey), strlen($rightKey));
        $edit = $maxLength > 0
            ? (1.0 - (levenshtein($leftKey, $rightKey) / $maxLength)) * 100.0
            : 0.0;
        return max(0.0, min(100.0, max($jaccard, $containment, $edit)));
    }

    private function employmentDuringPeriod(array $employee, string $periodStart, string $periodEnd): array
    {
        if ($periodStart === '' || $periodEnd === '' || $periodStart > $periodEnd) {
            return ['active' => false, 'reason' => 'payroll_period_unavailable'];
        }
        if ($employee['hire_date'] !== '' && $employee['hire_date'] > $periodEnd) {
            return ['active' => false, 'reason' => 'hire_date_after_period'];
        }
        if ($employee['separation_date'] !== '' && $employee['separation_date'] < $periodStart) {
            return ['active' => false, 'reason' => 'separated_before_period'];
        }

        $status = strtolower(trim((string)$employee['status']));
        if ($status === 'active') {
            return ['active' => true, 'reason' => 'active_status_and_dates_cover_period'];
        }
        if ($status === 'terminated' && $employee['separation_date'] !== '' && $employee['separation_date'] >= $periodStart) {
            return ['active' => true, 'reason' => 'terminated_during_or_after_period'];
        }
        return ['active' => false, 'reason' => 'status_not_active_for_period'];
    }

    private function aliasIsEffective(
        array $alias,
        string $periodStart,
        string $periodEnd,
        string $referenceDate
    ): bool
    {
        if ($alias['effective_from'] === '' && $alias['effective_to'] === '') {
            return true;
        }
        if ($periodStart !== '' && $periodEnd !== '') {
            if ($alias['effective_from'] !== '' && $alias['effective_from'] > $periodStart) {
                return false;
            }
            if ($alias['effective_to'] !== '' && $alias['effective_to'] < $periodEnd) {
                return false;
            }
            return true;
        }
        if ($referenceDate === '') {
            return false;
        }
        if ($alias['effective_from'] !== '' && $alias['effective_from'] > $referenceDate) {
            return false;
        }
        if ($alias['effective_to'] !== '' && $alias['effective_to'] < $referenceDate) {
            return false;
        }
        return true;
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00') {
            return '';
        }
        $formats = ['!Y-m-d', '!m/d/Y', '!n/j/Y', '!Y/m/d', '!d-M-Y', '!d M Y'];
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date instanceof DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }
        return '';
    }

    private function addContradiction(array &$result, string $code, string $message): void
    {
        if (!in_array($code, $result['contradictions'], true)) {
            $result['contradictions'][] = $code;
            $result['contradiction_details'][] = $this->detail($code, $message, 'hard');
        }
    }

    private function detail(string $code, string $message, string $severity): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'severity' => $severity,
        ];
    }
}
