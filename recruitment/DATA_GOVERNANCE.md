# Recruitment data governance contract

This contract defines the implemented minimum-data boundary. It does not replace DPO, Legal, Security, HR, or Recruitment approval. Foundation source locks remain mandatory until those owners approve the exact notice, retention schedule, access owners, processors, and deletion evidence.

| Data group | Purpose | Classification | Authorized capability | Collection stage | Retention trigger |
|---|---|---|---|---|---|
| Candidate email and account security | Identity, verification, recovery, service notices | Confidential, encrypted | Candidate self; audited identity operations | Account creation | Approved account/candidate retention schedule |
| Name, phone, city, experience summary | Assess one selected published role and contact the candidate | Confidential, encrypted | `application.view` within approved scope | Draft application | Final application disposition plus approved retention period |
| Job snapshot and acknowledgements | Prove the exact opportunity terms accepted at submission | Internal | `application.view`, `audit.view` | Submission | Application evidence schedule |
| Interview schedule and accessibility route | Coordinate assessment and candidate response | Confidential | `interview.manage`; candidate self | Interview stage | Application evidence schedule |
| Panel scorecards and staff notes | Structured human assessment and decision evidence | Confidential, encrypted where free text | Assigned assessment capabilities | Interview/review stage | Application evidence schedule |
| Offer terms, versions, approvals, response | Prepare, approve, deliver, and evidence the employment offer | Restricted, encrypted | `offer.prepare`, `offer.approve`; candidate self | Conditional decision | Employment/application evidence schedule |
| Onboarding checklist and exceptions | Complete approved pre-employment requirements | Confidential to highly restricted by item | `onboarding.manage`; candidate self | Accepted offer | Item-specific approved schedule |
| Uploaded documents | Fulfil an approved, explained requirement | Request-defined classification, encrypted filename, private storage | `document.request`, `document.review`; candidate self | Approved requirements/onboarding stage only | Request-specific schedule, legal hold overrides deletion |
| Government identifiers and bank details | Create the minimum approved employee record | Highly restricted, encrypted conversion payload | `employee_conversion.prepare`, `employee_conversion.approve` | Readiness approved | Employee-master schedule after conversion; unresolved candidate copy follows approved deletion process |
| Messages and delivery attempts | Candidate support and delivery evidence | Confidential | Candidate self; authorized staff; `audit.view` for metadata | Active recruitment/onboarding | Communication evidence schedule |
| Privacy requests | Access, correction, deletion, restriction, objection | Restricted, encrypted request detail | DPO-authorized workflow | Candidate request | DSR case schedule |
| Audit events, hashes, lineage, reconciliation | Accountability, incident response, duplicate prevention | Internal/restricted | `audit.view` | Every consequential action | Approved audit schedule; immutable legal hold where applicable |

Controls implemented in source include least-privilege capability grants, maker-checker decisions, candidate/staff session separation, CSRF enforcement, encryption at rest for candidate free text and identity, hashed lookup/token values, stage gates, immutable versions, provider-attempt evidence, private quarantine storage, malware-scan status, content hashes, release checks, idempotent conversion, duplicate screening, and reconciliation.

The website receives only the approved public-job projection. It never receives candidate, onboarding, document, employee, payroll, or staff data.
