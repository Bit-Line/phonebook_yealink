<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_admin();

$pdo = db();

// Collect data
$settings = [];
try {
    $settings = settings_all();
} catch (Throwable $e) {
    $settings = [];
}

$contacts = [];
try {
    // already includes numbers + tags
    $contacts = contacts_fetch_all_for_export();
} catch (Throwable $e) {
    $contacts = [];
}

$revisions = [];
try {
    $st = $pdo->query('SELECT id, revision_number, comment, created_at, active, format, xml, xml_sha256, snapshot_json FROM revisions ORDER BY revision_number ASC');
    $revisions = $st->fetchAll();
} catch (Throwable $e) {
    $revisions = [];
}

$users = [];
try {
    $stU = $pdo->query('SELECT username, password_hash, role, created_at FROM users ORDER BY username');
    $users = $stU->fetchAll();
} catch (Throwable $e) {
    $users = [];
}

$payload = [
    'app' => 'yealink-phonebook-manager',
    'backup_version' => 1,
    'created_at' => date('c'),
    'data' => [
        'settings' => $settings,
        'contacts' => snapshot_from_contacts($contacts),
        'revisions' => $revisions,
        'users' => $users,
    ],
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    $json = '{"error":"json_encode_failed"}';
}

$fn = 'yealink_phonebook_backup_' . date('Ymd_His') . '.json';
header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fn . '"');
header('X-Content-Type-Options: nosniff');

audit_log_event('backup_export', 'backup', null, ['filename' => $fn]);

echo $json;
exit;
