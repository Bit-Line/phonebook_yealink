<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_admin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 50;

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

$pdo = db();

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(u.username LIKE ? OR a.action LIKE ? OR a.entity_type LIKE ? OR a.entity_id LIKE ? OR a.ip LIKE ?)
        ';
    $like = '%' . $q . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}
if ($action !== '') {
    $where[] = 'a.action = ?';
    $params[] = $action;
}

$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = 0;
$pages = 1;
$offset = 0;
$rows = [];

try {
    $stC = $pdo->prepare('SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ' . $wsql);
    $stC->execute($params);
    $total = (int)$stC->fetchColumn();

    $pages = (int)max(1, (int)ceil($total / $perPage));
    if ($page > $pages) $page = $pages;
    $offset = ($page - 1) * $perPage;

    $st = $pdo->prepare('SELECT a.id, a.created_at, a.user_id, u.username, u.role, a.action, a.entity_type, a.entity_id, a.details, a.ip
                         FROM audit_log a
                         LEFT JOIN users u ON u.id = a.user_id
                         ' . $wsql . '
                         ORDER BY a.id DESC
                         LIMIT ? OFFSET ?');

    $i = 1;
    foreach ($params as $p) {
        $st->bindValue($i++, $p, PDO::PARAM_STR);
    }
    $st->bindValue($i++, $perPage, PDO::PARAM_INT);
    $st->bindValue($i++, $offset, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();
} catch (Throwable $e) {
    flash_set('danger', 'DB-Fehler: ' . $e->getMessage());
    $rows = [];
}

// collect actions for filter dropdown
$actions = [];
try {
    $stA = $pdo->query('SELECT DISTINCT action FROM audit_log ORDER BY action');
    while ($a = $stA->fetchColumn()) {
        $actions[] = (string)$a;
    }
} catch (Throwable $e) {
    $actions = [];
}

render_header('Audit-Log');
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Audit-Log</h1>

  <form class="d-flex gap-2" method="get">
    <input class="form-control" name="q" placeholder="Suche (User/Action/Entity/IP)" value="<?= h($q) ?>">
    <select class="form-select" name="action" style="max-width: 220px;">
      <option value="">Alle Aktionen</option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= h($a) ?>" <?= ($action === $a ? 'selected' : '') ?>><?= h($a) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
  </form>
</div>

<div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead>
      <tr>
        <th>Zeit</th>
        <th>User</th>
        <th>Action</th>
        <th>Entity</th>
        <th>IP</th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="text-muted">Keine Einträge.</td></tr>
      <?php endif; ?>

      <?php foreach ($rows as $r): ?>
        <?php
          $details = (string)($r['details'] ?? '');
          $pretty = null;
          $parsed = audit_parse_details($details);
          if ($parsed !== null) {
              $pretty = json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          }
          if ($pretty === null) {
              $pretty = $details;
          }
          if ($pretty !== null && strlen($pretty) > 300) {
              $prettyShort = substr($pretty, 0, 300) . '…';
          } else {
              $prettyShort = $pretty;
          }
        ?>
        <tr>
          <td class="text-muted" style="white-space:nowrap;"><?= h((string)$r['created_at']) ?></td>
          <td>
            <?php if (!empty($r['username'])): ?>
              <span class="fw-semibold"><?= h((string)$r['username']) ?></span>
              <span class="badge text-bg-secondary ms-1"><?= h((string)($r['role'] ?? '')) ?></span>
            <?php else: ?>
              <span class="text-muted">(system)</span>
            <?php endif; ?>
          </td>
          <td><code><?= h((string)$r['action']) ?></code></td>
          <td>
            <?php if (!empty($r['entity_type'])): ?>
              <span class="text-muted"><?= h((string)$r['entity_type']) ?></span>
              <?php if (!empty($r['entity_id'])): ?>
                <span class="text-muted">#<?= h((string)$r['entity_id']) ?></span>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= h((string)($r['ip'] ?? '')) ?></td>
          <td>
            <?php if ($prettyShort): ?>
              <details>
                <summary class="text-muted">anzeigen</summary>
                <pre class="mb-0" style="max-width: 480px; white-space: pre-wrap; word-break: break-word;"><?= h((string)$pretty) ?></pre>
              </details>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
  <?php
    $baseParams = [];
    if ($q !== '') $baseParams['q'] = $q;
    if ($action !== '') $baseParams['action'] = $action;

    $mkUrl = function(int $p) use ($baseParams): string {
        return 'admin_audit.php?' . http_build_query(array_merge($baseParams, ['page' => $p]));
    };

    $window = 2;
    $start = max(1, $page - $window);
    $end = min($pages, $page + $window);
  ?>

  <nav aria-label="Auditseiten" class="mt-3">
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

    <div class="text-center text-muted small">
      <?= (int)$total ?> Einträge • Seite <?= (int)$page ?> von <?= (int)$pages ?>
    </div>
  </nav>
<?php endif; ?>

<?php render_footer(); ?>
