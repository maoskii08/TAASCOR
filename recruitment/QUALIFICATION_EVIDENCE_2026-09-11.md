# Recruitment qualification evidence — 2026-09-11

## Gate decision

**PASS WITH OPEN QUALIFICATION ITEMS.** Database migration, transactional lifecycle, identity, authorization, maker-checker, idempotency, duplicate prevention, reconciliation, notification failure handling, document quarantine and recovery-integrity controls, source-lock, domain-boundary, responsive-source, Guide, and worker contracts pass locally against synthetic data. Production activation remains prohibited until the open real-provider, real-backup, accessibility, security, privacy, and business-UAT gates close.

## Baseline

- Source branch: `codex/recruitment-onboarding-20260909`
- Verified Git baseline before qualification changes: `9477527810b00cadad6f8b85aa1e2744f93c1424`
- Database runtime: MariaDB 11.4.10 Windows portable archive from the official MariaDB archive
- Archive SHA-256: `fb7c76f0804321ee373daa49145f2056d2d88f321b614130adeb05a1644ea003`
- Bind boundary: `127.0.0.1:43306`
- Disposable schema: `taascor_codex_recruitment_20260911`
- Data policy: synthetic `.invalid` identities and in-memory employee adapter only; no production applicant, employee, or payroll data

## Migration qualification

- Applied migrations `20260909_01` through `20260910_06` in order.
- Reapplied all six migrations to the same schema without error.
- Migration ledger: 6 records.
- Recruitment table inventory: 42 tables.
- Protected-table sentinel schemas remained unchanged across the second pass.
- Protected-table sentinel rows remained unchanged across the second pass.
- Protected sentinel scope: `employee_list`, `taascor_client`, `taascor_client_location`, and `taascor_user_access`.

## Synthetic lifecycle coverage

The database-backed lifecycle test verifies:

1. Missing-capability denial.
2. Requisition creation, submission, self-approval denial, and independent approval.
3. Job draft, approved scorecard criteria, publication, and versioned event evidence.
4. Candidate registration, anti-enumeration, encrypted identity data, email verification, and replay denial.
5. Application draft, wrong-candidate denial, submission, assignment, encrypted staff notes, and governed stage transitions.
6. Interview scheduling, candidate confirmation, assigned-panel scorecard, and conditional-offer progression.
7. Expired-offer denial, offer preparation, self-approval denial, independent approval, delivery, and candidate acceptance.
8. Cyclic onboarding-template denial, independent template approval, item completion/review, readiness preparation, self-approval denial, and independent readiness approval.
9. Conversion preparation, idempotent retry, duplicate block, cleared duplicate review, self-approval denial, independent approval, execution, and idempotent execution retry.
10. Reconciliation mismatch evidence followed by a successful corrected reconciliation.
11. Final `converted` application and onboarding states plus `reconciled` conversion state.
12. Consequential lifecycle audit-event evidence.

The employee adapter is synthetic and in memory. It does not write `employee_list` or create a real employee.

## Notification and document operational-control qualification

An isolated per-run disposable schema and synthetic adapters verify:

1. Provider rejection creates a durable retry with exponential delay and releases the worker claim.
2. A due retry can succeed and records its second provider attempt.
3. A fifth failed attempt enters terminal `dead_letter` state.
4. A stale processing claim is recovered before delivery.
5. Optional onboarding notifications honor the candidate preference and retain suppression evidence.
6. Clean documents advance from quarantine to independent review but remain unavailable before approval.
7. Infected files remain unavailable.
8. Scanner faults fail closed and can be retried.
9. Approved clean files pass candidate ownership and content-hash verification at release.
10. Post-approval tampering fails integrity verification.
11. Relative private roots and traversal-shaped storage keys fail closed.
12. CLI scanner configuration requires explicit absolute private and public web roots.

These checks use injected synthetic provider and scanner adapters. They do not send email, upload candidate data, invoke a production scanner, or access a production document store.

## Private-document recovery qualification

The local recovery verifier creates a signed, metadata-minimal manifest and proves that an isolated restore:

- matches the active storage-key, content-hash, and byte-size inventory;
- excludes original candidate filenames from the manifest;
- rejects an altered manifest signature;
- rejects tampered or missing restored documents; and
- rejects unexpected quarantine files.

This is implementation evidence only. It does not prove the selected production backup provider, encryption, access policy, retention schedule, deletion propagation, or recovery-time objective.

## Regression result

