<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_admin();

$keyAllow = 'phonebook_ip_allowlist';

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $allow = (string)($_POST['allowlist'] ?? '');
    // Normalize line endings
    $allow = str_replace(["\r\n", "\r"], "\n", $allow);

    settings_set($keyAllow, $allow);
    audit_log_event('settings_update', 'settings', null, ['key' => $keyAllow]);
    flash_set('success', 'Einstellungen gespeichert.');
    redirect('admin_settings.php');
}

$allow = settings_get($keyAllow, '');
if ($allow === null) $allow = '';

render_header('Einstellungen');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Einstellungen</h1>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5">Telefon-Zugriff (IP Allowlist)</h2>
        <p class="text-muted">
          Dieses Feld schützt <code>phonebook.php</code> optional per IP-Allowlist.
          Wenn die Allowlist leer ist, ist der Zugriff <strong>für alle IPs</strong> erlaubt.
        </p>

        <form method="post">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label">Erlaubte IPs / Netze (1 pro Zeile)</label>
            <textarea class="form-control" name="allowlist" rows="8" placeholder="Beispiele:
192.168.1.10
192.168.1.0/24
10.0.*.*
# Kommentare mit #" ><?= h((string)$allow) ?></textarea>
            <div class="form-text">
              Unterstützt: einzelne IPv4/IPv6, IPv4 CIDR (z.B. <code>192.168.1.0/24</code>), sowie Wildcards (z.B. <code>10.0.*.*</code>).
            </div>
          </div>

          <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Speichern</button>
        </form>

        <hr>

        <div class="alert alert-info mb-0">
          Hinweis: Yealink-Geräte (z.B. T46G) unterstützen beim Remote Phonebook oft kein HTTP Basic Auth.
          IP-Restriktion ist daher die einfachste Option.
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5">Info</h2>
        <div class="small text-muted mb-2">Aktuelle Client-IP:</div>
        <code><?= h((string)($_SERVER['REMOTE_ADDR'] ?? '')) ?></code>

        <hr>

        <div class="small text-muted mb-2">Remote URL fürs Yealink:</div>
        <?php
          $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
          $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
          $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
          $phonebookUrl = $scheme . '://' . $host . $path . '/phonebook.php';
        ?>
        <code><?= h($phonebookUrl) ?></code>
      </div>
    </div>
  </div>
</div>

<?php render_footer(); ?>
