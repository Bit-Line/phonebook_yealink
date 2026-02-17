<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    flash_set('warning', 'Ungültige ID.');
    redirect('revisions.php');
}

$r = revision_get($id);
if (!$r) {
    flash_set('warning', 'Revision nicht gefunden.');
    redirect('revisions.php');
}

$raw = !empty($_GET['raw']);
$download = !empty($_GET['download']);

if ($raw) {
    header('Content-Type: application/xml; charset=utf-8');
    if ($download) {
        $fn = 'phonebook_rev_' . (int)$r['revision_number'] . '.xml';
        header('Content-Disposition: attachment; filename="' . $fn . '"');
    }
    echo (string)$r['xml'];
    exit;
}

render_header('Revision anzeigen');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Revision <?= (int)$r['revision_number'] ?></h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="revisions.php"><i class="bi bi-arrow-left"></i> Zurück</a>
    <a class="btn btn-outline-primary" href="revision_view.php?id=<?= (int)$r['id'] ?>&raw=1" target="_blank" rel="noopener"><i class="bi bi-filetype-xml"></i> Raw</a>
    <a class="btn btn-primary" href="revision_view.php?id=<?= (int)$r['id'] ?>&raw=1&download=1"><i class="bi bi-download"></i> Download</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="mb-2"><span class="text-muted">Datum:</span> <?= h((string)$r['created_at']) ?></div>
        <div class="mb-2"><span class="text-muted">Kommentar:</span> <?= h((string)($r['comment'] ?? '')) ?></div>
        <div class="mb-2"><span class="text-muted">Format:</span> <code><?= h((string)($r['format'] ?? '')) ?></code></div>
        <div class="mb-2"><span class="text-muted">SHA-256:</span> <code class="small"><?= h((string)($r['xml_sha256'] ?? '')) ?></code></div>
        <?php if ((int)$r['active'] === 1): ?>
          <div class="mt-3 badge text-bg-success">Aktiv</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <pre class="mb-0" style="max-height: 70vh; overflow:auto;"><?= h((string)$r['xml']) ?></pre>
      </div>
    </div>
  </div>
</div>

<?php render_footer(); ?>
