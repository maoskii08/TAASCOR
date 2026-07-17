<?php

declare(strict_types=1);

/**
 * Resolves and verifies immutable payslip files without trusting caller path,
 * size, extension, or hash metadata.
 */
final class PayslipArtifactStore
{
    private string $projectRoot;
    private string $storageRoot;

    public function __construct(?string $projectRoot = null, ?string $storageRoot = null)
    {
        $resolved = realpath($projectRoot ?? dirname(__DIR__, 2));
        $this->projectRoot = $resolved === false ? '' : $resolved;
        $configuredRoot = trim((string)($storageRoot
            ?? (defined('TAASCOR_PAYSLIP_ARTIFACT_ROOT') ? constant('TAASCOR_PAYSLIP_ARTIFACT_ROOT') : '')
            ?: (getenv('TAASCOR_PAYSLIP_ARTIFACT_ROOT') ?: '')));
        $resolvedStorage = $configuredRoot === '' ? false : realpath($configuredRoot);
        $this->storageRoot = $resolvedStorage === false ? '' : $resolvedStorage;

        if ($this->projectRoot !== '' && $this->storageRoot !== '') {
            $projectPrefix = rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $insideWebProject = DIRECTORY_SEPARATOR === '\\'
                ? strncasecmp($this->storageRoot, $projectPrefix, strlen($projectPrefix)) === 0
                : strncmp($this->storageRoot, $projectPrefix, strlen($projectPrefix)) === 0;
            if ($insideWebProject || $this->storageRoot === $this->projectRoot) {
                $this->storageRoot = '';
            }
        }
    }

    public function verifyPdf(string $configuredPath, string $expectedHash): ?array
    {
        if ($this->projectRoot === '' || $this->storageRoot === ''
            || preg_match('/^[a-f0-9]{64}$/i', $expectedHash) !== 1) {
            return null;
        }

        $absolutePath = preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $configuredPath) === 1;
        $logicalPath = preg_replace('#^payruns[\\\\/]#i', '', ltrim($configuredPath, '/\\'));
        $candidate = $absolutePath
            ? $configuredPath
            : $this->storageRoot . DIRECTORY_SEPARATOR
                . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$logicalPath);
        $resolved = realpath($candidate);
        if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
            return null;
        }

        $privatePrefix = rtrim($this->storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $insidePrivateRoot = DIRECTORY_SEPARATOR === '\\'
            ? strncasecmp($resolved, $privatePrefix, strlen($privatePrefix)) === 0
            : strncmp($resolved, $privatePrefix, strlen($privatePrefix)) === 0;
        if (!$insidePrivateRoot) {
            return null;
        }

        $stream = fopen($resolved, 'rb');
        if ($stream === false) {
            return null;
        }
        $signature = fread($stream, 5);
        fclose($stream);
        if ($signature !== '%PDF-') {
            return null;
        }

        $actualHash = hash_file('sha256', $resolved);
        $byteSize = filesize($resolved);
        if ($actualHash === false || $byteSize === false
            || !hash_equals(strtolower($expectedHash), strtolower($actualHash))) {
            return null;
        }

        return [
            'absolute_path' => $resolved,
            'storage_path' => 'payruns/'
                . str_replace(DIRECTORY_SEPARATOR, '/', substr($resolved, strlen($privatePrefix))),
            'content_hash' => strtolower($actualHash),
            'byte_size' => (int)$byteSize,
        ];
    }
}
