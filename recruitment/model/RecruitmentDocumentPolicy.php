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
        return $root !== '' && $documentRoot !== '' && !str_starts_with(strtolower($root . '/'), strtolower($documentRoot . '/'));
    }

    public static function storageKey(): string
    {
        return gmdate('Y/m') . '/' . bin2hex(random_bytes(24)) . '.quarantine';
    }

    public static function canRelease(string $scanStatus, string $reviewStatus): bool
    {
        return $scanStatus === 'clean' && $reviewStatus === 'approved';
    }
}
