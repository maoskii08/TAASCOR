<?php

declare(strict_types=1);

require __DIR__ . '/../preview-binding-rules.php';

function dynamic_seal_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$report = (string)file_get_contents(__DIR__ . '/../payslip2.php');
dynamic_seal_check(
    str_contains($report, 'FROM payroll_import_release_locks l')
        && str_contains($report, "header('Location: payslip-sealed.php?"),
    'released enrolled payroll routes dynamic requests to the sealed artifact endpoint'
);
dynamic_seal_check(
    str_contains($report, '$serverRequiresPreview = true;')
        && str_contains($report, 'payslip_dynamic_watermark_policy(')
        && str_contains($report, '$pdf->preReleasePreview = (bool)$watermarkPolicy[\'required\'];'),
    'unreleased enrolled payroll is watermarked by server state even when preview=1 is omitted'
);

$craftedLegacyDirectRequest = payslip_dynamic_watermark_policy(true, false, false);
dynamic_seal_check(
    ($craftedLegacyDirectRequest['required'] ?? false) === true
        && ($craftedLegacyDirectRequest['mode'] ?? '') === 'unverified_legacy'
        && ($craftedLegacyDirectRequest['label'] ?? '') === 'UNVERIFIED LEGACY PREVIEW - NON-DISTRIBUTABLE',
    'crafted legacy direct URL cannot remove the server-required non-distributable watermark'
);

$legacyWithMisleadingRequest = payslip_dynamic_watermark_policy(true, false, true);
dynamic_seal_check(
    $legacyWithMisleadingRequest === $craftedLegacyDirectRequest,
    'request preview flags cannot weaken or replace the unverified legacy watermark'
);

dynamic_seal_check(
    str_contains($report, '$legacyPreviewMode = true;')
        && str_contains($report, '$pdf->previewWatermark = (string)$watermarkPolicy[\'label\'];')
        && str_contains($report, 'public function Header()')
        && str_contains($report, 'public function Footer()'),
    'schema-missing or unenrolled server output carries the prominent watermark on every PDF page'
);
dynamic_seal_check(
    str_contains($report, "exit('Payslip release status could not be verified. Please try again.');"),
    'dynamic payslip rendering fails closed when smart release status cannot be checked'
);

echo "RESULT: Dynamic payslip seal enforcement passed.\n";
