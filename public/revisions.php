<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 20;

$total = 0;
$pages = 1;
$offset = 0;
$revs = [];

try {
    $total = revision_count();
    $pages = (int)max(1, (int)ceil($total / $perPage));
    if ($page > $pages) $page = $pages;
    $offset = ($page - 1) * $perPage;
    $revs = revision_list_page($perPage, $offset);
} catch (Throwable $e) {
    flash_set('danger', 'DB-Fehler: ' . $e->getMessage());
    $revs = [];
}

$active = null;
try { $active = revision_get_active(); } catch (Throwable $e) { $active = null; }

$canPublish = has_min_role('editor');
$canAdmin = has_min_role('admin');

render_header('Revisionen');
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Revisionen</h1>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5">Aktive Revision</h2>
        <?php if ($active): ?>
          <div class="mb-2">Rev <span class="badge text-bg-success"><?= (int)$active['revision_number'] ?></span></div>
          <?php if (!empty($active['comment'])): ?>
            <div class="text-muted mb-2"><?= h((string)$active['comment']) ?></div>
          <?php endif; ?>
          <div class="text-muted small">Erstellt am: <?= h((string)$active['created_at']) ?></div>
          <div class="mt-3 d-grid gap-2">
            <a class="btn btn-outline-secondary" href="phonebook.php" target="_blank" rel="noopener"><i class="bi bi-filetype-xml"></i> Aktives XML öffnen</a>
          </div>
        <?php else: ?>
          <div class="alert alert-warning mb-0">
            Noch keine Revision publiziert. Kontakte werden erst ans Telefon ausgerollt, wenn du eine Revision publizierst.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5">Neue Revision publizieren</h2>
        <p class="text-muted">Erstellt eine neue Revision aus dem aktuellen Kontaktbestand und setzt sie aktiv. Vorher wird ein Diff angezeigt.</p>

        <?php if (!$canPublish): ?>
          <div class="alert alert-warning mb-0">Du hast Read-only Zugriff. Publizieren ist deaktiviert.</div>
        <?php else: ?>
          <form method="post" action="publish_preview.php">
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label">Kommentar (optional)</label>
              <input class="form-control" name="comment" maxlength="255" placeholder="z.B. Neue Nummern / Änderungen">
            </div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-list-check"></i> Diff anzeigen</button>
          </form>
        <?php endif; ?>

        <hr>
        <div class="alert alert-info mb-0">
          Änderungen an Kontakten erzeugen <strong>keine</strong> automatische Revision.
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mt-3">
  <div class="card-body">
    <h2 class="h5">Alle Revisionen</h2>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>Rev</th>
            <th>Kommentar</th>
            <th>Erstellt</th>
            <th>Status</th>
            <th class="text-end">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$revs): ?>
            <tr><td colspan="5" class="text-muted">Keine Revisionen.</td></tr>
          <?php endif; ?>

          <?php foreach ($revs as $r): ?>
            <?php
              $isActive = ((int)$r['active'] === 1);
              $revNo = (int)$r['revision_number'];
              $hasSnap = !empty($r['has_snapshot']);
            ?>
            <tr>
              <td class="fw-semibold"><?= $revNo ?></td>
              <td><?= h((string)($r['comment'] ?? '')) ?></td>
              <td class="text-muted" style="white-space:nowrap;"><?= h((string)$r['created_at']) ?></td>
              <td>
                <?php if ($isActive): ?>
                  <span class="badge text-bg-success">active</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">inactive</span>
                <?php endif; ?>
                <?php if ($hasSnap): ?>
                  <span class="badge text-bg-info" title="Snapshot vorhanden (CSV Export + Rollback)">snapshot</span>
                <?php endif; ?>
              </td>
              <td class="text-end" style="white-space:nowrap;">
                <a class="btn btn-sm btn-outline-secondary" href="revision_view.php?id=<?= (int)$r['id'] ?>" title="Ansehen"><i class="bi bi-eye"></i></a>
                <a class="btn btn-sm btn-outline-secondary" href="revision_export.php?id=<?= (int)$r['id'] ?>&type=xml" title="XML export"><i class="bi bi-download"></i></a>
                <a class="btn btn-sm btn-outline-secondary" href="revision_export.php?id=<?= (int)$r['id'] ?>&type=csv" title="CSV export"><i class="bi bi-file-earmark-spreadsheet"></i></a>

                <?php if ($canPublish): ?>
                  <?php if (!$isActive): ?>
                    <form method="post" action="revision_activate.php" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-outline-success" type="submit" title="Aktiv setzen"><i class="bi bi-check2-circle"></i></button>
                    </form>
                  <?php endif; ?>

                  <?php if (!$isActive): ?>
                    <form method="post" action="revision_delete.php" class="d-inline" onsubmit="return confirm('Revision wirklich löschen?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit" title="Löschen"><i class="bi bi-trash"></i></button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>

                <?php if ($canAdmin): ?>
                  <form method="post" action="revision_rollback.php" class="d-inline" onsubmit="return confirm('Rollback: Backend-Kontakte auf diese Revision zurücksetzen?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-warning" type="submit" title="Rollback (Backend)"><i class="bi bi-arrow-counterclockwise"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <?php
        $mkUrl = function(int $p): string {
            return 'revisions.php?' . http_build_query(['page' => $p]);
        };
        $window = 2;
        $start = max(1, $page - $window);
        $end = min($pages, $page + $window);
      ?>
      <nav aria-label="Revisionsseiten" class="mt-3">
        <ul class="pagination justify-content-center flex-wrap">
          <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>">
            <a class="page-link" href="<?= h($mkUrl(max(1, $page - 1))) ?>" aria-label="Vorherige"><span aria-hidden="true">&laquo;</span></a>
          </li>

          <?php if ($start > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= h($mkUrl(1)) ?>">1</a></li>
            <?php if ($start > 2): ?>
              <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
          <?php endif; ?>

          <?php for ($p = $start; $p <= $end; $p++): ?>
            <li class="page-item <?= ($p === $page ? 'active' : '') ?>">
              <a class="page-link" href="<?= h($mkUrl($p)) ?>"><?= (int)$p ?></a>
            </li>
          <?php endfor; ?>

          <?php if ($end < $pages): ?>
            <?php if ($end < $pages - 1): ?>
              <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= h($mkUrl($pages)) ?>"><?= (int)$pages ?></a></li>
          <?php endif; ?>

          <li class="page-item <?= ($page >= $pages ? 'disabled' : '') ?>">
            <a class="page-link" href="<?= h($mkUrl(min($pages, $page + 1))) ?>" aria-label="Nächste"><span aria-hidden="true">&raquo;</span></a>
          </li>
        </ul>

        <div class="text-center text-muted small"><?= (int)$total ?> Revisionen • Seite <?= (int)$page ?> von <?= (int)$pages ?></div>
      </nav>
    <?php endif; ?>

  </div>
</div>

<?php render_footer(); ?>
