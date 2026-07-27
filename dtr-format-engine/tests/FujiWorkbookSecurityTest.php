<?php
require_once(__DIR__ . '/../model/FujiPayrollSummaryAdapter.php');

$adapter = new FujiPayrollSummaryAdapter();
$method = new ReflectionMethod($adapter, 'validateFile');

function makeWorkbookZip(array $entries): string
{
    $path = tempnam(sys_get_temp_dir(), 'taascor-xlsx-');
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create workbook fixture.');
    }
    foreach ($entries as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
    return $path;
}

$validPath = makeWorkbookZip([
    '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
]);
$valid = $method->invoke($adapter, [
    'error' => UPLOAD_ERR_OK,
    'tmp_name' => $validPath,
    'name' => 'valid.xlsx',
    'size' => filesize($validPath),
]);
unlink($validPath);
if (($valid['valid'] ?? false) !== true) {
    throw new RuntimeException('Minimal safe workbook fixture should pass archive validation.');
}

$bombPath = makeWorkbookZip([
    '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
    'xl/worksheets/sheet1.xml' => str_repeat('A', 17 * 1024 * 1024),
]);
$bomb = $method->invoke($adapter, [
    'error' => UPLOAD_ERR_OK,
    'tmp_name' => $bombPath,
    'name' => 'compressed-bomb.xlsx',
    'size' => filesize($bombPath),
]);
unlink($bombPath);
if (($bomb['valid'] ?? true) !== false) {
    throw new RuntimeException('Oversized compressed workbook entry must be rejected.');
}

echo "RESULT: Fuji workbook archive security checks passed.\n";
