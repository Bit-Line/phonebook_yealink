<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$user = current_user();
if (!$user) {
    logout();
    redirect('login.php');
}

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old = (string)($_POST['old_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $new2 = (string)($_POST['new_password2'] ?? '');

    if ($new !== $new2) {
        $err = 'Neue Passwörter stimmen nicht überein.';
    } else {
        try {
            user_change_password((int)$user['id'], $old, $new);
            audit_log_event('user_change_password', 'user', (int)$user['id'], null);
            flash_set('success', 'Passwort geändert.');
            redirect('account.php');
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
}

render_header('Passwort ändern');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Passwort ändern</h1>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger"><?= h($err) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="mb-2"><span class="text-muted">Benutzer:</span> <strong><?= h((string)$user['username']) ?></strong></div>
        <div class="mb-2"><span class="text-muted">Rolle:</span> <code><?= h((string)($user['role'] ?? '')) ?></code></div>

        <hr>

        <form method="post" autocomplete="off">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label">Aktuelles Passwort</label>
            <input class="form-control" type="password" name="old_password" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Neues Passwort</label>
            <input class="form-control" type="password" name="new_password" required minlength="6">
            <div class="form-text">Mindestens 6 Zeichen.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Neues Passwort (Wiederholung)</label>
            <input class="form-control" type="password" name="new_password2" required minlength="6">
          </div>

          <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Passwort ändern</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="alert alert-info">
      Tipp: Wenn du noch den Standard-Login <code>admin/admin</code> verwendest, ändere das Passwort bitte sofort.
    </div>
  </div>
</div>

<?php render_footer(); ?>
