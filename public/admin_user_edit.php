<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;

$user = null;
if ($editing) {
    $user = user_get($id);
    if (!$user) {
        flash_set('warning', 'Benutzer nicht gefunden.');
        redirect('admin_users.php');
    }
}

$currentId = (int)($_SESSION['user_id'] ?? 0);
$isSelf = $editing && ($id === $currentId);

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = trim((string)($_POST['username'] ?? ''));
    $role = (string)($_POST['role'] ?? 'viewer');
    $password = (string)($_POST['password'] ?? '');

    if ($isSelf) {
        // avoid accidental lockout
        $role = (string)($user['role'] ?? 'admin');
    }

    try {
        if ($editing) {
            // prevent demoting the last admin
            $oldRole = (string)($user['role'] ?? 'viewer');
            $newRole = normalize_role($role);
            if ($oldRole === 'admin' && $newRole !== 'admin' && admins_count() <= 1) {
                throw new RuntimeException('Letzter Admin kann nicht auf eine andere Rolle geändert werden.');
            }

            user_update($id, $username, $newRole);
            audit_log_event('user_update', 'user', $id, ['username' => $username, 'role' => $newRole]);

            if (trim($password) !== '') {
                user_set_password($id, $password);
                audit_log_event('user_reset_password', 'user', $id, ['by_admin' => true]);
            }

            flash_set('success', 'Benutzer gespeichert.');
            redirect('admin_users.php');
        } else {
            $newRole = normalize_role($role);
            $newId = user_create($username, $password, $newRole);
            audit_log_event('user_create', 'user', $newId, ['username' => $username, 'role' => $newRole]);
            flash_set('success', 'Benutzer angelegt.');
            redirect('admin_users.php');
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
        $user = [
            'id' => $id,
            'username' => $username,
            'role' => normalize_role($role),
            'created_at' => $user['created_at'] ?? '',
        ];
    }
}

$title = $editing ? 'Benutzer bearbeiten' : 'Neuer Benutzer';
render_header($title);

$roles = roles_all();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0"><?= h($title) ?></h1>
  <a class="btn btn-outline-secondary" href="admin_users.php"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger"><?= h($err) ?></div>
<?php endif; ?>

<form method="post" class="card shadow-sm" autocomplete="off">
  <div class="card-body">
    <?= csrf_field() ?>

    <div class="row g-3">
      <div class="col-12 col-lg-6">
        <label class="form-label">Username *</label>
        <input class="form-control" name="username" required value="<?= h((string)($user['username'] ?? '')) ?>" <?= $editing ? '' : 'autofocus' ?>>
        <div class="form-text">Eindeutig, keine Leerzeichen empfohlen.</div>
      </div>

      <div class="col-12 col-lg-6">
        <label class="form-label">Rolle *</label>
        <select class="form-select" name="role" <?= $isSelf ? 'disabled' : '' ?>>
          <?php foreach ($roles as $k => $lbl): ?>
            <option value="<?= h($k) ?>" <?= (($user && (string)$user['role'] === $k) ? 'selected' : '') ?>><?= h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($isSelf): ?>
          <div class="form-text">Deine eigene Rolle wird hier nicht geändert (Lockout-Schutz).</div>
        <?php endif; ?>
      </div>
    </div>

    <hr class="my-4">

    <div class="row g-3">
      <div class="col-12 col-lg-6">
        <label class="form-label"><?= $editing ? 'Neues Passwort (optional)' : 'Passwort *' ?></label>
        <input class="form-control" type="password" name="password" <?= $editing ? '' : 'required' ?> minlength="6">
        <div class="form-text"><?= $editing ? 'Leer lassen, um Passwort nicht zu ändern.' : 'Mindestens 6 Zeichen.' ?></div>
      </div>
    </div>

  </div>

  <div class="card-footer d-flex justify-content-end gap-2">
    <a class="btn btn-outline-secondary" href="admin_users.php">Abbrechen</a>
    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Speichern</button>
  </div>
</form>

<?php render_footer(); ?>
