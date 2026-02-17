<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_editor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_set('warning', 'Ungültige ID.');
    redirect('revisions.php');
}

try {
    revision_delete($id);
    flash_set('success', 'Revision gelöscht.');
} catch (Throwable $e) {
    flash_set('danger', 'Löschen fehlgeschlagen: ' . $e->getMessage());
}
redirect('revisions.php');
