<?php

declare(strict_types=1);

final class RecruitmentDocumentPolicy
{
    public const MAX_BYTES = 5_242_880;
    public const ALLOWED_MEDIA_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public static function validateUpload(string $mediaType, int $byteSize, string $originalName): array
    {
        $errors = [];
        if (!in_array(strtolower(trim($mediaType)), self::ALLOWED_MEDIA_TYPES, true)) {
            $errors[] = 'Use a PDF, JPEG, or PNG file.';
        }
        if ($byteSize <= 0 || $byteSize > self::MAX_BYTES) {
            $errors[] = 'The file must be no larger than 5 MB.';
        }
        if (trim($originalName) === '' || preg_match('/[\x00-\x1F\x7F]/', $originalName)) {
            $errors[] = 'The file name is invalid.';
        }
        return $errors;
    }

    public static function privateRootIsSafe(string $root, string $documentRoot): bool
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        return self::isAbsolutePath($root)
            && self::isAbsolutePath($documentRoot)
            && !str_starts_with(strtolower($root . '/'), strtolower($documentRoot . '/'));
    }

    public static function storageKey(): string
    {
        return gmdate('Y/m') . '/' . bin2hex(random_bytes(24)) . '.quarantine';
    }

    public static function storagePath(string $root, string $storageKey): string
    {
        $normalizedRoot = rtrim(str_replace('\\', '/', trim($root)), '/');
        $normalizedKey = str_replace('\\', '/', trim($storageKey));
        if (!self::isAbsolutePath($normalizedRoot) || !preg_match('#^\d{4}/\d{2}/[a-f0-9]{48}\.quarantine$#', $normalizedKey)) {
            throw new RuntimeException('The recruitment document storage path is invalid.');
        }
        return str_replace('/', DIRECTORY_SEPARATOR, $normalizedRoot . '/' . $normalizedKey);
    }

    public static function canRelease(string $scanStatus, string $reviewStatus): bool
    {
        return $scanStatus === 'clean' && $reviewStatus === 'approved';
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || (bool)preg_match('#^[A-Za-z]:/#', $path);
    }
}
