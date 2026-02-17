<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_editor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}
csrf_verify();

$comment = trim((string)($_POST['comment'] ?? ''));
if ($comment === '') $comment = null;

// current working set
$currentContactsDb = contacts_fetch_all_for_export();
$currentSnap = snapshot_from_contacts($currentContactsDb);

// active revision snapshot
$activeRev = revision_get_active_full();
$activeContacts = [];
$activeRevNo = null;
if ($activeRev) {
    $activeRevNo = (int)$activeRev['revision_number'];
    $activeContacts = revision_snapshot_contacts($activeRev);
}

// Diff helper lives in src/snapshot.php (PHP 7.2 compatible)
$diff = snapshot_diff($activeContacts, $currentSnap);

$countAdded = count($diff['added']);
$countRemoved = count($diff['removed']);
$countChanged = count($diff['changed']);

render_header('Diff vor Publizieren');

$renderContact = function(array $c): string {
    $name = (string)($c['name'] ?? '');
    $dept = (string)($c['department'] ?? '');
    $tags = isset($c['tags']) && is_array($c['tags']) ? $c['tags'] : [];
    $nums = isset($c['numbers']) && is_array($c['numbers']) ? $c['numbers'] : [];

    $html = '<div><strong>' . h($name) . '</strong>';
    if ($dept !== '') {
        $html .= ' <span class="text-muted">(' . h($dept) . ')</span>';
    }
    $html .= '</div>';

    if ($tags) {
        $html .= '<div class="mt-1">';
        foreach ($tags as $t) {
            $html .= '<span class="badge text-bg-secondary me-1">' . h((string)$t) . '</span>';
        }
        $html .= '</div>';
    }

    if ($nums) {
        $html .= '<div class="small text-muted mt-1">';
        $parts = [];
        foreach ($nums as $n) {
            $lab = (string)($n['label'] ?? '');
            $num = (string)($n['number'] ?? '');
            if ($num === '') continue;
            $parts[] = h($lab) . ': <code>' . h($num) . '</code>';
        }
        $html .= implode(' • ', $parts);
        $html .= '</div>';
    }

    return $html;
};
?>

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Diff vor dem Publizieren</h1>
  <a class="btn btn-outline-secondary" href="revisions.php"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<div class="alert alert-info">
  Vergleich zwischen <strong>aktiver Revision</strong> (<?= $activeRevNo ? 'Rev ' . (int)$activeRevNo : 'keine' ?>)
  und dem aktuellen Backend-Stand.
</div>

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5">Zusammenfassung</h2>
        <ul class="mb-0">
          <li><span class="badge text-bg-success">+<?= (int)$countAdded ?></span> neue Kontakte</li>
          <li><span class="badge text-bg-danger">−<?= (int)$countRemoved ?></span> entfernte Kontakte</li>
          <li><span class="badge text-bg-warning">~<?= (int)$countChanged ?></span> geänderte Kontakte</li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5">Publizieren</h2>
        <p class="text-muted">Wenn alles passt, kannst du jetzt eine neue Revision erstellen und aktiv setzen.</p>

        <form method="post" action="publish.php" class="d-flex flex-column gap-2">
          <?= csrf_field() ?>
          <?php if ($comment !== null): ?>
            <input type="hidden" name="comment" value="<?= h($comment) ?>">
          <?php endif; ?>

          <div class="mb-2">
            <span class="text-muted">Kommentar:</span>
            <div><?= $comment !== null ? h($comment) : '<span class="text-muted">(kein)</span>' ?></div>
          </div>

          <button class="btn btn-primary" type="submit" onclick="return confirm('Neue Revision erstellen und aktiv setzen?');"><i class="bi bi-rocket-takeoff"></i> Publizieren & aktiv setzen</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="mt-3">
  <details class="mb-2">
    <summary class="h6">+ Neue Kontakte (<?= (int)$countAdded ?>)</summary>
    <div class="mt-2">
      <?php if (!$diff['added']): ?>
        <div class="text-muted">Keine.</div>
      <?php else: ?>
        <div class="list-group">
          <?php foreach (array_slice($diff['added'], 0, 200) as $c): ?>
            <div class="list-group-item"><?= $renderContact($c) ?></div>
          <?php endforeach; ?>
        </div>
        <?php if ($countAdded > 200): ?>
          <div class="text-muted small mt-2">… gekürzt (<?= (int)$countAdded ?> total)</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </details>

  <details class="mb-2">
    <summary class="h6">− Entfernte Kontakte (<?= (int)$countRemoved ?>)</summary>
    <div class="mt-2">
      <?php if (!$diff['removed']): ?>
        <div class="text-muted">Keine.</div>
      <?php else: ?>
        <div class="list-group">
          <?php foreach (array_slice($diff['removed'], 0, 200) as $c): ?>
            <div class="list-group-item"><?= $renderContact($c) ?></div>
          <?php endforeach; ?>
        </div>
        <?php if ($countRemoved > 200): ?>
          <div class="text-muted small mt-2">… gekürzt (<?= (int)$countRemoved ?> total)</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </details>

  <details class="mb-2">
    <summary class="h6">~ Geänderte Kontakte (<?= (int)$countChanged ?>)</summary>
    <div class="mt-2">
      <?php if (!$diff['changed']): ?>
        <div class="text-muted">Keine.</div>
      <?php else: ?>
        <div class="list-group">
          <?php foreach (array_slice($diff['changed'], 0, 200) as $chg): ?>
            <?php $before = $chg['before']; $after = $chg['after']; ?>
            <div class="list-group-item">
              <div class="row g-2">
                <div class="col-12 col-lg-6">
                  <div class="small text-muted">vorher</div>
                  <?= $renderContact($before) ?>
                </div>
                <div class="col-12 col-lg-6">
                  <div class="small text-muted">nachher</div>
                  <?= $renderContact($after) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($countChanged > 200): ?>
          <div class="text-muted small mt-2">… gekürzt (<?= (int)$countChanged ?> total)</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </details>
</div>

<?php render_footer(); ?>
