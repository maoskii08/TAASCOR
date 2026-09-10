<?php

declare(strict_types=1);

/**
 * Recruitment release controls.
 *
 * Foundation is intentionally fail-closed. Environment variables cannot enable
 * collection or mutations until a reviewed source change advances this stage.
 */
const TAASCOR_RECRUITMENT_RELEASE_STAGE = 'foundation';

function recruitment_env_enabled(string $name): bool
{
    $value = getenv($name);
    return $value !== false && filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function recruitment_public_jobs_enabled(): bool
{
    return in_array(TAASCOR_RECRUITMENT_RELEASE_STAGE, ['pilot', 'qualified'], true)
        && recruitment_env_enabled('TAASCOR_RECRUITMENT_PUBLIC_JOBS_ENABLED');
}

function recruitment_staff_workspace_enabled(): bool
{
    return in_array(TAASCOR_RECRUITMENT_RELEASE_STAGE, ['pilot', 'qualified'], true)
        && recruitment_env_enabled('TAASCOR_RECRUITMENT_STAFF_ENABLED');
}

function recruitment_candidate_identity_enabled(): bool
{
    return TAASCOR_RECRUITMENT_RELEASE_STAGE === 'qualified'
        && recruitment_env_enabled('TAASCOR_RECRUITMENT_CANDIDATE_ENABLED');
}

function recruitment_mutations_enabled(): bool
{
    return TAASCOR_RECRUITMENT_RELEASE_STAGE === 'qualified'
        && recruitment_env_enabled('TAASCOR_RECRUITMENT_MUTATIONS_ENABLED');
}

function recruitment_employee_conversion_enabled(): bool
{
    return recruitment_mutations_enabled()
        && TAASCOR_RECRUITMENT_RELEASE_STAGE === 'qualified'
        && recruitment_env_enabled('TAASCOR_RECRUITMENT_EMPLOYEE_CONVERSION_ENABLED');
}

function recruitment_foundation_mode(): bool
{
    return TAASCOR_RECRUITMENT_RELEASE_STAGE === 'foundation';
}
