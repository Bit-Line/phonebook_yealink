<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$filename = 'phonebook_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$delimiter = ';';

try {
    $contactsDb = contacts_fetch_all_for_export();
    $snapshot = snapshot_from_contacts($contactsDb);
    echo snapshot_to_csv($snapshot, $delimiter);
} catch (Throwable $e) {
    // Minimal CSV with error
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['ERROR', $e->getMessage()], $delimiter);
    fclose($fh);
}
exit;
