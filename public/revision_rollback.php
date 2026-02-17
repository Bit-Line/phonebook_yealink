<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_admin();

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
    revision_rollback_to_contacts($id);
    flash_set('success', 'Rollback abgeschlossen: Backend-Kontakte wurden zurückgesetzt.');
} catch (Throwable $e) {
    flash_set('danger', 'Rollback fehlgeschlagen: ' . $e->getMessage());
}

redirect('revisions.php');
