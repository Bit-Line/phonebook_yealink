<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (is_logged_in()) {
    redirect('contacts.php');
}

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    try {
        if (login_attempt($username, $password)) {
            flash_set('success', 'Login erfolgreich.');
            redirect('contacts.php');
        }
        $err = 'Login fehlgeschlagen.';
    } catch (Throwable $e) {
        $err = 'DB-Verbindung fehlgeschlagen (erst installieren/konfigurieren).';
    }
}

render_header('Login');
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h1 class="h4 mb-3">Login</h1>

        <?php if ($err): ?>
          <div class="alert alert-danger"><?= h($err) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Benutzername</label>
            <input class="form-control" name="username" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Passwort</label>
            <input class="form-control" type="password" name="password" required>
          </div>
          <button class="btn btn-primary w-100" type="submit">Einloggen</button>
        </form>

        <hr>
        <div class="small text-muted">
          Standard nach Installation: <code>admin / admin</code> (bitte danach ändern).
        </div>
      </div>
    </div>
  </div>
</div>
<?php render_footer(); ?>
