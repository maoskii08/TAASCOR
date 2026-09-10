<?php

declare(strict_types=1);

final class RecruitmentSecurity
{
    public const GENERIC_ACCOUNT_RESPONSE = 'If the account can continue, instructions will be sent to the registered email address.';

    public static function validatePassword(string $password): array
    {
        $errors = [];
        if (strlen($password) < 12) {
            $errors[] = 'Use at least 12 characters.';
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Use both uppercase and lowercase letters.';
        }
        if (!preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Use a number and a symbol.';
        }
        return $errors;
    }

    public static function issueToken(): array
    {
        $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        return ['raw' => $raw, 'hash' => hash('sha256', $raw)];
    }

    public static function tokenHash(string $raw): string
    {
        return hash('sha256', trim($raw));
    }

    public static function encrypt(string $plaintext, string $key): string
    {
        if (strlen($key) < 32) {
            throw new InvalidArgumentException('The recruitment data key must contain at least 32 characters.');
        }
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new RuntimeException('Candidate data encryption failed.');
        }
        return base64_encode($nonce . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded, string $key): string
    {
        if (strlen($key) < 32) {
            throw new InvalidArgumentException('The recruitment data key must contain at least 32 characters.');
        }
        $packed = base64_decode($encoded, true);
        if ($packed === false || strlen($packed) < 29) {
            throw new RuntimeException('Candidate data is invalid.');
        }
        $plaintext = openssl_decrypt(substr($packed, 28), 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, substr($packed, 0, 12), substr($packed, 12, 16));
        if ($plaintext === false) {
            throw new RuntimeException('Candidate data decryption failed.');
        }
        return $plaintext;
    }

    public static function networkFingerprint(string $address, string $userAgent, string $key): string
    {
        if (strlen($key) < 32) {
            throw new InvalidArgumentException('The recruitment lookup key must contain at least 32 characters.');
        }
        return hash_hmac('sha256', trim($address) . '|' . substr(trim($userAgent), 0, 240), $key);
    }

    public static function loginRateLimited(int $identifierAttempts, int $networkAttempts): bool
    {
        return $identifierAttempts >= 5 || $networkAttempts >= 30;
    }
}
