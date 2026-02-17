<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}
csrf_verify();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    flash_set('warning', 'Ungültige ID.');
    redirect('admin_users.php');
}

$currentId = (int)($_SESSION['user_id'] ?? 0);
if ($id === $currentId) {
    flash_set('danger', 'Du kannst dich nicht selbst löschen.');
    redirect('admin_users.php');
}

$u = user_get($id);
if (!$u) {
    flash_set('warning', 'Benutzer nicht gefunden.');
    redirect('admin_users.php');
}

if ((string)$u['role'] === 'admin' && admins_count() <= 1) {
    flash_set('danger', 'Letzter Admin kann nicht gelöscht werden.');
    redirect('admin_users.php');
}

try {
    user_delete($id);
    audit_log_event('user_delete', 'user', $id, ['username' => (string)$u['username']]);
    flash_set('success', 'Benutzer gelöscht.');
} catch (Throwable $e) {
    flash_set('danger', 'Löschen fehlgeschlagen: ' . $e->getMessage());
}

redirect('admin_users.php');
