<?php

declare(strict_types=1);

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        return;
    }
    echo "PASS: {$message}\n";
};

$root = dirname(__DIR__);
$globalScript = file_get_contents($root . '/assets/js/hris-global.js');
$footer = file_get_contents($root . '/includes/custom-footer.php');

$check($globalScript !== false, 'shared HRIS browser script is readable');
$check($footer !== false, 'shared authenticated-page footer is readable');

if ($globalScript !== false) {
    $maintenanceModules = [
        'branch-maintenance',
        'client-maintenance',
        'department-maintenance',
        'position-maintenance',
        'client-location-maintenance',
        'payday',
    ];

    foreach ($maintenanceModules as $module) {
        $check(
            str_contains($globalScript, $module),
            "{$module} is covered by the shared save-state recovery"
        );
    }

    $check(
        str_contains($globalScript, "$('#addBtn, #saveBtn')"),
        'add and edit save controls are both covered'
    );
    $check(
        str_contains($globalScript, "data('hris-idle-html', $(this).html())"),
        'the original button markup is retained before a save starts'
    );
    $check(
        str_contains($globalScript, "data('hris-save-xhr', xhr)"),
        'the disabled Saving control is tied to its exact AJAX request'
    );
    $check(
        str_contains($globalScript, "!\$button.data('hris-save-xhr')"),
        'parallel AJAX requests cannot replace the save request associated with a control'
    );
    $check(
        str_contains($globalScript, '$(document).ajaxComplete'),
        'save-state recovery runs for every completed request outcome'
    );
    $check(
        str_contains($globalScript, ".prop('disabled', false)"),
        'the completed save control is re-enabled'
    );
    $check(
        str_contains($globalScript, '.html($button.data(\'hris-idle-html\'))'),
        'the completed save control restores its original label'
    );
}

if ($footer !== false) {
    $check(
        str_contains($footer, 'hris-global.js?v=20260731b'),
        'authenticated pages request the cache-busted shared fix'
    );
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Maintenance save-state regression checks passed.\n";
