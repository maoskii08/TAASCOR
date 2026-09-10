# Recruitment Guide Center coverage ledger

The structured source is `includes/guide_manifest.php`. Availability is evaluated at render time from the same source release gates used by the platform. In foundation mode every operational article is marked **Disconnected** and explains that controls do not persist or mutate data.

| Guide ID | Page | Route or shared template | Audience | Current purpose |
|---|---|---|---|---|
| `overview` | Recruitment overview | `/recruitment/` | Everyone | Explain the complete lifecycle, roles, and release boundary |
| `candidate.sign-in` | Candidate sign in | `/recruitment/candidate/` | Candidates | Enter the protected candidate realm |
| `candidate.register` | Create candidate account | `/recruitment/candidate/register.php` | Candidates | Create identity and begin email verification |
| `candidate.recover` | Recover candidate account | `/recruitment/candidate/recover.php` | Candidates | Request anti-enumeration account recovery |
| `candidate.reset` | Reset candidate password | `/recruitment/candidate/reset.php` | Candidates | Replace password and revoke older sessions |
| `candidate.verify` | Verify candidate email | `/recruitment/candidate/verify.php` | Candidates | Prove email ownership |
| `candidate.privacy` | Candidate privacy notice | `/recruitment/candidate/privacy.php` | Candidates and reviewers | Explain purpose, stage limits, retention, and rights |
| `candidate.dashboard` | Candidate home | `/recruitment/candidate/dashboard.php` | Candidates | Show the current journey and next action |
| `candidate.applications` | Your applications | `/recruitment/candidate/applications.php` | Candidates | List candidate-owned applications |
| `candidate.application` | Application details | `/recruitment/candidate/application.php` | Candidates | Show accepted job snapshot and timeline |
| `candidate.apply` | Apply for a role | `/recruitment/candidate/apply.php` | Candidates | Save and submit a minimum-data application |
| `candidate.interviews` | Interviews | `/recruitment/candidate/interviews.php` | Candidates | Review schedule and respond |
| `candidate.offers` | Offers | `/recruitment/candidate/offers.php` | Candidates | Review and respond to exact approved version |
| `candidate.onboarding` | Onboarding | `/recruitment/candidate/onboarding.php` | Candidates | Complete applicable, explained tasks |
| `candidate.documents` | Documents | `/recruitment/candidate/documents.php` | Candidates | Upload against an approved request and track review |
| `candidate.messages` | Messages | `/recruitment/candidate/messages.php` | Candidates | Read and send secure recruitment communication |
| `candidate.settings` | Privacy and settings | `/recruitment/candidate/settings.php` | Candidates | Manage optional email and privacy requests |
| `staff.requisitions` | Requisition queue | `/recruitment/staff/requisitions.php` | Recruitment, HR, approvers | Prepare and independently approve demand |
| `staff.jobs` | Job publication | `/recruitment/staff/jobs.php` | Recruitment and HR | Version and publish approved roles |
| `staff.pipeline` | Candidate pipeline | `/recruitment/staff/pipeline.php` | Recruitment and hiring teams | Manage assigned candidate stages |
| `staff.candidate` | Candidate case | `/recruitment/staff/candidate.php?id=...` | Authorized staff | Review scoped evidence and actions |
| `staff.interviews` | Interview desk | `/recruitment/staff/interviews.php` | Coordinators and panelists | Schedule and score structured interviews |
| `staff.offers` | Offer center | `/recruitment/staff/offers.php` | Recruitment and HR approvers | Prepare, approve, deliver, and track offers |
| `staff.onboarding` | Onboarding cases | `/recruitment/staff/onboarding.php` | HR and onboarding owners | Resolve requirements and approve readiness |
| `staff.exceptions` | Exception desk | `/recruitment/staff/exceptions.php` | Recruitment and HR owners | Resolve aging, blocked, or failed work |
| `staff.conversions` | Employee conversion | `/recruitment/staff/conversions.php` | HRIS preparers and approvers | Create and reconcile one employee record |
| `staff.reports` | Operations snapshot | `/recruitment/staff/reports.php` | Recruitment and HR leaders | Review authoritative workload measures |
| `staff.access` | Recruitment access | `/recruitment/staff/admin/access.php` | Administrators | Govern scoped, expiring capabilities |

The full public searchable experience is available at `https://taascor.com/recruitment/guide/`; `/recruitment/guide.php` redirects there. Staff HRIS pages retain contextual **Guide** controls. Candidate HRIS preview URLs redirect to their corresponding canonical `taascor.com` routes.
