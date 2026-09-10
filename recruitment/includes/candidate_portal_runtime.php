<?php

declare(strict_types=1);

require_once __DIR__ . '/feature.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/candidate_session.php';
require_once dirname(__DIR__) . '/model/RecruitmentCandidatePortalService.php';
require_once dirname(__DIR__) . '/model/RecruitmentDocumentPolicy.php';
require_once dirname(__DIR__) . '/model/RecruitmentDocumentService.php';

function recruitment_candidate_portal_ready(): bool
{
    return recruitment_candidate_identity_enabled();
}

/** @return array{candidate_id:int,session_version:int,db:PDO,portal:RecruitmentCandidatePortalService} */
function recruitment_candidate_portal_context(): array
{
    if (!recruitment_candidate_portal_ready()) {
        throw new RuntimeException('Candidate portal access is not available.');
    }
    if (!recruitment_candidate_is_authenticated()) {
        throw new DomainException('Candidate authentication is required.');
    }
    $candidateId = (int)($_SESSION['recruitment_candidate_id'] ?? 0);
    $sessionVersion = (int)($_SESSION['recruitment_session_version'] ?? 0);
    $db = recruitment_database();
    $portal = new RecruitmentCandidatePortalService($db, recruitment_data_key());
    if (!$portal->sessionIsCurrent($candidateId, $sessionVersion)) {
        recruitment_candidate_destroy_session();
        throw new DomainException('The candidate session is no longer current.');
    }
    return [
        'candidate_id' => $candidateId,
        'session_version' => $sessionVersion,
        'db' => $db,
        'portal' => $portal,
    ];
}

function recruitment_candidate_document_service(PDO $db): RecruitmentDocumentService
{
    return new RecruitmentDocumentService($db, recruitment_data_key(), trim((string)getenv('TAASCOR_RECRUITMENT_DOCUMENT_ROOT')));
}
