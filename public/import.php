<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$err = null;
$stats = null;

$canEdit = has_min_role('editor');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        flash_set('danger', 'Keine Berechtigung (Read-only).');
        redirect('import.php');
    }
    csrf_verify();

    $replace = !empty($_POST['replace']);

    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $err = 'CSV Datei fehlt oder Upload fehlgeschlagen.';
    } else {
        $content = file_get_contents($_FILES['csv']['tmp_name']);
        if ($content === false) {
            $err = 'CSV Datei konnte nicht gelesen werden.';
        } else {
            try {
                $stats = contacts_import_csv($content, $replace);
                flash_set('success', 'Import abgeschlossen: ' . $stats['imported'] . ' importiert, ' . $stats['skipped'] . ' übersprungen.');
                redirect('import.php');
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
        }
    }
}

render_header('Import/Export');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Import/Export</h1>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger"><?= h($err) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5">CSV Import</h2>
        <p class="text-muted">Importiert Kontakte aus einer CSV Datei. Unterstützt auch Tags (Spalte <code>Tags</code>, kommagetrennt).</p>

        <?php if (!$canEdit): ?>
          <div class="alert alert-warning">Du hast Read-only Zugriff. Import ist deaktiviert.</div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label">CSV Datei</label>
            <input class="form-control" type="file" name="csv" accept="text/csv,.csv" required <?= $canEdit ? '' : 'disabled' ?>>
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="replace" id="replace" <?= $canEdit ? '' : 'disabled' ?>>
            <label class="form-check-label" for="replace">Vorhandene Kontakte ersetzen (alles löschen)</label>
          </div>

          <button class="btn btn-primary" type="submit" <?= $canEdit ? '' : 'disabled' ?>><i class="bi bi-upload"></i> Importieren</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5">Export</h2>
        <p class="text-muted">Hilft dir, Daten aus anderen Quellen aufzubereiten oder Backups zu erstellen.</p>

        <div class="d-grid gap-2">
          <a class="btn btn-outline-secondary" href="export_template.php"><i class="bi bi-file-earmark-spreadsheet"></i> CSV Template herunterladen</a>
          <a class="btn btn-outline-secondary" href="export_contacts.php"><i class="bi bi-download"></i> Aktuelle Kontakte als CSV exportieren</a>
          <a class="btn btn-outline-secondary" href="phonebook.php" target="_blank" rel="noopener"><i class="bi bi-filetype-xml"></i> Aktives XML anzeigen</a>
        </div>

        <hr>

        <div class="alert alert-info mb-0">
          CSV Spalten: <code>Name</code>, <code>Department</code>, <code>Office/Mobile/Other Number</code>, <code>Tags</code> und bis zu 5 Extra-Nummern.
        </div>
      </div>
    </div>
  </div>
</div>

<?php render_footer(); ?>
