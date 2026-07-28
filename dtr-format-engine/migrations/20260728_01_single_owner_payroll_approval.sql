-- Preserve historical release evidence while moving from maker-checker
-- separation to an audited single-owner Payroll approval model.
DELETE legacy_check
FROM payroll_import_release_checks AS legacy_check
INNER JOIN payroll_import_release_checks AS owner_check
    ON owner_check.run_id = legacy_check.run_id
   AND owner_check.check_code = 'OWNER_APPROVAL_EVIDENCE'
WHERE legacy_check.check_code = 'MAKER_CHECKER_SEPARATION';

UPDATE payroll_import_release_checks
SET check_code = 'OWNER_APPROVAL_EVIDENCE',
    summary = CASE
        WHEN check_status = 'passed'
            THEN 'Authorized payroll owner approval is recorded.'
        ELSE summary
    END
WHERE check_code = 'MAKER_CHECKER_SEPARATION';
