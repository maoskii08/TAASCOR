<?php

declare(strict_types=1);

require_once __DIR__ . '/../recruitment/includes/public_candidate_origin.php';

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        echo "FAIL: {$message}\n";
        return;
    }
    echo "PASS: {$message}\n";
};

$cases = [
    ['/hris/recruitment/candidate/index.php', [], '/account/login.php'],
    ['/hris/recruitment/candidate/register.php', [], '/account/register.php'],
    ['/hris/recruitment/candidate/privacy.php', [], '/apply/privacy.php'],
    ['/hris/recruitment/candidate/settings.php', [], '/account/settings.php'],
    ['/hris/recruitment/candidate/offers.php', [], '/applicant/'],
    ['/hris/recruitment/candidate/apply.php', ['job' => 'warehouse-associate'], '/apply/warehouse-associate/'],
    ['/hris/recruitment/candidate/apply.php', ['job' => 'not/a/safe/slug'], '/jobs/'],
];

foreach ($cases as [$path, $query, $expected]) {
    $_SERVER['REQUEST_URI'] = $path;
    $_GET = $query;
    $check(
        recruitment_public_candidate_destination() === $expected,
        "{$path} maps to {$expected}"
    );
}

$guide = (string) file_get_contents(__DIR__ . '/../recruitment/guide.php');
$check(str_contains($guide, "Location: https://taascor.com/recruitment/guide/"), 'public guide redirects to taascor.com');
$check(!str_contains($guide, 'Guide Center | TAASCOR HRIS'), 'HRIS no longer renders the candidate-facing Guide Center');

if ($failures !== []) {
    fwrite(STDERR, 'RESULT: ' . count($failures) . " domain boundary check(s) failed.\n");
    exit(1);
}

echo "RESULT: Recruitment domain boundary checks passed.\n";
