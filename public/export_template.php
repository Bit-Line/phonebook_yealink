<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$filename = 'phonebook_template.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$delimiter = ';';
$fh = fopen('php://output', 'w');

$header = [
    'Name',
    'Department',
    'Office Number',
    'Mobile Number',
    'Other Number',
    'Tags',
    'Extra 1 Label', 'Extra 1 Number',
    'Extra 2 Label', 'Extra 2 Number',
    'Extra 3 Label', 'Extra 3 Number',
    'Extra 4 Label', 'Extra 4 Number',
    'Extra 5 Label', 'Extra 5 Number',
];

fputcsv($fh, $header, $delimiter);

fclose($fh);
exit;
