<?php
// Remote phonebook endpoint (no UI)
require_once __DIR__ . '/../src/bootstrap.php';

// Optional IP allowlist for phone access (admin can bypass if logged-in)
try {
    $allow = settings_get('phonebook_ip_allowlist', '');
    $clientIp = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : null;
    if (!is_logged_in() && !ip_allowlist_allows($clientIp, $allow)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Forbidden\n";
        exit;
    }
} catch (Throwable $e) {
    // If settings table is not available, do not block
}

// We don't want a session lock while generating XML
session_write_close();

$xml = revision_get_active_xml();
if ($xml === null) {
    // No active revision yet; generate from current working set
    $contacts = contacts_fetch_all_for_export();
    $xml = phonebook_generate_xml($contacts);
}

header('Content-Type: application/xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
echo $xml;
