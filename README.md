# TAASCOR HRIS

TAASCOR's PHP/MySQL HRIS and payroll application.

## Local setup

1. Copy `config/mysql-config.example.php` to `config/mysql-config.php`.
2. Set `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_DATABASE`, or edit the local config copy.
3. Import the required database schema into a local MySQL instance.
4. Serve this directory with PHP and open `/login/`.

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
```

Deploy the migration before the application code. The gate intentionally fails closed when the notification schema is unavailable.

Preflight the existing `employee_list`, `taascor_client`, `taascor_client_location`, `taascor_user_access`, and legacy `dtr_upload` tables before applying the module migrations. The scripts are safe to rerun against the expected schema, but `CREATE TABLE IF NOT EXISTS` does not repair a partially created or drifted table.

Copy `dtr-format-engine/config/adapter_approvals.example.json` to `adapter_approvals.json` and `dtr-format-engine/config/adapter_profiles.example.json` to `adapter_profiles.json` in each environment. Approval decisions and client-specific workbook profiles are runtime configuration and are not tracked.

Private Fuji earnings reconciliation utilities are documented under `tools/fuji-reconciliation/`. Employee-level inputs and outputs must remain outside Git.

Run the focused local checks before release:

```powershell
php dtr-upload/tests/ImportEmployeeColumnMappingTest.php
php payslip/tests/FujiReferenceRulesTest.php
php payslip/tests/ReportFilterRulesTest.php
python tools/fuji-reconciliation/test_reconciliation.py
```

The database-backed tests under `dtr-format-engine/tests/`, `dtr-upload/tests/`, and `tests/MigrationRerunTest.php` use the ignored local config or `DB_*` environment variables. The migration rerun test creates and removes a disposable schema and therefore requires local `CREATE DATABASE` permission.

## Deployment

GitHub is the source of truth. Production releases are built from a reviewed commit and uploaded to Hostinger manually. Pushing this repository does not deploy production.
