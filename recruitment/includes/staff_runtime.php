<?php

declare(strict_types=1);

require_once __DIR__ . '/feature.php';
require_once __DIR__ . '/database.php';
require_once dirname(__DIR__) . '/model/RecruitmentStaffAccessService.php';
require_once dirname(__DIR__) . '/model/RecruitmentNotificationOutbox.php';
require_once dirname(__DIR__) . '/model/RecruitmentWorkflowService.php';
require_once dirname(__DIR__) . '/model/RecruitmentOfferOnboardingService.php';
require_once dirname(__DIR__) . '/model/HrisEmployeeConversionAdapter.php';
require_once dirname(__DIR__) . '/model/RecruitmentConversionService.php';
require_once dirname(__DIR__) . '/model/RecruitmentDocumentPolicy.php';
require_once dirname(__DIR__) . '/model/RecruitmentDocumentService.php';
require_once dirname(__DIR__) . '/model/RecruitmentStaffCaseService.php';

function recruitment_staff_mutations_ready(): bool
{
    return recruitment_staff_workspace_enabled() && recruitment_mutations_enabled();
}

/** @return array{db:PDO,access:RecruitmentStaffAccessService,workflow:RecruitmentWorkflowService,offer_onboarding:RecruitmentOfferOnboardingService,conversion:RecruitmentConversionService} */
function recruitment_staff_services(): array
{
    if (!recruitment_staff_mutations_ready()) {
        throw new RuntimeException('Recruitment staff actions are not available.');
    }
    $db = recruitment_database();
    $dataKey = recruitment_data_key();
    $access = new RecruitmentStaffAccessService($db);
    $outbox = new RecruitmentNotificationOutbox($db);
    return [
        'db' => $db,
        'access' => $access,
        'workflow' => new RecruitmentWorkflowService($db, $dataKey, $access, $outbox),
        'case' => new RecruitmentStaffCaseService($db, $dataKey, $access, $outbox),
        'offer_onboarding' => new RecruitmentOfferOnboardingService($db, $dataKey, $access, $outbox),
        'conversion' => new RecruitmentConversionService(
            $db,
            $dataKey,
            $access,
            new HrisEmployeeConversionAdapter($db),
            recruitment_employee_conversion_enabled()
        ),
    ];
}

function recruitment_staff_document_service(PDO $db): RecruitmentDocumentService
{
    return new RecruitmentDocumentService($db, recruitment_data_key(), trim((string)getenv('TAASCOR_RECRUITMENT_DOCUMENT_ROOT')));
}
