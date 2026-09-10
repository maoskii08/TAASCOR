<?php

declare(strict_types=1);

require_once __DIR__ . '/candidate_session.php';

function recruitment_candidate_csrf_token(): string
{
    recruitment_candidate_start_session();
    if (empty($_SESSION['recruitment_csrf_token'])) {
        $_SESSION['recruitment_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['recruitment_csrf_token'];
}

function recruitment_candidate_csrf_is_valid(string $submitted): bool
{
    recruitment_candidate_start_session();
    $expected = (string)($_SESSION['recruitment_csrf_token'] ?? '');
    return $expected !== '' && $submitted !== '' && hash_equals($expected, $submitted);
}
