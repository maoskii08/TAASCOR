<?php
/**
 * Client Master Sync Controller
 * Actions: sync | get-master-view
 * Admin only.
 */
require_once('../../includes/auth_guard.php');
auth_require_role([1]);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('content-type: application/json');

require('../../config/db_connect.php');

// ── Google Sheet (public CSV) ─────────────────────────────────────────────
define('SHEET_URL',
    'https://docs.google.com/spreadsheets/d/'
    . '18hCcyg4a0y_cG1le8Wp5l4YVWo7Ax-u88W5tBkLXMoc'
    . '/gviz/tq?tqx=out:csv&sheet=Client%20Master'
);

// ── Known HRIS name aliases ───────────────────────────────────────────────
// First word of HRIS name (uppercase) => fd_code
const ALIASES = [
    'BI'         => 'BICHAIN',
    'EURO'       => 'EURO_MED',
    'GLOBALMAXX' => 'GLOBALMAXX',
    'OHGITANI'   => 'OHGITANI',
    'SHOPEE'     => 'SCOMMERCE',
    'SLP'        => 'SIIX_EMS',
    'MELAOR'     => 'MTC_TRANSPORT',
    'TECHNOLOGY' => 'TECHNO_PRYME',
];

// ── Helper: fetch & parse the sheet ──────────────────────────────────────
function fetch_master(): array {
    $ctx = stream_context_create(['http' => ['timeout' => 15]]);
    $raw = @file_get_contents(SHEET_URL, false, $ctx);
    if ($raw === false) return [];
    $raw  = str_replace(["\r\n", "\r"], "\n", $raw);
    $rows = [];
    foreach (explode("\n", $raw) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $rows[] = str_getcsv($line);
    }
    return $rows;
}

// ── Route ─────────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? 'sync';

