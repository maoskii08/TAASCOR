<?php

declare(strict_types=1);

$sourcePath = __DIR__ . '/../js/index-13.js';
$source = file_get_contents($sourcePath);
$checks = 0;

function checkDtrMutationAuditUi(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

checkDtrMutationAuditUi(
    is_string($source) && $source !== '',
    'DTR Upload JavaScript is readable'
);
checkDtrMutationAuditUi(
    str_contains($source, 'function dtrAuditEvidenceUrl(eventId)'),
    'a single audit evidence URL helper owns deep-link construction'
);
checkDtrMutationAuditUi(
    str_contains($source, 'if (!/^DTRM-[A-F0-9]{32}$/.test(normalizedEventId))'),
    'audit evidence URLs accept only stable canonical DTR mutation event IDs'
);
checkDtrMutationAuditUi(
    str_contains($source, "return '../audit-log/?dtr_event=' + encodeURIComponent(normalizedEventId);"),
    'audit event IDs are encoded into the bounded Audit Log deep link'
);
checkDtrMutationAuditUi(
    str_contains($source, 'href="\' + dtrEscapeHtml(evidenceUrl) + \'"'),
    'the generated evidence URL is HTML escaped before rendering'
);
checkDtrMutationAuditUi(
    substr_count($source, 'html: dtrAuditedSuccessHtml(') === 4,
    'all four DTR mutation success dialogs render the governed evidence link'
);
checkDtrMutationAuditUi(
    substr_count(
        $source,
        'if(response.success == 1 && response.audit_recorded && auditEvidenceLink)'
    ) === 4,
    'all four DTR success states require a durable, valid audit event reference'
);

foreach ([
    'Government benefits removed and audited',
    'Records deleted and audited',
    'Employee DTR deleted and audited',
    'DTR change saved and audited',
] as $successTitle) {
    checkDtrMutationAuditUi(
        str_contains($source, "title: '{$successTitle}'"),
        "{$successTitle} is covered by the audited success contract"
    );
}

checkDtrMutationAuditUi(
    str_contains($source, 'id="dtr-delete-evidence"')
        && str_contains($source, 'id="dtr-employee-delete-evidence"'),
    'bulk-scope and employee-scope deletion dialogs collect an evidence reference'
);
checkDtrMutationAuditUi(
    substr_count($source, 'append("deletion_evidence",') === 2,
    'both destructive requests submit their evidence reference'
);
checkDtrMutationAuditUi(
    substr_count($source, "Swal.showValidationMessage('Enter a traceable evidence reference.');") === 3,
    'benefit, bulk-delete, and employee-delete evidence inputs fail closed when blank'
);

fwrite(STDOUT, "PASS: {$checks} DTR mutation audit UI checks\n");
