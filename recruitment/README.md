# Recruitment and Onboarding Module

This module places the authenticated recruitment and onboarding lifecycle inside TAASCOR HRIS. The public TAASCOR website remains responsible for approved job discovery and links candidates into this system with validated job context.

## Implementation status

The current source stage remains `foundation`, so every capability fails closed pending audit and qualification. The local implementation now provides:

- additive MySQL schema for candidate identity, requisitions, jobs, applications, status history, and recruitment audit events;
- server-enforced workflow transition definitions;
- a candidate-specific session cookie and session-key boundary;
- an authenticated Admin/HR workspace shell;
- a minimal public jobs JSON contract; and
- source plus environment feature gates that fail closed.

R2 through R5 implementation is also present behind those locks: governed requisition and job workflows, minimum-data applications, assignments and encrypted notes, interviews and scorecards, immutable offer versions with maker-checker approval, template-driven onboarding, secure document quarantine/scanning/review, candidate communications and preferences, operational queues, minimum-field employee conversion with duplicate checks and reconciliation, and the public website integration contract.

The local R1 security foundation additionally provides encrypted candidate contact storage, hashed single-use verification and reset tokens, anti-enumeration account responses, identifier/network throttling, password-strength rules, session-version revocation, separate candidate CSRF state, explicit scoped staff capabilities, maker-checker policy, a durable notification outbox, and quarantine-first document records.

No environment value can activate public jobs, candidate identity, staff data access, or recruitment mutations while the source stage remains `foundation`.

## Routes

- `/recruitment/` is the authenticated Admin/HR workspace.
- `/recruitment/guide.php` redirects to the canonical public Guide Center at `https://taascor.com/recruitment/guide/`. Every staff recruitment page retains its contextual `?` launcher for role-protected task guidance.
- `/recruitment/staff/requisitions.php`, `jobs.php`, `pipeline.php`, `interviews.php`, `offers.php`, `onboarding.php`, `conversions.php`, `exceptions.php`, and `reports.php` are the source-locked recruitment operations queues.
- `/recruitment/staff/admin/access.php` documents the explicit capability catalogue; `/recruitment/staff/actions.php` is the authenticated, CSRF-protected staff action API.
- `/recruitment/public/jobs.php` is the future public jobs projection used by the TAASCOR website. It returns HTTP 503 in foundation mode and does not connect to the database.
- `/recruitment/candidate/*` files retain the reviewed candidate implementation as a source reference, but web requests redirect to the corresponding canonical `taascor.com` candidate route. The Visiotech HRIS hostname is not a candidate destination.

Candidate registration, sign-in, verification, recovery, reset, privacy, application, interview, offer, onboarding, document, communication, and settings routes are implemented mobile first. All mutation controls are disabled and every action returns HTTP 503 while the source stage is `foundation`.

The staff operations workspace provides responsive, accessible, bounded queues across the entire lifecycle. Foundation mode renders explicit empty states, retrieves no candidate identity, opens no database connection, and exposes no mutation controls. The action layer is complete but requires both the qualified source stage and environment flags plus explicit capability grants.

## Local migration

Copy `config/mysql-config.example.php` to the ignored `config/mysql-config.php` and configure a disposable loopback-only database. Never point the local migration runner at production or a network database.

```powershell
php tools/apply-recruitment-migration.php --migration=20260909_01_recruitment_foundation.sql --confirm-local
php tools/apply-recruitment-migration.php --migration=20260909_02_recruitment_identity_controls.sql --confirm-local
php tools/apply-recruitment-migration.php --migration=20260910_03_recruitment_operations.sql --confirm-local
php tools/apply-recruitment-migration.php --migration=20260910_04_offers_onboarding.sql --confirm-local
php tools/apply-recruitment-migration.php --migration=20260910_05_employee_conversion.sql --confirm-local
php tools/apply-recruitment-migration.php --migration=20260910_06_document_governance.sql --confirm-local
```

The migration creates no data and does not alter payroll, DTR, employee, client, or user-access tables.

## Feature configuration

The `.env.example` file documents the future flags, private keys, provider, document root, and approved privacy-notice version. Real keys must stay outside Git. Enabling an environment flag alone is intentionally insufficient; a reviewed source change must advance the release stage after its named qualification gate closes.

## Verification

```powershell
php tests/RecruitmentFoundationTest.php
php tests/RecruitmentR1SecurityTest.php
php tests/RecruitmentCandidateSurfaceTest.php
php tests/RecruitmentStaffOperationsSurfaceTest.php
php tests/RecruitmentWorkspaceRenderTest.php
php tests/RecruitmentDatabaseTest.php
php tests/RecruitmentR1DatabaseTest.php
php tests/RecruitmentR2R5ImplementationTest.php
php tests/RecruitmentGuideTest.php
php tests/RecruitmentDomainBoundaryTest.php
```

The foundation and identity migrations were previously exercised against disposable loopback-only MySQL with synthetic `.invalid` data. Migrations 03 through 06 and the full lifecycle require the planned database and business UAT pass before qualification. Before any production candidate is proposed, approve and test the privacy/retention matrix, initial staff grant bootstrap, mail transport, malware scanner, private storage/restore exclusions, employee conversion mapping, and website-to-HRIS contract.
