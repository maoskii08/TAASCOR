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

## Fuji DTR identity gate

The DTR Format Engine stages Fuji payroll summaries, resolves employee identities, creates durable in-app notifications for blocking exceptions, and prevents the legacy DTR import from calculating partial payroll while unresolved P0 exceptions remain.

Before enabling this flow, apply these migrations in order:

```text
dtr-format-engine/migrations/20260621_01_dtr_format_engine_base.sql
dtr-format-engine/migrations/20260621_02_dtr_staging_columns.sql
dtr-format-engine/migrations/20260621_03_payroll_basis_preview.sql
dtr-format-engine/migrations/20260716_employee_identity_notifications.sql
dtr-format-engine/migrations/20260717_01_payroll_import_run_foundation.sql
```

Deploy the migration before the application code. The gate intentionally fails closed when the notification schema is unavailable.

Preflight the existing `employee_list`, `taascor_client`, `taascor_client_location`, `taascor_user_access`, and legacy `dtr_upload` tables before applying the module migrations. The scripts are safe to rerun against the expected schema, but `CREATE TABLE IF NOT EXISTS` does not repair a partially created or drifted table.

Copy `dtr-format-engine/config/adapter_approvals.example.json` to `adapter_approvals.json` and `dtr-format-engine/config/adapter_profiles.example.json` to `adapter_profiles.json` in each environment. Approval decisions and client-specific workbook profiles are runtime configuration and are not tracked.

Private Fuji earnings reconciliation utilities are documented under `tools/fuji-reconciliation/`. Employee-level inputs and outputs must remain outside Git.

## Smart DTR-to-payroll flow

The guarded workflow is intentionally fail-closed:

1. Stage the workbook once in the DTR Format Engine.
2. Analyze the complete employee cohort in shadow mode. Matching is client-scoped, payroll-period-aware, batch-wide, and one-to-one.
3. An Admin, HR, or Payroll owner explicitly approves only collision-free safe matches. Every decision and effective-dated alias is retained immutably.
4. Remaining ambiguous, inactive, conflicting, or missing employees stay blocked and generate durable notifications for Admin, HR, Payroll, and the uploader.
5. After every identity and staged row passes, create one immutable, run-scoped canonical DTR snapshot. This does not write legacy DTR or payroll tables.
6. Smart-flow enrollment is a one-way governed cutover per client. An application administrator cannot toggle an enrolled client back to the ungated legacy release path.
7. Payroll release requires a locked rule snapshot, passed calculation and reconciliation controls, maker-checker separation, zero blocking checks, live legacy-scope equality, and re-hashed private payslip artifact coverage. `Post Payroll` is the only action that can atomically release the smart run and lock the legacy payroll.
8. Enrolled, unreleased dynamic payslips are always watermarked by server state. Released requests are routed to the authenticated sealed-artifact endpoint; request parameters cannot remove the watermark or bypass the seal.

The repository does not contain the production statutory-contribution, tax, or loan procedure definitions. Until an approved effective-dated ruleset and the complete Fuji gold comparison are supplied, new smart runs show `ruleset_status=missing` and cannot be released.

Run the focused local checks before release:

```powershell
php dtr-upload/tests/ImportEmployeeColumnMappingTest.php
php dtr-upload/tests/PayrollLockGuardTest.php
php dtr-upload/tests/PayrollMutationLeaseDatabaseTest.php
php dtr-upload/tests/MutationLockCoverageTest.php
php dtr-format-engine/tests/EmployeeIdentityNotificationManagerTest.php
php dtr-format-engine/tests/EmployeeResolutionEngineTest.php
php dtr-format-engine/tests/PayrollImportRunManagerTest.php
php dtr-format-engine/tests/PayrollImportRunMigrationTest.php
php dtr-format-engine/tests/PayrollImportRunManagerDatabaseTest.php
php dtr-format-engine/tests/SmartEnrollmentGovernanceTest.php
php payslip/tests/FujiReferenceRulesTest.php
php payslip/tests/ReportFilterRulesTest.php
php payslip/tests/ReleaseGateRulesTest.php
php payslip/tests/PayslipDynamicSealTest.php
php payslip/tests/RequestSqlSafetyTest.php
php tests/CsrfAjaxCoverageTest.php
php tests/NavBarMountPathTest.php
python tools/fuji-reconciliation/test_reconciliation.py
python tools/fuji-reconciliation/test_payroll_release_gate.py
php tests/MigrationRerunTest.php
```

The database-backed tests under `dtr-format-engine/tests/`, `dtr-upload/tests/`, and `tests/MigrationRerunTest.php` use the ignored local config or `DB_*` environment variables. The migration rerun test creates and removes a disposable schema and therefore requires local `CREATE DATABASE` permission.

## Deployment

GitHub is the source of truth. Production releases are built from a reviewed commit and uploaded to Hostinger manually. Pushing this repository does not deploy production.
