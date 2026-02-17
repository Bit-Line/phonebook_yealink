<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_editor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}
csrf_verify();
$comment = isset($_POST['comment']) ? trim((string)$_POST['comment']) : null;
if ($comment === '') $comment = null;

try {
    $revNo = revision_create($comment);
    flash_set('success', 'Revision ' . $revNo . ' erstellt und aktiviert.');
} catch (Throwable $e) {
    flash_set('danger', 'Fehler beim Publizieren: ' . $e->getMessage());
}
redirect('revisions.php');
