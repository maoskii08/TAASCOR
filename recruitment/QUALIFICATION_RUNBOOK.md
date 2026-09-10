# Recruitment qualification and activation runbook

## Non-production qualification

1. Create a disposable loopback-only MySQL database and an ignored `config/mysql-config.php` from the example.
2. Apply migrations 01 through 06 twice. Confirm the second pass is a no-op and no protected HRIS table changed.
3. Seed synthetic `.invalid` candidates, two independent staff actors, one approved requisition, one job, and approved reference data. Never use a real applicant or create a production employee.
4. Execute the full lifecycle: requisition maker-checker, job publication/versioning, application draft/submission/withdrawal, assignment, encrypted note, stage decisions, interview response/scorecard, offer maker-checker/version/delivery/response, onboarding dependencies/items/readiness, conversion duplicate check/maker-checker/execution/reconciliation.
5. Repeat negative cases for self-approval, missing capability, wrong candidate, changed row, expired job/offer, cyclic template dependency, rejected or infected document, duplicate employee evidence, repeated conversion key, provider failure, scan failure, and reconciliation mismatch.
6. Validate private document storage is outside the web root and excluded from public backups; restore metadata and files into an isolated target and prove hash and authorization checks still hold.
7. Run keyboard, screen-reader, zoom, reduced-motion, desktop, tablet, and representative mobile checks across every candidate and staff route.
8. Run DPO/Legal language review, HR and Recruitment UAT, Security abuse testing, and HRIS employee-field reconciliation. Record P0/P1 closure and owner acceptance for each P2.

## Activation order

Activation requires a reviewed source change from `foundation` to the approved stage plus separately controlled environment values. Enable in this order: staff access, approved public jobs, website HRIS feed, candidate identity, application mutations, documents and scanner, offers/onboarding, then employee conversion. Stop and roll back the affected slice on any contract, authorization, delivery, scan, hash, duplicate, or reconciliation failure.

## Release evidence

Record the exact Git commit, package hash, deployment manifest, database backup, migration output, private-storage backup boundary, environment-variable names without values, smoke evidence, rollback command/path, and the owner who authorized each slice. Commit/push, production migration, file deployment, feature activation, and real-data actions remain separate approvals.
