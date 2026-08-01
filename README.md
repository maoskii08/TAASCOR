# TAASCOR HRIS

TAASCOR's PHP/MySQL HRIS and payroll application.

## Local setup

1. Copy `config/mysql-config.example.php` to `config/mysql-config.php`.
2. Set `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_DATABASE`, or edit the local config copy.
3. Import the required database schema into a local MySQL instance.
4. Serve this directory with PHP and open `/login/`.

For smart-run payslip artifacts, set `TAASCOR_PAYSLIP_ARTIFACT_ROOT` to an absolute, writable directory outside the served project directory. The application refuses artifact registration when this setting is blank, missing, or points inside the web root. See `.env.example`; the legacy PHP app does not automatically load that file, so configure the variable in the PHP/Apache runtime or define the equivalent `TAASCOR_PAYSLIP_ARTIFACT_ROOT` constant in environment-specific configuration.

Example:

```powershell
php -S 127.0.0.1:8797 -t .
```

The local credential file, database exports, logs, uploaded payroll data, and local authentication helpers are intentionally excluded from source control.

## TASCA Gemini AI

TASCA sends authenticated chat requests to the same-origin `tasca-ai/chat.php`
gateway. The gateway keeps the Gemini credential server-side, requires CSRF and
one of the five authenticated HRIS roles, rate-limits requests, blocks likely
private identifiers before generation, and supplies only curated Page Guide
context. Gemini cannot access or modify HRIS records.

Configure either `GEMINI_API_KEY` in the PHP runtime or point
`TAASCOR_GEMINI_ENV_FILE` to a private environment file outside the web root.
`GEMINI_MODEL` defaults to `gemini-3.5-flash`. The ignored local
`.env.gemini` file may contain the private file path, but never commit an API key.
Set `GEMINI_CA_BUNDLE` only when the local PHP runtime does not already have a
trusted CA bundle configured. TLS certificate verification must remain enabled.
See `.env.example` for the blank settings.

Users must not enter employee names, IDs, contact details, compensation values,
banking information, credentials, or other private records into TASCA.

## Multi-client DTR identity gate

The DTR Format Engine stages client DTR files through approved, versioned adapters,
resolves employee identities, creates durable in-app notifications for blocking
exceptions, and prevents the legacy DTR import from calculating partial payroll
while unresolved P0 exceptions remain. The reusable multi-sheet period-summary
parser selects one worksheet from the approved payroll period and preserves the
source row and sheet; Fuji remains one specialized adapter behind the same
multi-client intake contract.

Before enabling this flow, apply these migrations in order:

```text
dtr-format-engine/migrations/20260621_01_dtr_format_engine_base.sql
dtr-format-engine/migrations/20260621_02_dtr_staging_columns.sql
dtr-format-engine/migrations/20260621_03_payroll_basis_preview.sql
dtr-format-engine/migrations/20260716_employee_identity_notifications.sql
dtr-format-engine/migrations/20260717_01_payroll_import_run_foundation.sql
dtr-format-engine/migrations/20260726_01_password_reset_security.sql
dtr-format-engine/migrations/20260726_02_payroll_population_exceptions.sql
dtr-format-engine/migrations/20260727_01_payroll_adjustment_audit.sql
dtr-format-engine/migrations/20260727_02_dtr_mutation_audit.sql
dtr-format-engine/migrations/20260727_03_multi_client_dtr_intake.sql
```

Deploy the migrations before the application code. Payroll adjustment and governed
DTR mutations intentionally roll back when their audit schemas are unavailable.

Preflight the existing `employee_list`, `taascor_client`, `taascor_client_location`, `taascor_user_access`, and legacy `dtr_upload` tables before applying the module migrations. The scripts are safe to rerun against the expected schema, but `CREATE TABLE IF NOT EXISTS` does not repair a partially created or drifted table.

The legacy real-sample preview still reads the ignored
`adapter_profiles.json` and `adapter_approvals.json` files. Those files never
authorize payroll handoff. Real uploads use the database-backed
`dtr_adapter_profiles` registry and append-only approval events. A different
Admin must approve an immutable client-bound adapter version before it appears
in Real DTR Upload.

Private Fuji earnings reconciliation utilities are documented under `tools/fuji-reconciliation/`. Employee-level inputs and outputs must remain outside Git.

## Smart DTR-to-payroll flow

The guarded workflow is intentionally fail-closed:

1. Stage the workbook once in the DTR Format Engine.
2. Analyze the complete employee cohort in shadow mode. Matching is client-scoped, payroll-period-aware, batch-wide, and one-to-one.
3. An Admin, HR, or Payroll owner explicitly approves only collision-free safe matches. Every decision and effective-dated alias is retained immutably.
4. Remaining ambiguous, inactive, conflicting, or missing employees stay blocked and generate durable notifications for Admin, HR, Payroll, and the uploader.
5. Reconcile the expected payslip/reference population against the DTR. Every payslip-only employee remains a P0 population exception until an owner records an evidence-backed disposition.
6. After every identity, population, and staged-row control passes, create one immutable, run-scoped canonical DTR snapshot. This does not write legacy DTR or payroll tables.
7. Smart-flow enrollment is a one-way governed cutover per client. An application administrator cannot toggle an enrolled client back to the ungated legacy release path.
8. Payroll release requires a locked rule snapshot, passed calculation and reconciliation controls, maker-checker separation, zero blocking checks, live legacy-scope equality, and re-hashed private payslip artifact coverage. `Post Payroll` is the only action that can atomically release the smart run and lock the legacy payroll.
8. Enrolled, unreleased dynamic payslips are always watermarked by server state. Released requests are routed to the authenticated sealed-artifact endpoint; request parameters cannot remove the watermark or bypass the seal.