// ══════════════════════════════════════════════════════════════════════════
if ($action === 'sync') {
// ══════════════════════════════════════════════════════════════════════════

    $rows = fetch_master();
    if (empty($rows)) {
        echo json_encode(['success' => 0, 'error' => 'Could not fetch Client Master sheet.']);
        exit;
    }
    array_shift($rows); // remove header

    $masterByCode = []; $codeLookup1 = []; $codeLookup2 = [];
    foreach ($rows as $r) {
        if (count($r) < 4) continue;
        $canonical = trim($r[0]); $group = trim($r[1]);
        $isActive  = strtolower(trim($r[2])) === 'active' ? 1 : 0;
        $fdCode    = trim($r[3]);
        if (!$canonical || !$fdCode) continue;
        $masterByCode[$fdCode] = compact('canonical', 'group', 'isActive', 'fdCode');
        $words = preg_split('/[\s\-]+/', strtoupper($canonical));
        $k1 = $words[0]; $k2 = isset($words[1]) ? $words[0].' '.$words[1] : null;
        if ($k2 && !isset($codeLookup2[$k2])) $codeLookup2[$k2] = $fdCode;
        if (!isset($codeLookup1[$k1])) $codeLookup1[$k1] = $fdCode;
    }

    $clients = $pdoConn->query(
        "SELECT client_id, client_name FROM taascor_client WHERE client_name <> 'No Client'"
    )->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdoConn->prepare(
        "UPDATE taascor_client
         SET fd_code=:fc, fd_canonical=:cn, fd_group=:gr, is_active=:ia
         WHERE client_id=:id"
    );

    $updated = []; $unmatched = [];
    $aliases = ALIASES;

    foreach ($clients as $c) {
        $name  = trim($c['client_name']);
        $words = preg_split('/[\s\-]+/', strtoupper($name));
        $k1 = $words[0] ?? ''; $k2 = isset($words[1]) ? $words[0].' '.$words[1] : null;
        $fdCode = ($k2 && isset($codeLookup2[$k2])) ? $codeLookup2[$k2]
                : (isset($aliases[$k1])              ? $aliases[$k1]
                : (isset($codeLookup1[$k1])           ? $codeLookup1[$k1]
                : null));

        if ($fdCode && isset($masterByCode[$fdCode])) {
            $m = $masterByCode[$fdCode];
            $stmt->execute([':fc'=>$m['fdCode'],':cn'=>$m['canonical'],':gr'=>$m['group'],':ia'=>$m['isActive'],':id'=>$c['client_id']]);
            $updated[] = ['name' => $name, 'fd_code' => $m['fdCode'], 'canonical' => $m['canonical'], 'active' => $m['isActive']];
        } else {
            $unmatched[] = $name;
        }
    }

    $active   = $pdoConn->query("SELECT COUNT(*) FROM taascor_client WHERE is_active=1 AND client_name<>'No Client'")->fetchColumn();
    $inactive = $pdoConn->query("SELECT COUNT(*) FROM taascor_client WHERE is_active=0 AND client_name<>'No Client'")->fetchColumn();
    $mapped   = $pdoConn->query("SELECT COUNT(*) FROM taascor_client WHERE fd_code IS NOT NULL AND client_name<>'No Client'")->fetchColumn();

    echo json_encode([
        'success'          => 1,
        'master_entries'   => count($masterByCode),
        'hris_clients'     => count($clients),
        'updated'          => count($updated),
        'unmatched_count'  => count($unmatched),
        'unmatched'        => $unmatched,
        'active_clients'   => (int)$active,
        'inactive_clients' => (int)$inactive,
        'mapped_clients'   => (int)$mapped,
        'detail'           => $updated,
        'synced_at'        => date('Y-m-d H:i:s'),
    ]);

// ══════════════════════════════════════════════════════════════════════════
} elseif ($action === 'get-master-view') {
// ══════════════════════════════════════════════════════════════════════════

    $rows = fetch_master();
    if (empty($rows)) {
        echo json_encode(['success' => 0, 'error' => 'Could not fetch Client Master sheet.']);
        exit;
    }
    array_shift($rows); // remove header

    // HRIS lookup: fd_code → list of {name, is_active, active_count}
    $hrisRows = $pdoConn->query(
        "SELECT c.client_name,
                COALESCE(c.is_active, 1)          AS is_active,
                COALESCE(c.fd_code, '')            AS fd_code,
                COUNT(CASE WHEN e.status='Active' THEN 1 END) AS active_count
         FROM taascor_client c
         LEFT JOIN employee_list e ON c.client_id = e.client_id
         WHERE c.client_name <> 'No Client'
         GROUP BY c.client_id, c.client_name, c.is_active, c.fd_code
         ORDER BY c.client_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $hrisByCode = []; $hrisNoCode = [];
    foreach ($hrisRows as $h) {
        $fc = trim($h['fd_code']);
        if ($fc !== '') { $hrisByCode[$fc][] = $h; }
        else            { $hrisNoCode[]       = $h; }
    }

    // Build one row per master canonical entry
    $masterView = []; $usedCodes = [];
    foreach ($rows as $r) {
        if (count($r) < 4) continue;
        $canonical    = trim($r[0]); $group = trim($r[1]);
        $statusStr    = trim($r[2]); $fdCode = trim($r[3]);
        $inactiveDate = isset($r[5]) ? trim($r[5]) : '';
        if (!$canonical || !$fdCode) continue;

        $matched  = $hrisByCode[$fdCode] ?? [];
        $usedCodes[$fdCode] = true;
        $totalEmp = array_sum(array_column($matched, 'active_count'));

        $masterView[] = [
            'canonical'     => $canonical,
            'group'         => $group,
            'status'        => $statusStr,
            'fd_code'       => $fdCode,
            'inactive_date' => $inactiveDate,
            'hris_clients'  => $matched,
            'total_emp'     => (int)$totalEmp,
            'has_hris'      => count($matched) > 0,
        ];
    }

    // HRIS clients whose fd_code isn't in any master row (orphaned)
    $orphaned = [];
    foreach ($hrisByCode as $fc => $clients) {
        if (!isset($usedCodes[$fc])) {
            foreach ($clients as $c) $orphaned[] = array_merge($c, ['reason' => 'FD code not in master']);
        }
    }
    foreach ($hrisNoCode as $c) {
        $orphaned[] = array_merge($c, ['reason' => 'No FD code assigned']);
    }

    echo json_encode([
        'success'     => 1,
        'master_rows' => $masterView,
        'orphaned'    => $orphaned,
        'fetched_at'  => date('Y-m-d H:i:s'),
    ]);

// ══════════════════════════════════════════════════════════════════════════
} else {
    echo json_encode(['success' => 0, 'error' => 'Unknown action.']);
}
