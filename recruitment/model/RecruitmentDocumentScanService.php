<?php

declare(strict_types=1);

final class RecruitmentDocumentScanService
{
    private Closure $scanner;

    public function __construct(
        private PDO $db,
        private RecruitmentDocumentService $documents,
        private string $privateRoot,
        callable $scanner,
        private string $scannerName
    ) {
        $this->scanner = Closure::fromCallable($scanner);
        if (trim($this->scannerName) === '') {
            throw new InvalidArgumentException('A scanner name is required.');
        }
    }

    public function processBatch(int $limit = 20): int
    {
        $limit = max(1, min(100, $limit));
        $rows = $this->db->query(
            "SELECT public_id, storage_key
             FROM recruitment_documents
             WHERE scan_status IN ('quarantined', 'scan_failed')
               AND deleted_at IS NULL
             ORDER BY uploaded_at
             LIMIT {$limit}"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $result = 'scan_failed';
            $code = 'scanner_exception';
            try {
                $path = RecruitmentDocumentPolicy::storagePath($this->privateRoot, (string)$row['storage_key']);
                if (!is_file($path)) {
                    throw new RuntimeException('document_missing');
                }
                $exitCode = (int)($this->scanner)($path);
                $result = $exitCode === 0 ? 'clean' : ($exitCode === 1 ? 'infected' : 'scan_failed');
                $code = 'exit_' . $exitCode;
            } catch (Throwable $error) {
                $code = substr(
                    preg_replace('/[^A-Za-z0-9_.-]/', '_', strtolower($error->getMessage())) ?: 'scanner_exception',
                    0,
                    80
                );
            }
            $this->documents->recordScan((string)$row['public_id'], $result, $this->scannerName, $code);
        }
        return count($rows);
    }
}
