(function (window) {
    'use strict';

    function step(title, detail) {
        return {title: title, detail: detail};
    }

    function action(title, steps) {
        return {title: title, steps: steps};
    }

    function dataQualityGuide(title, issue, correction) {
        return {
            title: title,
            audience: 'HR, Payroll, Data Quality, and Admin',
            summary: 'Use this page to find employee-master records with ' + issue + ' before they affect payroll, remittances, or employee reporting.',
            whatsHere: [
                'A client-scoped list of employee records affected by ' + issue + '.',
                'Client and Population (Branch / Location) filters, plus the results-table search, sorting, and export controls.',
                'Clickable Employee Ident values and role-aware Update, Terminate, and Delete shortcuts into Employee Management.'
            ],
            canDo: [
                'Filter the exception list by client or employee population.',
                'Open the full employee record and update supported employee information.',
                'Terminate an employee with a separation date, or permanently delete the record when signed in as Admin.',
                'Export the findings for an HR owner or source-system owner.',
                'Confirm that a corrected record leaves the exception list.'
            ],
            actions: [
                action('Filter and review the exceptions', [
                    'Select a Client, a Population (Branch / Location), or both. Choose Clear filters to return to all results.',
                    'Search for the employee ID or name.',
                    'Check the flagged value against the employee master document.',
                    'Do not change a value based only on a guess or formatting preference.'
                ]),
                action('Update employee information', [
                    'Click Employee Ident or the Update employee pencil. Employee Management opens the matching record and the full Update Employee Details form.',
                    correction,
                    'Save the employee change with the supporting evidence.',
                    'Return to this page and refresh the results.',
                    'Confirm the employee no longer appears in the exception list.'
                ]),
                action('Terminate or delete an employee', [
                    'Choose Terminate employee when employment has ended, select the verified separation date, and confirm the warning.',
                    'Admins can choose Delete employee to permanently remove a selected record after the confirmation warning.',
                    'HR and Payroll can update or terminate; permanent deletion remains Admin-only.',
                    'Never terminate or delete an employee merely to hide a data-quality issue.'
                ])
            ],
            flow: [
                step('Filter scope', 'Select the Client and Population (Branch / Location) to inspect.'),
                step('Open employee', 'Click Employee Ident and compare the full employee record with source evidence.'),
                step('Choose action', 'Update the record, terminate employment, or use Admin-only permanent deletion.'),
                step('Confirm guarded change', 'Save verified updates or confirm the lifecycle warning.'),
                step('Refresh', 'Return to the tracker and confirm the issue is cleared.'),
                step('Continue payroll', 'Proceed only when blocking data-quality issues are resolved.')
            ],
            tips: [
                'An empty result means no current records match this specific rule; it does not certify the whole payroll.',
                'If the source document is unavailable, assign the issue to HR instead of entering a placeholder.',
                'C&B can review this tracker; an Admin, HR, or Payroll user must perform Employee Management changes.',
                'Termination and deletion always require confirmation. Permanent deletion is irreversible and Admin-only.'
            ]
        };
    }

    function maintenanceGuide(title, recordName, dependencies) {
        return {
            title: title,
            audience: 'Admin and authorized Payroll/HR users',
            summary: 'Maintain the approved ' + recordName + ' values used by employee records, reports, filters, and payroll setup.',
            whatsHere: [
                'A searchable list of existing ' + recordName + ' records.',
                'Add and edit forms for authorized users.',
                'Status and relationship fields used by downstream modules.'
            ],
            canDo: [
                'Add a new approved ' + recordName + '.',
                'Correct the name or supported configuration.',
                'Review dependencies before changing a record already in use.'
            ],
            actions: [
                action('Add a ' + recordName, [
                    'Select Add.',
                    'Enter the official name and required relationship fields.',
                    'Check for an existing record with the same meaning.',
                    'Save, then confirm it appears in the list.'
                ]),
                action('Update a ' + recordName, [
                    'Open the existing record.',
                    'Change only the verified field.',
                    'Consider affected ' + dependencies + '.',
                    'Save and confirm dependent screens still show the correct value.'
                ])
            ],
            flow: [
                step('Search existing', 'Prevent duplicates by checking the current list first.'),
                step('Add or open', 'Create a new record or select the record to maintain.'),
                step('Validate details', 'Confirm spelling, status, and required relationships.'),
                step('Save', 'Record the authorized change.'),
                step('Verify downstream', 'Check affected employee, client, or payroll screens.')
            ],
            tips: [
                'Do not rename a record to represent a different business entity; create a new record when the meaning changes.',
                'In-use records should normally be deactivated or corrected, not deleted.'
            ]
        };
    }

    var guides = {
        'dashboard': {
            title: 'Dashboard',
            audience: 'All authenticated users',
            summary: 'View the current HRIS workforce overview and use it as a starting point for employee and payroll work.',
            whatsHere: [
                'Workforce counts and employee demographics.',
                'Employee distribution by branch, client, status, or other configured dimensions.',
                'High-level indicators that link operational users to the underlying HRIS modules.'
            ],
            canDo: [
                'Review current workforce totals.',
                'Spot unusual changes or missing segments.',
                'Navigate to Employee Management or the appropriate operational module for detail.'
            ],
            actions: [
                action('Review the workforce overview', [
                    'Check the total employee count and visible category totals.',
                    'Compare the distribution cards and charts for unexpected movement.',
                    'Open the related module when a number needs investigation.'
                ])
            ],
            flow: [
                step('Open dashboard', 'Start from the latest HRIS overview.'),
                step('Review indicators', 'Check workforce totals and distribution.'),
                step('Identify an exception', 'Note the client, branch, or population that needs review.'),
                step('Open detail module', 'Investigate and correct the source record.'),
                step('Return and confirm', 'Refresh the dashboard after supported changes.')
            ],
            tips: ['Dashboard totals summarize source records; correct the underlying module rather than adjusting a dashboard number.']
        },
        'employee-management': {
            title: 'Employee Management',
            audience: 'HR, Payroll, Admin, and authorized Coordinators',
            summary: 'Create, search, update, and manage the lifecycle of employee master records used throughout HRIS and payroll. Legacy workbook finalization is fail-closed until its transaction-neutral transfer contract is installed.',
            whatsHere: [
                'Employee search and client filters.',
                'Employee profile, government IDs, employment, contact, bank, and payroll-related master fields.',
                'Add Employee, Upload Employee Data staging, edit, and termination actions.',
                'A clear safety blocker when a staged workbook cannot be finalized without a transaction-neutral transfer contract.',
                'Deep links from DTR identity exceptions with employee details prefilled for review.'
            ],
            canDo: [
                'Create a supported employee master record.',
                'Correct verified employee information.',
                'As Admin or HR, stage and validate one approved, client-scoped employee file without silently transferring a partial workbook.',
                'Terminate an employee using the correct effective date and reason.'
            ],
            actions: [
                action('Create an employee', [
                    'Search first to avoid creating a duplicate.',
                    'Select Add Employee.',
                    'Complete the required personal and employment fields using official evidence.',
                    'Confirm client, location, hire date, status, and payroll identifiers.',
                    'Save and review the new profile.'
                ]),
                action('Update an employee', [
                    'Search for the employee and open the profile.',
                    'Change only the fields supported by current documentation.',
                    'Save Changes.',
                    'Re-run any related data-quality or DTR identity check.'
                ]),
                action('Handle a staged employee workbook', [
                    'As Admin or HR, select the exact client and upload an approved file with no more than 1,000 rows or 5 MiB.',
                    'Confirm every workbook row matches the selected client; the server binds the upload to that client and issues the import reference.',
                    'Review all validation findings. Missing, repeated, out-of-order, or cross-user batches fail closed.',
                    'If finalization is blocked, read the displayed safety message and keep the exact import reference and next action.',
                    'Do not repeatedly upload the same workbook; its staging rows were preserved.',
                    'Continue urgent verified changes through individual Employee Management actions.',
                    'Ask Admin to complete the transaction-neutral employee transfer v2 migration before bulk finalization.'
                ]),
                action('Terminate an employee', [
                    'Open the correct employee record.',
                    'Select Terminate Employee.',
                    'Enter the approved separation date and reason.',
                    'Confirm the date does not incorrectly remove the employee from an open payroll period.',
                    'Save and verify the employee appears under Terminated Employees.'
                ])
            ],
            flow: [
                step('Search employee', 'Check current and terminated records before creating or editing.'),
                step('Choose action', 'Add, update, stage a workbook, or terminate.'),
                step('Enter evidence', 'Use approved employee documents and effective dates.'),
                step('Validate relationships', 'Confirm client, site, position, status, and payroll identifiers.'),
                step('Respect the transfer gate', 'A staged workbook remains blocked until transaction-neutral v2 finalization is installed.'),
                step('Save and recheck', 'Verify individual changes and downstream data-quality results.')
            ],
            tips: [
                'Never create a second employee solely to clear a DTR exception.',
                'Employment status and dates affect whether an employee is eligible for a payroll period.',
                'A blocked workbook is preserved staging evidence, not a completed employee import.'
            ]
        },
        'payroll-dashboard': {
            title: 'Payroll Dashboard',
            audience: 'Payroll, HR, and Admin',
            summary: 'Review one exact client and pay date using source-backed payroll totals, DTR population, governed-run evidence, release blockers, and posting status.',
            whatsHere: [
                'Client and pay-date filters that never mix different payroll cycles.',
                'Employee, gross-income, net-pay, and employee-deduction totals read from Payroll Summary.',
                'Governed-run, maker/checker, release, and posting-lock status when that evidence exists.',
                'DTR and payroll-result population counts, source lineage, and evidence-contract availability.',
                'Release-readiness blockers with links to the owning workflow or data-quality page.',
                'A deduction and payroll-component breakdown for the selected scope.'
            ],
            canDo: [
                'Select one client and one available pay date.',
                'Compare payroll-result employees with the DTR population.',
                'Identify whether the scope is governed, legacy, posted, ready, or blocked.',
                'Open Payroll Workflow, Payroll Data Quality, or Payslip for the next controlled action.'
            ],
            actions: [
                action('Select a payroll cycle', [
                    'Choose the payroll client.',
                    'Choose one pay date returned for that client.',
                    'Confirm the displayed cutoff and period start and end dates.',
                    'Verify the source badge says Governed payroll run or Legacy payroll results.'
                ]),
                action('Review results and population', [
                    'Compare Employees, Gross income, Net pay, and Employee deductions.',
                    'Confirm DTR employees and Payroll result rows describe the expected population.',
                    'Review the component breakdown and investigate unexplained movement in Payroll Summary.',
                    'Treat Unverified or unavailable freshness as a review limitation, not as approval.'
                ]),
                action('Work release blockers', [
                    'Read every item under Release readiness.',
                    'Open the linked Payroll Workflow or Payroll Data Quality action.',
                    'Correct the owning source and refresh the dashboard.',
                    'Proceed to Payslip only after the authoritative run and all required evidence are ready.'
                ])
            ],
            flow: [
                step('Select exact scope', 'Choose one client and one pay date.'),
                step('Verify lineage', 'Confirm governed or legacy mode, source file, run, and freshness evidence.'),
                step('Review population', 'Compare DTR employees, payroll rows, and exceptions.'),
                step('Review totals', 'Check gross, deductions, net pay, and employer components.'),
                step('Clear blockers', 'Resolve every P0 and P1 issue in its owning workflow.'),
                step('Use release gate', 'Post only from the approved, sealed, authoritative run.')
            ],
            tips: [
                'The dashboard does not invent accuracy, compliance, or performance scores.',
                'Legacy payroll results are clearly marked unverified because they do not carry run-level maker/checker evidence.',
                'A governed run must be bound to the same verified financial snapshot; missing or mismatched binding blocks release.',
                'Dashboard review is read-only and is never a payroll approval by itself.'
            ],
            faq: true
        },
        'payroll-summary': {
            title: 'Payroll Summary',
            audience: 'Executives using Admin access and Payroll Officers',
            summary: 'Review client-level totals calculated from canonical employee payroll within an explicit client, cutoff, and pay-date scope.',
            whatsHere: [
                'A source-coverage notice that states the included HRIS date range and whether the shared-drive historical archive is included.',
                'Review scope filters for Period, Client, Cutoff, From, and To dates.',
                'Executive overview and Payroll review modes for different review responsibilities.',
                'Gross payroll, net pay, employee population, employee deductions, and employer-contribution metrics with prior-period comparisons.',
                'A client comparison chart, review context, detailed table, search, sorting, and scoped download.'
            ],
            canDo: [
                'Review one payday, a custom pay-date range, or all available canonical payroll.',
                'Filter the summary by client and cutoff.',
                'Compare payroll cost and population with the previous available comparable payroll.',
                'Switch between executive cost review and payroll-component reconciliation.',
                'Download exactly the currently selected aggregate scope.'
            ],
            actions: [
                action('Set the payroll review scope', [
                    'Choose a Period such as Latest available payday, Last 30 days of available data, or Custom dates.',
                    'Choose a Client and Cutoff, or leave either control at All to retain the full selected population.',
                    'Confirm the From and To dates.',
                    'Select Apply filters and verify the Selected scope shown under Review context.'
                ]),
                action('Complete an executive review', [
                    'Choose Executive overview.',
                    'Compare Gross payroll, Net pay, Employees, Employee deductions, and Employer contributions with the prior comparison.',
                    'Use Gross and net pay by client to identify material client movement.',
                    'Review Total Payroll Cost and Net / Gross in the overview table before asking Payroll to investigate a variance.'
                ]),
                action('Complete a payroll-officer review', [
                    'Choose Payroll review.',
                    'Check employee population and payroll-run count before reviewing amounts.',
                    'Reconcile Basic Pay, OT, Leaves, Other Additional, Gross Income, statutory deductions, loans, Net Pay, and employer contributions.',
                    'Correct differences in the owning DTR, employee, loan, adjustment, or payroll workflow; this page is read-only.',
                    'Apply the same filters again and confirm the regenerated totals.'
                ]),
                action('Download the scoped summary', [
                    'Confirm the selected scope and review mode.',
                    'Choose Download scoped summary.',
                    'Open the Excel file and confirm its filename contains the review mode, client scope, and date range.',
                    'Handle the exported payroll aggregate according to company access and retention policy.'
                ])
            ],
            flow: [
                step('Confirm source', 'Read the coverage notice and verify whether historical records are included.'),
                step('Set scope', 'Choose the period, client, cutoff, and date range.'),
                step('Choose review', 'Use Executive overview or Payroll review.'),
                step('Investigate variance', 'Check population, gross-to-net movement, deductions, and contributions.'),
                step('Correct source', 'Resolve supported issues in the owning module and regenerate payroll.'),
                step('Validate release', 'Use payroll data quality and the release gate before releasing payslips.')
            ],
            tips: [
                'The shared Google Drive Payroll Summary archive for 2017-2026 is not included until its one-time migration is validated, approved, and loaded into a separate historical-summary table.',
                'A variance is a review signal, not proof of an error. Compare like-for-like clients, cutoffs, and populations.',
                'Employee deductions include tax, statutory deductions, loans, tardy, and other deductions; employer contributions are reported separately.',
                'This aggregate report does not approve payroll or release payslips. Complete data-quality, reconciliation, and release-gate checks.'
            ],
            faq: true
        },
        'payroll-data-quality': {
            title: 'Payroll Data Quality',
            audience: 'Payroll, HR, Data Quality, and Admin',
            summary: 'Trace payroll validation findings to affected records, open the permitted correction workspace, and rerun checks before generating or releasing payroll.',
            whatsHere: [
                'A status band showing validation-rule and open-finding counts.',
                'Payroll findings grouped by severity, affected-record count, status, and business impact.',
                'A Review issues drawer with affected employee, client, payday, cutoff, period, issue value, and source evidence.',
                'Role-aware correction links to Employee Management, DTR Upload, Other Additional, Other Deduction, User Access, or the Payroll Workflow.'
            ],
            canDo: [
                'Prioritize high-severity findings before medium-severity findings.',
                'Open the affected records without losing the validation overview.',
                'Update an employee from the affected row when Employee Management owns the correction.',
                'Open the owning payroll workspace for DTR, addition, deduction, access, lock, or lineage correction.',
                'Search the loaded issue records and rerun all checks after a correction.'
            ],
            actions: [
                action('Review affected records', [
                    'Start with a High severity row that shows Needs Review.',
                    'Select Review issues.',
                    'Read Recommended correction, Correction owner, and the affected employee or payroll-period evidence.',
                    'Use Find an employee or client to narrow the loaded records.',
                    'Do not change a source record until its employee, client, cutoff, payday, and period are verified.'
                ]),
                action('Update an affected employee', [
                    'Select Review issues for an employee-level finding.',
                    'Choose Update employee on the correct affected record.',
                    'Employee Management opens the matching Update Employee Details form.',
                    'Correct only the HR-approved field, save the employee, and return to Payroll Data Quality.',
                    'Choose Rerun checks and confirm the affected record count decreases or clears.'
                ]),
                action('Correct a payroll-source finding', [
                    'Select Review issues and read the Recommended correction guidance.',
                    'Choose the named action such as Open DTR Upload, Review Other Deduction, or Open Payroll Workflow.',
                    'Use the client, payday, cutoff, period, and employee evidence from the drawer to locate the owning record.',
                    'Correct or re-import the source through its guarded workflow, then return to Payroll Data Quality.',
                    'Choose Rerun checks and confirm the source correction cleared the finding.'
                ]),
                action('Escalate an Admin-owned finding', [
                    'Open the issue drawer and confirm Correction owner shows Admin.',
                    'Capture the affected username and evidence without sharing credentials or payroll values outside approved channels.',
                    'Ask an Admin to correct User Access; Payroll cannot bypass the role restriction.',
                    'Rerun checks after the Admin confirms the saved change.'
                ])
            ],
            flow: [
                step('Run validation', 'Load current payroll findings.'),
                step('Review issues', 'Open the drawer for a finding that needs review.'),
                step('Verify evidence', 'Confirm employee, client, payday, cutoff, period, and issue value.'),
                step('Open correction', 'Use the role-aware employee or source-workspace action.'),
                step('Rerun checks', 'Return and refresh the validation counts.'),
                step('Release gate', 'Proceed only when required findings are closed.')
            ],
            tips: [
                'The drawer is a review and routing workspace; it never edits aggregate payroll data directly.',
                'Admin-owned User Access findings remain visible to Payroll for escalation, but only an Admin can change them.',
                'The drawer loads up to 100 affected records. When a rule is truncated, use the owning source workspace for the full population.',
                'Missing DTR provenance may require an approved database migration before governed source lineage can be recorded.',
                'A cleared rule confirms only that validation rule. Complete reconciliation and the payroll release gate before releasing payslips.'
            ],
            faq: true
        },
        'dtr-format-engine': {
            title: 'DTR Format Engine and Payroll Workflow',
            audience: 'Payroll, HR, and Admin',
            summary: 'Stage inconsistent DTR files, align employees to HRIS, review exceptions, and create a guarded payroll snapshot only after every release condition passes.',
            whatsHere: [
                'A multi-client Real DTR Upload using approved, versioned format adapters.',
                'A governed format registry with client binding, employee-ID policy, and maker-checker approval.',
                'A compact Manage DTR templates button that opens the template library and editor in a drawer.',
                'Smart Employee Alignment, the automated candidate workbench for selected owner approvals.',
                'Employee Identity Exceptions, the manual correction queue with an in-page employee workspace and HR decision-packet export.',
                'DTR and Payslip Population Review with owner notifications.',
                'A guarded canonical snapshot action that stays disabled while blockers remain.'
            ],
            canDo: [
                'Stage a supported source workbook without writing canonical payroll.',
                'Review DTR templates without leaving the active payroll workflow.',
                'Analyze one-to-one employee matches.',
                'Select individual or all visible eligible proposals and record one governed owner approval reason.',
                'Resolve identity and population exceptions using documented evidence.',
                'Export unresolved cases for HR action.'
            ],
            actions: [
                action('Stage and analyze a DTR', [
                    'Select the client. The Approved format list shows only effective adapters assigned to that client.',
                    'Choose the source file and confirm period start, period end, and payday.',
                    'Select Stage for review. The system rejects unapproved formats, client mismatches, oversized files, and incomplete row counts.',
                    'Choose the staged batch under Smart Employee Alignment.',
                    'Select Analyze batch and review the approved, safe, review, and blocked counts.'
                ]),
                action('Onboard a new client DTR format', [
                    'Select Manage DTR templates under Governed DTR Format Registry. HR and Payroll see View DTR templates because template changes are Admin controlled.',
                    'Review the library, then select New template or Edit and complete the client, site, source, expected header, and column-mapping details.',
                    'Select Save template and confirm the template appears in the drawer list. Closing the drawer returns to the unchanged payroll workflow.',
                    'Under Governed DTR Format Registry, select the template and create an immutable adapter version.',
                    'Choose Multi-sheet period summary when one historical workbook contains separate payroll-period tabs with employee-level attendance totals.',
                    'Choose Approved mapping required for vendor identifiers. Use Trusted HRIS identifier only when the source contains governed HRIS IDs.',
                    'A different Admin reviews the sample, mapping, row reconciliation, and identity policy, records the evidence, and approves the draft.',
                    'Confirm the approved version appears under Real DTR Upload only for the assigned client and effective dates.'
                ]),
                action('Approve selected employee mappings', [
                    'Under Smart Employee Alignment, filter the automated proposals you want to review.',
                    'Tick each supported proposal or use the table header checkbox to select all eligible mappings in the current view.',
                    'Review the candidate, score, evidence, and remaining blockers for every selected row.',
                    'Enter a clear owner approval reason.',
                    'Select Approve selected and confirm the approved count increases. Blocked rows cannot be selected.'
                ]),
                action('Work remaining blockers', [
                    'Open Employee Identity Exceptions after Smart Employee Alignment.',
                    'Use Resolve for a supported individual mapping or evidence-backed exclusion.',
                    'Select Edit employee to open the employee workspace drawer without leaving the payroll batch or clearing filters.',
                    'Update, terminate, or remove the employee using the governed Employee Management controls in the drawer.',
                    'Use Create employee only after confirming the person is genuinely missing; the creation form also opens in the drawer.',
                    'After saving, confirm the same batch is re-evaluated and the exception count changes.',
                    'Export the HR decision packet when multiple owner decisions are needed.',
                    'Open DTR and Payslip Population Review for payslip-only employees.'
                ])
            ],
            flow: [
                step('Stage file', 'Load the DTR into a non-canonical review batch.'),
                step('Analyze identity', 'Use aliases, names, dates, and employment status.'),
                step('Approve selections', 'Approve supported proposals; route blocked cases to exceptions.'),
                step('Reconcile population', 'Resolve DTR-only and payslip-only differences.'),
                step('Validate payroll rules', 'Confirm versioned calculations and reference reconciliation.'),
                step('Create snapshot', 'Create the immutable payroll input only when every gate is ready.')
            ],
            tips: [
                'Fuji is one specialized adapter behind the same registry; it does not define the workflow for other clients.',
                'Unknown formats remain blocked until a versioned adapter is created, tested, and independently approved.',
                'The template drawer separates occasional format administration from day-to-day upload and reconciliation work.',
                'Safe means eligible for explicit owner approval; it does not mean the system silently maps employees.',
                'Smart Employee Alignment proposes and approves matches; Employee Identity Exceptions corrects the remaining master-data or evidence blockers.',
                'The employee drawer preserves the selected batch, search text, and exception filter while Employee Management saves the record.',
                'A disabled Create canonical snapshot button means at least one required gate is still blocked.'
            ],
            faq: true
        },
        'dtr-upload': {
            title: 'DTR Upload',
            audience: 'Payroll, HR, and Admin',
            summary: 'Review canonical DTR values and perform controlled cleanup within one exact client and payroll period. New files and corrections go through the governed DTR Format Engine while the legacy self-committing calculator is quarantined.',
            whatsHere: [
                'Client, pay date, branch, and client-location filters.',
                'The active HRIS population with matched DTR values, plus calculated payroll-variable results.',
                'Accessible employee actions for supported government-benefit overrides or guarded employee-scope deletion.',
                'A fail-closed safety notice and route to the DTR Format Engine for new files or corrections.',
                'A bulk-delete impact review covering DTR, gross, addition, deduction, and Payroll Summary rows.',
                'Posted-payroll locks that disable all DTR and payroll mutations.'
            ],
            canDo: [
                'Review the existing exact-scope DTR and payroll basis.',
                'Stage a new or corrected file through the governed DTR Format Engine.',
                'Apply a supported government-benefit override only with business reason, evidence, and typed confirmation.',
                'Remove one employee payroll scope only after reason and typed confirmation.',
                'Review and, when authorized, remove an exact unposted payroll scope.',
                'Proceed to payroll preparation only after scope and validation are correct.'
            ],
            actions: [
                action('Stage a new or corrected DTR', [
                    'Confirm the client, cutoff, and payday.',
                    'Open the DTR Format Engine and stage the approved source file.',
                    'Resolve employee identity, date, population, duplicate, and calculation-rule blockers.',
                    'Create the governed payroll input only after every required gate passes.',
                    'Return here only to review the canonical scope or perform an explicitly supported cleanup.'
                ]),
                action('Correct an employee DTR', [
                    'Filter the exact client and pay date and identify the employee difference.',
                    'Correct the approved source timekeeping evidence rather than forcing a payroll total.',
                    'Restage the corrected file in the DTR Format Engine.',
                    'Re-run identity, population, calculation, and reconciliation checks.',
                    'Do not bypass the legacy-calculator safety blocker.'
                ]),
                action('Remove government benefits for one scope', [
                    'Confirm the exact employee, client, cutoff, and pay date.',
                    'Verify an approved exception authorizes the statutory override.',
                    'Enter the business reason and evidence reference, then type the displayed confirmation phrase.',
                    'Submit once and verify the linked audit event and recalculated net pay.'
                ]),
                action('Delete one employee payroll scope', [
                    'Choose Delete payroll records for the correct employee.',
                    'Review the employee, client, cutoff, and pay date shown.',
                    'Enter a specific business reason and type the exact confirmation phrase displayed.',
                    'Confirm only when all five impacted payroll tables should be cleared for that employee.',
                    'Verify the success result and retained audit event before recalculating.'
                ]),
                action('Delete all records in a payroll scope', [
                    'Confirm client, pay date, branch, and location filters before choosing Delete All Records.',
                    'Review every displayed table count and total row impact.',
                    'Enter a specific reason and type the exact scope confirmation phrase.',
                    'Complete the action within ten minutes; changed filters or row counts require a fresh review.',
                    'Never continue if the reviewed counts do not match the expected payroll population.'
                ])
            ],
            flow: [
                step('Confirm exact scope', 'Select client, cutoff, period, payday, branch, and location.'),
                step('Stage through Format Engine', 'Load new or corrected timekeeping into guarded staging.'),
                step('Validate', 'Check employee ownership, dates, hours, status codes, population, and approved rules.'),
                step('Correct exceptions', 'Resolve errors in the source or review workflow.'),
                step('Reconcile population', 'Compare DTR employees with payroll results and expected payslips.'),
                step('Proceed', 'Move to payroll inputs only after validation passes and no posting lock exists.')
            ],
            tips: [
                'Legacy direct upload and manual DTR Save are intentionally blocked because the installed calculator can commit outside application rollback.',
                'Read-only review remains available; use the DTR Format Engine for new or corrected inputs.',
                'Deletion requires a reason, typed confirmation, a current signed impact review, and transaction-coupled audit evidence.',
                'If scope or counts change after review, deletion fails closed and must be reviewed again.',
                'Never upload the same file repeatedly without checking whether the previous batch was accepted or staged.'
            ],
            faq: true
        },
        'other-additional': {
            title: 'Other Additional',
            audience: 'Payroll and Admin',
            summary: 'Add authorized one-time earnings through an exact-scope, all-or-nothing workflow that recalculates payroll and records reconstructable audit evidence in the same transaction.',
            whatsHere: [
                'Exact client, cutoff, period, payday, and employee filters.',
                'Atomic Upload Other Additional for approved bulk inputs.',
                'Add Additional for an individual employee entry.',
                'Required adjustment type, business reason, and evidence reference fields.',
                'Loading, empty, error, and success states without forced page reloads.'
            ],
            canDo: ['Upload up to 1,000 approved additions in one atomic request.', 'Add an individual earning adjustment.', 'Delete an adjustment with typed confirmation.', 'Review additions and their audit evidence before payroll release.'],
            actions: [
                action('Add an adjustment', [
                    'Confirm the employee, client, cutoff, and payday.',
                    'Select the approved addition type.',
                    'Enter a positive amount, business reason, and approval or source-file reference.',
                    'Save once and confirm the exact entry and recalculated gross and net pay.'
                ]),
                action('Upload additions atomically', [
                    'Prepare one approved file containing no more than 1,000 normalized rows and 1 MiB.',
                    'Confirm every employee belongs to the exact active client and payroll period.',
                    'Enter the shared business reason and evidence reference, then upload once.',
                    'If any row, audit write, or recalculation fails, correct the source and retry the complete file; no partial batch is retained.'
                ]),
                action('Delete an addition', [
                    'Open the exact adjustment and review its employee, type, amount, and payroll scope.',
                    'Enter the reason and evidence reference and type the displayed confirmation phrase.',
                    'Confirm once, then verify the removal, payroll recalculation, and linked audit event.'
                ])
            ],
            flow: [
                step('Choose exact payroll scope', 'Confirm client, cutoff, period, payday, and employee.'),
                step('Select addition type', 'Use the approved earning category.'),
                step('Enter evidence', 'Record the positive amount, business reason, and supporting reference.'),
                step('Validate all rows', 'Confirm active ownership, limits, and no duplicate adjustment.'),
                step('Commit atomically', 'Adjustment, recalculation, and audit evidence succeed together or roll back together.'),
                step('Reconcile payroll', 'Verify employee and aggregate gross-to-net results.')
            ],
            tips: [
                'Do not use Other Additional to compensate for an incorrect DTR calculation; correct the DTR source.',
                'Bulk upload is one bounded request, not a sequence of partially committed browser batches.',
                'If the audit schema is unavailable, the adjustment fails closed.'
            ],
            faq: true
        },
        'other-deduction': {
            title: 'Other Deduction',
            audience: 'Payroll and Admin',
            summary: 'Add authorized one-time deductions through an exact-scope, all-or-nothing workflow that recalculates net pay and records reconstructable audit evidence in the same transaction.',
            whatsHere: [
                'Exact client, cutoff, period, payday, and employee filters.',
                'Atomic Upload Other Deduction for approved bulk inputs.',
                'An individual deduction entry action.',
                'Required deduction type, business reason, and evidence reference fields.',
                'Loading, empty, error, and success states without forced page reloads.'
            ],
            canDo: ['Upload up to 1,000 approved deductions in one atomic request.', 'Add an individual deduction.', 'Delete a deduction with typed confirmation.', 'Review deductions and their audit evidence before payroll release.'],
            actions: [
                action('Add a deduction', [
                    'Confirm the employee, client, cutoff, and payday.',
                    'Select the approved deduction type.',
                    'Enter a positive amount, business reason, and approval or source-file reference.',
                    'Save once and confirm the exact entry and recalculated net pay.'
                ]),
                action('Upload deductions atomically', [
                    'Prepare one approved file containing no more than 1,000 normalized rows and 1 MiB.',
                    'Confirm every employee belongs to the exact active client and payroll period.',
                    'Enter the shared business reason and evidence reference, then upload once.',
                    'If any row, audit write, or recalculation fails, correct the source and retry the complete file; no partial batch is retained.'
                ]),
                action('Delete a deduction', [
                    'Open the exact deduction and review its employee, type, amount, and payroll scope.',
                    'Enter the reason and evidence reference and type the displayed confirmation phrase.',
                    'Confirm once, then verify the removal, net-pay recalculation, and linked audit event.'
                ])
            ],
            flow: [
                step('Choose exact payroll scope', 'Confirm client, cutoff, period, payday, and employee.'),
                step('Select deduction type', 'Use the authorized category.'),
                step('Enter evidence', 'Record the positive amount, business reason, and approval reference.'),
                step('Validate all rows', 'Confirm active ownership, limits, and no duplicate adjustment.'),
                step('Commit atomically', 'Deduction, recalculation, and audit evidence succeed together or roll back together.'),
                step('Reconcile net pay', 'Validate the employee and aggregate payroll result.')
            ],
            tips: [
                'Never hide attendance deductions or loan deductions under a generic category.',
                'Bulk upload is one bounded request, not a sequence of partially committed browser batches.',
                'If the audit schema is unavailable, the deduction fails closed.'
            ],
            faq: true
        },
        'payslip': {
            title: 'Payslip',
            audience: 'Payroll, HR, and Admin',
            summary: 'Review payroll results, generate employee-facing payslips, and post only the approved, reconciled, sealed authoritative run.',
            whatsHere: [
                'Generate Payroll and Generate Payslip actions.',
                'Client, pay date, branch, location, pay type, and bank filters.',
                'An authoritative payroll-run selector for clients enrolled in the governed workflow.',
                'A visible release-gate status explaining why posting is ready, blocked, or already locked.',
                'A Post Payroll button that stays disabled until the exact smart run passes every mandatory release check.',
                'Preview output before release and sealed payslip output after a governed run is posted.'
            ],
            canDo: [
                'Load employee and aggregate payroll results for one scope.',
                'Preview payslips while a governed run is still under review.',
                'Generate or open sealed payslips after approval.',
                'Post one exact run after reconciliation, artifact verification, and maker/checker approval.'
            ],
            actions: [
                action('Generate payroll', [
                    'Confirm client, cutoff, and payday.',
                    'Verify DTR, identity, population, additions, deductions, loans, and rules are ready.',
                    'Select Generate Payroll.',
                    'Read the release-gate message and review Payroll Summary and Payroll Data Quality.'
                ]),
                action('Generate payslips', [
                    'Select the exact payroll scope and, when shown, the authoritative run.',
                    'Select Generate Payslip.',
                    'If the selected run, stored binding, live payroll hash, or released-run lock does not match exactly, stop and resolve the blocker instead of using an unbound preview.',
                    'Compare sample employees and aggregate totals with the approved reference.',
                    'Treat an unreleased governed-run output as a preview and do not distribute it.'
                ]),
                action('Post payroll', [
                    'Confirm the status names one authoritative run and shows the release gate as ready.',
                    'Verify identity, ruleset, calculation, reconciliation, population, maker/checker, and sealed-artifact checks passed.',
                    'Confirm the maker and checker are different users, and the authenticated posting user is not the recorded checker.',
                    'Select Post Payroll only once.',
                    'Verify the posted lock, released run, published artifacts, outbox event, and audit evidence.',
                    'Treat corrections after posting as a controlled adjustment or reversal.'
                ])
            ],
            flow: [
                step('Complete inputs', 'Finish DTR, adjustments, loans, and employee alignment.'),
                step('Generate payroll', 'Calculate employee earnings and deductions.'),
                step('Reconcile', 'Validate totals, samples, and release rules.'),
                step('Generate payslips', 'Create the employee-facing artifacts.'),
                step('Approve release', 'Obtain final payroll owner approval.'),
                step('Post and distribute', 'Lock the approved cycle and release artifacts.')
            ],
            tips: [
                'Generate Payroll is not the same as Post Payroll.',
                'Post Payroll fails closed when the smart-run schema, client enrollment, authoritative run, reconciliation, or sealed artifacts are missing.',
                'If net pay differs, trace the exact component instead of manually changing the final net amount.',
                'Never use Post Payroll to test a cycle; posting locks the approved scope.'
            ],
            faq: true
        },
        'loans': {
            title: 'Loans',
            audience: 'C&B, Payroll, HR, and Admin',
            summary: 'Create and maintain employee loan records that feed authorized payroll deductions.',
            whatsHere: [
                'Employee and loan filters.',
                'Add Loan and edit loan-detail forms.',
                'Loan type, principal, schedule, balance, and status information.'
            ],
            canDo: ['Create an approved employee loan.', 'Update supported loan details.', 'Review balances and payroll deduction setup.'],
            actions: [
                action('Add a loan', [
                    'Search for the employee and existing loan first.',
                    'Select Add Loan.',
                    'Enter the approved loan type, principal, schedule, and start date.',
                    'Validate the deduction does not duplicate another active record.',
                    'Save and confirm the schedule.'
                ])
            ],
            flow: [
                step('Find employee', 'Confirm the correct employee and client.'),
                step('Check existing loans', 'Prevent duplicate active schedules.'),
                step('Enter approved terms', 'Record principal, dates, and deduction schedule.'),
                step('Save and validate', 'Confirm the balance and first payroll deduction.'),
                step('Monitor balance', 'Review subsequent deductions and closure.')
            ],
            tips: ['Do not change a loan balance just to make payroll match; reconcile transactions and approved adjustments.']
        },
        'loans-report': {
            title: 'Loans Report',
            audience: 'C&B, Payroll, Finance, HR, and Admin',
            summary: 'Review and export employee loan balances, deductions, and status for reconciliation.',
            whatsHere: ['Loan and employee filters.', 'A report table of loan balances and payroll activity.', 'Sorting and export controls when available.'],
            canDo: ['Review active and completed loans.', 'Reconcile balances to payroll deductions.', 'Export a controlled report.'],
            actions: [
                action('Reconcile loans', [
                    'Select the client, employee, loan type, or date scope.',
                    'Review principal, deductions, and remaining balance.',
                    'Compare exceptions with the Loans page and payroll result.',
                    'Correct the source transaction, then refresh the report.'
                ])
            ],
            flow: [
                step('Choose filters', 'Set the client and reporting scope.'),
                step('Review balances', 'Check active, completed, and unusual balances.'),
                step('Trace deduction', 'Compare loan transactions with payroll.'),
                step('Correct source', 'Update only authorized loan records.'),
                step('Export or sign off', 'Retain the reconciled report.')
            ],
            tips: ['The report should explain the balance; it should not be used as the place to change the loan.']
        },
        'billing': {
            title: 'Billing',
            audience: 'Finance, Payroll, and Admin',
            summary: 'Review client billing totals and employee-level billing detail for a selected payday.',
            whatsHere: ['Billing Summary.', 'Client and payday search controls.', 'Employee Billing Detail for the selected billing cycle.'],
            canDo: ['Review billing totals.', 'Drill into employee billing detail.', 'Trace differences to payroll or client setup.'],
            actions: [
                action('Review client billing', [
                    'Select the client and payday.',
                    'Search and review the billing summary.',
                    'Open employee detail for unexpected totals.',
                    'Reconcile differences with approved payroll and billing rules.'
                ])
            ],
            flow: [
                step('Select client and payday', 'Choose the exact billing cycle.'),
                step('Review summary', 'Check total headcount and billable amounts.'),
                step('Open employee detail', 'Trace unusual or missing charges.'),
                step('Correct source', 'Fix client setup or payroll data with approval.'),
                step('Finalize billing', 'Export or issue only after reconciliation.')
            ],
            tips: ['Billing totals should reconcile to the approved payroll scope and the applicable client agreement.']
        },
        'accounting-finance': {
            title: 'Accounting / Finance',
            audience: 'Finance, Payroll, and Admin',
            summary: 'Review government remittances, annual summaries, and employee annual tax information.',
            whatsHere: [
                'Government Remittances by client and payday.',
                'Annual Summary by client and year.',
                'Employee Annual information used for BIR 2316 review.'
            ],
            canDo: ['Search remittance totals.', 'Review annual payroll summaries.', 'Review employee annual tax details and export supported reports.'],
            actions: [
                action('Review government remittances', [
                    'Open the Government Remittances tab.',
                    'Select client and payday.',
                    'Search and reconcile statutory totals with approved payroll.'
                ]),
                action('Review annual information', [
                    'Open Annual Summary or Employee Annual.',
                    'Select client and year.',
                    'Search, review totals, and investigate missing periods before export.'
                ])
            ],
            flow: [
                step('Choose report', 'Select remittance, annual summary, or employee annual view.'),
                step('Set scope', 'Choose client, payday, or year.'),
                step('Search and review', 'Inspect totals and employee details.'),
                step('Reconcile', 'Trace differences to payroll and employee tax data.'),
                step('Export or file', 'Use only the approved reconciled report.')
            ],
            tips: ['Annual reports are only as complete as the posted payroll cycles included in the selected year.']
        },
        'client-management': {
            title: 'Client Management',
            audience: 'Admin, HR, and authorized Payroll users',
            summary: 'Manage client-level operational settings and relationships used by employee and payroll workflows.',
            whatsHere: ['Client records and key details.', 'Operational status and configuration fields.', 'Save action for authorized changes.'],
            canDo: ['Review a client setup.', 'Update supported client details.', 'Confirm settings used by employee and payroll modules.'],
            actions: [
                action('Update a client', [
                    'Select the client record.',
                    'Confirm the legal and operational source for the change.',
                    'Update the supported fields.',
                    'Save and verify employee, location, and payroll screens.'
                ])
            ],
            flow: [
                step('Find client', 'Open the correct client record.'),
                step('Review configuration', 'Check status, identifiers, and relationships.'),
                step('Apply supported change', 'Enter the approved client detail.'),
                step('Save', 'Record the change.'),
                step('Verify downstream', 'Check employees, locations, payroll, and billing.')
            ],
            tips: ['Client-level changes can affect many employees; verify downstream impact before saving.']
        },
        'audit-log': {
            title: 'Audit Log',
            audience: 'Admin, HR, Payroll, and authorized reviewers',
            summary: 'Review recorded system actions and reconstructable payroll-change evidence to understand who changed or approved data, when, and for which exact payroll scope.',
            whatsHere: ['Searchable activity entries.', 'Login summary.', 'Payroll Change Evidence with exact Additional/Deduction scope, reason, approval reference, row evidence, and SHA-256 integrity status.', 'DTR Change Evidence with bounded history, canonical before/after data, exact scope, actor, business reason, approval reference, and server-verified hashes.'],
            canDo: ['Search for a user or action.', 'Trace an approval or change.', 'Open a payroll audit Event ID returned by an Additional/Deduction action.', 'Open a DTRM Event ID returned by a governed DTR edit, benefit change, or deletion.', 'Verify exact affected data and evidence integrity during payroll review.'],
            actions: [
                action('Trace an action', [
                    'Use Activity Log to search by user, action, or date.',
                    'Confirm the timestamp and affected record.',
                    'Compare the entry with the current state and supporting evidence.',
                    'Escalate unexplained or unauthorized changes.'
                ]),
                action('Verify a payroll change', [
                    'Open Payroll Change Evidence or follow the Audit Event ID link returned after the change.',
                    'Filter by Event ID, client, pay day, or adjustment kind.',
                    'Open the Event ID and verify the exact payroll scope, business reason, approval/evidence reference, actor, and row list.',
                    'Require a green SHA-256 verified status. Treat a missing event or hash mismatch as a release blocker.'
                ]),
                action('Verify a DTR change', [
                    'Open DTR Change Evidence or follow the DTRM Event ID link returned after the governed action.',
                    'Filter by Event ID, client, pay day, operation, employee, or scope. Use Previous and Next for the bounded server pages.',
                    'Open the Event ID and verify the employee or payroll scope, business reason, approval/evidence reference, actor, and before/after evidence.',
                    'Require verified before, after, and scope hashes. Treat a missing event, unavailable required scope evidence, or hash mismatch as a payroll release blocker.'
                ])
            ],
            flow: [
                step('Define question', 'Identify the record, user, or event to trace.'),
                step('Search', 'Filter the audit log.'),
                step('Open exact evidence', 'Open the payroll or DTR Event ID for the change being traced.'),
                step('Confirm evidence', 'Verify scope, affected data, actor, reason, approval reference, and every required hash status.'),
                step('Resolve or escalate', 'Document the outcome.')
            ],
            tips: ['Absence of an audit entry should be treated as a control gap, not proof that no change occurred.', 'A hash mismatch is a P0 integrity exception and must block payroll release.']
        },
        'terminated-employees': {
            title: 'Terminated Employees',
            audience: 'HR, Payroll, Admin, and authorized Coordinators',
            summary: 'Review employees retained after termination or removal from active HRIS and confirm the effective lifecycle date.',
            whatsHere: ['Filterable terminated-and-removed employee list.', 'Record Status and Terminated / Removed On columns near the employee identifier.', 'Employee, client, and separation details.', 'A restore action for authorized users.'],
            canDo: ['Review terminated or administratively removed employees.', 'Confirm termination or removal effective dates.', 'Restore a retained employee record when supported by HR evidence.', 'Investigate an employee incorrectly included or excluded from payroll.'],
            actions: [
                action('Review a termination', [
                    'Search for the employee.',
                    'Confirm Record Status and Terminated / Removed On before reviewing the remaining employee details.',
                    'Compare the date with the payroll period being reviewed.',
                    'Correct the employee lifecycle record only with HR evidence.'
                ])
            ],
            flow: [
                step('Search employee', 'Locate the separated employee.'),
                step('Review lifecycle date', 'Compare the termination or removal date with the relevant payroll period.'),
                step('Check final obligations', 'Confirm final pay and open deductions are handled.'),
                step('Correct if supported', 'Update the master record with HR evidence.'),
                step('Revalidate payroll', 'Confirm period eligibility is correct.')
            ],
            tips: [
                'An employee terminated during a payroll period may still be eligible for that period.',
                'Remove from active HRIS retains the record and stamps a removal date; Restore returns it to Active and clears that date.',
                'Older employees permanently deleted before this control was introduced cannot appear in this retained list.'
            ]
        },
        'users-access': {
            title: 'Users Access',
            audience: 'Admin and authorized access administrators',
            summary: 'Create user accounts, review roles, and manage page access without changing employee payroll data.',
            whatsHere: [
                'User Accounts and Create User controls.',
                'Role descriptions and the module access matrix.',
                'User details and access-level settings.'
            ],
            canDo: ['Create an authorized user account.', 'Assign the correct role.', 'Review which modules each role can access.'],
            actions: [
                action('Create a user', [
                    'Confirm the user is authorized and has an employee or administrator identity.',
                    'Select Create User.',
                    'Enter the username and required account details.',
                    'Assign the least-privileged role that meets the job requirement.',
                    'Save and test the user’s intended pages.'
                ]),
                action('Review access', [
                    'Open Role & Access Guide.',
                    'Compare the role description with the module access matrix.',
                    'Correct access only through the centralized role model.',
                    'Verify the change using a non-production test account.'
                ])
            ],
            flow: [
                step('Confirm authorization', 'Validate the user and business need.'),
                step('Choose role', 'Select the least-privileged suitable role.'),
                step('Create or update', 'Save the account settings.'),
                step('Test access', 'Verify allowed and denied pages.'),
                step('Review periodically', 'Remove stale or unnecessary access.')
            ],
            tips: ['Never share accounts. HR, Payroll, and Admin roles may have broad client access but different page permissions.']
        },
        'forms-templates': {
            title: 'Forms and Templates',
            audience: 'HR, Payroll, C&B, and Admin',
            summary: 'Download approved HRIS forms and import templates for employee, payroll, loan, and statutory processes.',
            whatsHere: ['Templates grouped by Employee Management, Payroll, Loans, SSS, Pag-Ibig, Philhealth, BIR, and HMO.', 'Download links for the supported file layouts.'],
            canDo: ['Download the correct template.', 'Prepare an import file using required headers and formats.', 'Avoid using outdated personal copies.'],
            actions: [
                action('Use a template', [
                    'Choose the business category.',
                    'Download the latest template from this page.',
                    'Do not rename required columns or change data formats.',
                    'Complete the file and validate it before upload.'
                ])
            ],
            flow: [
                step('Choose process', 'Select employee, payroll, loan, or statutory category.'),
                step('Download current template', 'Start from the system-provided file.'),
                step('Complete required fields', 'Follow the column and format rules.'),
                step('Validate file', 'Check duplicates, dates, IDs, and amounts.'),
                step('Upload to owning module', 'Review validation results before accepting.')
            ],
            tips: ['A template controls structure; it does not replace approval or source-document validation.']
        },
        'payroll-help': {
            title: 'Payroll Officer Help and FAQ',
            audience: 'Payroll, HR, and Admin',
            summary: 'A beginner-friendly reference for preparing, validating, generating, and releasing payroll.',
            whatsHere: ['The end-to-end payroll process map.', 'Searchable questions grouped by task.', 'Direct links to the relevant payroll pages.'],
            canDo: ['Search a payroll question.', 'Follow the recommended sequence.', 'Open the exact page needed for the next action.'],
            actions: [
                action('Find an answer', [
                    'Type a word such as DTR, employee, deduction, payslip, or error.',
                    'Choose a category when you want to narrow the list.',
                    'Open the question and follow the steps.',
                    'Use the linked page and return to the FAQ if another blocker appears.'
                ])
            ],
            flow: [
                step('Prepare employee master', 'Confirm employees, clients, status, and payday setup.'),
                step('Stage DTR', 'Use the DTR Format Engine for guarded source staging and validation.'),
                step('Resolve identity and population', 'Clear missing employees and DTR/payslip differences.'),
                step('Load payroll inputs', 'Add approved adjustments atomically and verify loan schedules.'),
                step('Generate and reconcile', 'Review payroll results and data-quality findings.'),
                step('Approve, post, and distribute', 'Release the sealed authoritative run only after every gate passes.')
            ],
            tips: ['When uncertain, stop before posting payroll and preserve the evidence needed for review.'],
            faq: true
        }
    };

    guides['incomplete-details'] = dataQualityGuide(
        'Incomplete Employee Details',
        'required employee information that is missing',
        'Open Employee Management and complete only the missing fields supported by the employee documents.'
    );
    guides['sss-format'] = dataQualityGuide(
        'SSS Number Format',
        'an invalid or incomplete SSS number format',
        'Correct the SSS number in Employee Management using the employee’s official SSS record.'
    );
    guides['duplicate-sss'] = dataQualityGuide(
        'Duplicate SSS Numbers',
        'the same SSS number assigned to more than one employee',
        'Determine the rightful owner from official SSS records, then correct the incorrect employee profile.'
    );
    guides['duplicate-philhealth'] = dataQualityGuide(
        'Duplicate PhilHealth Numbers',
        'the same PhilHealth number assigned to more than one employee',
        'Determine the rightful owner from official PhilHealth records, then correct the incorrect employee profile.'
    );
    guides['duplicate-pagibig'] = dataQualityGuide(
        'Duplicate Pag-IBIG Numbers',
        'the same Pag-IBIG number assigned to more than one employee',
        'Determine the rightful owner from official Pag-IBIG records, then correct the incorrect employee profile.'
    );
    guides['duplicate-tin'] = dataQualityGuide(
        'Duplicate TIN',
        'the same tax identification number assigned to more than one employee',
        'Determine the rightful owner from official BIR evidence, then correct the incorrect employee profile.'
    );
    guides['duplicate-bank-account'] = dataQualityGuide(
        'Duplicate Bank Accounts',
        'the same payroll bank account assigned to more than one employee',
        'Validate the bank enrollment evidence for both employees before correcting the account details.'
    );
    guides['invalid-salary'] = dataQualityGuide(
        'Invalid Salary',
        'a missing, zero, negative, or otherwise invalid salary setup',
        'Confirm the approved compensation record and effective date, then update the employee salary setup.'
    );
    guides['invalid-contact-number'] = dataQualityGuide(
        'Invalid Contact Number',
        'a contact number that is missing or does not meet the accepted format',
        'Confirm the employee’s current contact information and update the profile.'
    );
    guides['invalid-employee-type'] = dataQualityGuide(
        'Invalid Employee Type',
        'a missing or unsupported employment type',
        'Confirm the employment classification and effective date, then update the employee record.'
    );

    guides['branch-maintenance'] = maintenanceGuide('Branch Maintenance', 'branch', 'employees and operational reports');
    guides['client-maintenance'] = maintenanceGuide('Client Maintenance', 'client', 'employees, locations, payroll, billing, and reports');
    guides['department-maintenance'] = maintenanceGuide('Department Maintenance', 'department', 'employee assignments and reports');
    guides['position-maintenance'] = maintenanceGuide('Position Maintenance', 'position', 'employee assignments, compensation, and reports');
    guides['client-location-maintenance'] = maintenanceGuide('Client Location Maintenance', 'client location', 'employee assignments, DTR, payroll, and billing');
    guides['payday'] = maintenanceGuide('Pay Day Maintenance', 'payday and cutoff schedule', 'DTR uploads, payroll inputs, payslips, and posting');

    var payrollHelp = {
        checklist: [
            step('Confirm payroll scope', 'Verify client, cutoff, period, payday, and approving owner.'),
            step('Validate employees', 'Confirm active population, status dates, client assignment, and payroll identifiers.'),
            step('Stage and align DTR', 'Use guarded staging, then resolve every identity and population exception.'),
            step('Complete payroll inputs', 'Load additions and deductions atomically, validate loans, and lock versioned rules.'),
            step('Generate and reconcile', 'Review employee results, aggregate totals, variances, and Payroll Data Quality findings.'),
            step('Approve and release', 'Post and distribute sealed payslips only after the authoritative release gate and owner approval pass.')
        ],
        faqs: [
            {
                pages: ['payroll-dashboard', 'payroll-summary', 'payroll-data-quality', 'dtr-format-engine', 'dtr-upload', 'other-additional', 'other-deduction', 'payslip'],
                question: 'Where should I start when preparing payroll?',
                answer: 'Confirm the client, employee population, cutoff, payday, and source ownership first. Then load the DTR and resolve identity and population blockers before adjustments or generation.',
                url: '../employee-management/',
                keywords: 'start prepare sequence employee client payday'
            },
            {
                pages: ['payroll-dashboard', 'payroll-summary', 'payroll-data-quality'],
                question: 'What must be clear before payroll can be released?',
                answer: 'Employee identity, DTR population, calculation rules, additions, deductions, loans, reconciliation, sample payslips, artifact storage, and final owner approval must be complete with no release-blocking finding.',
                url: '../payroll-data-quality/',
                keywords: 'release gate blocker approval ready'
            },
            {
                pages: ['payroll-dashboard', 'payroll-summary'],
                question: 'Is the Payroll Dashboard or Summary an approval?',
                answer: 'No. These pages support review and variance investigation. Payroll is approved only through the governed release workflow after required data-quality and reconciliation checks pass.',
                url: '../dtr-format-engine/',
                keywords: 'dashboard summary approval variance review'
            },
            {
                pages: ['payroll-dashboard', 'payroll-summary'],
                question: 'What should executives review before asking Payroll to investigate?',
                answer: 'Use a like-for-like client, cutoff, payday, and population. Review employee count, gross-to-net movement, deductions, employer contributions, total payroll cost, and material variance against the prior comparable period.',
                url: '../payroll-summary/',
                keywords: 'executive compare gross net population contribution variance'
            },
            {
                pages: ['payroll-summary'],
                question: 'Does Payroll Summary include the shared-drive historical archive?',
                answer: 'Not yet. The page currently reports canonical employee-level HRIS payroll. The shared-drive archive remains excluded until its separate historical migration is validated, approved, and loaded.',
                url: '../payroll-summary/',
                keywords: 'historical google drive archive coverage'
            },
            {
                pages: ['payroll-summary', 'payroll-data-quality', 'other-deduction', 'payslip'],
                question: 'What should I do when net pay does not match?',
                answer: 'Trace gross earnings, attendance deductions, statutory deductions, loans, other deductions, additions, and effective deductions separately. Correct the owning source instead of forcing the final net amount.',
                url: '../payroll-data-quality/',
                keywords: 'net pay mismatch difference deduction gross'
            },
            {
                pages: ['payroll-data-quality'],
                question: 'How do I correct a Payroll Data Quality finding?',
                answer: 'Select Review issues, verify the employee and payroll-period evidence, then use the named employee or source-workspace action. Return and choose Rerun checks after the correction is saved.',
                url: '../payroll-data-quality/',
                keywords: 'data quality finding drawer rerun correction'
            },
            {
                pages: ['payroll-data-quality'],
                question: 'Why can Payroll see an issue but not correct it?',
                answer: 'Some findings are owned by HR or Admin, such as employee lifecycle evidence or User Access. Payroll can trace and escalate them but must not bypass role separation.',
                url: '../payroll-data-quality/',
                keywords: 'admin hr access permission escalate owner'
            },
            {
                pages: ['dtr-format-engine', 'dtr-upload'],
                question: 'When should I use the DTR Format Engine instead of DTR Upload?',
                answer: 'Use the DTR Format Engine for every new or corrected payroll input while the legacy direct writer and calculator are quarantined. Use DTR Upload to review the canonical scope and perform only the explicitly guarded cleanup actions.',
                url: '../dtr-format-engine/',
                keywords: 'dtr upload format engine inconsistent identifier'
            },
            {
                pages: ['dtr-format-engine', 'dtr-upload'],
                question: 'Why are legacy DTR Upload and manual Save blocked?',
                answer: 'The installed legacy calculator controls its own database commit and cannot participate safely in application rollback or audit. The block prevents a raw DTR change from becoming durable without matching payroll results. Correct the source and stage it through the governed Format Engine.',
                url: '../dtr-format-engine/',
                keywords: 'dtr blocked save legacy calculator transaction rollback'
            },
            {
                pages: ['dtr-format-engine', 'dtr-upload'],
                question: 'Why was my DTR rejected or blocked?',
                answer: 'Check the file layout, employee identifiers, client, dates, cutoff, payday, duplicate rows, status codes, and identity or population exceptions. Correct the source rather than repeatedly uploading it.',
                url: '../dtr-upload/',
                keywords: 'dtr rejected blocked upload validation duplicate'
            },
            {
                pages: ['dtr-format-engine'],
                question: 'What should I do when an employee is missing from HRIS?',
                answer: 'Search active and terminated employees first. If the employee is genuinely missing, HR should create a verified employee master record. Never create a duplicate solely to clear a payroll blocker.',
                url: '../employee-management/',
                keywords: 'missing employee identity hris create duplicate'
            },
            {
                pages: ['other-additional', 'other-deduction'],
                question: 'Where should a one-time earning or deduction be corrected?',
                answer: 'Use Other Additional or Other Deduction only for an approved one-time input. Enter the exact payroll scope, reason, and evidence; the adjustment, recalculation, and audit commit together. Attendance, statutory, loan, or DTR differences must be corrected in their owning source.',
                url: '../payroll-data-quality/',
                keywords: 'additional deduction one time adjustment source'
            },
            {
                pages: ['other-additional', 'other-deduction'],
                question: 'What happens if one row in an adjustment workbook fails?',
                answer: 'The whole bounded upload rolls back. Correct the complete file and upload it once again; the system does not keep earlier browser batches or silently skip rejected rows.',
                url: '../payroll-data-quality/',
                keywords: 'bulk upload atomic rollback row failure'
            },
            {
                pages: ['payslip'],
                question: 'What is the difference between Generate Payroll, Generate Payslip, and Post Payroll?',
                answer: 'Generate Payroll calculates results for review. Generate Payslip creates employee-facing artifacts. Post Payroll finalizes the approved cycle and must never be used as a test.',
                url: '../payslip/',
                keywords: 'generate payroll payslip post difference'
            },
            {
                pages: ['payslip', 'payroll-dashboard', 'payroll-data-quality'],
                question: 'Can payroll be changed after posting?',
                answer: 'Treat any post-release change as a controlled correction, reversal, or adjustment with approval and audit evidence. Do not silently overwrite a posted cycle.',
                url: '../audit-log/',
                keywords: 'after posting correction reversal audit'
            }
        ]
    };

    window.HrisHelpGuides = {
        guides: guides,
        fallback: {
            title: 'Page Guide',
            audience: 'Authorized HRIS users',
            summary: 'Use this page to review and complete the business task shown in the current module.',
            whatsHere: ['The current module’s filters, records, and authorized actions.'],
            canDo: ['Review the visible data and complete actions supported by your role.'],
            actions: [
                action('Use this page safely', [
                    'Confirm the page, client, employee, and period before acting.',
                    'Review the source evidence.',
                    'Complete the authorized action.',
                    'Verify the resulting record or status.'
                ])
            ],
            flow: [
                step('Confirm scope', 'Check the page and selected records.'),
                step('Review evidence', 'Understand the source information.'),
                step('Take action', 'Use an authorized page control.'),
                step('Validate result', 'Confirm the expected record or status.')
            ],
            tips: ['If the expected guide is missing, report the page name to the HRIS administrator.']
        },
        payrollFlow: guides['payroll-help'].flow,
        payrollHelp: payrollHelp
    };
}(window));
