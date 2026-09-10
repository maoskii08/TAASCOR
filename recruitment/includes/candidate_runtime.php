<?php

declare(strict_types=1);

require_once __DIR__ . '/feature.php';
require_once __DIR__ . '/database.php';
require_once dirname(__DIR__) . '/model/CandidateIdentityService.php';

function recruitment_candidate_runtime_ready(): bool
{
    return recruitment_candidate_identity_enabled() && recruitment_mutations_enabled();
}

function recruitment_candidate_service(): CandidateIdentityService
{
    if (!recruitment_candidate_runtime_ready()) {
        throw new RuntimeException('Candidate account access is not available.');
    }

    $db = recruitment_database();
    return new CandidateIdentityService(
        $db,
        recruitment_lookup_key(),
        recruitment_data_key(),
        new RecruitmentNotificationOutbox($db)
    );
}

function recruitment_candidate_flash(string $type, string $message): void
{
    recruitment_candidate_start_session();
    $_SESSION['recruitment_candidate_flash'] = ['type' => $type, 'message' => $message];
}

function recruitment_candidate_take_flash(): ?array
{
    recruitment_candidate_start_session();
    $flash = $_SESSION['recruitment_candidate_flash'] ?? null;
    unset($_SESSION['recruitment_candidate_flash']);
    return is_array($flash) ? $flash : null;
}
