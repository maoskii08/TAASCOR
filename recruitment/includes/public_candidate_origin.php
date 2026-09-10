<?php

declare(strict_types=1);

const TAASCOR_PUBLIC_CANDIDATE_ORIGIN = 'https://taascor.com';

function recruitment_public_candidate_destination(): string
{
    $requestPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $route = strtolower(basename($requestPath));
    if ($route === '' || $route === 'candidate') {
        $route = 'index.php';
    }

    if ($route === 'apply.php') {
        $job = trim((string) ($_GET['job'] ?? ''));
        if (preg_match('/\A[a-z0-9][a-z0-9-]{0,159}\z/', $job) === 1) {
            return '/apply/' . rawurlencode($job) . '/';
        }
        return '/jobs/';
    }

    return match ($route) {
        'register.php' => '/account/register.php',
        'privacy.php' => '/apply/privacy.php',
        'settings.php' => '/account/settings.php',
        'dashboard.php', 'applications.php', 'application.php',
        'interviews.php', 'offers.php', 'onboarding.php', 'documents.php',
        'document-download.php', 'messages.php' => '/applicant/',
        'recover.php', 'reset.php', 'verify.php', 'index.php' => '/account/login.php',
        default => '/recruitment/guide/',
    };
}

function recruitment_redirect_candidate_to_public_origin(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    header('Location: ' . TAASCOR_PUBLIC_CANDIDATE_ORIGIN . recruitment_public_candidate_destination(), true, 302);
    exit;
}
