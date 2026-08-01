<?php

class BulkSampleTemplateIntegrator
{
    public $db = null;

    private const SAMPLE_CONTEXT = 'real_sample_batch10_profile_adapter';
    private const GENERIC_ERROR = 'Unable to run bulk sample template integration. Please contact your administrator.';

    public function run(string $user): array
    {
        try {
            $sampleDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Sample Data';
            $auditDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'audit_reports';
            $regressionDir = $auditDir . DIRECTORY_SEPARATOR . 'regression' . DIRECTORY_SEPARATOR . 'dtr_engine_sprint4';
            if (!is_dir($regressionDir)) {
                mkdir($regressionDir, 0777, true);
            }

            $files = $this->sampleFiles($sampleDir);
            $existingProfiles = $this->loadProfiles();
            $existingByFilename = [];
            foreach ($existingProfiles as $profile) {
                $existingByFilename[strtolower((string)($profile['filename'] ?? ''))] = $profile;
            }

            $inventory = [];
            $profiles = [];
            $mappingQueue = [];
            $clusters = [];

            foreach ($files as $file) {
                $profile = $existingByFilename[strtolower($file['name'])] ?? null;
                $profileWasExisting = is_array($profile) && in_array((string)($profile['key'] ?? ''), ['COXON', 'CYA', 'DELTA'], true);
                $analysis = $this->analyzeSample($file, $profileWasExisting ? $profile : null);
                $inventory[] = $analysis['inventory'];

                if (!$profileWasExisting) {
                    $profile = $analysis['profile'];
                } else {
                    $profile = $this->mergeProfileMetadata($profile, $analysis);
                }

                $profiles[] = $profile;
                $cluster = (string)$analysis['inventory']['format_cluster'];
                $clusters[$cluster] = ($clusters[$cluster] ?? 0) + 1;

                if ($analysis['mapping_required']) {
                    $mappingQueue[] = $analysis['mapping_queue'];
                }
            }

            $payload = [
                'version' => '2026-06-21-sprint4',
                'scope' => 'bulk-sample-template-integration-local-only',
                'generated_at' => date('Y-m-d H:i:s'),
                'profiles' => $profiles,
            ];
            $this->saveProfiles($payload);

            require_once(__DIR__ . '/RealSampleAdapter.php');
            $adapter = new RealSampleAdapter();
            $adapter->db = $this->db;
            $adapterRun = $adapter->runAdapters($user);
            $coverage = $this->coverageMatrix($inventory, $profiles, $adapterRun, $mappingQueue);
            $mappingQueue = $this->augmentMappingQueueFromCoverage($mappingQueue, $coverage);
            $coverage = $this->coverageMatrix($inventory, $profiles, $adapterRun, $mappingQueue);

            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'sample_inventory.csv', $inventory);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'profile_coverage_matrix.csv', $coverage);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'owner_mapping_queue.csv', $mappingQueue);

            $summary = $this->summary($inventory, $coverage, $mappingQueue, $clusters, $adapterRun);
            $this->writeReport($auditDir . DIRECTORY_SEPARATOR . 'dtr_engine_sprint4_bulk_sample_template_integration.md', $summary, $coverage, $mappingQueue);

            return [
                'success' => 1,
                'summary' => $summary,
                'clusters' => $clusters,
                'adapter_run' => $adapterRun,
                'files' => [
                    'sample_inventory' => $regressionDir . DIRECTORY_SEPARATOR . 'sample_inventory.csv',
                    'profile_coverage_matrix' => $regressionDir . DIRECTORY_SEPARATOR . 'profile_coverage_matrix.csv',
                    'owner_mapping_queue' => $regressionDir . DIRECTORY_SEPARATOR . 'owner_mapping_queue.csv',
                    'report' => $auditDir . DIRECTORY_SEPARATOR . 'dtr_engine_sprint4_bulk_sample_template_integration.md',
                ],
            ];
        } catch (\Throwable $th) {
            error_log('DTR Sprint 4 bulk integration failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function runSprint5(string $user): array
    {
        try {
            $sampleDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Sample Data';
            $auditDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'audit_reports';
            $regressionDir = $auditDir . DIRECTORY_SEPARATOR . 'regression' . DIRECTORY_SEPARATOR . 'dtr_engine_sprint5';
            $convertedDir = $regressionDir . DIRECTORY_SEPARATOR . 'converted';
            if (!is_dir($convertedDir)) {
                mkdir($convertedDir, 0777, true);
            }

            $files = $this->sampleFiles($sampleDir);
            $existingProfiles = $this->loadProfiles();
            $existingByFilename = [];
            foreach ($existingProfiles as $profile) {
                $existingByFilename[strtolower((string)($profile['filename'] ?? ''))] = $profile;
            }

            $inventory = [];
            $profiles = [];
            $mappingQueue = [];
            $clusters = [];

            foreach ($files as $file) {
                $profile = $existingByFilename[strtolower($file['name'])] ?? null;
                $profileWasExisting = is_array($profile) && in_array((string)($profile['key'] ?? ''), ['COXON', 'CYA', 'DELTA'], true);
                $analysis = $this->analyzeSample($file, $profileWasExisting ? $profile : null);
                $inventory[] = $analysis['inventory'];

                if (!$profileWasExisting) {
                    $profile = $analysis['profile'];
                } else {
                    $profile = $this->mergeProfileMetadata($profile, $analysis);
                }

                $profiles[] = $profile;
                $cluster = (string)$analysis['inventory']['format_cluster'];
                $clusters[$cluster] = ($clusters[$cluster] ?? 0) + 1;

                if ($analysis['mapping_required']) {
                    $mappingQueue[] = $analysis['mapping_queue'];
                }
            }

            $payload = [
                'version' => '2026-06-21-sprint5',
                'scope' => 'bulk-adapter-gap-closure-local-only',
                'generated_at' => date('Y-m-d H:i:s'),
                'profiles' => $profiles,
            ];
            $this->saveProfiles($payload);

            require_once(__DIR__ . '/RealSampleAdapter.php');
            $adapter = new RealSampleAdapter();
            $adapter->db = $this->db;
            $adapterRun = $adapter->runAdapters($user);
            $coverage = $this->coverageMatrix($inventory, $profiles, $adapterRun, $mappingQueue);
            $mappingQueue = $this->augmentMappingQueueFromCoverage($mappingQueue, $coverage);
            $mappingQueue = $this->refineMappingQueue($mappingQueue, $inventory, $coverage);
            $coverage = $this->coverageMatrix($inventory, $profiles, $adapterRun, $mappingQueue);

            $unsupported = $this->unsupportedFiles($coverage);
            $xlsStrategy = $this->xlsReaderStrategy($inventory);
            $rawPunchMatrix = $this->rawPunchSupportMatrix($inventory, $coverage, $profiles);

            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'sample_inventory.csv', $inventory);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'profile_coverage_matrix.csv', $coverage);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'owner_mapping_queue.csv', $mappingQueue);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'unsupported_files.csv', $unsupported);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'xls_reader_strategy.csv', $xlsStrategy);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'raw_punch_support_matrix.csv', $rawPunchMatrix);

            $summary = $this->summary($inventory, $coverage, $mappingQueue, $clusters, $adapterRun);
            $summary['legacy_xls_files'] = count($xlsStrategy);
            $summary['legacy_xls_converted'] = count(array_filter($xlsStrategy, static fn($row) => $row['conversion_status'] === 'converted'));
            $summary['raw_punch_files'] = count($rawPunchMatrix);
            $summary['raw_punch_supported'] = count(array_filter($rawPunchMatrix, static fn($row) => $row['support_status'] === 'supported_preview_only'));
            $summary['raw_punch_blocked'] = count(array_filter($rawPunchMatrix, static fn($row) => $row['support_status'] !== 'supported_preview_only'));
            $summary['sprint4_baseline_files_parsed'] = 12;
            $summary['sprint4_baseline_valid_preview_files'] = 9;

            $this->writeSprint5Report($auditDir . DIRECTORY_SEPARATOR . 'dtr_engine_sprint5_bulk_adapter_gap_closure.md', $summary, $coverage, $mappingQueue, $xlsStrategy, $rawPunchMatrix);

            return [
                'success' => 1,
                'summary' => $summary,
                'clusters' => $clusters,
                'adapter_run' => $adapterRun,
                'files' => [
                    'sample_inventory' => $regressionDir . DIRECTORY_SEPARATOR . 'sample_inventory.csv',
                    'profile_coverage_matrix' => $regressionDir . DIRECTORY_SEPARATOR . 'profile_coverage_matrix.csv',
                    'owner_mapping_queue' => $regressionDir . DIRECTORY_SEPARATOR . 'owner_mapping_queue.csv',
                    'unsupported_files' => $regressionDir . DIRECTORY_SEPARATOR . 'unsupported_files.csv',
                    'xls_reader_strategy' => $regressionDir . DIRECTORY_SEPARATOR . 'xls_reader_strategy.csv',
                    'raw_punch_support_matrix' => $regressionDir . DIRECTORY_SEPARATOR . 'raw_punch_support_matrix.csv',
                    'report' => $auditDir . DIRECTORY_SEPARATOR . 'dtr_engine_sprint5_bulk_adapter_gap_closure.md',
                ],
            ];
        } catch (\Throwable $th) {
            error_log('DTR Sprint 5 bulk adapter gap closure failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function runSprint6(string $user): array
    {
        try {
            $sampleDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Sample Data';
            $auditDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'audit_reports';
            $regressionDir = $auditDir . DIRECTORY_SEPARATOR . 'regression' . DIRECTORY_SEPARATOR . 'dtr_engine_sprint6';
            $convertedDir = $regressionDir . DIRECTORY_SEPARATOR . 'converted';
            if (!is_dir($convertedDir)) {
                mkdir($convertedDir, 0777, true);
            }

            $files = $this->sampleFilesForSprint6($sampleDir, $convertedDir);
            $existingProfiles = $this->loadProfiles();
            $existingByFilename = [];
            foreach ($existingProfiles as $profile) {
                $existingByFilename[strtolower((string)($profile['filename'] ?? ''))] = $profile;
            }

            $inventory = [];
            $profiles = [];
            $mappingQueue = [];
            $clusters = [];

            foreach ($files as $file) {
                $profile = $existingByFilename[strtolower($file['name'])] ?? null;
                $profileWasExisting = is_array($profile) && in_array((string)($profile['key'] ?? ''), ['COXON', 'CYA', 'DELTA'], true);
                $analysis = $this->analyzeSample($file, $profileWasExisting ? $profile : null);
                $inventory[] = $analysis['inventory'];

                if (!$profileWasExisting) {
                    $profile = $analysis['profile'];
                } else {
                    $profile = $this->mergeProfileMetadata($profile, $analysis);
                }

                $profiles[] = $profile;
                $cluster = (string)$analysis['inventory']['format_cluster'];
                $clusters[$cluster] = ($clusters[$cluster] ?? 0) + 1;

                if ($analysis['mapping_required']) {
                    $mappingQueue[] = $analysis['mapping_queue'];
                }
            }

            $payload = [
                'version' => '2026-06-22-sprint6',
                'scope' => 'xls-conversion-and-matrix-punch-adapter-local-only',
                'generated_at' => date('Y-m-d H:i:s'),
                'profiles' => $profiles,
            ];
            $this->saveProfiles($payload);

            require_once(__DIR__ . '/RealSampleAdapter.php');
            $adapter = new RealSampleAdapter();
            $adapter->db = $this->db;
            $adapterRun = $adapter->runAdapters($user);
            $coverage = $this->coverageMatrix($inventory, $profiles, $adapterRun, $mappingQueue);
            $mappingQueue = $this->augmentMappingQueueFromCoverage($mappingQueue, $coverage);
            $mappingQueue = $this->refineMappingQueue($mappingQueue, $inventory, $coverage);
            $coverage = $this->coverageMatrix($inventory, $profiles, $adapterRun, $mappingQueue);

            $unsupported = $this->unsupportedFiles($coverage);
            $xlsConversion = $this->xlsConversionResults($inventory, $coverage);
            $matrixPunch = $this->matrixPunchSupportMatrix($inventory, $coverage, $profiles);

            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'sample_inventory.csv', $inventory);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'profile_coverage_matrix.csv', $coverage);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'owner_mapping_queue.csv', $mappingQueue);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'unsupported_files.csv', $unsupported);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'xls_conversion_results.csv', $xlsConversion);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'matrix_punch_support_matrix.csv', $matrixPunch);

            $summary = $this->summary($inventory, $coverage, $mappingQueue, $clusters, $adapterRun);
            $summary['sprint5_baseline_files_parsed'] = 18;
            $summary['sprint5_baseline_valid_preview_files'] = 13;
            $summary['sprint5_baseline_unsupported_files'] = 10;
            $summary['legacy_xls_files'] = count($xlsConversion);
            $summary['legacy_xls_converted'] = count(array_filter($xlsConversion, static fn($row) => $row['conversion_status'] === 'converted'));
            $summary['matrix_punch_files'] = count($matrixPunch);
            $summary['matrix_punch_supported'] = count(array_filter($matrixPunch, static fn($row) => $row['support_status'] === 'supported_preview_only'));
            $summary['matrix_punch_blocked'] = count(array_filter($matrixPunch, static fn($row) => $row['support_status'] !== 'supported_preview_only'));

            $this->writeSprint6Report($auditDir . DIRECTORY_SEPARATOR . 'dtr_engine_sprint6_xls_matrix_adapter.md', $summary, $coverage, $mappingQueue, $xlsConversion, $matrixPunch);

            return [
                'success' => 1,
                'summary' => $summary,
                'clusters' => $clusters,
                'adapter_run' => $adapterRun,
                'files' => [
                    'sample_inventory' => $regressionDir . DIRECTORY_SEPARATOR . 'sample_inventory.csv',
                    'profile_coverage_matrix' => $regressionDir . DIRECTORY_SEPARATOR . 'profile_coverage_matrix.csv',
                    'owner_mapping_queue' => $regressionDir . DIRECTORY_SEPARATOR . 'owner_mapping_queue.csv',
                    'unsupported_files' => $regressionDir . DIRECTORY_SEPARATOR . 'unsupported_files.csv',
                    'xls_conversion_results' => $regressionDir . DIRECTORY_SEPARATOR . 'xls_conversion_results.csv',
                    'matrix_punch_support_matrix' => $regressionDir . DIRECTORY_SEPARATOR . 'matrix_punch_support_matrix.csv',
                    'report' => $auditDir . DIRECTORY_SEPARATOR . 'dtr_engine_sprint6_xls_matrix_adapter.md',
                ],
            ];
        } catch (\Throwable $th) {
            error_log('DTR Sprint 6 XLS/matrix adapter failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function runSprint7(string $user): array
    {
        try {
            $sampleDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Sample Data';
            $auditDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'audit_reports';
            $regressionDir = $auditDir . DIRECTORY_SEPARATOR . 'regression' . DIRECTORY_SEPARATOR . 'dtr_engine_sprint7';
            $convertedDir = $auditDir . DIRECTORY_SEPARATOR . 'regression' . DIRECTORY_SEPARATOR . 'dtr_engine_sprint6' . DIRECTORY_SEPARATOR . 'converted';
            if (!is_dir($regressionDir)) {
                mkdir($regressionDir, 0777, true);
            }

            $files = $this->sampleFilesForSprint6($sampleDir, $convertedDir);
            $existingProfiles = $this->loadProfiles();
            $existingByFilename = [];
            foreach ($existingProfiles as $profile) {
                $existingByFilename[strtolower((string)($profile['filename'] ?? ''))] = $profile;
            }

            $inventory = [];
            $profiles = [];
            $mappingQueue = [];
            $clusters = [];

            foreach ($files as $file) {
                $profile = $existingByFilename[strtolower($file['name'])] ?? null;
                $profileWasExisting = is_array($profile) && in_array((string)($profile['key'] ?? ''), ['COXON', 'CYA', 'DELTA'], true);
                $analysis = $this->analyzeSample($file, $profileWasExisting ? $profile : null);
                $inventory[] = $analysis['inventory'];

                if (!$profileWasExisting) {
                    $profile = $analysis['profile'];
                } else {
                    $profile = $this->mergeProfileMetadata($profile, $analysis);
                }

                $profiles[] = $profile;
                $cluster = (string)$analysis['inventory']['format_cluster'];
                $clusters[$cluster] = ($clusters[$cluster] ?? 0) + 1;

                if ($analysis['mapping_required']) {
                    $mappingQueue[] = $analysis['mapping_queue'];
                }
            }

            $payload = [
                'version' => '2026-06-22-sprint7',
                'scope' => 'preview-qa-and-mapping-hardening-local-only',
                'generated_at' => date('Y-m-d H:i:s'),
                'profiles' => $profiles,
            ];
            $this->saveProfiles($payload);

            require_once(__DIR__ . '/RealSampleAdapter.php');
            $adapter = new RealSampleAdapter();
            $adapter->db = $this->db;
            $adapterRun = $adapter->runAdapters($user);
            $coverage = $this->coverageMatrix($inventory, $profiles, $adapterRun, $mappingQueue);
            $mappingQueue = $this->augmentMappingQueueFromCoverage($mappingQueue, $coverage);
            $mappingQueue = $this->refineMappingQueue($mappingQueue, $inventory, $coverage);
            $coverage = $this->coverageMatrix($inventory, $profiles, $adapterRun, $mappingQueue);

            $qaScorecard = $this->fileQaScorecard($inventory, $profiles, $adapterRun, $coverage, $mappingQueue);
            $profileCoverage = $this->sprint7ProfileCoverageMatrix($coverage, $qaScorecard);
            $ownerQueue = $this->sprint7OwnerMappingQueue($mappingQueue, $qaScorecard, $coverage);
            $unsupportedBacklog = $this->unsupportedFileBacklog($inventory, $coverage, $qaScorecard);
            $suspiciousFindings = $this->suspiciousPreviewFindings($adapterRun, $qaScorecard);
            $statusGaps = $this->statusDictionaryGaps($adapterRun, $profiles);

            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'file_qa_scorecard.csv', $qaScorecard);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'profile_coverage_matrix.csv', $profileCoverage);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'owner_mapping_queue.csv', $ownerQueue);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'unsupported_file_backlog.csv', $unsupportedBacklog);
            if (count($unsupportedBacklog) === 0) {
                file_put_contents(
                    $regressionDir . DIRECTORY_SEPARATOR . 'unsupported_file_backlog.csv',
                    "filename,reason_unsupported,suspected_layout,required_engineering_action,required_owner_mapping_action,likely_reusable_adapter_type,recommended_priority\n",
                    LOCK_EX
                );
            }
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'suspicious_preview_findings.csv', $suspiciousFindings);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'status_dictionary_gaps.csv', $statusGaps);

            $summary = $this->summary($inventory, $coverage, $mappingQueue, $clusters, $adapterRun);
            $summary['sprint6_baseline_valid_preview_files'] = 21;
            $summary['qa_preview_ok_files'] = count(array_filter($qaScorecard, static fn($row) => $row['recommended_readiness'] === 'preview_ok'));
            $summary['qa_needs_mapping_files'] = count(array_filter($qaScorecard, static fn($row) => $row['recommended_readiness'] === 'needs_mapping'));
            $summary['qa_engineering_gap_files'] = count(array_filter($qaScorecard, static fn($row) => $row['recommended_readiness'] === 'engineering_gap'));
            $summary['qa_unsupported_files'] = count(array_filter($qaScorecard, static fn($row) => $row['recommended_readiness'] === 'unsupported'));
            $summary['suspicious_findings'] = count($suspiciousFindings);
            $summary['status_dictionary_gaps'] = count($statusGaps);
            $summary['unsupported_backlog_items'] = count($unsupportedBacklog);

            $this->writeSprint7Report($auditDir . DIRECTORY_SEPARATOR . 'dtr_engine_sprint7_preview_qa_mapping_hardening.md', $summary, $qaScorecard, $ownerQueue, $unsupportedBacklog, $suspiciousFindings, $statusGaps);

            return [
                'success' => 1,
                'summary' => $summary,
                'adapter_run' => $adapterRun,
                'files' => [
                    'file_qa_scorecard' => $regressionDir . DIRECTORY_SEPARATOR . 'file_qa_scorecard.csv',
                    'profile_coverage_matrix' => $regressionDir . DIRECTORY_SEPARATOR . 'profile_coverage_matrix.csv',
                    'owner_mapping_queue' => $regressionDir . DIRECTORY_SEPARATOR . 'owner_mapping_queue.csv',
                    'unsupported_file_backlog' => $regressionDir . DIRECTORY_SEPARATOR . 'unsupported_file_backlog.csv',
                    'suspicious_preview_findings' => $regressionDir . DIRECTORY_SEPARATOR . 'suspicious_preview_findings.csv',
                    'status_dictionary_gaps' => $regressionDir . DIRECTORY_SEPARATOR . 'status_dictionary_gaps.csv',
                    'report' => $auditDir . DIRECTORY_SEPARATOR . 'dtr_engine_sprint7_preview_qa_mapping_hardening.md',
                ],
            ];
        } catch (\Throwable $th) {
            error_log('DTR Sprint 7 preview QA failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function runSprint8(string $user): array
    {
        try {
            $sampleDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Sample Data';
            $auditDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'audit_reports';
            $regressionDir = $auditDir . DIRECTORY_SEPARATOR . 'regression' . DIRECTORY_SEPARATOR . 'dtr_engine_sprint8';
            $convertedDir = $auditDir . DIRECTORY_SEPARATOR . 'regression' . DIRECTORY_SEPARATOR . 'dtr_engine_sprint6' . DIRECTORY_SEPARATOR . 'converted';
            if (!is_dir($regressionDir)) {
                mkdir($regressionDir, 0777, true);
            }

            $files = $this->sampleFilesForSprint6($sampleDir, $convertedDir);
            $existingProfiles = $this->loadProfiles();
            $existingByFilename = [];
            foreach ($existingProfiles as $profile) {
                $existingByFilename[strtolower((string)($profile['filename'] ?? ''))] = $profile;
            }

            $inventory = [];
            $profiles = [];
            $mappingQueue = [];
            $clusters = [];

            foreach ($files as $file) {
                $profile = $existingByFilename[strtolower($file['name'])] ?? null;
                $profileWasExisting = is_array($profile) && in_array((string)($profile['key'] ?? ''), ['COXON', 'CYA', 'DELTA'], true);
                $analysis = $this->analyzeSample($file, $profileWasExisting ? $profile : null);
                $inventoryRow = $analysis['inventory'];

                if (!$profileWasExisting) {
                    $profile = $analysis['profile'];
                } else {
                    $profile = $this->mergeProfileMetadata($profile, $analysis);
                }

                $profile = $this->sprint8EnhanceProfile($profile, $inventoryRow, (array)($analysis['signals'] ?? []));
                $inventoryRow['profile_action'] = (string)($profile['coverage_classification'] ?? $inventoryRow['profile_action']);
                $inventory[] = $inventoryRow;
                $profiles[] = $profile;
                $cluster = (string)$inventoryRow['format_cluster'];
                $clusters[$cluster] = ($clusters[$cluster] ?? 0) + 1;

                if ($analysis['mapping_required'] || $this->sprint8ProfileStillNeedsMapping($profile)) {
                    $mappingQueue[] = $analysis['mapping_queue'];
                }
            }

            $payload = [
                'version' => '2026-06-22-sprint8',
                'scope' => 'mapping-automation-and-unsupported-closure-local-only',
                'generated_at' => date('Y-m-d H:i:s'),
                'profiles' => $profiles,
            ];
            $this->saveProfiles($payload);

            require_once(__DIR__ . '/RealSampleAdapter.php');
            $adapter = new RealSampleAdapter();
            $adapter->db = $this->db;
            $adapterRun = $adapter->runAdapters($user);
            $coverage = $this->coverageMatrix($inventory, $profiles, $adapterRun, $mappingQueue);
            $mappingQueue = $this->augmentMappingQueueFromCoverage($mappingQueue, $coverage);
            $mappingQueue = $this->refineMappingQueue($mappingQueue, $inventory, $coverage);
            $coverage = $this->coverageMatrix($inventory, $profiles, $adapterRun, $mappingQueue);

            $qaScorecard = $this->fileQaScorecard($inventory, $profiles, $adapterRun, $coverage, $mappingQueue);
            $profileCoverage = $this->sprint7ProfileCoverageMatrix($coverage, $qaScorecard);
            $ownerQueue = $this->sprint8OwnerMappingQueue($mappingQueue, $qaScorecard, $coverage);
            $unsupportedBacklog = $this->unsupportedFileBacklog($inventory, $coverage, $qaScorecard);
            $suspiciousFindings = $this->sprint8SuspiciousPreviewFindings($adapterRun, $qaScorecard);
            $statusGaps = $this->sprint8StatusDictionaryGaps($adapterRun, $profiles);
            $mappingRecommendations = $this->mappingRecommendations($inventory, $profiles, $coverage, $qaScorecard);
            $employeeSuggestions = $this->employeeMatchSuggestions();

            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'file_qa_scorecard.csv', $qaScorecard);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'profile_coverage_matrix.csv', $profileCoverage);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'owner_mapping_queue.csv', $ownerQueue);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'unsupported_file_backlog.csv', $unsupportedBacklog);
            if (count($unsupportedBacklog) === 0) {
                file_put_contents(
                    $regressionDir . DIRECTORY_SEPARATOR . 'unsupported_file_backlog.csv',
                    "filename,reason_unsupported,suspected_layout,required_engineering_action,required_owner_mapping_action,likely_reusable_adapter_type,recommended_priority\n",
                    LOCK_EX
                );
            }
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'suspicious_preview_findings.csv', $suspiciousFindings);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'status_dictionary_gaps.csv', $statusGaps);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'mapping_recommendations.csv', $mappingRecommendations);
            $this->writeCsv($regressionDir . DIRECTORY_SEPARATOR . 'employee_match_suggestions.csv', $employeeSuggestions);

            $summary = $this->summary($inventory, $coverage, $mappingQueue, $clusters, $adapterRun);
            $summary['sprint7_baseline_valid_preview_files'] = 21;
            $summary['sprint7_baseline_unsupported_files'] = 5;
            $summary['sprint7_baseline_owner_mapping_queue'] = 27;
            $summary['sprint7_baseline_suspicious_findings'] = 124;
            $summary['qa_preview_ok_files'] = count(array_filter($qaScorecard, static fn($row) => $row['recommended_readiness'] === 'preview_ok'));
            $summary['qa_needs_mapping_files'] = count(array_filter($qaScorecard, static fn($row) => $row['recommended_readiness'] === 'needs_mapping'));
            $summary['qa_engineering_gap_files'] = count(array_filter($qaScorecard, static fn($row) => $row['recommended_readiness'] === 'engineering_gap'));
            $summary['qa_unsupported_files'] = count(array_filter($qaScorecard, static fn($row) => $row['recommended_readiness'] === 'unsupported'));
            $summary['suspicious_findings'] = count($suspiciousFindings);
            $summary['status_dictionary_gaps'] = count($statusGaps);
            $summary['unsupported_backlog_items'] = count($unsupportedBacklog);
            $summary['mapping_recommendations'] = count($mappingRecommendations);
            $summary['employee_match_suggestions'] = count($employeeSuggestions);

            $this->writeSprint8Report(
                $auditDir . DIRECTORY_SEPARATOR . 'dtr_engine_sprint8_mapping_automation_unsupported_closure.md',
                $summary,
                $qaScorecard,
                $ownerQueue,
                $unsupportedBacklog,
                $suspiciousFindings,
                $statusGaps,
                $mappingRecommendations,
                $employeeSuggestions
            );

            return [
                'success' => 1,
                'summary' => $summary,
                'adapter_run' => $adapterRun,
                'files' => [
                    'file_qa_scorecard' => $regressionDir . DIRECTORY_SEPARATOR . 'file_qa_scorecard.csv',
                    'profile_coverage_matrix' => $regressionDir . DIRECTORY_SEPARATOR . 'profile_coverage_matrix.csv',
                    'owner_mapping_queue' => $regressionDir . DIRECTORY_SEPARATOR . 'owner_mapping_queue.csv',
                    'unsupported_file_backlog' => $regressionDir . DIRECTORY_SEPARATOR . 'unsupported_file_backlog.csv',
                    'suspicious_preview_findings' => $regressionDir . DIRECTORY_SEPARATOR . 'suspicious_preview_findings.csv',
                    'status_dictionary_gaps' => $regressionDir . DIRECTORY_SEPARATOR . 'status_dictionary_gaps.csv',
                    'mapping_recommendations' => $regressionDir . DIRECTORY_SEPARATOR . 'mapping_recommendations.csv',
                    'employee_match_suggestions' => $regressionDir . DIRECTORY_SEPARATOR . 'employee_match_suggestions.csv',
                    'report' => $auditDir . DIRECTORY_SEPARATOR . 'dtr_engine_sprint8_mapping_automation_unsupported_closure.md',
                ],
            ];
        } catch (\Throwable $th) {
            error_log('DTR Sprint 8 mapping automation failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    private function sprint8EnhanceProfile(array $profile, array $inventory, array $signals): array
    {
        $convertedLegacy = (string)($inventory['converted_from_xls'] ?? '') === 'yes'
            && (string)($inventory['reader_status'] ?? '') === 'converted_xls_profiled';
        $candidateCol = (int)($signals['numeric_summary_candidate_col'] ?? 0);
        $employeeNameCol = (int)($signals['employee_name_col'] ?? 0);
        $employeeIdCol = (int)($signals['employee_identifier_col'] ?? 0);
        $filename = (string)($inventory['filename'] ?? $profile['filename'] ?? '');

        if ($convertedLegacy && ($profile['mode'] ?? '') === 'unsupported') {
            if (stripos($filename, 'PHLAG') !== false) {
                $employeeIdCol = 3;
                $employeeNameCol = 3;
                $candidateCol = 4;
                $profile['sheet_selector'] = 'att';
                $profile['data_start_row'] = 3;
            } elseif (stripos($filename, 'GLOBALWEEKLY') !== false) {
                $employeeIdCol = 2;
                $employeeNameCol = 2;
                $candidateCol = 4;
                $profile['sheet_selector'] = 'Sheet1';
                $profile['data_start_row'] = 7;
            }
        }

        if ($convertedLegacy && ($profile['mode'] ?? '') === 'unsupported' && $candidateCol > 0 && ($employeeNameCol > 0 || $employeeIdCol > 0)) {
            $employeeIdCol = $employeeIdCol > 0 ? $employeeIdCol : $employeeNameCol;
            $employeeNameCol = $employeeNameCol > 0 ? $employeeNameCol : $employeeIdCol;
            $profile['workbook_type'] = 'converted_legacy_summary';
            $profile['mode'] = 'summary_period';
            $profile['coverage_classification'] = 'existing profile works with minor config change';
            $profile['profile_reuse_notes'] = 'Sprint 8 converted legacy summary fallback selected a numeric worked-days/hours candidate. Preview-only and owner confirmation required.';
            $profile['employee_identifier_label'] = 'Column ' . $this->columnLabel($employeeIdCol) . ' / auto-suggested';
            $profile['columns'] = [
                'employee_identifier' => $employeeIdCol,
                'employee_name' => $employeeNameCol,
                'area' => (int)($signals['area_col'] ?? 0),
                'worked_days' => $candidateCol,
                'work_date' => 0,
                'time_in' => 0,
                'time_out' => 0,
            ];
            $profile['totals_description'] = 'Sprint 8 candidate worked-days/hours column=' . $this->columnLabel($candidateCol) . '; owner must confirm meaning.';
            $profile['remarks_status_fields'] = (string)($profile['remarks_status_fields'] ?? '') ?: 'No explicit status field; summary value inferred.';
            $profile['summary_conversion'] = [
                'hours_per_worked_day' => 8,
                'basis' => 'legacy_summary_candidate_preview_only',
            ];
            $profile['preview_safety'] = [
                'max_daily_hours_per_row' => 16,
                'max_summary_hours_per_row' => 160,
                'max_worked_days_per_period' => 20,
                'max_profile_preview_hours' => 2200,
            ];
            $profile['total_validation'] = [
                'enabled' => false,
                'reason' => 'Sprint 8 inferred summary column; totals remain owner-review only.',
            ];
            $profile['fields'] = [
                ['source_header' => 'Employee identifier/name', 'canonical_field' => 'employee_id', 'data_type' => 'text', 'is_required' => 1],
                ['source_header' => 'Worked days/hours candidate', 'canonical_field' => 'hours_worked', 'data_type' => 'number', 'is_required' => 1],
            ];
            $profile['owner_decision_checklist'] = [
                'Confirm Sprint 8 inferred employee identifier/name column.',
                'Confirm whether numeric candidate column means worked days, hours, shift units, or payroll summary value.',
                'Confirm client/site binding and pay-period extraction.',
                'Confirm whether footer/summary rows are fully excluded.',
            ];
            $profile['adapter_hardening_notes'][] = 'Sprint 8 converted legacy fallback closed prior unsupported state for preview only.';
        }

        $profile['mapping_recommendation_status'] = $this->sprint8ProfileStillNeedsMapping($profile) ? 'owner_confirmation_required' : 'automation_recommendation_available';
        return $profile;
    }

    private function sprint8ProfileStillNeedsMapping(array $profile): bool
    {
        if (($profile['mode'] ?? '') === 'unsupported') {
            return true;
        }
        if (stripos((string)($profile['coverage_classification'] ?? ''), 'owner') !== false) {
            return true;
        }
        if (stripos((string)($profile['profile_reuse_notes'] ?? ''), 'owner') !== false) {
            return true;
        }
        if ((int)($profile['client_id'] ?? 0) <= 0 || (int)($profile['location_id'] ?? 0) <= 0) {
            return true;
        }
        return count((array)($profile['owner_decision_checklist'] ?? [])) > 0;
    }

    private function sprint8OwnerMappingQueue(array $mappingQueue, array $qaScorecard, array $coverage): array
    {
        $rows = $this->sprint7OwnerMappingQueue($mappingQueue, $qaScorecard, $coverage);
        foreach ($rows as $index => $row) {
            $category = (string)($row['issue_category'] ?? '');
            $rows[$index]['automation_recommendation_available'] = $category === 'technical_reader' ? 'partial' : 'yes';
            $rows[$index]['owner_confirmation_still_required'] = 'yes';
            $rows[$index]['recommended_sprint8_next_action'] = $category === 'engineering_adapter'
                ? 'Review generated mapping recommendations and parser gaps before relying on preview.'
                : 'Review suggested mapping defaults; approve, reject, or defer each decision.';
        }
        return $rows;
    }

    private function mappingRecommendations(array $inventory, array $profiles, array $coverage, array $qaScorecard): array
    {
        $profilesByFile = [];
        foreach ($profiles as $profile) {
            $profilesByFile[strtolower((string)($profile['filename'] ?? ''))] = $profile;
        }
        $coverageByFile = [];
        foreach ($coverage as $row) {
            $coverageByFile[strtolower((string)$row['filename'])] = $row;
        }
        $qaByFile = [];
        foreach ($qaScorecard as $row) {
            $qaByFile[strtolower((string)$row['filename'])] = $row;
        }

        $rows = [];
        foreach ($inventory as $item) {
            $filename = (string)$item['filename'];
            $profile = $profilesByFile[strtolower($filename)] ?? [];
            $coverageRow = $coverageByFile[strtolower($filename)] ?? [];
            $qa = $qaByFile[strtolower($filename)] ?? [];
            $columns = (array)($profile['columns'] ?? []);
            $baseConfidence = (float)($coverageRow['profile_confidence'] ?? 0.50);
            $ownerRequired = ((string)($qa['recommended_readiness'] ?? '') === 'preview_ok') ? 'review_recommended' : 'yes';

            $this->appendRecommendation($rows, $filename, $profile, 'employee_identifier_column', $this->columnEvidence($columns['employee_identifier'] ?? 0), $baseConfidence, 'Detected from employee ID/code/no. signal or fallback identifier column.', $ownerRequired);
            $this->appendRecommendation($rows, $filename, $profile, 'employee_name_column', $this->columnEvidence($columns['employee_name'] ?? 0), $baseConfidence, 'Detected from NAME-like header or text-heavy employee column.', $ownerRequired);
            $this->appendRecommendation($rows, $filename, $profile, 'date_day_columns', $this->dateColumnEvidence($profile), $baseConfidence, 'Detected day-number run, raw date column, or pay-period filename fallback.', $ownerRequired);
            $this->appendRecommendation($rows, $filename, $profile, 'hours_worked_days_column', $this->columnEvidence($columns['worked_days'] ?? 0), max(0.35, $baseConfidence - 0.10), 'Detected worked-days/hours header or Sprint 8 numeric summary candidate.', 'yes');
            $this->appendRecommendation($rows, $filename, $profile, 'status_remarks_columns', (string)($profile['remarks_status_fields'] ?? ''), max(0.30, $baseConfidence - 0.15), 'Detected status/remarks headers or no explicit status field for summary-only layouts.', 'yes');
            $this->appendRecommendation($rows, $filename, $profile, 'total_footer_rows', implode('|', (array)($profile['footer_detection_rules'] ?? [])), max(0.45, $baseConfidence - 0.10), 'Footer/summary keywords are excluded before staging.', 'yes');
            $this->appendRecommendation($rows, $filename, $profile, 'client_source', (string)($item['inferred_client_source'] ?? ''), 0.70, 'Inferred from workbook filename only; not a client/site binding approval.', 'yes');
            $this->appendRecommendation($rows, $filename, $profile, 'pay_period_extraction', (string)($profile['payroll_period_indicators'] ?? $item['inferred_payroll_period'] ?? ''), 0.72, 'Inferred from filename month/day range.', 'yes');
        }

        return $rows;
    }

    private function appendRecommendation(array &$rows, string $filename, array $profile, string $type, string $evidence, float $confidence, string $reason, string $ownerRequired): void
    {
        $rows[] = [
            'filename' => $filename,
            'profile_key' => (string)($profile['key'] ?? ''),
            'recommendation_type' => $type,
            'recommended_mapping' => $evidence !== '' ? $evidence : 'not_detected',
            'confidence_score' => number_format(max(0.0, min(0.99, $confidence)), 2, '.', ''),
            'reason' => $reason,
            'sample_evidence' => $evidence !== '' ? $evidence : 'No reliable local signal.',
            'owner_confirmation_required' => $ownerRequired,
        ];
    }

    private function columnEvidence($column): string
    {
        $column = (int)$column;
        return $column > 0 ? 'Column ' . $this->columnLabel($column) : '';
    }

    private function dateColumnEvidence(array $profile): string
    {
        if (($profile['mode'] ?? '') === 'wide_daily') {
            $range = (array)($profile['date_day_column_range'] ?? []);
            $start = (int)($range['start_col'] ?? 0);
            $count = (int)($range['count'] ?? 0);
            return $start > 0 && $count > 0 ? 'Columns ' . $this->columnLabel($start) . ':' . $this->columnLabel($start + $count - 1) : '';
        }
        if (($profile['mode'] ?? '') === 'raw_punch') {
            return $this->columnEvidence((int)($profile['columns']['work_date'] ?? 0));
        }
        return (string)($profile['payroll_period_indicators'] ?? '');
    }

    private function sprint8SuspiciousPreviewFindings(array $adapterRun, array $qaScorecard): array
    {
        $rows = $this->suspiciousPreviewFindings($adapterRun, $qaScorecard);
        $filtered = [];
        foreach ($rows as $row) {
            $severity = $this->findingSeverity((string)$row['finding_type']);
            if ($severity === 'info') {
                continue;
            }
            $row['severity'] = $severity;
            $row['blocking_issue'] = $severity === 'blocker' ? 'yes' : 'no';
            $filtered[] = $row;
        }
        return $filtered;
    }

    private function findingSeverity(string $finding): string
    {
        if (preg_match('/unsupported_profile|zero_valid|invalid_time|duplicate|conflict|mismatch|unknown_employee|status_invalid|invalid_status/i', $finding)) {
            return 'blocker';
        }
        if (preg_match('/exceed|possible_total|zero_hour|footer|blank|unknown|unsupported/i', $finding)) {
            return 'warning';
        }
        return 'info';
    }

    private function sprint8StatusDictionaryGaps(array $adapterRun, array $profiles): array
    {
        $rows = $this->statusDictionaryGaps($adapterRun, $profiles);
        foreach ($rows as $index => $row) {
            $rows[$index]['suggested_category'] = $this->suggestStatusCategory((string)($row['gap_type'] ?? ''), (string)($row['mode'] ?? ''));
            $rows[$index]['suggestion_confidence'] = $rows[$index]['suggested_category'] === 'owner_review_required' ? '0.35' : '0.65';
            $rows[$index]['owner_confirmation_required'] = 'yes';
        }
        return $rows;
    }

    private function suggestStatusCategory(string $gapType, string $mode): string
    {
        if ($mode === 'summary_period') {
            return 'summary_value_owner_review_required';
        }
        if (stripos($gapType, 'blank') !== false) {
            return 'blank_ignored_candidate';
        }
        return 'owner_review_required';
    }

    private function employeeMatchSuggestions(): array
    {
        if (!$this->db) {
            return [];
        }
        $employees = $this->loadEmployeeMatchCandidates();
        $stmt = $this->db->prepare("
            SELECT
                b.original_filename,
                r.raw_payload,
                r.parsed_payload,
                r.error_summary
            FROM dtr_upload_staging_rows r
            INNER JOIN dtr_upload_batches b ON b.id = r.batch_id
            WHERE b.source_context = :source_context
            ORDER BY b.original_filename ASC, r.source_row_number ASC
            LIMIT 2000
        ");
        $stmt->execute([':source_context' => self::SAMPLE_CONTEXT]);

        $rows = [];
        $seen = [];
        while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $parsed = json_decode((string)$record['parsed_payload'], true) ?: [];
            $raw = json_decode((string)$record['raw_payload'], true) ?: [];
            $errors = json_decode((string)$record['error_summary'], true) ?: [];
            $rule = (string)($parsed['employee_match_rule'] ?? '');
            if ($rule !== 'unmatched' && !in_array('unknown_employee', $errors, true)) {
                continue;
            }
            $identifier = trim((string)($parsed['employee_identifier_source'] ?? $raw['employee_identifier_source'] ?? ''));
            $name = trim((string)($parsed['employee_name_source'] ?? $raw['employee_name_source'] ?? ''));
            $key = strtolower((string)$record['original_filename'] . '|' . $identifier . '|' . $name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $candidate = $this->bestEmployeeCandidate($identifier, $name, $employees);
            $rows[] = [
                'filename' => (string)$record['original_filename'],
                'source_employee_identifier' => $identifier,
                'source_employee_name' => $name,
                'suggestion_type' => $candidate['type'],
                'candidate_employee_id' => $candidate['employee_id'],
                'candidate_employee_name' => $candidate['employee_name'],
                'confidence_score' => $candidate['confidence'],
                'reason' => $candidate['reason'],
                'owner_confirmation_required' => 'yes',
                'record_update_performed' => 'no',
            ];
            if (count($rows) >= 300) {
                break;
            }
        }
        return $rows;
    }

    private function loadEmployeeMatchCandidates(): array
    {
        $stmt = $this->db->query("
            SELECT
                employee_id,
                old_employee_id,
                payroll_employee_id,
                COALESCE(NULLIF(full_name, ''), CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) AS employee_name
            FROM employee_list
            LIMIT 10000
        ");
        $employees = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $employees[] = [
                'employee_id' => (string)($row['employee_id'] ?? ''),
                'old_employee_id' => (string)($row['old_employee_id'] ?? ''),
                'payroll_employee_id' => (string)($row['payroll_employee_id'] ?? ''),
                'employee_name' => trim((string)($row['employee_name'] ?? '')),
                'norm_id' => $this->normalizeMatchText((string)($row['payroll_employee_id'] ?? $row['old_employee_id'] ?? $row['employee_id'] ?? '')),
                'norm_old_id' => $this->normalizeMatchText((string)($row['old_employee_id'] ?? '')),
                'norm_name' => $this->normalizeMatchText((string)($row['employee_name'] ?? '')),
            ];
        }
        return $employees;
    }

    private function bestEmployeeCandidate(string $identifier, string $name, array $employees): array
    {
        $normId = $this->normalizeMatchText($identifier);
        $normName = $this->normalizeMatchText($name);
        $best = ['type' => 'unmatched_employee_queue', 'employee_id' => '', 'employee_name' => '', 'confidence' => '0.00', 'reason' => 'No local employee candidate exceeded fuzzy threshold.'];

        foreach ($employees as $employee) {
            if ($normId !== '' && ($normId === $employee['norm_id'] || $normId === $employee['norm_old_id'] || $normId === $this->normalizeMatchText($employee['employee_id']))) {
                return [
                    'type' => 'normalized_id_match_candidate',
                    'employee_id' => $employee['employee_id'],
                    'employee_name' => $employee['employee_name'],
                    'confidence' => '0.92',
                    'reason' => 'Source identifier normalized to an existing employee ID/no. Candidate only; no record update performed.',
                ];
            }
            similar_text($normName, $employee['norm_name'], $percent);
            if ($normName !== '' && $percent >= (float)$best['confidence'] * 100 && $percent >= 72) {
                $best = [
                    'type' => $percent >= 90 ? 'name_match_candidate' : 'fuzzy_name_candidate',
                    'employee_id' => $employee['employee_id'],
                    'employee_name' => $employee['employee_name'],
                    'confidence' => number_format(min(0.89, $percent / 100), 2, '.', ''),
                    'reason' => 'Local name similarity candidate. Owner must approve before any alias/mapping is used.',
                ];
            }
        }

        if ($best['type'] !== 'unmatched_employee_queue') {
            $best['type'] = 'alias_suggestion';
        }
        return $best;
    }

    private function normalizeMatchText(string $value): string
    {
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', '', $value);
        return (string)$value;
    }

    private function sampleFiles(string $sampleDir): array
    {
        $files = [];
        foreach (scandir($sampleDir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $sampleDir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($path)) {
                continue;
            }
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
                continue;
            }
            $files[] = [
                'name' => $name,
                'path' => $path,
                'extension' => $extension,
                'size' => filesize($path) ?: 0,
            ];
        }
        usort($files, static function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $files;
    }

    private function sampleFilesForSprint6(string $sampleDir, string $convertedDir): array
    {
        $files = $this->sampleFiles($sampleDir);
        foreach ($files as $index => $file) {
            if ($file['extension'] !== 'xls') {
                $files[$index]['converted_from_xls'] = false;
                continue;
            }

            $convertedPath = $convertedDir . DIRECTORY_SEPARATOR . pathinfo($file['name'], PATHINFO_FILENAME) . '.xlsx';
            $files[$index]['converted_from_xls'] = false;
            if (is_file($convertedPath)) {
                $files[$index]['path'] = $convertedPath;
                $files[$index]['size'] = filesize($convertedPath) ?: $file['size'];
                $files[$index]['converted_from_xls'] = true;
                $files[$index]['original_path'] = $file['path'];
                $files[$index]['converted_filename'] = basename($convertedPath);
                $files[$index]['converted_sample_path'] = $convertedPath;
            }
        }

        return $files;
    }

    private function analyzeSample(array $file, ?array $existingProfile): array
    {
        $base = [
            'filename' => $file['name'],
            'extension' => $file['extension'],
            'file_size_bytes' => $file['size'],
            'sheets' => '',
            'selected_sheet' => '',
            'visible_range' => '',
            'likely_header_rows' => '',
            'data_start_row' => '',
            'employee_identifier_fields' => '',
            'employee_name_fields' => '',
            'date_day_columns' => '',
            'time_in_out_columns' => '',
            'hours_worked_days_total_columns' => '',
            'status_remarks_columns' => '',
            'footer_summary_rows' => '',
            'merged_header_structures' => '',
            'inferred_payroll_period' => '',
            'inferred_client_source' => $this->inferClientSource($file['name']),
            'format_cluster' => 'unsupported/unknown',
            'profile_key' => $existingProfile['key'] ?? $this->profileKey($file['name']),
            'profile_action' => 'unsupported',
            'reader_status' => 'not_attempted',
            'converted_from_xls' => !empty($file['converted_from_xls']) ? 'yes' : 'no',
            'converted_filename' => (string)($file['converted_filename'] ?? ''),
        ];

        $period = $this->inferPeriod($file['name']);
        $base['inferred_payroll_period'] = $period['label'];

        if ($file['extension'] === 'xls' && empty($file['converted_from_xls'])) {
            $inventory = $base;
            $inventory['format_cluster'] = 'unsupported/unknown';
            $inventory['profile_action'] = 'unsupported legacy xls reader required';
            $inventory['reader_status'] = 'legacy_xls_reader_unavailable';

            return [
                'inventory' => $inventory,
                'profile' => $this->unsupportedProfile($file, $inventory, $period),
                'mapping_required' => true,
                'mapping_queue' => $this->mappingQueueRow($inventory, 'Legacy .xls workbook cannot be parsed by the local XML reader.', 'Provide .xlsx export or approve legacy .xls reader support.', 'Convert to .xlsx for safest local parser path.'),
            ];
        }

        $workbook = $this->readXlsxWorkbook($file['path']);
        $sheetNames = array_keys($workbook['sheets']);
        $selectedSheet = $existingProfile['sheet_selector'] ?? $this->selectSheet($workbook['sheets']);
        if (!isset($workbook['sheets'][$selectedSheet])) {
            $selectedSheet = $sheetNames[0] ?? '';
        }
        $matrix = $workbook['sheets'][$selectedSheet]['matrix'] ?? [];
        $signals = $this->profileSignals($matrix);
        $cluster = $this->clusterFromSignals($signals);
        $profileAction = $existingProfile ? 'existing profile works or supersedes generated profile' : $this->profileAction($cluster, $signals);

        $inventory = $base;
        $inventory['sheets'] = implode(' | ', $sheetNames);
        $inventory['selected_sheet'] = $selectedSheet;
        $inventory['visible_range'] = $this->visibleRange($matrix);
        $inventory['likely_header_rows'] = $signals['header_rows'];
        $inventory['data_start_row'] = (string)$signals['data_start_row'];
        $inventory['employee_identifier_fields'] = $this->columnLabel($signals['employee_identifier_col']);
        $inventory['employee_name_fields'] = $this->columnLabel($signals['employee_name_col']);
        $inventory['date_day_columns'] = $signals['day_column_summary'];
        $inventory['time_in_out_columns'] = $signals['time_columns'];
        $inventory['hours_worked_days_total_columns'] = $signals['summary_columns'];
        $inventory['status_remarks_columns'] = $signals['status_columns'];
        $inventory['footer_summary_rows'] = $signals['footer_rows'];
        $inventory['merged_header_structures'] = (string)($workbook['sheets'][$selectedSheet]['merge_count'] ?? 0) . ' merged ranges';
        $inventory['format_cluster'] = $cluster;
        $inventory['profile_action'] = $profileAction;
        $inventory['reader_status'] = !empty($file['converted_from_xls']) ? 'converted_xls_profiled' : 'xlsx_profiled';

        $mappingRequired = $cluster === 'unsupported/unknown'
            || $cluster === 'raw punch log'
            || $profileAction === 'owner mapping decision required'
            || $signals['employee_name_col'] <= 0
            || ($cluster !== 'wide daily summary' && $signals['worked_days_col'] <= 0);

        $profile = $existingProfile ?: $this->generatedProfile($file, $inventory, $signals, $period);
        $mappingRow = $this->mappingQueueRow(
            $inventory,
            $this->mappingReason($inventory, $signals, $mappingRequired),
            $this->recommendedOwnerAction($inventory, $signals),
            $this->suggestedDefault($inventory)
        );

        return [
            'inventory' => $inventory,
            'profile' => $profile,
            'signals' => $signals,
            'mapping_required' => $mappingRequired,
            'mapping_queue' => $mappingRow,
        ];
    }

    private function generatedProfile(array $file, array $inventory, array $signals, array $period): array
    {
        $rawPunchReady = $inventory['format_cluster'] === 'raw punch log'
            && $signals['raw_date_col'] > 0
            && $signals['raw_time_in_col'] > 0
            && $signals['raw_time_out_col'] > 0;
        $mode = $inventory['format_cluster'] === 'wide daily summary' || $inventory['format_cluster'] === 'mixed employee/date matrix'
            ? 'wide_daily'
            : ($rawPunchReady ? 'raw_punch' : (in_array($inventory['format_cluster'], ['unsupported/unknown', 'raw punch log'], true) ? 'unsupported' : 'summary_period'));
        $key = $inventory['profile_key'];
        $employeeIdCol = $signals['employee_identifier_col'] > 0 ? $signals['employee_identifier_col'] : $signals['employee_name_col'];
        $employeeNameCol = $signals['employee_name_col'] > 0 ? $signals['employee_name_col'] : $employeeIdCol;
        $workedDaysCol = $signals['worked_days_col'] > 0 ? $signals['worked_days_col'] : ($signals['hours_col'] > 0 ? $signals['hours_col'] : 0);

        $profile = [
            'key' => $key,
            'profile_name' => $inventory['inferred_client_source'] . ' Bulk Generated Profile',
            'template_name' => 'Sprint 4 Profile - ' . $inventory['inferred_client_source'],
            'filename' => $file['name'],
            'file_type' => $file['extension'],
            'converted_sample_path' => (string)($file['converted_sample_path'] ?? ''),
            'converted_filename' => (string)($file['converted_filename'] ?? ''),
            'workbook_type' => str_replace(' ', '_', $inventory['format_cluster']),
            'mode' => $mode,
            'client_source_inferred' => $inventory['inferred_client_source'],
            'coverage_classification' => $inventory['profile_action'],
            'profile_reuse_notes' => 'Generated during Sprint 4 bulk sample discovery; owner review required before payroll use.',
            'payroll_period_indicators' => $inventory['inferred_payroll_period'],
            'merged_header_structure' => $inventory['merged_header_structures'],
            'sheet_selector' => $inventory['selected_sheet'],
            'header_row' => $inventory['likely_header_rows'],
            'data_start_row' => (int)$inventory['data_start_row'],
            'employee_identifier_label' => $employeeIdCol > 0 ? 'Column ' . $this->columnLabel($employeeIdCol) : 'Owner mapping required',
            'columns' => [
                'employee_identifier' => $employeeIdCol,
                'employee_name' => $employeeNameCol,
                'area' => $signals['area_col'],
                'worked_days' => $workedDaysCol,
                'work_date' => $signals['raw_date_col'],
                'time_in' => $signals['raw_time_in_col'],
                'time_out' => $signals['raw_time_out_col'],
            ],
            'date_day_column_range' => [
                'start_col' => $signals['day_start_col'],
                'count' => $signals['day_count'],
            ],
            'period_start' => $period['start'],
            'period_end' => $period['end'],
            'client_id' => 0,
            'location_id' => 0,
            'ignored_rows' => [],
            'ignored_columns' => [],
            'footer_detection_rules' => ['TOTAL', 'GRAND TOTAL', 'SUBTOTAL', 'SUMMARY', 'PREPARED BY', 'CHECKED BY', 'APPROVED BY', '#REF!'],
            'totals_description' => $inventory['hours_worked_days_total_columns'] ?: 'Owner confirmation required.',
            'adapter_hardening_notes' => [
                'Generated by bulk sample profiler; preview values are not payroll-approved.',
                'Owner must confirm employee matching, status codes, client/site binding, and period extraction before any promotion.',
            ],
            'remarks_status_fields' => $inventory['status_remarks_columns'],
            'employee_matching' => [
                'enabled_rules' => ['exact_employee_id', 'normalized_employee_id', 'employee_name_fallback', 'alias_manual_mapping'],
                'alias_map' => new stdClass(),
            ],
            'status_dictionary' => [
                'numeric_zero_category' => 'absent',
                'codes' => [
                    'P' => ['category' => 'present_worked', 'blocking' => false, 'default_hours' => 8],
                    'A' => ['category' => 'absent', 'blocking' => true],
                    'ABSENT' => ['category' => 'absent', 'blocking' => true],
                    'RD' => ['category' => 'rest_day', 'blocking' => true],
                    'REST DAY' => ['category' => 'rest_day', 'blocking' => true],
                    'NW' => ['category' => 'rest_day', 'blocking' => true],
                    'RH' => ['category' => 'holiday', 'blocking' => true],
                    'HOLIDAY' => ['category' => 'holiday', 'blocking' => true],
                    'VL' => ['category' => 'leave', 'blocking' => true],
                    'SL' => ['category' => 'leave', 'blocking' => true],
                    '-' => ['category' => 'blank', 'skip' => true],
                ],
            ],
            'summary_conversion' => [
                'hours_per_worked_day' => 8,
                'basis' => $mode === 'raw_punch' ? 'raw_time_in_out_preview_only' : ($mode === 'summary_period' ? 'worked_days_summary_preview_only' : 'daily_value_preview_only'),
            ],
            'preview_safety' => [
                'max_daily_hours_per_row' => 16,
                'max_summary_hours_per_row' => 128,
                'max_worked_days_per_period' => 16,
                'max_profile_preview_hours' => 2000,
            ],
            'total_validation' => [
                'enabled' => false,
                'reason' => 'Generated profile requires owner confirmation before enforcing workbook totals.',
            ],
            'headers' => $signals['header_values'],
            'fields' => [
                ['source_header' => 'Employee identifier', 'canonical_field' => 'employee_id', 'data_type' => 'text', 'is_required' => 1],
                ['source_header' => 'Employee name', 'canonical_field' => 'employee_name', 'data_type' => 'text', 'is_required' => 0],
                ['source_header' => 'Hours/worked days', 'canonical_field' => 'hours_worked', 'data_type' => 'number', 'is_required' => 1],
            ],
            'owner_decision_checklist' => [
                'Confirm employee identifier/name matching rules for this source.',
                'Confirm whether numeric values mean hours, worked days, shift units, or summary values.',
                'Confirm status-code meanings and non-work day handling.',
                'Confirm client/site binding and payroll period extraction.',
            ],
        ];
        if ($mode === 'raw_punch') {
            $profile['coverage_classification'] = 'new reusable raw punch adapter profile required';
            $profile['profile_reuse_notes'] = 'Generated row-based raw punch profile with date, time-in, and time-out columns; preview-only until owner confirms mapping.';
            $profile['fields'] = [
                ['source_header' => 'Employee identifier', 'canonical_field' => 'employee_id', 'data_type' => 'text', 'is_required' => 1],
                ['source_header' => 'Employee name', 'canonical_field' => 'employee_name', 'data_type' => 'text', 'is_required' => 0],
                ['source_header' => 'Date', 'canonical_field' => 'work_date', 'data_type' => 'date', 'is_required' => 1],
                ['source_header' => 'IN', 'canonical_field' => 'time_in', 'data_type' => 'time', 'is_required' => 1],
                ['source_header' => 'OUT', 'canonical_field' => 'time_out', 'data_type' => 'time', 'is_required' => 1],
            ];
            $profile['owner_decision_checklist'][] = 'Confirm row-based punch date/time columns and overnight shift handling.';
        }

        if ($mode === 'unsupported') {
            $profile['reader_status'] = $inventory['format_cluster'] === 'raw punch log'
                ? 'raw_punch_adapter_required'
                : 'mapping_required_before_parse';
            $profile['adapter_hardening_notes'][] = $inventory['format_cluster'] === 'raw punch log'
                ? 'Raw punch log layout detected, but punch-log conversion is blocked until a dedicated adapter is owner-approved.'
                : 'Unsupported layout remains blocked until owner mapping is confirmed.';
        }

        return $profile;
    }

    private function unsupportedProfile(array $file, array $inventory, array $period): array
    {
        return [
            'key' => $inventory['profile_key'],
            'profile_name' => $inventory['inferred_client_source'] . ' Unsupported Legacy Workbook',
            'template_name' => 'Sprint 4 Unsupported - ' . $inventory['inferred_client_source'],
            'filename' => $file['name'],
            'file_type' => $file['extension'],
            'workbook_type' => 'unsupported_legacy_xls',
            'mode' => 'unsupported',
            'reader_status' => 'legacy_xls_reader_unavailable',
            'client_source_inferred' => $inventory['inferred_client_source'],
            'coverage_classification' => 'unsupported structure',
            'profile_reuse_notes' => 'Legacy .xls workbook requires conversion to .xlsx or a separately approved reader before parser support.',
            'payroll_period_indicators' => $inventory['inferred_payroll_period'],
            'merged_header_structure' => 'Not inspected: legacy .xls reader unavailable.',
            'sheet_selector' => '',
            'header_row' => '',
            'data_start_row' => 0,
            'employee_identifier_label' => 'Owner mapping required',
            'columns' => ['employee_identifier' => 0, 'employee_name' => 0, 'area' => 0, 'worked_days' => 0],
            'date_day_column_range' => ['start_col' => 0, 'count' => 0],
            'period_start' => $period['start'],
            'period_end' => $period['end'],
            'client_id' => 0,
            'location_id' => 0,
            'ignored_rows' => [],
            'ignored_columns' => [],
            'footer_detection_rules' => ['TOTAL', 'GRAND TOTAL'],
            'totals_description' => 'Not inspected: legacy .xls reader unavailable.',
            'adapter_hardening_notes' => ['Unsupported until converted to .xlsx or legacy .xls reader support is approved.'],
            'remarks_status_fields' => '',
            'employee_matching' => ['enabled_rules' => ['alias_manual_mapping'], 'alias_map' => new stdClass()],
            'status_dictionary' => ['numeric_zero_category' => 'absent', 'codes' => new stdClass()],
            'summary_conversion' => ['hours_per_worked_day' => 8],
            'preview_safety' => ['max_profile_preview_hours' => 0],
            'total_validation' => ['enabled' => false, 'reason' => 'Unsupported legacy workbook.'],
            'headers' => [],
            'fields' => [],
            'owner_decision_checklist' => [
                'Provide .xlsx export or approve legacy .xls reader support.',
                'Confirm employee identifier, timekeeping values, status-code dictionary, and period extraction after workbook is readable.',
            ],
        ];
    }

    private function mergeProfileMetadata(array $profile, array $analysis): array
    {
        $profile['file_type'] = $profile['file_type'] ?? $analysis['inventory']['extension'];
        $profile['bulk_inventory_classification'] = $analysis['inventory']['format_cluster'];
        $profile['bulk_profile_action'] = $analysis['inventory']['profile_action'];
        $profile['bulk_reader_status'] = $analysis['inventory']['reader_status'];
        return $profile;
    }

    private function coverageMatrix(array $inventory, array $profiles, array $adapterRun, array $mappingQueue): array
    {
        $profileByFilename = [];
        foreach ($profiles as $profile) {
            $profileByFilename[strtolower((string)$profile['filename'])] = $profile;
        }
        $resultByFilename = [];
        foreach (($adapterRun['results'] ?? []) as $result) {
            $resultByFilename[strtolower((string)$result['filename'])] = $result;
        }
        $mappingRequiredByFilename = [];
        foreach ($mappingQueue as $queueRow) {
            $fileProfile = (string)($queueRow['file_profile'] ?? '');
            $filename = trim(explode(' / ', $fileProfile, 2)[0] ?? '');
            if ($filename !== '') {
                $mappingRequiredByFilename[strtolower($filename)] = true;
            }
        }

        $rows = [];
        foreach ($inventory as $item) {
            $profile = $profileByFilename[strtolower($item['filename'])] ?? [];
            $result = $resultByFilename[strtolower($item['filename'])] ?? [];
            $validRows = (int)($result['valid_rows'] ?? 0);
            $errorRows = (int)($result['error_rows'] ?? 0);
            $rows[] = [
                'filename' => $item['filename'],
                'profile_key' => (string)($profile['key'] ?? $item['profile_key']),
                'format_cluster' => $item['format_cluster'],
                'coverage_classification' => (string)($profile['coverage_classification'] ?? $item['profile_action']),
                'reader_status' => $item['reader_status'],
                'parsed' => !empty($result) && ($result['mode'] ?? '') !== 'unsupported' ? 'yes' : 'no',
                'staged_rows' => (int)($result['rows'] ?? 0),
                'valid_preview_rows' => $validRows,
                'error_rows' => $errorRows,
                'zero_eligible_rows' => $validRows > 0 ? 'no' : 'yes',
                'owner_mapping_required' => !empty($mappingRequiredByFilename[strtolower($item['filename'])]) ? 'yes' : 'no',
                'unsupported' => ($profile['mode'] ?? '') === 'unsupported' ? 'yes' : 'no',
                'suspicious_hour_file' => (int)($result['suspicious_hour_rows'] ?? 0) > 0 || count((array)($result['profile_safety_warnings'] ?? [])) > 0 ? 'yes' : 'no',
                'high_error_file' => ((int)($result['rows'] ?? 0) > 0 && $errorRows / max(1, (int)$result['rows']) >= 0.5) ? 'yes' : 'no',
                'preview_hours' => (float)($result['preview_hours'] ?? 0),
                'best_profile' => (string)($profile['key'] ?? $item['profile_key']),
                'profile_confidence' => $this->profileConfidence($item, $profile, $result),
                'profile_selection_reason' => $this->profileSelectionReason($item, $profile, $result),
                'fallback_profile' => $this->fallbackProfile($item),
                'unsupported_reason' => $this->unsupportedReason($item, $profile, $result),
            ];
        }

        return $rows;
    }

    private function profileConfidence(array $item, array $profile, array $result): string
    {
        if (($profile['mode'] ?? '') === 'unsupported') {
            return '0.00';
        }
        if (!empty($result) && (int)($result['valid_rows'] ?? 0) > 0) {
            return ((string)$item['format_cluster'] === 'raw punch log') ? '0.74' : '0.82';
        }
        if ((string)$item['reader_status'] === 'xlsx_profiled') {
            return '0.55';
        }
        return '0.10';
    }

    private function profileSelectionReason(array $item, array $profile, array $result): string
    {
        if (($profile['mode'] ?? '') === 'raw_punch') {
            return 'Detected row-based employee/date/time-in/time-out headers; selected raw punch preview adapter.';
        }
        if (($profile['mode'] ?? '') === 'wide_daily') {
            return 'Detected daily column run or retained owner-reviewed wide summary profile.';
        }
        if (($profile['mode'] ?? '') === 'summary_period') {
            return 'Detected summary worked-days/hours profile; preview remains non-payroll.';
        }
        if ((string)$item['extension'] === 'xls') {
            return 'Legacy .xls workbook requires conversion or approved reader before profile selection.';
        }
        return 'No supported profile matched the detected layout.';
    }

    private function fallbackProfile(array $item): string
    {
        if ((string)$item['extension'] === 'xls') {
            return 'convert_to_xlsx_then_reprofile';
        }
        if ((string)$item['format_cluster'] === 'raw punch log') {
            return 'manual_raw_punch_mapping_review';
        }
        if ((string)$item['format_cluster'] === 'unsupported/unknown') {
            return 'owner_mapping_required';
        }
        return 'generated_summary_preview_profile';
    }

    private function unsupportedReason(array $item, array $profile, array $result): string
    {
        if (($profile['mode'] ?? '') !== 'unsupported') {
            return '';
        }
        if ((string)$item['extension'] === 'xls') {
            if ((string)($item['reader_status'] ?? '') === 'converted_xls_profiled') {
                return 'unsupported_layout_after_local_xls_conversion';
            }
            return 'legacy_xls_reader_unavailable';
        }
        if ((string)$item['format_cluster'] === 'raw punch log') {
            return 'raw_punch_columns_incomplete_or_owner_mapping_required';
        }
        return 'unsupported_or_unknown_layout';
    }

    private function augmentMappingQueueFromCoverage(array $mappingQueue, array $coverage): array
    {
        $existing = [];
        foreach ($mappingQueue as $row) {
            $fileProfile = (string)($row['file_profile'] ?? '');
            if ($fileProfile !== '') {
                $existing[strtolower(explode(' / ', $fileProfile, 2)[0])] = true;
            }
        }

        foreach ($coverage as $row) {
            $filename = (string)$row['filename'];
            $filenameKey = strtolower($filename);
            if (!empty($existing[$filenameKey])) {
                continue;
            }

            $needsQueue = false;
            $reason = '';
            $action = 'Confirm employee mapping, source semantics, and whether generated profile defaults are acceptable.';
            $default = 'Keep preview-only and blocked from payroll until owner confirms.';

            if ((string)$row['profile_key'] === 'CYA') {
                $needsQueue = true;
                $reason = 'CYA remains needs_owner_mapping for identifier/name/alias and worked-days interpretation.';
                $action = 'Provide or approve CYA employee identifier/name/alias mapping and worked-days interpretation.';
                $default = 'Keep CYA blocked beyond diagnostic preview.';
            } elseif ($row['parsed'] === 'yes' && (int)$row['valid_preview_rows'] === 0) {
                $needsQueue = true;
                $reason = 'Parsed file produced zero valid preview rows.';
            } elseif ($row['high_error_file'] === 'yes') {
                $needsQueue = true;
                $reason = 'Parsed file has a high validation error rate.';
            }

            if ($needsQueue) {
                $mappingQueue[] = [
                    'file_profile' => $filename . ' / ' . $row['profile_key'],
                    'missing_mapping_decision' => $reason,
                    'why_it_matters' => 'Without this decision, preview rows may not represent approved employee identity, client/site, period, or timekeeping semantics.',
                    'recommended_owner_action' => $action,
                    'suggested_default_if_safe' => $default,
                    'blocked_until_owner_confirms' => 'yes',
                ];
                $existing[$filenameKey] = true;
            }
        }

        return $mappingQueue;
    }

    private function refineMappingQueue(array $mappingQueue, array $inventory, array $coverage): array
    {
        $inventoryByFile = [];
        foreach ($inventory as $row) {
            $inventoryByFile[strtolower((string)$row['filename'])] = $row;
        }
        $coverageByFile = [];
        foreach ($coverage as $row) {
            $coverageByFile[strtolower((string)$row['filename'])] = $row;
        }

        foreach ($mappingQueue as $index => $row) {
            $filename = trim(explode(' / ', (string)($row['file_profile'] ?? ''), 2)[0] ?? '');
            $item = $inventoryByFile[strtolower($filename)] ?? [];
            $coverageRow = $coverageByFile[strtolower($filename)] ?? [];
            $issueType = $this->mappingIssueType($item, $coverageRow, $row);
            $issueCategory = in_array($issueType, ['legacy_xls_reader_required', 'unsupported_parser_layout'], true)
                ? 'technical_reader'
                : (in_array($issueType, ['raw_punch_adapter_mapping', 'profile_zero_valid_rows'], true) ? 'engineering_adapter' : 'business_mapping');

            $mappingQueue[$index]['issue_type'] = $issueType;
            $mappingQueue[$index]['client_source'] = (string)($item['inferred_client_source'] ?? '');
            $mappingQueue[$index]['issue_category'] = $issueCategory;
            $mappingQueue[$index]['default_safe_action'] = (string)($row['suggested_default_if_safe'] ?? 'Keep preview-only and blocked from payroll.');
            $mappingQueue[$index]['engineering_can_proceed_without_owner_decision'] = $issueCategory === 'technical_reader' || $issueType === 'raw_punch_adapter_mapping' ? 'yes_preview_only' : 'no';
        }

        return $mappingQueue;
    }

    private function mappingIssueType(array $item, array $coverageRow, array $queueRow): string
    {
        if ((string)($item['extension'] ?? '') === 'xls') {
            return 'legacy_xls_reader_required';
        }
        if ((string)($item['format_cluster'] ?? '') === 'raw punch log') {
            return 'raw_punch_adapter_mapping';
        }
        if ((string)($coverageRow['parsed'] ?? '') === 'yes' && (int)($coverageRow['valid_preview_rows'] ?? 0) === 0) {
            return 'profile_zero_valid_rows';
        }
        if ((string)($item['format_cluster'] ?? '') === 'unsupported/unknown') {
            return 'unsupported_parser_layout';
        }
        if (stripos((string)($queueRow['missing_mapping_decision'] ?? ''), 'employee') !== false) {
            return 'employee_mapping';
        }
        return 'business_mapping_confirmation';
    }

    private function unsupportedFiles(array $coverage): array
    {
        $rows = [];
        foreach ($coverage as $row) {
            if ($row['unsupported'] === 'yes' || $row['parsed'] === 'no') {
                $rows[] = [
                    'filename' => $row['filename'],
                    'format_cluster' => $row['format_cluster'],
                    'reader_status' => $row['reader_status'],
                    'unsupported_reason' => $row['unsupported_reason'],
                    'fallback_profile' => $row['fallback_profile'],
                    'default_safe_action' => 'Keep blocked from payroll and canonical DTR; use preview-only diagnostics if technically parseable.',
                ];
            }
        }
        return $rows;
    }

    private function xlsReaderStrategy(array $inventory): array
    {
        $rows = [];
        foreach ($inventory as $item) {
            if ((string)$item['extension'] !== 'xls') {
                continue;
            }
            $rows[] = [
                'filename' => $item['filename'],
                'client_source' => $item['inferred_client_source'],
                'php_zip_xml_reader_can_read' => 'no',
                'local_conversion_strategy' => 'Convert owner-provided copy to .xlsx or csv under audit_reports/regression/dtr_engine_sprint5/converted/ and rerun profiling.',
                'conversion_status' => 'blocked_no_local_converter_available',
                'production_dependency_required' => 'not_approved',
                'original_file_modified' => 'no',
            ];
        }
        return $rows;
    }

    private function rawPunchSupportMatrix(array $inventory, array $coverage, array $profiles): array
    {
        $coverageByFile = [];
        foreach ($coverage as $row) {
            $coverageByFile[strtolower((string)$row['filename'])] = $row;
        }
        $profileByFile = [];
        foreach ($profiles as $profile) {
            $profileByFile[strtolower((string)($profile['filename'] ?? ''))] = $profile;
        }

        $rows = [];
        foreach ($inventory as $item) {
            if ((string)$item['format_cluster'] !== 'raw punch log') {
                continue;
            }
            $profile = $profileByFile[strtolower((string)$item['filename'])] ?? [];
            $coverageRow = $coverageByFile[strtolower((string)$item['filename'])] ?? [];
            $supported = ($profile['mode'] ?? '') === 'raw_punch' && ($coverageRow['parsed'] ?? '') === 'yes' && (int)($coverageRow['valid_preview_rows'] ?? 0) > 0;
            $rows[] = [
                'filename' => $item['filename'],
                'client_source' => $item['inferred_client_source'],
                'sheet' => $item['selected_sheet'],
                'employee_identifier_fields' => $item['employee_identifier_fields'],
                'employee_name_fields' => $item['employee_name_fields'],
                'date_columns' => $item['date_day_columns'],
                'time_columns' => $item['time_in_out_columns'],
                'support_status' => $supported ? 'supported_preview_only' : 'blocked_mapping_or_layout',
                'parsed' => (string)($coverageRow['parsed'] ?? 'no'),
                'valid_preview_rows' => (int)($coverageRow['valid_preview_rows'] ?? 0),
                'notes' => $supported ? 'Row-based raw punch adapter produced preview rows only.' : 'Not enough row-based date/time-in/time-out mapping confidence for safe preview conversion.',
            ];
        }
        return $rows;
    }

    private function xlsConversionResults(array $inventory, array $coverage): array
    {
        $coverageByFile = [];
        foreach ($coverage as $row) {
            $coverageByFile[strtolower((string)$row['filename'])] = $row;
        }

        $rows = [];
        foreach ($inventory as $item) {
            if ((string)$item['extension'] !== 'xls') {
                continue;
            }
            $coverageRow = $coverageByFile[strtolower((string)$item['filename'])] ?? [];
            $converted = (string)($item['converted_from_xls'] ?? 'no') === 'yes';
            $rows[] = [
                'filename' => $item['filename'],
                'converted_filename' => (string)($item['converted_filename'] ?? ''),
                'client_source' => $item['inferred_client_source'],
                'local_tool' => $converted ? 'Excel COM local conversion' : 'none',
                'direct_php_read_supported' => 'no',
                'conversion_status' => $converted ? 'converted' : 'blocked_no_local_converter_available',
                'reader_status' => (string)($item['reader_status'] ?? ''),
                'parsed' => (string)($coverageRow['parsed'] ?? 'no'),
                'valid_preview_rows' => (int)($coverageRow['valid_preview_rows'] ?? 0),
                'original_file_modified' => 'no',
                'production_dependency_required' => 'optional_not_approved',
                'notes' => $converted
                    ? 'Converted copy is local-only under audit_reports/regression/dtr_engine_sprint6/converted/.'
                    : 'Requires owner-provided .xlsx/CSV export or approved local reader.',
            ];
        }
        return $rows;
    }

    private function matrixPunchSupportMatrix(array $inventory, array $coverage, array $profiles): array
    {
        $coverageByFile = [];
        foreach ($coverage as $row) {
            $coverageByFile[strtolower((string)$row['filename'])] = $row;
        }
        $profileByFile = [];
        foreach ($profiles as $profile) {
            $profileByFile[strtolower((string)($profile['filename'] ?? ''))] = $profile;
        }

        $rows = [];
        foreach ($inventory as $item) {
            $cluster = (string)$item['format_cluster'];
            $filename = (string)$item['filename'];
            $isMatrixCandidate = in_array($cluster, ['raw punch log', 'wide daily summary'], true)
                || stripos($filename, 'MTC') !== false
                || stripos($filename, 'Manhours') !== false
                || stripos($filename, 'TIMEKEEPING') !== false;
            if (!$isMatrixCandidate) {
                continue;
            }

            $profile = $profileByFile[strtolower($filename)] ?? [];
            $coverageRow = $coverageByFile[strtolower($filename)] ?? [];
            $validRows = (int)($coverageRow['valid_preview_rows'] ?? 0);
            $parsed = (string)($coverageRow['parsed'] ?? 'no');
            $mode = (string)($profile['mode'] ?? '');
            $supported = $parsed === 'yes' && $validRows > 0 && in_array($mode, ['raw_punch', 'wide_daily', 'summary_period'], true);

            $rows[] = [
                'filename' => $filename,
                'client_source' => $item['inferred_client_source'],
                'format_cluster' => $cluster,
                'profile_mode' => $mode,
                'sheet' => $item['selected_sheet'],
                'date_day_columns' => $item['date_day_columns'],
                'time_columns' => $item['time_in_out_columns'],
                'support_status' => $supported ? 'supported_preview_only' : 'blocked_mapping_or_layout',
                'parsed' => $parsed,
                'valid_preview_rows' => $validRows,
                'notes' => $supported
                    ? 'Matrix/raw/summary layout produced canonical preview rows only.'
                    : 'Layout remains blocked or produced zero valid rows; owner mapping or dedicated adapter rules required.',
            ];
        }

        return $rows;
    }

    private function fileQaScorecard(array $inventory, array $profiles, array $adapterRun, array $coverage, array $mappingQueue): array
    {
        $profileByFile = [];
        foreach ($profiles as $profile) {
            $profileByFile[strtolower((string)($profile['filename'] ?? ''))] = $profile;
        }
        $resultByFile = [];
        foreach (($adapterRun['results'] ?? []) as $result) {
            $resultByFile[strtolower((string)($result['filename'] ?? ''))] = $result;
        }
        $coverageByFile = [];
        foreach ($coverage as $row) {
            $coverageByFile[strtolower((string)$row['filename'])] = $row;
        }
        $mappingByFile = [];
        foreach ($mappingQueue as $row) {
            $filename = trim(explode(' / ', (string)($row['file_profile'] ?? ''), 2)[0] ?? '');
            if ($filename !== '') {
                $mappingByFile[strtolower($filename)] = $row;
            }
        }

        $rows = [];
        foreach ($inventory as $item) {
            $filename = (string)$item['filename'];
            $key = strtolower($filename);
            $profile = $profileByFile[$key] ?? [];
            $result = $resultByFile[$key] ?? [];
            $coverageRow = $coverageByFile[$key] ?? [];
            $mappingRow = $mappingByFile[$key] ?? [];
            $totalRows = (int)($result['rows'] ?? 0);
            $validRows = (int)($result['valid_rows'] ?? 0);
            $errorRows = (int)($result['error_rows'] ?? 0);
            $unmatchedRows = (int)($result['unmatched_rows'] ?? 0);
            $suspiciousRows = (int)($result['suspicious_hour_rows'] ?? 0);
            $totalMismatchRows = (int)($result['total_mismatch_rows'] ?? 0);
            $errorSummary = (array)($result['error_summary'] ?? []);
            $unsupportedValueRows = $this->sumErrorKeys($errorSummary, ['invalid_status_code', 'status_invalid', 'invalid_unknown', 'unsupported_format']);
            $duplicateRows = $this->sumErrorKeys($errorSummary, ['duplicate_punch_within_file', 'duplicate_row_within_file', 'duplicate_row_against_staged_batch', 'duplicate_or_conflict_requires_approval']);
            $mappingScore = $this->mappingCompletenessScore($profile, $mappingRow);
            $readiness = $this->recommendedReadiness($coverageRow, $totalRows, $validRows, $errorRows, $unmatchedRows, $suspiciousRows, $unsupportedValueRows, $totalMismatchRows, $mappingScore);

            $rows[] = [
                'filename' => $filename,
                'profile_key' => (string)($profile['key'] ?? $item['profile_key']),
                'format_cluster' => (string)$item['format_cluster'],
                'mode' => (string)($profile['mode'] ?? 'unsupported'),
                'parsed_successfully' => (string)($coverageRow['parsed'] ?? 'no'),
                'total_rows' => $totalRows,
                'valid_preview_rows' => $validRows,
                'error_rows' => $errorRows,
                'valid_preview_row_rate' => $this->rate($validRows, $totalRows),
                'error_row_rate' => $this->rate($errorRows, $totalRows),
                'unmatched_employee_rate' => $this->rate($unmatchedRows, $totalRows),
                'suspicious_hours_rate' => $this->rate($suspiciousRows, $totalRows),
                'duplicate_conflict_rate' => $this->rate($duplicateRows, $totalRows),
                'unsupported_value_rate' => $this->rate($unsupportedValueRows, $totalRows),
                'total_mismatch_rate' => $this->rate($totalMismatchRows, $totalRows),
                'mapping_completeness_score' => $mappingScore,
                'recommended_readiness' => $readiness,
                'primary_qa_reason' => $this->primaryQaReason($readiness, $coverageRow, $totalRows, $validRows, $errorRows, $unmatchedRows, $suspiciousRows, $unsupportedValueRows, $totalMismatchRows, $mappingScore),
                'preview_hours' => (float)($result['preview_hours'] ?? 0),
                'owner_mapping_required' => !empty($mappingRow) ? 'yes' : 'no',
            ];
        }

        return $rows;
    }

    private function sprint7ProfileCoverageMatrix(array $coverage, array $qaScorecard): array
    {
        $qaByFile = [];
        foreach ($qaScorecard as $row) {
            $qaByFile[strtolower((string)$row['filename'])] = $row;
        }

        $rows = [];
        foreach ($coverage as $row) {
            $qa = $qaByFile[strtolower((string)$row['filename'])] ?? [];
            $rows[] = array_merge($row, [
                'qa_readiness' => (string)($qa['recommended_readiness'] ?? ''),
                'qa_reason' => (string)($qa['primary_qa_reason'] ?? ''),
                'mapping_completeness_score' => (int)($qa['mapping_completeness_score'] ?? 0),
                'valid_preview_row_rate' => (string)($qa['valid_preview_row_rate'] ?? '0.0000'),
                'error_row_rate' => (string)($qa['error_row_rate'] ?? '0.0000'),
                'unmatched_employee_rate' => (string)($qa['unmatched_employee_rate'] ?? '0.0000'),
                'suspicious_hours_rate' => (string)($qa['suspicious_hours_rate'] ?? '0.0000'),
            ]);
        }

        return $rows;
    }

    private function sprint7OwnerMappingQueue(array $mappingQueue, array $qaScorecard, array $coverage): array
    {
        $existing = [];
        $rows = [];
        foreach ($mappingQueue as $row) {
            $filename = trim(explode(' / ', (string)($row['file_profile'] ?? ''), 2)[0] ?? '');
            $existing[strtolower($filename)] = true;
            $issueType = (string)($row['issue_type'] ?? 'business_mapping_confirmation');
            $rows[] = $this->ownerMappingQueueSprint7Row($row, $issueType);
        }

        $coverageByFile = [];
        foreach ($coverage as $row) {
            $coverageByFile[strtolower((string)$row['filename'])] = $row;
        }

        foreach ($qaScorecard as $qa) {
            $filename = (string)$qa['filename'];
            if (!empty($existing[strtolower($filename)])) {
                continue;
            }
            if (!in_array((string)$qa['recommended_readiness'], ['needs_mapping', 'engineering_gap', 'unsupported'], true)) {
                continue;
            }
            $coverageRow = $coverageByFile[strtolower($filename)] ?? [];
            $issueType = $qa['recommended_readiness'] === 'engineering_gap' ? 'engineering_parser_issue' : 'owner_business_decision';
            $rows[] = $this->ownerMappingQueueSprint7Row([
                'file_profile' => $filename . ' / ' . (string)($qa['profile_key'] ?? ''),
                'missing_mapping_decision' => (string)$qa['primary_qa_reason'],
                'recommended_owner_action' => $qa['recommended_readiness'] === 'engineering_gap' ? 'Review parser/mapping rules before relying on preview output.' : 'Confirm mapping semantics before promotion beyond preview.',
                'suggested_default_if_safe' => 'Keep preview-only and blocked from payroll.',
                'issue_category' => $qa['recommended_readiness'] === 'engineering_gap' ? 'engineering_adapter' : 'business_mapping',
                'client_source' => (string)($coverageRow['client_source'] ?? ''),
            ], $issueType);
        }

        return $rows;
    }

    private function ownerMappingQueueSprint7Row(array $row, string $issueType): array
    {
        return [
            'file_profile' => (string)($row['file_profile'] ?? ''),
            'issue_type' => $issueType,
            'issue_category' => (string)($row['issue_category'] ?? ''),
            'client_source' => (string)($row['client_source'] ?? ''),
            'owner_business_decision' => in_array($issueType, ['business_mapping_confirmation', 'owner_business_decision'], true) ? 'required' : 'not_primary',
            'employee_alias_matching_decision' => stripos($issueType, 'employee') !== false ? 'required' : 'review_if_unmatched',
            'status_code_decision' => in_array($issueType, ['status_code_decision', 'unsupported_status_value'], true) ? 'required' : 'review_if_status_gap',
            'client_site_binding_decision' => 'required_before_promotion',
            'pay_period_extraction_decision' => 'required_before_promotion',
            'engineering_parser_issue' => in_array($issueType, ['engineering_parser_issue', 'unsupported_parser_layout', 'profile_zero_valid_rows', 'raw_punch_adapter_mapping'], true) ? 'yes' : 'no',
            'reader_conversion_issue' => in_array($issueType, ['legacy_xls_reader_required', 'reader_conversion_issue'], true) ? 'yes' : 'no',
            'recommended_action' => (string)($row['recommended_owner_action'] ?? ''),
            'default_safe_action' => (string)($row['suggested_default_if_safe'] ?? 'Keep preview-only and blocked from payroll.'),
        ];
    }

    private function unsupportedFileBacklog(array $inventory, array $coverage, array $qaScorecard): array
    {
        $coverageByFile = [];
        foreach ($coverage as $row) {
            $coverageByFile[strtolower((string)$row['filename'])] = $row;
        }
        $qaByFile = [];
        foreach ($qaScorecard as $row) {
            $qaByFile[strtolower((string)$row['filename'])] = $row;
        }

        $rows = [];
        foreach ($inventory as $item) {
            $filename = (string)$item['filename'];
            $coverageRow = $coverageByFile[strtolower($filename)] ?? [];
            if (($coverageRow['unsupported'] ?? 'no') !== 'yes' && ($coverageRow['parsed'] ?? 'no') === 'yes') {
                continue;
            }
            $reason = (string)($coverageRow['unsupported_reason'] ?? 'unsupported_or_zero_parse');
            $suspected = $this->suspectedLayout($item);
            $rows[] = [
                'filename' => $filename,
                'reason_unsupported' => $reason !== '' ? $reason : (string)($qaByFile[strtolower($filename)]['primary_qa_reason'] ?? 'unsupported_or_zero_parse'),
                'suspected_layout' => $suspected,
                'required_engineering_action' => $this->engineeringActionForLayout($suspected),
                'required_owner_mapping_action' => 'Confirm employee identifier/name, status meanings, client/site binding, and pay-period extraction if engineering support is added.',
                'likely_reusable_adapter_type' => $this->adapterTypeForLayout($suspected),
                'recommended_priority' => $this->unsupportedPriority($filename, $suspected),
            ];
        }

        return $rows;
    }

    private function suspiciousPreviewFindings(array $adapterRun, array $qaScorecard): array
    {
        $qaByFile = [];
        foreach ($qaScorecard as $row) {
            $qaByFile[strtolower((string)$row['filename'])] = $row;
        }

        $rows = [];
        foreach (($adapterRun['results'] ?? []) as $result) {
            $filename = (string)($result['filename'] ?? '');
            $qa = $qaByFile[strtolower($filename)] ?? [];
            $sources = [
                'profile_safety_warnings' => (array)($result['profile_safety_warnings'] ?? []),
                'suspicious_hour_summary' => array_keys((array)($result['suspicious_hour_summary'] ?? [])),
                'error_summary' => array_keys((array)($result['error_summary'] ?? [])),
            ];
            foreach ($sources as $source => $items) {
                foreach ($items as $item) {
                    if (!$this->isSuspiciousFinding((string)$item)) {
                        continue;
                    }
                    $count = $source === 'suspicious_hour_summary'
                        ? $this->summaryCount((array)($result['suspicious_hour_summary'] ?? []), (string)$item)
                        : $this->summaryCount((array)($result['error_summary'] ?? []), (string)$item);
                    $rows[] = [
                        'filename' => $filename,
                        'profile_key' => (string)($result['key'] ?? ''),
                        'finding_type' => (string)$item,
                        'finding_source' => $source,
                        'affected_rows' => max(1, $count),
                        'preview_hours' => (float)($result['preview_hours'] ?? 0),
                        'recommended_action' => $this->findingAction((string)$item),
                        'qa_readiness' => (string)($qa['recommended_readiness'] ?? ''),
                    ];
                }
            }
            if ((float)($result['preview_hours'] ?? 0) > 2000) {
                $rows[] = [
                    'filename' => $filename,
                    'profile_key' => (string)($result['key'] ?? ''),
                    'finding_type' => 'profile_preview_hours_exceed_review_threshold',
                    'finding_source' => 'profile_total',
                    'affected_rows' => (int)($result['valid_rows'] ?? 0),
                    'preview_hours' => (float)($result['preview_hours'] ?? 0),
                    'recommended_action' => 'Confirm whether values represent hours, days, totals, or payroll summaries.',
                    'qa_readiness' => (string)($qa['recommended_readiness'] ?? ''),
                ];
            }
        }

        return $rows;
    }

    private function summaryCount(array $summary, string $key): int
    {
        return max(1, (int)($summary[$key] ?? 1));
    }

    private function statusDictionaryGaps(array $adapterRun, array $profiles): array
    {
        $profileByKey = [];
        foreach ($profiles as $profile) {
            $profileByKey[(string)($profile['key'] ?? '')] = $profile;
        }
        $rows = [];
        foreach (($adapterRun['results'] ?? []) as $result) {
            $key = (string)($result['key'] ?? '');
            $errorSummary = (array)($result['error_summary'] ?? []);
            $statusSummary = (array)($result['status_summary'] ?? []);
            $gapCount = $this->sumErrorKeys($errorSummary, ['invalid_status_code', 'status_invalid', 'invalid_unknown']);
            if ($gapCount <= 0 && !isset($statusSummary['invalid_unknown']) && !isset($statusSummary['invalid'])) {
                continue;
            }
            $profile = $profileByKey[$key] ?? [];
            $rows[] = [
                'filename' => (string)($result['filename'] ?? ''),
                'profile_key' => $key,
                'mode' => (string)($result['mode'] ?? ''),
                'gap_type' => 'unresolved_or_invalid_status_value',
                'affected_rows' => max($gapCount, (int)($statusSummary['invalid_unknown'] ?? 0), (int)($statusSummary['invalid'] ?? 0)),
                'known_status_codes' => implode('|', array_keys((array)($profile['status_dictionary']['codes'] ?? []))),
                'recommended_owner_decision' => 'Confirm whether unresolved values mean worked, absent, rest day, leave, holiday, blank/ignored, unknown, or invalid.',
            ];
        }
        return $rows;
    }

    private function sumErrorKeys(array $summary, array $keys): int
    {
        $total = 0;
        foreach ($keys as $key) {
            $total += (int)($summary[$key] ?? 0);
        }
        return $total;
    }

    private function rate(int $numerator, int $denominator): string
    {
        return $denominator > 0 ? number_format($numerator / $denominator, 4, '.', '') : '0.0000';
    }

    private function mappingCompletenessScore(array $profile, array $mappingRow): int
    {
        $score = 100;
        $columns = (array)($profile['columns'] ?? []);
        foreach (['employee_identifier', 'employee_name'] as $field) {
            if ((int)($columns[$field] ?? 0) <= 0) {
                $score -= 20;
            }
        }
        if (empty($profile['period_start']) || empty($profile['period_end'])) {
            $score -= 15;
        }
        if (empty($profile['status_dictionary']['codes']) && ($profile['mode'] ?? '') === 'wide_daily') {
            $score -= 15;
        }
        if (!empty($mappingRow)) {
            $score -= 20;
        }
        if ((int)($profile['client_id'] ?? 0) <= 0) {
            $score -= 10;
        }
        if ((int)($profile['location_id'] ?? 0) <= 0) {
            $score -= 5;
        }
        return max(0, min(100, $score));
    }

    private function recommendedReadiness(array $coverageRow, int $totalRows, int $validRows, int $errorRows, int $unmatchedRows, int $suspiciousRows, int $unsupportedValueRows, int $totalMismatchRows, int $mappingScore): string
    {
        if (($coverageRow['unsupported'] ?? 'yes') === 'yes' || ($coverageRow['parsed'] ?? 'no') !== 'yes') {
            return 'unsupported';
        }
        if ($validRows <= 0 || ($totalRows > 0 && $errorRows / max(1, $totalRows) >= 0.90) || ($totalRows > 0 && $unsupportedValueRows / max(1, $totalRows) >= 0.50)) {
            return 'engineering_gap';
        }
        if ($mappingScore < 80 || $totalMismatchRows > 0 || ($totalRows > 0 && ($unmatchedRows / max(1, $totalRows) > 0.25 || $suspiciousRows / max(1, $totalRows) > 0.25))) {
            return 'needs_mapping';
        }
        return 'preview_ok';
    }

    private function primaryQaReason(string $readiness, array $coverageRow, int $totalRows, int $validRows, int $errorRows, int $unmatchedRows, int $suspiciousRows, int $unsupportedValueRows, int $totalMismatchRows, int $mappingScore): string
    {
        if ($readiness === 'unsupported') {
            return (string)($coverageRow['unsupported_reason'] ?? 'unsupported_or_not_parsed');
        }
        if ($validRows <= 0) {
            return 'zero_valid_preview_rows';
        }
        if ($totalRows > 0 && $errorRows / max(1, $totalRows) >= 0.90) {
            return 'high_error_rate';
        }
        if ($unsupportedValueRows > 0) {
            return 'unsupported_or_invalid_status_values';
        }
        if ($unmatchedRows > 0) {
            return 'unmatched_employee_review_required';
        }
        if ($suspiciousRows > 0) {
            return 'suspicious_preview_values_require_review';
        }
        if ($totalMismatchRows > 0) {
            return 'total_mismatch_requires_review';
        }
        if ($mappingScore < 80) {
            return 'mapping_completeness_below_threshold';
        }
        return 'preview_qa_thresholds_passed';
    }

    private function suspectedLayout(array $item): string
    {
        if ((string)($item['extension'] ?? '') === 'xls' && (string)($item['converted_from_xls'] ?? '') === 'yes') {
            return 'converted_legacy_xls_unmapped_summary';
        }
        if ((string)($item['format_cluster'] ?? '') === 'raw punch log') {
            return 'raw_punch_or_biometric_layout';
        }
        if ((string)($item['format_cluster'] ?? '') === 'wide daily summary') {
            return 'wide_daily_or_matrix_layout';
        }
        return 'unknown_or_unmapped_summary_layout';
    }

    private function engineeringActionForLayout(string $layout): string
    {
        if ($layout === 'converted_legacy_xls_unmapped_summary') {
            return 'Profile converted workbook sheets and add reusable summary mapping rules.';
        }
        if ($layout === 'raw_punch_or_biometric_layout') {
            return 'Add dedicated raw punch/biometric adapter rules for date, in/out, and duplicate grouping.';
        }
        if ($layout === 'wide_daily_or_matrix_layout') {
            return 'Add matrix day-column detection and footer/summary row exclusion rules.';
        }
        return 'Inspect workbook structure and create adapter profile or mark unsupported.';
    }

    private function adapterTypeForLayout(string $layout): string
    {
        if ($layout === 'converted_legacy_xls_unmapped_summary') {
            return 'converted_xls_summary_profile';
        }
        if ($layout === 'raw_punch_or_biometric_layout') {
            return 'raw_punch_adapter';
        }
        if ($layout === 'wide_daily_or_matrix_layout') {
            return 'wide_matrix_adapter';
        }
        return 'new_profile_required';
    }

    private function unsupportedPriority(string $filename, string $layout): string
    {
        if (stripos($filename, 'PHLAG') !== false) {
            return 'high_reusable_family';
        }
        if ($layout === 'converted_legacy_xls_unmapped_summary') {
            return 'medium';
        }
        return 'low_until_owner_mapping';
    }

    private function isSuspiciousFinding(string $item): bool
    {
        return preg_match('/suspicious|exceeds|invalid|unknown|unsupported|duplicate|mismatch|zero_hour|possible_total|summary|blank|footer|conflict/i', $item) === 1;
    }

    private function findingAction(string $item): string
    {
        if (stripos($item, 'duplicate') !== false || stripos($item, 'conflict') !== false) {
            return 'Review duplicate/conflict handling before approving preview rows.';
        }
        if (stripos($item, 'status') !== false || stripos($item, 'unknown') !== false || stripos($item, 'invalid') !== false) {
            return 'Confirm status-code dictionary and blank/unknown semantics.';
        }
        if (stripos($item, 'summary') !== false || stripos($item, 'total') !== false || stripos($item, 'hours') !== false) {
            return 'Confirm whether values represent daily hours, worked days, totals, or payroll summary values.';
        }
        return 'Review before any promotion beyond preview.';
    }

    private function summary(array $inventory, array $coverage, array $mappingQueue, array $clusters, array $adapterRun): array
    {
        return [
            'total_files_assessed' => count($inventory),
            'files_successfully_profiled' => count(array_filter($inventory, static fn($row) => in_array($row['reader_status'], ['xlsx_profiled', 'converted_xls_profiled'], true))),
            'files_parsed' => count(array_filter($coverage, static fn($row) => $row['parsed'] === 'yes')),
            'files_with_valid_preview_rows' => count(array_filter($coverage, static fn($row) => (int)$row['valid_preview_rows'] > 0)),
            'files_with_zero_eligible_rows' => count(array_filter($coverage, static fn($row) => $row['zero_eligible_rows'] === 'yes')),
            'files_requiring_owner_mapping' => count($mappingQueue),
            'files_requiring_new_profile' => count(array_filter($inventory, static fn($row) => strpos((string)$row['profile_action'], 'new reusable') !== false)),
            'unsupported_files' => count(array_filter($coverage, static fn($row) => $row['unsupported'] === 'yes')),
            'suspicious_hour_files' => count(array_filter($coverage, static fn($row) => $row['suspicious_hour_file'] === 'yes')),
            'high_error_files' => count(array_filter($coverage, static fn($row) => $row['high_error_file'] === 'yes')),
            'reusable_profile_count' => count($coverage),
            'clusters' => $clusters,
            'adapter_success' => (int)($adapterRun['success'] ?? 0),
            'source_context' => self::SAMPLE_CONTEXT,
            'deployment_recommendation' => 'no-go',
        ];
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if (!$handle) {
            throw new RuntimeException('Unable to write CSV: ' . basename($path));
        }
        if (count($rows) === 0) {
            fclose($handle);
            return;
        }
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    private function writeReport(string $path, array $summary, array $coverage, array $mappingQueue): void
    {
        $clusterLines = [];
        foreach ($summary['clusters'] as $cluster => $count) {
            $clusterLines[] = '- ' . $cluster . ': ' . $count;
        }
        $coverageLines = [];
        foreach ($coverage as $row) {
            $coverageLines[] = '| `' . $row['filename'] . '` | ' . $row['format_cluster'] . ' | ' . $row['coverage_classification'] . ' | ' . $row['parsed'] . ' | ' . $row['valid_preview_rows'] . ' | ' . $row['owner_mapping_required'] . ' |';
        }
        $mappingLines = [];
        foreach ($mappingQueue as $row) {
            $mappingLines[] = '| `' . $row['file_profile'] . '` | ' . $row['missing_mapping_decision'] . ' | ' . $row['recommended_owner_action'] . ' |';
        }

        $body = "# DTR Engine Sprint 4 Bulk Sample Template Integration\n\n"
            . "Date: 2026-06-21\n\n"
            . "## Scope\n\n"
            . "Sprint 4 processed all owner-provided local sample files for adapter-template coverage. Work remained local-only and preview-only.\n\n"
            . "No deployment, commit, push, production access, canonical DTR write, payroll table write, payroll generation, payroll formula change, employee/client update, mutating stored procedure, or payroll handoff occurred.\n\n"
            . "## Summary\n\n"
            . "- Total files assessed: {$summary['total_files_assessed']}\n"
            . "- Files successfully profiled: {$summary['files_successfully_profiled']}\n"
            . "- Files parsed through DTR engine staging: {$summary['files_parsed']}\n"
            . "- Files with valid preview rows: {$summary['files_with_valid_preview_rows']}\n"
            . "- Files with zero eligible rows: {$summary['files_with_zero_eligible_rows']}\n"
            . "- Files requiring owner mapping: {$summary['files_requiring_owner_mapping']}\n"
            . "- Files requiring new profile: {$summary['files_requiring_new_profile']}\n"
            . "- Unsupported files: {$summary['unsupported_files']}\n"
            . "- Suspicious-hour files: {$summary['suspicious_hour_files']}\n"
            . "- High-error files: {$summary['high_error_files']}\n"
            . "- Reusable profile count: {$summary['reusable_profile_count']}\n\n"
            . "## Format Clusters\n\n"
            . implode("\n", $clusterLines) . "\n\n"
            . "## Coverage Matrix\n\n"
            . "| File | Cluster | Classification | Parsed | Valid Preview Rows | Owner Mapping Required |\n"
            . "|---|---|---|---:|---:|---|\n"
            . implode("\n", $coverageLines) . "\n\n"
            . "## Owner Mapping Queue\n\n"
            . "| File/Profile | Missing Decision | Recommended Owner Action |\n"
            . "|---|---|---|\n"
            . (count($mappingLines) ? implode("\n", $mappingLines) : "| None | None | None |") . "\n\n"
            . "## Evidence Files\n\n"
            . "- `audit_reports/regression/dtr_engine_sprint4/sample_inventory.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint4/profile_coverage_matrix.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint4/owner_mapping_queue.csv`\n\n"
            . "## Safety Result\n\n"
            . "All generated rows are DTR Format Engine staging/preview rows only. Preview hours are not payroll-approved values.\n\n"
            . "## Deployment Recommendation\n\n"
            . "No-go for deployment. Proceed next to owner mapping decisions and adapter refinement only. Do not connect to canonical DTR or payroll generation.\n";

        file_put_contents($path, $body, LOCK_EX);
    }

    private function writeSprint5Report(string $path, array $summary, array $coverage, array $mappingQueue, array $xlsStrategy, array $rawPunchMatrix): void
    {
        $coverageLines = [];
        foreach ($coverage as $row) {
            $coverageLines[] = '| `' . $row['filename'] . '` | ' . $row['format_cluster'] . ' | ' . $row['best_profile'] . ' | ' . $row['profile_confidence'] . ' | ' . $row['parsed'] . ' | ' . $row['valid_preview_rows'] . ' | ' . $row['unsupported_reason'] . ' |';
        }

        $xlsLines = [];
        foreach ($xlsStrategy as $row) {
            $xlsLines[] = '| `' . $row['filename'] . '` | ' . $row['php_zip_xml_reader_can_read'] . ' | ' . $row['conversion_status'] . ' | ' . $row['production_dependency_required'] . ' |';
        }

        $rawLines = [];
        foreach ($rawPunchMatrix as $row) {
            $rawLines[] = '| `' . $row['filename'] . '` | ' . $row['support_status'] . ' | ' . $row['parsed'] . ' | ' . $row['valid_preview_rows'] . ' | ' . $row['notes'] . ' |';
        }

        $queueLines = [];
        foreach ($mappingQueue as $row) {
            $queueLines[] = '| `' . $row['file_profile'] . '` | ' . ($row['issue_type'] ?? '') . ' | ' . ($row['issue_category'] ?? '') . ' | ' . ($row['client_source'] ?? '') . ' | ' . ($row['engineering_can_proceed_without_owner_decision'] ?? '') . ' |';
        }

        $body = "# DTR Engine Sprint 5 Bulk Adapter Gap Closure\n\n"
            . "Date: 2026-06-21\n\n"
            . "## Scope\n\n"
            . "Sprint 5 expanded local-only adapter coverage for the 28-file bulk sample set. Work focused on legacy `.xls` classification, row-based raw punch preview conversion, profile matching confidence, and owner mapping queue refinement.\n\n"
            . "No deployment, commit, push, production access, production modification, canonical DTR write, payroll table write, payroll generation, payroll formula change, employee/client update, mutating stored procedure, destructive workflow implementation, or payroll handoff occurred. Preview hours remain non-payroll values.\n\n"
            . "## Summary\n\n"
            . "- Total samples assessed: {$summary['total_files_assessed']}\n"
            . "- Files parsed before Sprint 5 baseline: {$summary['sprint4_baseline_files_parsed']}\n"
            . "- Files parsed after Sprint 5: {$summary['files_parsed']}\n"
            . "- Files with valid preview rows before Sprint 5 baseline: {$summary['sprint4_baseline_valid_preview_files']}\n"
            . "- Files with valid preview rows after Sprint 5: {$summary['files_with_valid_preview_rows']}\n"
            . "- Legacy `.xls` files classified: {$summary['legacy_xls_files']}\n"
            . "- Legacy `.xls` files converted locally: {$summary['legacy_xls_converted']}\n"
            . "- Raw punch files identified: {$summary['raw_punch_files']}\n"
            . "- Raw punch files supported for preview: {$summary['raw_punch_supported']}\n"
            . "- Raw punch files still blocked: {$summary['raw_punch_blocked']}\n"
            . "- Unsupported files: {$summary['unsupported_files']}\n"
            . "- Owner mapping queue items: {$summary['files_requiring_owner_mapping']}\n\n"
            . "## Coverage Matrix\n\n"
            . "| File | Cluster | Best Profile | Confidence | Parsed | Valid Preview Rows | Unsupported Reason |\n"
            . "|---|---|---|---:|---:|---:|---|\n"
            . implode("\n", $coverageLines) . "\n\n"
            . "## Legacy XLS Strategy\n\n"
            . "| File | PHP ZIP/XML Reader Can Read | Conversion Status | Production Dependency Required |\n"
            . "|---|---|---|---|\n"
            . (count($xlsLines) ? implode("\n", $xlsLines) : "| None | n/a | n/a | n/a |") . "\n\n"
            . "## Raw Punch Support Matrix\n\n"
            . "| File | Support Status | Parsed | Valid Preview Rows | Notes |\n"
            . "|---|---|---:|---:|---|\n"
            . (count($rawLines) ? implode("\n", $rawLines) : "| None | n/a | n/a | n/a | n/a |") . "\n\n"
            . "## Owner Mapping Queue Refinement\n\n"
            . "| File/Profile | Issue Type | Category | Client/Source | Engineering Can Proceed Without Owner Decision |\n"
            . "|---|---|---|---|---|\n"
            . (count($queueLines) ? implode("\n", $queueLines) : "| None | None | None | None | n/a |") . "\n\n"
            . "## Evidence Files\n\n"
            . "- `audit_reports/regression/dtr_engine_sprint5/sample_inventory.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint5/profile_coverage_matrix.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint5/owner_mapping_queue.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint5/unsupported_files.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint5/xls_reader_strategy.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint5/raw_punch_support_matrix.csv`\n\n"
            . "## Safety Confirmation\n\n"
            . "All parsed rows are staged only in DTR Format Engine preview/staging tables. Canonical DTR and payroll tables remain outside this sprint's write boundary.\n\n"
            . "## Remaining Blockers\n\n"
            . "- Legacy `.xls` files need owner-provided `.xlsx`/CSV exports or separately approved local reader/conversion dependency.\n"
            . "- Matrix-style raw punch/schedule files need dedicated profile rules before preview conversion.\n"
            . "- Owner mapping decisions remain required for business semantics, employee aliases, status meanings, client/site binding, and pay-period extraction.\n"
            . "- Payroll handoff remains blocked.\n\n"
            . "## Recommended Sprint 6\n\n"
            . "Continue adapter coverage work by adding a controlled matrix punch/schedule adapter design and testing owner-approved conversions of `.xls` files to `.xlsx`/CSV in the audit regression folder only.\n\n"
            . "## Deployment Recommendation\n\n"
            . "No-go for deployment. Continue local adapter coverage until the sample set has a stable coverage baseline. Do not connect to canonical DTR or payroll generation.\n";

        file_put_contents($path, $body, LOCK_EX);
    }

    private function writeSprint6Report(string $path, array $summary, array $coverage, array $mappingQueue, array $xlsConversion, array $matrixPunch): void
    {
        $coverageLines = [];
        foreach ($coverage as $row) {
            $coverageLines[] = '| `' . $row['filename'] . '` | ' . $row['format_cluster'] . ' | ' . $row['best_profile'] . ' | ' . $row['profile_confidence'] . ' | ' . $row['parsed'] . ' | ' . $row['valid_preview_rows'] . ' | ' . $row['unsupported_reason'] . ' |';
        }

        $xlsLines = [];
        foreach ($xlsConversion as $row) {
            $xlsLines[] = '| `' . $row['filename'] . '` | `' . $row['converted_filename'] . '` | ' . $row['conversion_status'] . ' | ' . $row['parsed'] . ' | ' . $row['valid_preview_rows'] . ' | ' . $row['original_file_modified'] . ' |';
        }

        $matrixLines = [];
        foreach ($matrixPunch as $row) {
            $matrixLines[] = '| `' . $row['filename'] . '` | ' . $row['format_cluster'] . ' | ' . $row['profile_mode'] . ' | ' . $row['support_status'] . ' | ' . $row['parsed'] . ' | ' . $row['valid_preview_rows'] . ' |';
        }

        $queueLines = [];
        foreach ($mappingQueue as $row) {
            $queueLines[] = '| `' . $row['file_profile'] . '` | ' . ($row['issue_type'] ?? '') . ' | ' . ($row['issue_category'] ?? '') . ' | ' . ($row['client_source'] ?? '') . ' | ' . ($row['engineering_can_proceed_without_owner_decision'] ?? '') . ' |';
        }

        $body = "# DTR Engine Sprint 6 XLS Conversion and Matrix Punch Adapter\n\n"
            . "Date: 2026-06-22\n\n"
            . "## Scope\n\n"
            . "Sprint 6 expanded local-only sample coverage by converting legacy `.xls` copies through the local Excel COM runtime and improving matrix/day-column detection for complex timekeeping workbooks.\n\n"
            . "No deployment, commit, push, production access, production modification, canonical DTR write, payroll table write, payroll generation, payroll formula change, employee/client update, mutating stored procedure, destructive workflow implementation, or payroll handoff occurred. Preview hours remain non-payroll values.\n\n"
            . "## Summary\n\n"
            . "- Total samples assessed: {$summary['total_files_assessed']}\n"
            . "- Files parsed before Sprint 6 baseline: {$summary['sprint5_baseline_files_parsed']}\n"
            . "- Files parsed after Sprint 6: {$summary['files_parsed']}\n"
            . "- Files with valid preview rows before Sprint 6 baseline: {$summary['sprint5_baseline_valid_preview_files']}\n"
            . "- Files with valid preview rows after Sprint 6: {$summary['files_with_valid_preview_rows']}\n"
            . "- Unsupported files before Sprint 6 baseline: {$summary['sprint5_baseline_unsupported_files']}\n"
            . "- Unsupported files after Sprint 6: {$summary['unsupported_files']}\n"
            . "- Legacy `.xls` files: {$summary['legacy_xls_files']}\n"
            . "- Legacy `.xls` files converted locally: {$summary['legacy_xls_converted']}\n"
            . "- Matrix/raw punch candidates: {$summary['matrix_punch_files']}\n"
            . "- Matrix/raw punch supported preview-only: {$summary['matrix_punch_supported']}\n"
            . "- Matrix/raw punch blocked: {$summary['matrix_punch_blocked']}\n"
            . "- Owner mapping queue items: {$summary['files_requiring_owner_mapping']}\n\n"
            . "## Coverage Matrix\n\n"
            . "| File | Cluster | Best Profile | Confidence | Parsed | Valid Preview Rows | Unsupported Reason |\n"
            . "|---|---|---|---:|---:|---:|---|\n"
            . implode("\n", $coverageLines) . "\n\n"
            . "## XLS Conversion Results\n\n"
            . "| Original XLS | Converted Copy | Status | Parsed | Valid Preview Rows | Original Modified |\n"
            . "|---|---|---|---:|---:|---|\n"
            . (count($xlsLines) ? implode("\n", $xlsLines) : "| None | None | None | 0 | 0 | no |") . "\n\n"
            . "## Matrix / Complex Punch Support\n\n"
            . "| File | Cluster | Profile Mode | Support Status | Parsed | Valid Preview Rows |\n"
            . "|---|---|---|---|---:|---:|\n"
            . (count($matrixLines) ? implode("\n", $matrixLines) : "| None | None | None | None | 0 | 0 |") . "\n\n"
            . "## Owner Mapping Queue\n\n"
            . "| File/Profile | Issue Type | Category | Client/Source | Engineering Can Proceed Without Owner Decision |\n"
            . "|---|---|---|---|---|\n"
            . (count($queueLines) ? implode("\n", $queueLines) : "| None | None | None | None | n/a |") . "\n\n"
            . "## Evidence Files\n\n"
            . "- `audit_reports/regression/dtr_engine_sprint6/sample_inventory.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint6/profile_coverage_matrix.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint6/owner_mapping_queue.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint6/unsupported_files.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint6/xls_conversion_results.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint6/matrix_punch_support_matrix.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint6/converted/`\n\n"
            . "## Safety Confirmation\n\n"
            . "Converted files are local audit copies only. All parsed rows are staged only in DTR Format Engine preview/staging tables. Canonical DTR and payroll tables remain outside this sprint's write boundary.\n\n"
            . "## Remaining Blockers\n\n"
            . "- Owner mapping decisions remain required for business semantics, employee aliases, status meanings, client/site binding, and pay-period extraction.\n"
            . "- Zero-valid/high-error generated profiles need owner mapping or dedicated adapter hardening before promotion beyond preview.\n"
            . "- Payroll handoff remains blocked.\n\n"
            . "## Recommended Next Sprint\n\n"
            . "Continue adapter coverage hardening for zero-valid/high-error profiles and add owner-reviewed mapping defaults for converted `.xls` files. Keep all flows preview-only.\n\n"
            . "## Deployment Recommendation\n\n"
            . "No-go for deployment. Continue adapter coverage work until sample support is stable. Do not connect to canonical DTR or payroll generation.\n";

        file_put_contents($path, $body, LOCK_EX);
    }

    private function writeSprint7Report(string $path, array $summary, array $qaScorecard, array $ownerQueue, array $unsupportedBacklog, array $suspiciousFindings, array $statusGaps): void
    {
        $readinessCounts = [];
        foreach ($qaScorecard as $row) {
            $key = (string)$row['recommended_readiness'];
            $readinessCounts[$key] = ($readinessCounts[$key] ?? 0) + 1;
        }
        $readinessLines = [];
        foreach ($readinessCounts as $label => $count) {
            $readinessLines[] = '- ' . $label . ': ' . $count;
        }

        $qaLines = [];
        foreach ($qaScorecard as $row) {
            $qaLines[] = '| `' . $row['filename'] . '` | ' . $row['recommended_readiness'] . ' | ' . $row['mapping_completeness_score'] . ' | ' . $row['valid_preview_rows'] . ' | ' . $row['valid_preview_row_rate'] . ' | ' . $row['error_row_rate'] . ' | ' . $row['primary_qa_reason'] . ' |';
        }

        $unsupportedLines = [];
        foreach ($unsupportedBacklog as $row) {
            $unsupportedLines[] = '| `' . $row['filename'] . '` | ' . $row['reason_unsupported'] . ' | ' . $row['suspected_layout'] . ' | ' . $row['likely_reusable_adapter_type'] . ' | ' . $row['recommended_priority'] . ' |';
        }

        $findingTypeCounts = [];
        foreach ($suspiciousFindings as $row) {
            $type = (string)$row['finding_type'];
            $findingTypeCounts[$type] = ($findingTypeCounts[$type] ?? 0) + 1;
        }
        arsort($findingTypeCounts);
        $findingLines = [];
        foreach (array_slice($findingTypeCounts, 0, 12, true) as $type => $count) {
            $findingLines[] = '- ' . $type . ': ' . $count;
        }

        $statusLines = [];
        foreach ($statusGaps as $row) {
            $statusLines[] = '| `' . $row['filename'] . '` | ' . $row['profile_key'] . ' | ' . $row['gap_type'] . ' | ' . $row['affected_rows'] . ' |';
        }

        $body = "# DTR Engine Sprint 7 Preview QA and Mapping Hardening\n\n"
            . "Date: 2026-06-22\n\n"
            . "## Scope\n\n"
            . "Sprint 7 added preview-level QA scoring, suspicious-value detection, status dictionary gap reporting, refined owner mapping queues, and a clearer unsupported-file engineering backlog over the Sprint 6 preview-capable sample set.\n\n"
            . "No deployment, commit, push, production access, production modification, canonical DTR write, payroll table write, payroll generation, payroll formula change, employee/client update, mutating stored procedure, destructive workflow implementation, or payroll handoff occurred. Preview hours remain non-payroll values.\n\n"
            . "## Summary\n\n"
            . "- Total samples assessed: {$summary['total_files_assessed']}\n"
            . "- Parsed files: {$summary['files_parsed']}\n"
            . "- Valid preview files: {$summary['files_with_valid_preview_rows']}\n"
            . "- Unsupported files: {$summary['unsupported_files']}\n"
            . "- Preview OK files: {$summary['qa_preview_ok_files']}\n"
            . "- Needs mapping files: {$summary['qa_needs_mapping_files']}\n"
            . "- Engineering gap files: {$summary['qa_engineering_gap_files']}\n"
            . "- Unsupported QA files: {$summary['qa_unsupported_files']}\n"
            . "- Suspicious findings: {$summary['suspicious_findings']}\n"
            . "- Status dictionary gaps: {$summary['status_dictionary_gaps']}\n"
            . "- Unsupported backlog items: {$summary['unsupported_backlog_items']}\n\n"
            . "## Readiness Distribution\n\n"
            . implode("\n", $readinessLines) . "\n\n"
            . "## File QA Scorecard\n\n"
            . "| File | Readiness | Mapping Score | Valid Rows | Valid Rate | Error Rate | Primary QA Reason |\n"
            . "|---|---|---:|---:|---:|---:|---|\n"
            . implode("\n", $qaLines) . "\n\n"
            . "## Unsupported File Backlog\n\n"
            . "| File | Reason | Suspected Layout | Likely Adapter | Priority |\n"
            . "|---|---|---|---|---|\n"
            . (count($unsupportedLines) ? implode("\n", $unsupportedLines) : "| None | None | None | None | None |") . "\n\n"
            . "## Suspicious Finding Summary\n\n"
            . (count($findingLines) ? implode("\n", $findingLines) : "- None") . "\n\n"
            . "## Status Dictionary Gaps\n\n"
            . "| File | Profile | Gap Type | Affected Rows |\n"
            . "|---|---|---|---:|\n"
            . (count($statusLines) ? implode("\n", $statusLines) : "| None | None | None | 0 |") . "\n\n"
            . "## Evidence Files\n\n"
            . "- `audit_reports/regression/dtr_engine_sprint7/file_qa_scorecard.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint7/profile_coverage_matrix.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint7/owner_mapping_queue.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint7/unsupported_file_backlog.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint7/suspicious_preview_findings.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint7/status_dictionary_gaps.csv`\n\n"
            . "## Remaining Blockers\n\n"
            . "- Owner decisions are still required for business semantics, employee alias matching, status-code meanings, client/site binding, and pay-period extraction.\n"
            . "- Unsupported and engineering-gap files require adapter closure before they can become stable preview sources.\n"
            . "- Payroll handoff remains blocked.\n\n"
            . "## Recommended Sprint 8\n\n"
            . "Proceed next to owner mapping decisions for high-value preview-capable profiles or close the remaining unsupported-layout adapter backlog. Keep all work preview-only.\n\n"
            . "## Deployment Recommendation\n\n"
            . "No-go for deployment. Do not connect to canonical DTR or payroll generation.\n";

        file_put_contents($path, $body, LOCK_EX);
    }

    private function writeSprint8Report(string $path, array $summary, array $qaScorecard, array $ownerQueue, array $unsupportedBacklog, array $suspiciousFindings, array $statusGaps, array $mappingRecommendations, array $employeeSuggestions): void
    {
        $readinessCounts = [];
        foreach ($qaScorecard as $row) {
            $key = (string)$row['recommended_readiness'];
            $readinessCounts[$key] = ($readinessCounts[$key] ?? 0) + 1;
        }
        $readinessLines = [];
        foreach ($readinessCounts as $label => $count) {
            $readinessLines[] = '- ' . $label . ': ' . $count;
        }

        $severityCounts = [];
        foreach ($suspiciousFindings as $row) {
            $key = (string)($row['severity'] ?? 'unclassified');
            $severityCounts[$key] = ($severityCounts[$key] ?? 0) + 1;
        }
        $severityLines = [];
        foreach ($severityCounts as $label => $count) {
            $severityLines[] = '- ' . $label . ': ' . $count;
        }

        $unsupportedLines = [];
        foreach ($unsupportedBacklog as $row) {
            $unsupportedLines[] = '| `' . $row['filename'] . '` | ' . $row['reason_unsupported'] . ' | ' . $row['suspected_layout'] . ' | ' . $row['likely_reusable_adapter_type'] . ' | ' . $row['recommended_priority'] . ' |';
        }

        $body = "# DTR Engine Sprint 8 Mapping Automation and Unsupported Closure\n\n"
            . "Date: 2026-06-22\n\n"
            . "## Scope\n\n"
            . "Sprint 8 added local-only automated mapping recommendations, employee match suggestions, status-code inference, suspicious finding severity, and a converted legacy summary fallback for unsupported local `.xls` conversion outputs.\n\n"
            . "No deployment, commit, push, production access, production modification, canonical DTR write, payroll table write, payroll generation, payroll formula change, employee/client update, mutating stored procedure, destructive workflow implementation, or payroll handoff occurred. Preview hours remain non-payroll values.\n\n"
            . "## Summary\n\n"
            . "- Total samples assessed: {$summary['total_files_assessed']}\n"
            . "- Parsed files: {$summary['files_parsed']}\n"
            . "- Valid preview files: {$summary['files_with_valid_preview_rows']}\n"
            . "- Unsupported files: {$summary['unsupported_files']}\n"
            . "- Sprint 7 baseline valid preview files: {$summary['sprint7_baseline_valid_preview_files']}\n"
            . "- Sprint 7 baseline unsupported files: {$summary['sprint7_baseline_unsupported_files']}\n"
            . "- Owner mapping queue rows: " . count($ownerQueue) . "\n"
            . "- Sprint 7 baseline owner mapping queue rows: {$summary['sprint7_baseline_owner_mapping_queue']}\n"
            . "- Suspicious warning/blocker findings: {$summary['suspicious_findings']}\n"
            . "- Sprint 7 baseline suspicious findings: {$summary['sprint7_baseline_suspicious_findings']}\n"
            . "- Status dictionary gaps: {$summary['status_dictionary_gaps']}\n"
            . "- Mapping recommendations generated: {$summary['mapping_recommendations']}\n"
            . "- Employee match suggestions generated: {$summary['employee_match_suggestions']}\n\n"
            . "## Readiness Distribution\n\n"
            . (count($readinessLines) ? implode("\n", $readinessLines) : "- none") . "\n\n"
            . "## Suspicious Finding Severity\n\n"
            . (count($severityLines) ? implode("\n", $severityLines) : "- none") . "\n\n"
            . "## Unsupported File Backlog\n\n"
            . "| File | Reason | Suspected Layout | Likely Adapter | Priority |\n"
            . "|---|---|---|---|---|\n"
            . (count($unsupportedLines) ? implode("\n", $unsupportedLines) : "| None | Closed for preview-only automation | None | None | None |") . "\n\n"
            . "## Mapping Automation Outputs\n\n"
            . "- `audit_reports/regression/dtr_engine_sprint8/file_qa_scorecard.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint8/profile_coverage_matrix.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint8/owner_mapping_queue.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint8/unsupported_file_backlog.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint8/suspicious_preview_findings.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint8/status_dictionary_gaps.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint8/mapping_recommendations.csv`\n"
            . "- `audit_reports/regression/dtr_engine_sprint8/employee_match_suggestions.csv`\n\n"
            . "## Remaining Blockers\n\n"
            . "- Owner confirmation remains required before any mapping can be promoted beyond preview.\n"
            . "- Employee match suggestions are proposed aliases/candidates only; no employee records were changed.\n"
            . "- Status-code inference is advisory only and is not a payroll rule.\n"
            . "- Payroll handoff remains blocked.\n\n"
            . "## Recommended Sprint 9\n\n"
            . "Proceed next to owner review of Sprint 8 mapping recommendations and employee match suggestions, or close remaining engineering-gap profiles with owner-prioritized rules. Keep all work preview-only.\n\n"
            . "## Deployment Recommendation\n\n"
            . "No-go for deployment. Do not connect to canonical DTR or payroll generation.\n";

        file_put_contents($path, $body, LOCK_EX);
    }

    private function readXlsxWorkbook(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to read workbook: ' . basename($path));
        }
        try {
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
            $sharedStrings = $this->sharedStrings($zip);
            if ($workbookXml === false || $relsXml === false) {
                throw new RuntimeException('Workbook metadata missing: ' . basename($path));
            }
            $workbook = simplexml_load_string($workbookXml);
            $rels = simplexml_load_string($relsXml);
            $relMap = [];
            foreach ($rels->Relationship as $rel) {
                $relMap[(string)$rel['Id']] = (string)$rel['Target'];
            }
            $sheets = [];
            foreach ($workbook->sheets->sheet as $sheet) {
                $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $rid = (string)$attrs['id'];
                $target = $relMap[$rid] ?? '';
                if ($target === '') {
                    continue;
                }
                $sheetPath = 'xl/' . ltrim($target, '/');
                if (strpos($sheetPath, 'xl/worksheets/') === false && strpos($target, 'worksheets/') === 0) {
                    $sheetPath = 'xl/' . $target;
                }
                $sheetXml = $zip->getFromName($sheetPath);
                if ($sheetXml === false) {
                    continue;
                }
                $sheets[(string)$sheet['name']] = $this->parseSheet($sheetXml, $sharedStrings);
            }
        } finally {
            $zip->close();
        }

        return ['sheets' => $sheets];
    }

    private function parseSheet(string $sheetXml, array $sharedStrings): array
    {
        $xml = simplexml_load_string($sheetXml);
        $matrix = [];
        if ($xml && isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $row) {
                $rowNumber = (int)$row['r'];
                $values = [];
                foreach ($row->c as $cell) {
                    $cellRef = (string)$cell['r'];
                    $columnIndex = $this->columnIndexFromCellRef($cellRef);
                    $type = (string)$cell['t'];
                    $value = isset($cell->v) ? (string)$cell->v : '';
                    if ($type === 's') {
                        $value = $sharedStrings[(int)$value] ?? '';
                    } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                        $value = (string)$cell->is->t;
                    }
                    $value = trim(preg_replace('/\s+/', ' ', (string)$value));
                    if ($value !== '') {
                        $values[$columnIndex] = $value;
                    }
                }
                if (count($values) > 0) {
                    $matrix[$rowNumber] = $values;
                }
            }
        }

        $mergeCount = 0;
        if ($xml && isset($xml->mergeCells)) {
            $mergeCount = count($xml->mergeCells->mergeCell);
        }

        return ['matrix' => $matrix, 'merge_count' => $mergeCount];
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $parsed = simplexml_load_string($xml);
        if (!$parsed || !isset($parsed->si)) {
            return [];
        }
        $strings = [];
        foreach ($parsed->si as $si) {
            $parts = [];
            if (isset($si->t)) {
                $parts[] = (string)$si->t;
            }
            if (isset($si->r)) {
                foreach ($si->r as $run) {
                    if (isset($run->t)) {
                        $parts[] = (string)$run->t;
                    }
                }
            }
            $strings[] = trim(implode('', $parts));
        }
        return $strings;
    }

    private function profileSignals(array $matrix): array
    {
        $headerScores = [];
        $footerRows = [];
        $maxCol = 0;
        foreach ($matrix as $rowNumber => $cells) {
            $maxCol = max($maxCol, max(array_keys($cells)));
            $joined = strtoupper(implode(' ', $cells));
            if (preg_match('/TOTAL|GRAND TOTAL|PREPARED BY|CHECKED BY|APPROVED BY|SUMMARY/', $joined)) {
                $footerRows[] = $rowNumber;
            }
            if ($rowNumber <= 40) {
                $score = 0;
                foreach ($cells as $value) {
                    $upper = strtoupper((string)$value);
                    if (preg_match('/EMPLOYEE|NAME| ID|ID |NO\.|DAYS?|HOURS?|HRS|MINUTES|TIME|DATE|STATUS|REMARK|LATE|UNDERTIME|OT/', $upper)) {
                        $score++;
                    }
                }
                $headerScores[$rowNumber] = $score;
            }
        }
        arsort($headerScores);
        $headerRows = array_slice(array_keys(array_filter($headerScores)), 0, 3);
        sort($headerRows);
        $headerRow = $headerRows[0] ?? (array_key_first($matrix) ?: 1);
        $dataStart = $this->firstLikelyDataRow($matrix, $headerRow);
        $employeeIdCol = 0;
        $employeeNameCol = 0;
        $workedDaysCol = 0;
        $hoursCol = 0;
        $areaCol = 0;
        $rawDateCol = 0;
        $rawTimeInCol = 0;
        $rawTimeOutCol = 0;
        $statusCols = [];
        $timeCols = [];
        $headerValues = [];

        foreach ($headerRows as $rowNo) {
            foreach (($matrix[$rowNo] ?? []) as $col => $value) {
                $headerValues[] = $value;
                $upper = strtoupper((string)$value);
                if ($employeeIdCol === 0 && preg_match('/\b(ID|EMP.*NO|EMPLOYEE.*ID|BIOMETRIC|CODE|NO\.)\b/', $upper)) {
                    $employeeIdCol = $col;
                }
                if ($employeeNameCol === 0 && preg_match('/NAME/', $upper) && !preg_match('/\b(ID|NO\.?|CODE)\b/', $upper)) {
                    $employeeNameCol = $col;
                }
                if ($workedDaysCol === 0 && preg_match('/WORK.*DAYS|DAYS|NO\.? OF DAYS|REGULAR DAYS/', $upper)) {
                    $workedDaysCol = $col;
                }
                if ($hoursCol === 0 && preg_match('/HOURS|HRS|REG HRS|TOTAL HRS/', $upper)) {
                    $hoursCol = $col;
                }
                if ($areaCol === 0 && preg_match('/AREA|SITE|LOCATION|DESIGNATION|DEPARTMENT/', $upper)) {
                    $areaCol = $col;
                }
                if ($rawDateCol === 0 && preg_match('/^\s*(DATE|PUNCH DATE|WORK DATE|DTR DATE)\s*$/', $upper)) {
                    $rawDateCol = $col;
                }
                if ($rawTimeInCol === 0 && preg_match('/^\s*(IN|TIME IN|CLOCK IN|PUNCH IN|LOGIN)\s*$/', $upper)) {
                    $rawTimeInCol = $col;
                }
                if ($rawTimeOutCol === 0 && preg_match('/^\s*(OUT|TIME OUT|CLOCK OUT|PUNCH OUT|LOGOUT)\s*$/', $upper)) {
                    $rawTimeOutCol = $col;
                }
                if (preg_match('/STATUS|REMARK|COMMENT|ABSENT|LEAVE|HOLIDAY|DEDUCTION/', $upper)) {
                    $statusCols[] = $col;
                }
                if (preg_match('/^\s*(IN|OUT|TIME IN|TIME OUT|CLOCK IN|CLOCK OUT|PUNCH IN|PUNCH OUT)\s*$/', $upper)) {
                    $timeCols[] = $col;
                }
            }
        }

        if ($employeeNameCol === 0) {
            $employeeNameCol = $this->guessTextColumn($matrix, $dataStart);
        }
        if ($employeeIdCol === 0) {
            $employeeIdCol = $this->guessIdentifierColumn($matrix, $dataStart, $employeeNameCol);
        }

        $numericSummaryCandidateCol = $this->numericSummaryCandidateColumn($matrix, $dataStart, [$employeeIdCol, $employeeNameCol, $areaCol]);
        $dayScanRows = array_values(array_unique(array_merge($headerRows, range(1, 12))));
        $dayRun = $this->longestDayColumnRun($matrix, $dayScanRows);
        return [
            'header_rows' => implode('-', $headerRows ?: [$headerRow]),
            'header_values' => array_values(array_unique(array_filter(array_slice($headerValues, 0, 30)))),
            'data_start_row' => $dataStart,
            'employee_identifier_col' => $employeeIdCol,
            'employee_name_col' => $employeeNameCol,
            'worked_days_col' => $workedDaysCol,
            'hours_col' => $hoursCol,
            'numeric_summary_candidate_col' => $numericSummaryCandidateCol,
            'area_col' => $areaCol,
            'raw_date_col' => $rawDateCol,
            'raw_time_in_col' => $rawTimeInCol,
            'raw_time_out_col' => $rawTimeOutCol,
            'day_start_col' => $dayRun['start_col'],
            'day_count' => $dayRun['count'],
            'day_column_summary' => $dayRun['count'] > 0 ? $this->columnLabel($dayRun['start_col']) . ':' . $this->columnLabel($dayRun['start_col'] + $dayRun['count'] - 1) . ' (' . $dayRun['count'] . ')' : '',
            'time_columns' => implode(', ', array_map([$this, 'columnLabel'], array_values(array_unique($timeCols)))),
            'summary_columns' => implode(', ', array_filter([
                $workedDaysCol > 0 ? 'worked_days=' . $this->columnLabel($workedDaysCol) : '',
                $hoursCol > 0 ? 'hours=' . $this->columnLabel($hoursCol) : '',
                ($workedDaysCol <= 0 && $hoursCol <= 0 && $numericSummaryCandidateCol > 0) ? 'numeric_candidate=' . $this->columnLabel($numericSummaryCandidateCol) : '',
            ])),
            'status_columns' => implode(', ', array_map([$this, 'columnLabel'], array_values(array_unique($statusCols)))),
            'footer_rows' => implode(', ', array_slice($footerRows, 0, 20)),
            'max_col' => $maxCol,
        ];
    }

    private function firstLikelyDataRow(array $matrix, int $headerRow): int
    {
        foreach ($matrix as $rowNumber => $cells) {
            if ($rowNumber <= $headerRow) {
                continue;
            }
            $joined = strtoupper(implode(' ', $cells));
            if (preg_match('/TOTAL|PREPARED BY|CHECKED BY|APPROVED BY/', $joined)) {
                continue;
            }
            $numeric = 0;
            $text = 0;
            foreach ($cells as $value) {
                if (is_numeric(str_replace(',', '', (string)$value))) {
                    $numeric++;
                } elseif (preg_match('/[A-Z]/i', (string)$value)) {
                    $text++;
                }
            }
            if ($text > 0 && ($numeric > 0 || count($cells) >= 3)) {
                return $rowNumber;
            }
        }
        return $headerRow + 1;
    }

    private function guessTextColumn(array $matrix, int $dataStart): int
    {
        $scores = [];
        foreach ($matrix as $rowNumber => $cells) {
            if ($rowNumber < $dataStart || $rowNumber > $dataStart + 20) {
                continue;
            }
            foreach ($cells as $col => $value) {
                if (preg_match('/[A-Z]{2,}/i', (string)$value)) {
                    $scores[$col] = ($scores[$col] ?? 0) + 1;
                }
            }
        }
        arsort($scores);
        return (int)(array_key_first($scores) ?: 0);
    }

    private function guessIdentifierColumn(array $matrix, int $dataStart, int $nameCol): int
    {
        $scores = [];
        foreach ($matrix as $rowNumber => $cells) {
            if ($rowNumber < $dataStart || $rowNumber > $dataStart + 20) {
                continue;
            }
            foreach ($cells as $col => $value) {
                if ($col === $nameCol) {
                    continue;
                }
                $clean = preg_replace('/[^0-9]/', '', (string)$value);
                if ($clean !== '' && strlen($clean) >= 2 && strlen($clean) <= 8) {
                    $scores[$col] = ($scores[$col] ?? 0) + 1;
                }
            }
        }
        arsort($scores);
        return (int)(array_key_first($scores) ?: 0);
    }

    private function numericSummaryCandidateColumn(array $matrix, int $dataStart, array $excludedColumns): int
    {
        $excluded = array_filter(array_map('intval', $excludedColumns));
        $scores = [];
        foreach ($matrix as $rowNumber => $cells) {
            if ($rowNumber < $dataStart || $rowNumber > $dataStart + 60) {
                continue;
            }
            $joined = strtoupper(implode(' ', $cells));
            if (preg_match('/TOTAL|GRAND TOTAL|SUBTOTAL|PREPARED BY|CHECKED BY|APPROVED BY/', $joined)) {
                continue;
            }
            foreach ($cells as $col => $value) {
                $col = (int)$col;
                if (in_array($col, $excluded, true)) {
                    continue;
                }
                $raw = trim(str_replace(',', '', (string)$value));
                if ($raw === '' || !is_numeric($raw)) {
                    continue;
                }
                $number = (float)$raw;
                if ($number <= 0 || $number > 31) {
                    continue;
                }
                $scores[$col] = ($scores[$col] ?? 0) + 1;
            }
        }
        arsort($scores);
        return (int)(array_key_first($scores) ?: 0);
    }

    private function longestDayColumnRun(array $matrix, array $headerRows): array
    {
        $best = ['start_col' => 0, 'count' => 0];
        foreach ($headerRows as $rowNo) {
            $dayCols = [];
            foreach (($matrix[$rowNo] ?? []) as $col => $value) {
                $text = trim((string)$value);
                if (preg_match('/^(?:[1-9]|[12][0-9]|3[01])$/', $text)) {
                    $dayCols[] = $col;
                }
            }
            sort($dayCols);
            $runs = [];
            foreach ($dayCols as $col) {
                if (count($runs) === 0 || $col !== $runs[count($runs) - 1][count($runs[count($runs) - 1]) - 1] + 1) {
                    $runs[] = [$col];
                } else {
                    $runs[count($runs) - 1][] = $col;
                }
            }
            foreach ($runs as $run) {
                if (count($run) > $best['count']) {
                    $best = ['start_col' => $run[0], 'count' => count($run)];
                }
            }
        }
        return $best;
    }

    private function clusterFromSignals(array $signals): string
    {
        if ($signals['raw_date_col'] > 0 && $signals['raw_time_in_col'] > 0 && $signals['raw_time_out_col'] > 0) {
            return 'raw punch log';
        }
        if ($signals['day_count'] >= 7) {
            return 'wide daily summary';
        }
        if ($signals['worked_days_col'] > 0 && $signals['hours_col'] > 0) {
            return 'payroll-style summary';
        }
        if ($signals['worked_days_col'] > 0) {
            return 'worked-days summary';
        }
        if ($signals['hours_col'] > 0) {
            return 'hours summary';
        }
        return 'unsupported/unknown';
    }

    private function profileAction(string $cluster, array $signals): string
    {
        if ($cluster === 'raw punch log') {
            return 'new reusable raw punch adapter profile required';
        }
        if ($cluster === 'unsupported/unknown') {
            return 'owner mapping decision required';
        }
        if ($signals['employee_name_col'] <= 0) {
            return 'owner mapping decision required';
        }
        return 'new reusable adapter profile required';
    }

    private function selectSheet(array $sheets): string
    {
        $bestName = '';
        $bestScore = -PHP_INT_MAX;
        foreach ($sheets as $name => $sheet) {
            $rows = count($sheet['matrix'] ?? []);
            $upper = strtoupper((string)$name);
            $score = $rows;
            if (preg_match('/\b(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\b|\d{1,2}\s*-\s*\d{1,2}/', $upper)) {
                $score += 1000;
            }
            if (preg_match('/\b(TK|DTR|TIMEKEEP|TIMEKEEPING|ATTENDANCE)\b/', $upper)) {
                $score += 2000;
            }
            if (preg_match('/LEGEND|ALLOWANCE|LATE|NO IN|NO OUT|ATM|SUMMARY|ADJUSTMENT|NO SCHED|BIOMETRIC/', $upper)) {
                $score -= 1000;
            }
            if ($score > $bestScore) {
                $bestName = $name;
                $bestScore = $score;
            }
        }
        return $bestName;
    }

    private function visibleRange(array $matrix): string
    {
        if (count($matrix) === 0) {
            return '';
        }
        $minRow = min(array_keys($matrix));
        $maxRow = max(array_keys($matrix));
        $maxCol = 0;
        foreach ($matrix as $cells) {
            if (count($cells) > 0) {
                $maxCol = max($maxCol, max(array_keys($cells)));
            }
        }
        return 'A' . $minRow . ':' . $this->columnLabel($maxCol) . $maxRow;
    }

    private function inferClientSource(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/\([^)]*\)/', '', $name);
        $name = preg_replace('/\b(MAY|APRIL|JUNE|JAN|FEB|MAR|APR|JUL|AUG|SEP|OCT|NOV|DEC)\b.*$/i', '', $name);
        $name = preg_replace('/\b(DTR|TK|TIMEKEEPING|SUMMARY|FORMAT|FOR PAYROLL|PAYROLL)\b/i', '', $name);
        $name = preg_replace('/[-_]+/', ' ', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));
        return $name !== '' ? strtoupper($name) : strtoupper(pathinfo($filename, PATHINFO_FILENAME));
    }

    private function inferPeriod(string $filename): array
    {
        $months = [
            'JANUARY' => 1, 'JAN' => 1, 'FEBRUARY' => 2, 'FEB' => 2, 'MARCH' => 3, 'MAR' => 3,
            'APRIL' => 4, 'APR' => 4, 'MAY' => 5, 'JUNE' => 6, 'JUN' => 6, 'JULY' => 7, 'JUL' => 7,
            'AUGUST' => 8, 'AUG' => 8, 'SEPTEMBER' => 9, 'SEP' => 9, 'OCTOBER' => 10, 'OCT' => 10,
            'NOVEMBER' => 11, 'NOV' => 11, 'DECEMBER' => 12, 'DEC' => 12,
        ];
        $upper = strtoupper($filename);
        $month = 5;
        $year = 2026;
        foreach ($months as $label => $number) {
            if (preg_match('/\b' . preg_quote($label, '/') . '\b/', $upper)) {
                $month = $number;
                break;
            }
        }
        if (preg_match('/20\d{2}/', $upper, $m)) {
            $year = (int)$m[0];
        }
        $startDay = 1;
        $endDay = 15;
        if (preg_match('/\b([0-3]?\d)\s*(?:-|TO)\s*([0-3]?\d)\b/', $upper, $m)) {
            $startDay = max(1, min(31, (int)$m[1]));
            $endDay = max($startDay, min(31, (int)$m[2]));
        }
        $start = sprintf('%04d-%02d-%02d', $year, $month, $startDay);
        $end = sprintf('%04d-%02d-%02d', $year, $month, $endDay);
        return ['start' => $start, 'end' => $end, 'label' => $start . ' to ' . $end];
    }

    private function mappingQueueRow(array $inventory, string $why, string $action, string $default): array
    {
        return [
            'file_profile' => $inventory['filename'] . ' / ' . $inventory['profile_key'],
            'missing_mapping_decision' => $why,
            'why_it_matters' => 'Without this decision, preview rows may not represent approved employee identity, client/site, period, or timekeeping semantics.',
            'recommended_owner_action' => $action,
            'suggested_default_if_safe' => $default,
            'blocked_until_owner_confirms' => 'yes',
        ];
    }

    private function mappingReason(array $inventory, array $signals, bool $mappingRequired): string
    {
        if (!$mappingRequired) {
            return 'Owner confirmation still required before promotion beyond preview.';
        }
        if ($inventory['format_cluster'] === 'unsupported/unknown') {
            return 'Unsupported/unknown layout requires owner mapping and/or parser rule selection.';
        }
        if ($inventory['format_cluster'] === 'raw punch log') {
            return 'Raw punch log layout requires a dedicated punch-log adapter and owner-confirmed time-in/time-out mapping.';
        }
        if ($signals['employee_name_col'] <= 0) {
            return 'Employee name/identifier column could not be confidently detected.';
        }
        return 'Generated reusable profile requires owner confirmation of mapping semantics.';
    }

    private function recommendedOwnerAction(array $inventory, array $signals): string
    {
        if ($inventory['extension'] === 'xls') {
            return 'Provide .xlsx export or approve legacy .xls reader support.';
        }
        return 'Confirm employee mapping, status values, client/site binding, period extraction, and whether numeric values represent hours or days.';
    }

    private function suggestedDefault(array $inventory): string
    {
        if ($inventory['format_cluster'] === 'wide daily summary') {
            return 'Treat numeric daily cells as preview-only hours and keep summary totals as review flags.';
        }
        if ($inventory['format_cluster'] === 'worked-days summary' || $inventory['format_cluster'] === 'payroll-style summary') {
            return 'Treat worked days as preview-only days x 8 hours until owner confirms.';
        }
        return 'Keep blocked until owner supplies mapping.';
    }

    private function profileKey(string $filename): string
    {
        $key = strtoupper(pathinfo($filename, PATHINFO_FILENAME));
        $key = preg_replace('/\([^)]*\)/', '', $key);
        $key = preg_replace('/\b(MAY|APRIL|JUNE|JAN|FEB|MAR|APR|JUL|AUG|SEP|OCT|NOV|DEC)\b.*$/', '', $key);
        $key = preg_replace('/[^A-Z0-9]+/', '_', $key);
        $key = trim($key, '_');
        return substr($key !== '' ? $key : 'SAMPLE_' . substr(hash('sha1', $filename), 0, 8), 0, 40);
    }

    private function loadProfiles(): array
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'adapter_profiles.json';
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded['profiles'] ?? null) ? $decoded['profiles'] : [];
    }

    private function saveProfiles(array $payload): void
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'adapter_profiles.json';
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
    }

    private function columnIndexFromCellRef(string $cellRef): int
    {
        preg_match('/^[A-Z]+/i', $cellRef, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }
        return $index;
    }

    private function columnLabel(int $index): string
    {
        if ($index <= 0) {
            return '';
        }
        $label = '';
        while ($index > 0) {
            $index--;
            $label = chr(65 + ($index % 26)) . $label;
            $index = intdiv($index, 26);
        }
        return $label;
    }
}

?>
