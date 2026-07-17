<?php
/**
 * Role-Page Permission Map — SINGLE SOURCE OF TRUTH
 * ─────────────────────────────────────────────────
 * Required by:
 *   restriction/controller/RestrictionController.php  → access gating
 *   role-guide/controller/RoleGuideController.php     → guide display
 *
 * To change who can access what: EDIT THIS FILE ONLY.
 */

// ── Role display names ────────────────────────────────────────────────────
$role_names = [
    '1' => 'Admin',
    '2' => 'HR',
    '3' => 'Payroll',
    '4' => 'Coordinator',
    '5' => 'C&B',
];

// ── Module display map (ordered for the Role Guide table) ─────────────────
// page_id => [ label, group, is_sub_item ]
$page_map = [
    80  => ['Dashboard',                 'Dashboard',   false],
    81  => ['Employee Management',       'HR',          false],
    87  => ['Terminated Employees',      'HR',          false],
    88  => ['Forms & Templates',         'HR',          false],
    1   => ['Data Issue Tracker',        'Data Quality',false],
    11  => ['Incomplete Details',        'Data Quality',true],
    12  => ['SSS Format',                'Data Quality',true],
    13  => ['Duplicate SSS',             'Data Quality',true],
    14  => ['Duplicate Philhealth',      'Data Quality',true],
    20  => ['Duplicate Pag-ibig',        'Data Quality',true],
    15  => ['Duplicate TIN',             'Data Quality',true],
    16  => ['Duplicate Bank Account',    'Data Quality',true],
    17  => ['Invalid Salary',            'Data Quality',true],
    18  => ['Invalid Contact Number',    'Data Quality',true],
    19  => ['Invalid Employee Type',     'Data Quality',true],
    3   => ['Payroll',                   'Payroll',     false],
    35  => ['Payroll Dashboard',         'Payroll',     true],
    36  => ['Payroll Summary',           'Payroll',     true],
    38  => ['DTR Format Engine',         'Payroll',     true],
    31  => ['DTR Upload',                'Payroll',     true],
    32  => ['Other Additional',          'Payroll',     true],
    33  => ['Other Deduction',           'Payroll',     true],
    34  => ['Payslip',                   'Payroll',     true],
    70  => ['HR C&B',                    'HR C&B',      false],
    71  => ['Loans',                     'HR C&B',      true],
    72  => ['Loans Report',              'HR C&B',      true],
    82  => ['Billing',                   'Finance',     false],
    83  => ['Accounting / Finance',      'Finance',     false],
    84  => ['Client Management',         'Management',  false],
    85  => ['Audit Log',                 'Management',  false],
    86  => ['Users Access',              'Management',  false],
    5   => ['Maintenance',               'Maintenance', false],
    51  => ['Branch Maintenance',        'Maintenance', true],
    52  => ['Client Maintenance',        'Maintenance', true],
    53  => ['Department Maintenance',    'Maintenance', true],
    54  => ['Position Maintenance',      'Maintenance', true],
    55  => ['Pay Day',                   'Maintenance', true],
    56  => ['Client Location Maint.',    'Maintenance', true],
];

// ── Role → accessible page IDs ────────────────────────────────────────────
$pages = [
    '1' => [
                1,11,12,13,14,15,16,17,18,19,20,
                80,81,82,83,84,85,86,87,88,
                5,51,52,53,54,55,56,
                3,31,32,33,34,35,36,38,
                70,71,72,
            ], // Admin — all pages
    '2' => [
                1,11,12,13,14,15,16,17,18,19,20,
                80,81,84,87,88,
            ], // HR
    '3' => [
                80,81,82,83,84,87,
                3,31,32,33,34,35,36,
                70,71,72,88,
                5,51,52,53,54,55,56,
                1,11,12,13,14,15,16,17,18,19,20,
            ], // Payroll
    '4' => [
                81,87,
            ], // Coordinator
    '5' => [
                80,70,71,72,88,
                1,11,12,13,14,15,16,17,18,19,20,
            ], // C&B
];
