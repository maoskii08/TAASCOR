<?php

declare(strict_types=1);

final class RecruitmentDocumentRecoveryService
{
    public function __construct(
        private string $privateRoot,
        private string $signingKey,
        string $documentRoot
    )
    {
        if (strlen($this->signingKey) < 32) {
            throw new InvalidArgumentException('The recovery-manifest signing key must contain at least 32 characters.');
        }
        if (!RecruitmentDocumentPolicy::privateRootIsSafe($this->privateRoot, $documentRoot)) {
            throw new RuntimeException('Recovery verification must use private storage outside the web root.');
        }
    }

    /** @return list<array{public_id:string,storage_key:string,content_sha256:string,byte_size:int}> */
    public static function activeInventory(PDO $db): array
    {
        $rows = $db->query(
            'SELECT public_id, storage_key, content_sha256, byte_size
             FROM recruitment_documents
             WHERE deleted_at IS NULL
             ORDER BY public_id'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return self::normalizeInventory($rows);
    }

    /** @param list<array<string,mixed>> $inventory @return array<string,mixed> */
    public function createManifest(array $inventory): array
    {
        $documents = self::normalizeInventory($inventory);
        $this->verifyFiles($documents);
        $payload = [
            'version' => 1,
            'created_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'documents' => $documents,
        ];
        return $payload + ['signature' => $this->sign($payload)];
    }

    /**
     * @param array<string,mixed> $manifest
     * @param list<array<string,mixed>> $expectedInventory
     * @return array{documents:int,bytes:int,unexpected_files:int}
     */
    public function verifyRestore(array $manifest, array $expectedInventory): array
    {
        $signature = strtolower(trim((string)($manifest['signature'] ?? '')));
        $payload = $manifest;
        unset($payload['signature']);
        if (!preg_match('/^[a-f0-9]{64}$/', $signature) || !hash_equals($this->sign($payload), $signature)) {
            throw new RuntimeException('The recovery manifest signature is invalid.');
        }
        if (($payload['version'] ?? null) !== 1 || !is_array($payload['documents'] ?? null)) {
            throw new RuntimeException('The recovery manifest format is unsupported.');
        }

        $manifestInventory = self::normalizeInventory($payload['documents']);
        $expected = self::normalizeInventory($expectedInventory);
        if (!hash_equals(
            RecruitmentContentPolicy::canonicalJson($expected),
            RecruitmentContentPolicy::canonicalJson($manifestInventory)
        )) {
            throw new RuntimeException('The recovery manifest does not match the active document inventory.');
        }

        $bytes = $this->verifyFiles($manifestInventory);
        $expectedKeys = array_fill_keys(array_column($manifestInventory, 'storage_key'), true);
        $unexpected = array_values(array_filter(
            $this->storedQuarantineKeys(),
            static fn (string $key): bool => !isset($expectedKeys[$key])
        ));
        if ($unexpected !== []) {
            throw new RuntimeException('The recovered document store contains unexpected quarantine files.');
        }

        return ['documents' => count($manifestInventory), 'bytes' => $bytes, 'unexpected_files' => 0];
    }

    /** @param list<array<string,mixed>> $rows @return list<array{public_id:string,storage_key:string,content_sha256:string,byte_size:int}> */
    private static function normalizeInventory(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $publicId = strtolower(trim((string)($row['public_id'] ?? '')));
            $storageKey = str_replace('\\', '/', trim((string)($row['storage_key'] ?? '')));
            $sha = strtolower(trim((string)($row['content_sha256'] ?? '')));
            $bytes = filter_var($row['byte_size'] ?? null, FILTER_VALIDATE_INT);
            if (
                !preg_match('/^[a-f0-9-]{36}$/', $publicId)
                || !preg_match('#^\d{4}/\d{2}/[a-f0-9]{48}\.quarantine$#', $storageKey)
                || !preg_match('/^[a-f0-9]{64}$/', $sha)
                || $bytes === false
                || $bytes < 1
            ) {
                throw new RuntimeException('The active document inventory contains an invalid record.');
            }
            $normalized[] = [
                'public_id' => $publicId,
                'storage_key' => $storageKey,
                'content_sha256' => $sha,
                'byte_size' => (int)$bytes,
            ];
        }
        usort($normalized, static fn (array $left, array $right): int => $left['public_id'] <=> $right['public_id']);
        return $normalized;
    }

    /** @param list<array{public_id:string,storage_key:string,content_sha256:string,byte_size:int}> $documents */
    private function verifyFiles(array $documents): int
    {
        $bytes = 0;
        foreach ($documents as $document) {
            $path = RecruitmentDocumentPolicy::storagePath($this->privateRoot, $document['storage_key']);
            if (!is_file($path)) {
                throw new RuntimeException('A document listed in the recovery manifest is missing.');
            }
            $actualBytes = filesize($path);
            $actualHash = hash_file('sha256', $path);
            if (
                $actualBytes === false
                || $actualHash === false
                || (int)$actualBytes !== $document['byte_size']
                || !hash_equals($document['content_sha256'], $actualHash)
            ) {
                throw new RuntimeException('A recovered document failed size or hash verification.');
            }
            $bytes += (int)$actualBytes;
        }
        return $bytes;
    }

    /** @return list<string> */
    private function storedQuarantineKeys(): array
    {
        if (!is_dir($this->privateRoot)) {
            throw new RuntimeException('The private document root is unavailable.');
        }
        $root = rtrim(str_replace('\\', '/', $this->privateRoot), '/');
        $keys = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->privateRoot, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item->isFile() || !str_ends_with(strtolower($item->getFilename()), '.quarantine')) {
                continue;
            }
            $path = str_replace('\\', '/', $item->getPathname());
            $keys[] = ltrim(substr($path, strlen($root)), '/');
        }
        sort($keys, SORT_STRING);
        return $keys;
    }

    /** @param array<string,mixed> $payload */
    private function sign(array $payload): string
    {
        return hash_hmac(
            'sha256',
            RecruitmentContentPolicy::canonicalJson($payload),
            hash_hmac('sha256', 'recruitment-document-recovery-manifest-v1', $this->signingKey, true)
        );
    }
}
