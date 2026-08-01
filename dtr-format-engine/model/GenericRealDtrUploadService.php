<?php

class GenericRealDtrUploadService
{
    public $db = null;
    public ?DtrAdapterRegistry $registry = null;
    public ?SyntheticUploadParser $tabularParser = null;
    public ?FujiPayrollSummaryAdapter $fujiAdapter = null;

    public function stage(array $post, array $file, string $user): array
    {
        $clientId = (int)($post['client_id'] ?? 0);
        $profileId = (int)($post['adapter_profile_id'] ?? 0);
        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $periodEnd = trim((string)($post['period_end'] ?? ''));

        if ($clientId <= 0) {
            return ['success' => 0, 'error' => 'Select a client.'];
        }
        if ($extension === '') {
            return ['success' => 0, 'error' => 'Select a DTR file.'];
        }
        if (!$this->registry || !$this->tabularParser || !$this->fujiAdapter) {
            return ['success' => 0, 'error' => 'The governed DTR intake service is unavailable.'];
        }

        try {
            $detection = null;
            if ($profileId <= 0) {
                $inspection = $this->tabularParser->inspectRealUpload($file);
                if (empty($inspection['success'])) {
                    return $inspection;
                }
                $detection = $this->registry->detectApprovedProfile(
                    $clientId,
                    (string)$inspection['extension'],
                    (array)$inspection['headers'],
                    $periodEnd
                );
                if (empty($detection['success'])) {
                    return $detection;
                }
                $profileId = (int)$detection['profile_id'];
            }
            $profile = $this->registry->approvedProfile($profileId, $clientId, $extension, $periodEnd);
            if (!$profile) {
                return [
                    'success' => 0,
                    'error' => 'The selected adapter is not approved, effective, client-bound, or compatible with this file.',
                ];
            }

            $parserKey = (string)$profile['parser_key'];
            if ($parserKey === 'template_tabular_v1') {
                $result = $this->tabularParser->uploadReal($post, $file, $user, $profile);
            } elseif ($parserKey === 'period_summary_workbook_v1') {
                $result = $this->tabularParser->uploadPeriodSummary($post, $file, $user, $profile);
            } elseif ($parserKey === 'fuji_payroll_summary_v1') {
                $result = $this->fujiAdapter->stageUpload($post, $file, $user, $profile);
            } else {
                return ['success' => 0, 'error' => 'The approved adapter parser is not available.'];
            }

            if (empty($result['success']) || empty($result['batch_id'])) {
                return $result;
            }
            if (!$this->verifyBatchBinding((int)$result['batch_id'], $profile)) {
                return [
                    'success' => 0,
                    'error' => 'The staged batch did not retain its approved client and adapter identity.',
                ];
            }
            $result['adapter_match'] = [
                'profile_id' => (int)$profile['id'],
                'adapter_key' => (string)$profile['adapter_key'],
                'adapter_version' => (string)$profile['adapter_version'],
                'configuration_hash' => (string)$profile['configuration_hash'],
                'identity_policy' => (string)$profile['identity_policy'],
                'match_method' => $detection['match_method'] ?? 'explicit_approved_profile',
                'confidence' => isset($detection['confidence']) ? (float)$detection['confidence'] : 1.0,
            ];
            return $result;
        } catch (Throwable $error) {
            error_log('Generic real DTR intake failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => 'Unable to stage the DTR file with the approved adapter.'];
        }
    }

    private function verifyBatchBinding(int $batchId, array $profile): bool
    {
        $stmt = $this->db->prepare("
            SELECT client_id, location_id, template_id, adapter_profile_id,
                   adapter_key, adapter_version, adapter_config_hash,
                   identity_policy, was_truncated, row_count,
                   accepted_row_count, rejected_row_count
            FROM dtr_upload_batches
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $batchId]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$batch) {
            return false;
        }
        return (int)$batch['client_id'] === (int)$profile['client_id']
            && (int)$batch['template_id'] === (int)$profile['template_id']
            && (int)$batch['adapter_profile_id'] === (int)$profile['id']
            && hash_equals((string)$profile['configuration_hash'], (string)$batch['adapter_config_hash'])
            && (string)$batch['adapter_key'] === (string)$profile['adapter_key']
            && (string)$batch['adapter_version'] === (string)$profile['adapter_version']
            && (string)$batch['identity_policy'] === (string)$profile['identity_policy']
            && (int)$batch['was_truncated'] === 0
            && (int)$batch['row_count']
                === ((int)$batch['accepted_row_count'] + (int)$batch['rejected_row_count']);
    }
}
