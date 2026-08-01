<?php

require_once __DIR__ . '/../model/SyntheticUploadParser.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

function makeSyntheticWorkbook(array $entries): string
{
    $path = tempnam(sys_get_temp_dir(), 'taascor-synthetic-xlsx-');
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create synthetic workbook fixture.');
    }
    foreach ($entries as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
    return $path;
}

$parser = new SyntheticUploadParser();
$archiveValidator = new ReflectionMethod($parser, 'validateArchive');
$xmlParser = new ReflectionMethod($parser, 'parseXml');

$validPath = makeSyntheticWorkbook([
    '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
    'xl/worksheets/sheet1.xml' => '<worksheet><sheetData/></worksheet>',
]);
$validZip = new ZipArchive();
$validZip->open($validPath);
$archiveValidator->invoke($parser, $validZip);
$validZip->close();
unlink($validPath);
check(true, 'safe synthetic workbook archive passes validation');

$bombPath = makeSyntheticWorkbook([
    '[Content_Types].xml' => '<Types/>',
    'xl/worksheets/sheet1.xml' => str_repeat('A', 17 * 1024 * 1024),
]);
$bombZip = new ZipArchive();
$bombZip->open($bombPath);
$bombRejected = false;
try {
    $archiveValidator->invoke($parser, $bombZip);
} catch (Throwable $error) {
    $bombRejected = true;
}
$bombZip->close();
unlink($bombPath);
check($bombRejected, 'compressed synthetic workbook bomb is rejected');

$xmlRejected = false;
try {
    $xmlParser->invoke($parser, '<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><x>&e;</x>', 'test XML');
} catch (Throwable $error) {
    $xmlRejected = true;
}
check($xmlRejected, 'synthetic workbook XML entity declarations are rejected');

echo "RESULT: Synthetic workbook archive and XML security checks passed.\n";