The repository does not contain the production statutory-contribution, tax, or loan procedure definitions. Until an approved effective-dated ruleset and the complete Fuji gold comparison are supplied, new smart runs show `ruleset_status=missing` and cannot be released.

Run the focused local checks before release:

```powershell
php dtr-upload/tests/ImportEmployeeColumnMappingTest.php
php dtr-upload/tests/DTRBulkDeleteContainmentTest.php
php dtr-upload/tests/DTRCalculationSafetyGateTest.php
php dtr-upload/tests/DTRGovernedMutationAuditTest.php
php dtr-upload/tests/DTRLegacyImportContainmentTest.php
php dtr-upload/tests/DTRMutationRulesTest.php
php dtr-upload/tests/DTRScopedMutationContainmentTest.php
php dtr-upload/tests/PayrollLockGuardTest.php
php dtr-upload/tests/PayrollMutationLeaseDatabaseTest.php
php dtr-upload/tests/MutationLockCoverageTest.php
php dtr-format-engine/tests/EmployeeIdentityNotificationManagerTest.php
php dtr-format-engine/tests/EmployeeResolutionEngineTest.php
php dtr-format-engine/tests/FujiWorkbookSecurityTest.php
php dtr-format-engine/tests/GenericRealDtrParserLimitTest.php
php dtr-format-engine/tests/PeriodSummaryWorkbookParserTest.php
php dtr-format-engine/tests/PayrollImportRunManagerTest.php
php dtr-format-engine/tests/PayrollImportRunMigrationTest.php
php dtr-format-engine/tests/PayrollImportRunManagerDatabaseTest.php
php dtr-format-engine/tests/SmartEnrollmentGovernanceTest.php
php dtr-format-engine/tests/SyntheticWorkbookSecurityTest.php
php payslip/tests/FujiReferenceRulesTest.php
php payslip/tests/PayslipPreviewBindingTest.php
php payslip/tests/PayslipReleaseContainmentTest.php
php payslip/tests/PayslipReleaseTransactionTest.php
php payslip/tests/ReportFilterRulesTest.php
php payslip/tests/ReleaseGateRulesTest.php
php payslip/tests/PayslipDynamicSealTest.php
php payslip/tests/RequestSqlSafetyTest.php
php tests/CsrfAjaxCoverageTest.php
php tests/ClientScopeAuthorizationTest.php
php tests/DataIssueTrackerWorkflowTest.php
php tests/DtrTemplateClientScopeTest.php
php tests/HrisHelpCenterTest.php
php tests/LegacyRouteQuarantineTest.php
php tests/LoginAuthenticationTest.php
php tests/NavBarMountPathTest.php
php tests/NotificationDeliveryWorkerTest.php
php tests/PasswordResetSecurityTest.php
php tests/PayrollAdjustmentAuditReaderTest.php
php tests/PayrollDashboardTest.php
php tests/PayrollDataQualityTest.php
php tests/PayrollInputAdjustmentContainmentTest.php
php tests/PayrollInputAdjustmentGuardTest.php
php tests/PayslipClientFilterResilienceTest.php
php tests/RuntimeDependencyClosureTest.php
php tests/StoredRoutineManifestTest.php
php employee-management/tests/EmployeeImportStagingContainmentTest.php
php employee-management/tests/EmployeeImportTransferContainmentTest.php
python tools/fuji-reconciliation/test_payslip_oracle.py
python tools/fuji-reconciliation/test_reconciliation.py
python tools/fuji-reconciliation/test_payroll_release_gate.py
php tests/MigrationRerunTest.php
```

The database-backed tests under `dtr-format-engine/tests/`, `dtr-upload/tests/`, and `tests/MigrationRerunTest.php` use the ignored local config or `DB_*` environment variables. The migration rerun test creates and removes a disposable schema and therefore requires local `CREATE DATABASE` permission.

The public password-reset flow fails closed until the final migration is applied and
`TAASCOR_APP_URL` plus a random `TAASCOR_SECURITY_KEY` of at least 32 characters
are configured. Production must use an HTTPS application URL. Reset tokens are
stored only as SHA-256 hashes and requests are throttled by keyed account/IP
fingerprints.

Missing-employee identity events enqueue client-scoped in-app and email deliveries.
Run `php dtr-format-engine/workers/notification-delivery-worker.php 25` from a
server scheduler every minute. The worker retries failures with backoff, recovers
stale claims, and moves exhausted deliveries to `dead_letter` for operational
review. Email delivery remains disabled until the server mail transport and
`TAASCOR_APP_URL` are configured and verified.

## Deployment

GitHub is the source of truth. Production releases are built from a reviewed commit and uploaded to Hostinger manually. Pushing this repository does not deploy production.