Fifteen of fifteen selected qualification test files passed, and all 127 PHP files under the recruitment, test, and authentication scopes passed syntax validation:

- `RecruitmentFoundationTest.php`
- `RecruitmentR1SecurityTest.php`
- `RecruitmentCandidateSurfaceTest.php`
- `RecruitmentStaffOperationsSurfaceTest.php`
- `RecruitmentWorkspaceRenderTest.php`
- `RecruitmentR2R5ImplementationTest.php`
- `RecruitmentGuideTest.php`
- `RecruitmentDomainBoundaryTest.php`
- `RecruitmentDatabaseTest.php`
- `RecruitmentR1DatabaseTest.php`
- `RecruitmentLifecycleDatabaseTest.php`
- `RecruitmentOperationalControlsDatabaseTest.php`
- `RecruitmentDocumentRecoveryTest.php`
- `NotificationDeliveryWorkerTest.php`
- `AuthenticationAssetTest.php`

The operational-control test creates and removes its own schema and private document directory. The other three database-backed tests were rerun against an already-used disposable schema to prove run isolation and repeatability.

## Changes made during qualification

- Added `tests/RecruitmentLifecycleDatabaseTest.php` for synthetic R2-R5 lifecycle and negative-path coverage.
- Made `tests/RecruitmentDatabaseTest.php` baseline-aware so it validates only its transactional records.
- Made `tests/RecruitmentR1DatabaseTest.php` use a run-specific candidate and candidate-scoped evidence queries.
- Made synthetic access grants and employee references repeatable across lifecycle-test reruns.
- Added a testable recruitment notification-delivery service with stale-claim recovery, durable provider outcomes, retry, suppression, success, and dead-letter transitions.
- Added a testable document-scan service and strengthened private-storage path validation.
- Added `tests/RecruitmentOperationalControlsDatabaseTest.php` for synthetic notification and document negative-path qualification.
- Required the CLI scanner worker to receive explicit absolute private-storage and public-web roots.
- Added a signed recovery-manifest verifier and `tests/RecruitmentDocumentRecoveryTest.php` for isolated restore integrity and inventory reconciliation.
- Removed deployment-only asset references from the HRIS sign-in, recovery, and reset pages; switched them to tracked dependencies, the tracked favicon, zoom-safe viewports, explicit labels, one primary heading, and visible keyboard focus.
- Added `tests/AuthenticationAssetTest.php` and completed a local mobile browser readback with no missing assets, horizontal overflow, or browser console errors.

## Rendered browser evidence

- Public TAASCOR candidate routes sampled at desktop and mobile widths: `/account/login.php`, `/jobs/`, `/recruitment/guide/`, and `/apply/warehouse-associate/`.
- Sampled public pages had one primary heading, no document-level horizontal overflow, and no captured browser warnings or errors.
- Mobile primary navigation opened, exposed its expanded state, and closed with Escape while returning focus to the menu control.
- Desktop guide keyboard order reached the skip link, logo, primary navigation, theme control, portal route, and journey anchors in a logical sequence.
- The local HRIS login and forgot-password pages rendered at mobile width with no horizontal overflow, missing images, missing tracked assets, or browser console errors.
- The login show-password checkbox changed the password field type and exposed its checked state.
- Candidate HRIS routes correctly redirected to `taascor.com`; the staff recruitment route correctly redirected to HRIS authentication.

## Open qualification items

- P0: DPO/Legal approval of field purposes, notices, retention, deletion, and candidate language.
- P0: Security qualification of staff MFA, provisioning/deprovisioning, negative role access, and abuse paths.
- P1: Approved real notification-provider sandbox qualification, including sender-domain authentication, provider rejection classification, rate limiting, and delivery observability.
- P1: Approved real scanner sandbox qualification, including the selected engine, signature freshness, timeout enforcement, process isolation, and infected-file handling.
- P1: Approved backup-provider integration, encryption/access policy, retention/deletion propagation, and timed restore exercise using the local manifest verifier.
- P1: HRIS employee adapter reconciliation against an approved non-production HRIS schema; no real employee creation.
- P1: Complete authenticated staff and candidate browser matrix, screen-reader pass, 200%/400% zoom, reduced-motion emulation, and representative-device qualification for every route. The sampled public and unauthenticated routes passed the checks recorded above.
- P1: Recruitment and HR business UAT using synthetic cases.

## Activation boundary

The release remains source-locked in `foundation` mode. This evidence does not authorize production migrations, capability activation, public job publication, applicant collection, document upload, notification delivery, or employee conversion.
