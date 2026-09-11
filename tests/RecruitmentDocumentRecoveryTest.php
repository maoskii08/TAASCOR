<?php

declare(strict_types=1);

require_once __DIR__ . '/../recruitment/model/RecruitmentContentPolicy.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentDocumentPolicy.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentDocumentRecoveryService.php';

$run = bin2hex(random_bytes(5));
$base = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/taascor-recovery-' . $run;
$sourceRoot = $base . '/source';
$restoreRoot = $base . '/restore';
$documentRoot = str_replace('\\', '/', __DIR__ . '/../public_html');
$signingKey = str_repeat('recovery-qualification-', 2);
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        echo "FAIL: {$message}\n";
        return;
    }
    echo "PASS: {$message}\n";
};
$rejects = static function (callable $action, string $message) use ($check): void {
    try {
        $action();
        $check(false, $message);
    } catch (RuntimeException|InvalidArgumentException $expected) {
        $check(true, $message);
    }
};
$removeTree = static function (string $path) use ($base): void {
    $normalized = rtrim(str_replace('\\', '/', $path), '/');
    if ($normalized !== $base || !str_contains($normalized, '/taascor-recovery-')) {
        throw new RuntimeException('Refusing to remove an unexpected recovery-test directory.');
    }
    if (!is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
};
$writeDocument = static function (string $root, string $storageKey, string $content): string {
    $path = RecruitmentDocumentPolicy::storagePath($root, $storageKey);
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }
    file_put_contents($path, $content);
    return $path;
};

try {
    mkdir($sourceRoot, 0700, true);
    mkdir($restoreRoot, 0700, true);
    $keys = [
        gmdate('Y/m') . '/' . bin2hex(random_bytes(24)) . '.quarantine',
        gmdate('Y/m') . '/' . bin2hex(random_bytes(24)) . '.quarantine',
    ];
    $contents = ['SYNTHETIC RECOVERY DOCUMENT A', 'SYNTHETIC RECOVERY DOCUMENT B'];
    $inventory = [];
    foreach ($keys as $index => $key) {
        $sourcePath = $writeDocument($sourceRoot, $key, $contents[$index]);
        $inventory[] = [
            'public_id' => sprintf('50000000-0000-4000-8000-%012d', $index + 1),
            'storage_key' => $key,
            'content_sha256' => hash_file('sha256', $sourcePath),
            'byte_size' => filesize($sourcePath),
        ];
        $writeDocument($restoreRoot, $key, $contents[$index]);
    }

    $source = new RecruitmentDocumentRecoveryService($sourceRoot, $signingKey, $documentRoot);
    $manifest = $source->createManifest($inventory);
    $check(!str_contains(json_encode($manifest, JSON_THROW_ON_ERROR), '.pdf'), 'recovery manifest excludes original candidate filenames');
    $rejects(
        fn () => new RecruitmentDocumentRecoveryService($documentRoot . '/private', $signingKey, $documentRoot),
        'recovery verification rejects storage inside the web root'
    );
    $restore = new RecruitmentDocumentRecoveryService($restoreRoot, $signingKey, $documentRoot);
    $result = $restore->verifyRestore($manifest, $inventory);
    $check($result['documents'] === 2 && $result['unexpected_files'] === 0, 'isolated restore matches the signed active inventory');
    $check($result['bytes'] === array_sum(array_column($inventory, 'byte_size')), 'isolated restore reconciles total protected bytes');

    $alteredManifest = $manifest;
    $alteredManifest['documents'][0]['byte_size']++;
    $rejects(fn () => $restore->verifyRestore($alteredManifest, $inventory), 'altered recovery manifest fails signature verification');

    $tamperedPath = RecruitmentDocumentPolicy::storagePath($restoreRoot, $keys[0]);
    file_put_contents($tamperedPath, 'TAMPERED RECOVERY DOCUMENT');
    $rejects(fn () => $restore->verifyRestore($manifest, $inventory), 'tampered restored file fails size or hash verification');
    file_put_contents($tamperedPath, $contents[0]);

    $unexpectedKey = gmdate('Y/m') . '/' . bin2hex(random_bytes(24)) . '.quarantine';
    $unexpectedPath = $writeDocument($restoreRoot, $unexpectedKey, 'UNEXPECTED RECOVERY FILE');
    $rejects(fn () => $restore->verifyRestore($manifest, $inventory), 'unexpected quarantine file fails restore reconciliation');
    unlink($unexpectedPath);

    unlink(RecruitmentDocumentPolicy::storagePath($restoreRoot, $keys[1]));
    $rejects(fn () => $restore->verifyRestore($manifest, $inventory), 'missing restored file fails recovery verification');
} finally {
    $removeTree($base);
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Recruitment private-document recovery qualification passed.\n";
