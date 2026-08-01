<?php

declare(strict_types=1);

/**
 * Fail-closed proof gate for legacy payroll calculation procedures.
 *
 * A procedure is executable only when:
 *  - its definition is readable from INFORMATION_SCHEMA;
 *  - its normalized SHA-256 hash is explicitly approved through
 *    TAASCOR_DTR_SAFE_ROUTINE_HASHES;
 *  - it contains no transaction control, implicit-commit/DDL, dynamic SQL,
 *    or unsafe/unapproved procedure dependency.
 *
 * The observed routine manifest is intentionally not an approval source.
 */
final class DTRCalculationSafetyGate
{
    public const APPROVAL_ENV = 'TAASCOR_DTR_SAFE_ROUTINE_HASHES';
    public const HASH_BASIS = 'sha256-normalized-routine-definition-v1';

    private $db;
    private array $approvedHashes = [];
    private ?string $configurationError = null;

    public function __construct($db, ?array $approvedHashes = null)
    {
        $this->db = $db;
        if ($approvedHashes === null) {
            $this->approvedHashes = $this->approvedHashesFromEnvironment();
            return;
        }
        $this->approvedHashes = $this->normalizeApprovedHashes($approvedHashes);
    }

    public function verifyForCutoff(string $cutOff, string $mode): array
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['individual', 'batch'], true)) {
            return $this->failure(
                'dtr_calculator_mode_invalid',
                '',
                'The requested calculator mode is not supported.'
            );
        }

        $weekly = strcasecmp(trim($cutOff), 'Weekly') === 0;
        $routine = $mode === 'individual'
            ? ($weekly ? 'sp_calculate_indv_dtr_weekly' : 'sp_calculate_indv_dtr')
            : ($weekly ? 'sp_calculate_dtr_weekly' : 'sp_calculate_dtr');

        if ($this->configurationError !== null) {
            return $this->failure(
                'dtr_calculator_approval_config_invalid',
                $routine,
                'The calculator safety approval configuration is invalid.'
            );
        }

        $evidence = [];
        $verified = $this->verifyRoutine($routine, [], $evidence);
        if (($verified['success'] ?? 0) !== 1) {
            return $verified;
        }

        return [
            'success' => 1,
            'code' => 'dtr_calculator_transaction_safe',
            'routine' => $routine,
            'routine_hash' => $verified['routine_hash'],
            'hash_basis' => self::HASH_BASIS,
            'verified_routines' => array_values($evidence),
        ];
    }

    public static function definitionHash(string $definition): string
    {
        return hash('sha256', self::normalizeDefinition($definition));
    }

    private function verifyRoutine(
        string $routine,
        array $stack,
        array &$evidence
    ): array {
        $routine = strtolower(trim($routine));
        if (isset($evidence[$routine])) {
            return [
                'success' => 1,
                'routine' => $routine,
                'routine_hash' => $evidence[$routine]['routine_hash'],
            ];
        }
        if (
            preg_match('/^[a-z0-9_]{1,64}$/', $routine) !== 1
            || in_array($routine, $stack, true)
            || count($stack) >= 16
        ) {
            return $this->failure(
                'dtr_calculator_dependency_invalid',
                $routine,
                'The calculator dependency graph could not be proven safe.'
            );
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT ROUTINE_NAME, ROUTINE_DEFINITION
                 FROM INFORMATION_SCHEMA.ROUTINES
                 WHERE ROUTINE_SCHEMA = DATABASE()
                   AND ROUTINE_TYPE = \'PROCEDURE\'
                   AND LOWER(ROUTINE_NAME) = :routine_name
                 ORDER BY ROUTINE_NAME
                 LIMIT 2'
            );
            $stmt->execute([':routine_name' => $routine]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $error) {
            error_log(
                'DTR calculator safety metadata check failed for '
                . $routine . ': ' . $error->getMessage()
            );
            return $this->failure(
                'dtr_calculator_definition_unavailable',
                $routine,
                'The calculator definition could not be verified.'
            );
        }

        if (count($rows) !== 1) {
            return $this->failure(
                'dtr_calculator_definition_unavailable',
                $routine,
                'The calculator definition is missing or ambiguous.'
            );
        }
        $definition = (string)($rows[0]['ROUTINE_DEFINITION'] ?? '');
        if (trim($definition) === '') {
            return $this->failure(
                'dtr_calculator_definition_unavailable',
                $routine,
                'The calculator definition is not readable by the application account.'
            );
        }

        $hash = self::definitionHash($definition);
        $controls = self::unsafeControls($definition);
        if ($controls !== []) {
            return $this->failure(
                'dtr_calculator_unsafe',
                $routine,
                'The calculator contains transaction-unsafe operations.',
                [
                    'routine_hash' => $hash,
                    'hash_basis' => self::HASH_BASIS,
                    'detected_controls' => $controls,
                ]
            );
        }

        $approvedHash = strtolower(trim((string)($this->approvedHashes[$routine] ?? '')));
        if ($approvedHash === '') {
            return $this->failure(
                'dtr_calculator_unapproved',
                $routine,
                'The transaction-safe calculator definition has not been explicitly approved.',
                [
                    'routine_hash' => $hash,
                    'hash_basis' => self::HASH_BASIS,
                ]
            );
        }
        if (
            preg_match('/^[a-f0-9]{64}$/', $approvedHash) !== 1
            || !hash_equals($approvedHash, $hash)
        ) {
            return $this->failure(
                'dtr_calculator_hash_mismatch',
                $routine,
                'The calculator definition does not match its approved version.',
                [
                    'routine_hash' => $hash,
                    'hash_basis' => self::HASH_BASIS,
                ]
            );
        }

        $stack[] = $routine;
        $dependencies = self::calledProcedures($definition);
        foreach ($dependencies as $dependency) {
            $dependencyResult = $this->verifyRoutine($dependency, $stack, $evidence);
            if (($dependencyResult['success'] ?? 0) !== 1) {
                $dependencyResult['required_by'] = $routine;
                return $dependencyResult;
            }
        }

        $evidence[$routine] = [
            'routine' => $routine,
            'routine_hash' => $hash,
            'dependencies' => $dependencies,
        ];
        return [
            'success' => 1,
            'routine' => $routine,
            'routine_hash' => $hash,
        ];
    }

    private static function normalizeDefinition(string $definition): string
    {
        $definition = str_replace(["\r\n", "\r"], "\n", $definition);
        $definition = preg_replace('/[ \t]+$/m', '', $definition) ?? $definition;
        return trim($definition);
    }

    private static function executableDefinition(string $definition): string
    {
        $definition = preg_replace('~/\*.*?\*/~s', ' ', $definition) ?? $definition;
        $definition = preg_replace('/--[ \t].*(?:\R|$)/', ' ', $definition) ?? $definition;
        $definition = preg_replace('/#[^\r\n]*/', ' ', $definition) ?? $definition;
        $definition = preg_replace(
            "/'(?:''|\\\\.|[^'\\\\])*'/s",
            "''",
            $definition
        ) ?? $definition;
        $definition = preg_replace(
            '/"(?:""|\\\\.|[^"\\\\])*"/s',
            '""',
            $definition
        ) ?? $definition;
        return strtoupper($definition);
    }

    private static function unsafeControls(string $definition): array
    {
        $code = self::executableDefinition($definition);
        $patterns = [
            'START TRANSACTION' => '/\bSTART\s+TRANSACTION\b/',
            'COMMIT' => '/\bCOMMIT\b/',
            'ROLLBACK' => '/\bROLLBACK\b/',
            'AUTOCOMMIT' => '/\bSET\s+(?:SESSION\s+|GLOBAL\s+)?AUTOCOMMIT\b/',
            'SAVEPOINT' => '/\b(?:SAVEPOINT|RELEASE\s+SAVEPOINT)\b/',
            'BEGIN WORK' => '/\bBEGIN\s+WORK\b/',
            'SET TRANSACTION' => '/\bSET\s+(?:SESSION\s+|GLOBAL\s+)?TRANSACTION\b/',
            'DDL' => '/\b(?:ALTER|CREATE|DROP|TRUNCATE|RENAME)\b/',
            'TABLE LOCK' => '/\b(?:LOCK|UNLOCK)\s+TABLES?\b/',
            'IMPLICIT COMMIT ADMIN' => '/\b(?:ANALYZE|OPTIMIZE|REPAIR)\s+TABLE\b/',
            'PRIVILEGE CHANGE' => '/\b(?:GRANT|REVOKE)\b/',
            'FLUSH OR RESET' => '/\b(?:FLUSH|RESET)\b/',
            'DYNAMIC SQL' => '/\b(?:PREPARE|EXECUTE|DEALLOCATE\s+PREPARE)\b/',
        ];
        $detected = [];
        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $code) === 1) {
                $detected[] = $label;
            }
        }
        return $detected;
    }

    private static function calledProcedures(string $definition): array
    {
        $code = self::executableDefinition($definition);
        preg_match_all(
            '/\bCALL\s+(?:`?[A-Z0-9_]+`?\.)?`?([A-Z0-9_]+)`?\s*\(/',
            $code,
            $matches
        );
        $dependencies = array_map(
            static fn(string $name): string => strtolower($name),
            $matches[1] ?? []
        );
        $dependencies = array_values(array_unique($dependencies));
        sort($dependencies, SORT_STRING);
        return $dependencies;
    }

    private function approvedHashesFromEnvironment(): array
    {
        $raw = trim((string)(getenv(self::APPROVAL_ENV) ?: ''));
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            $this->configurationError = 'invalid_json';
            return [];
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            $this->configurationError = 'invalid_map';
            return [];
        }
        return $this->normalizeApprovedHashes($decoded);
    }

    private function normalizeApprovedHashes(array $approvedHashes): array
    {
        $normalized = [];
        foreach ($approvedHashes as $routine => $hash) {
            $routine = strtolower(trim((string)$routine));
            $hash = strtolower(trim((string)$hash));
            if (
                preg_match('/^[a-z0-9_]{1,64}$/', $routine) !== 1
                || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1
            ) {
                $this->configurationError = 'invalid_entry';
                return [];
            }
            $normalized[$routine] = $hash;
        }
        return $normalized;
    }

    private function failure(
        string $code,
        string $routine,
        string $detail,
        array $evidence = []
    ): array {
        return [
            'success' => 0,
            'code' => $code,
            'error' => 'Legacy DTR mutation is blocked because the configured calculator cannot guarantee transaction-safe rollback.',
            'detail' => $detail,
            'mutation_blocked' => true,
            'routine' => $routine,
        ] + $evidence;
    }
}
