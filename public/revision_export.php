<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : '';

if ($id <= 0 || ($type !== 'xml' && $type !== 'csv')) {
    http_response_code(400);
    echo 'Bad request';
    exit;
}

$rev = revision_get($id);
if (!$rev) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$revNo = (int)($rev['revision_number'] ?? 0);

if ($type === 'xml') {
    $fn = 'phonebook_rev_' . $revNo . '.xml';
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    header('X-Content-Type-Options: nosniff');
    echo (string)($rev['xml'] ?? '');
    exit;
}

// CSV
$snapshotContacts = revision_snapshot_contacts($rev);
$delimiter = ';';
$csv = snapshot_to_csv($snapshotContacts, $delimiter);

$fn = 'phonebook_rev_' . $revNo . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fn . '"');
header('X-Content-Type-Options: nosniff');

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";
echo $csv;
exit;
