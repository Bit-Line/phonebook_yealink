<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$dept = isset($_GET['dept']) ? trim((string)$_GET['dept']) : '';
$tag = isset($_GET['tag']) ? trim((string)$_GET['tag']) : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 20;

$total = 0;
$pages = 1;
$offset = 0;
$contacts = [];

try {
    $total = contacts_count_filtered($q, $dept, $tag);
    $pages = (int)max(1, (int)ceil($total / $perPage));
    if ($page > $pages) $page = $pages;
    $offset = ($page - 1) * $perPage;
    $contacts = contacts_list_page_filtered($q, $dept, $tag, $perPage, $offset);
} catch (Throwable $e) {
    flash_set('danger', 'DB-Fehler: ' . $e->getMessage());
    $contacts = [];
}

$departments = [];
$tags = [];
try { $departments = departments_list_with_counts(); } catch (Throwable $e) { $departments = []; }
try { $tags = tags_list_with_counts(); } catch (Throwable $e) { $tags = []; }

$canEdit = has_min_role('editor');

$baseParams = [];
if ($q !== '') $baseParams['q'] = $q;
if ($dept !== '') $baseParams['dept'] = $dept;
if ($tag !== '') $baseParams['tag'] = $tag;

render_header('Kontakte');
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Kontakte</h1>

  <div class="d-flex gap-2 align-items-center">
    <form class="d-flex gap-2" method="get">
      <input class="form-control" type="search" name="q" placeholder="Suche (Name, Abteilung, Nummer)" value="<?= h($q) ?>" style="min-width: 260px;">
      <?php if ($dept !== ''): ?><input type="hidden" name="dept" value="<?= h($dept) ?>"><?php endif; ?>
      <?php if ($tag !== ''): ?><input type="hidden" name="tag" value="<?= h($tag) ?>"><?php endif; ?>
      <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
    </form>

    <?php if ($canEdit): ?>
      <a class="btn btn-primary" href="contact_edit.php"><i class="bi bi-plus-lg"></i> Neuer Kontakt</a>
    <?php endif; ?>
  </div>
</div>

<div class="alert alert-info">
  Änderungen an Kontakten beeinflussen <strong>nicht</strong> automatisch die aktive Telefonbuch-Version.
  Zum Ausrollen („propagate“) bitte unter <a href="revisions.php" class="alert-link">Revisionen</a> manuell eine neue Revision erstellen.
</div>

<?php if ($dept !== '' || $tag !== ''): ?>
  <div class="mb-2">
    <span class="text-muted">Filter:</span>
    <?php if ($dept !== ''): ?>
      <span class="badge text-bg-secondary">Abteilung: <?= h($dept === '__none__' ? '(ohne)' : $dept) ?></span>
    <?php endif; ?>
    <?php if ($tag !== ''): ?>
      <span class="badge text-bg-secondary">Tag: <?= h($tag) ?></span>
    <?php endif; ?>
    <a class="btn btn-sm btn-outline-secondary ms-2" href="contacts.php?<?= h(http_build_query(['q' => $q])) ?>"><i class="bi bi-x"></i> Filter löschen</a>
  </div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="mb-2">
      <div class="small text-muted mb-1">Abteilungen</div>
      <div class="d-flex flex-wrap gap-2">
        <?php
          $mkDeptUrl = function($d) use ($q, $tag) {
              $p = [];
              if ($q !== '') $p['q'] = $q;
              if ($tag !== '') $p['tag'] = $tag;
              if ($d !== '') $p['dept'] = $d;
              return 'contacts.php?' . http_build_query($p);
          };
        ?>
        <a class="btn btn-sm <?= ($dept === '' ? 'btn-primary' : 'btn-outline-primary') ?>" href="<?= h($mkDeptUrl('')) ?>">Alle</a>
        <?php foreach ($departments as $d): ?>
          <?php
            $name = (string)($d['department'] ?? '');
            $cnt = (int)($d['cnt'] ?? 0);
            $value = ($name === '' ? '__none__' : $name);
            $label = ($name === '' ? '(ohne Abteilung)' : $name);
          ?>
          <a class="btn btn-sm <?= ($dept === $value ? 'btn-primary' : 'btn-outline-primary') ?>" href="<?= h($mkDeptUrl($value)) ?>">
            <?= h($label) ?> <span class="badge text-bg-light ms-1"><?= (int)$cnt ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <div class="small text-muted mb-1">Tags</div>
      <div class="d-flex flex-wrap gap-2">
        <?php
          $mkTagUrl = function($t) use ($q, $dept) {
              $p = [];
              if ($q !== '') $p['q'] = $q;
              if ($dept !== '') $p['dept'] = $dept;
              if ($t !== '') $p['tag'] = $t;
              return 'contacts.php?' . http_build_query($p);
          };
        ?>
        <a class="btn btn-sm <?= ($tag === '' ? 'btn-success' : 'btn-outline-success') ?>" href="<?= h($mkTagUrl('')) ?>">Alle</a>
        <?php foreach ($tags as $t): ?>
          <?php
            $name = (string)($t['name'] ?? '');
            $cnt = (int)($t['cnt'] ?? 0);
          ?>
          <a class="btn btn-sm <?= ($tag === $name ? 'btn-success' : 'btn-outline-success') ?>" href="<?= h($mkTagUrl($name)) ?>">
            <?= h($name) ?> <span class="badge text-bg-light ms-1"><?= (int)$cnt ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>Name</th>
            <th>Abteilung</th>
            <th>Nummern</th>
            <th class="text-end">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$contacts): ?>
            <tr><td colspan="4" class="text-muted">Keine Kontakte.</td></tr>
          <?php endif; ?>

          <?php foreach ($contacts as $c): ?>
            <tr>
              <td class="fw-semibold"><?= h($c['name']) ?></td>
              <td>
                <div><?= h($c['department'] !== '' ? $c['department'] : '—') ?></div>
                <?php if (!empty($c['tags'])): ?>
                  <div class="mt-1">
                    <?php foreach ($c['tags'] as $tg): ?>
                      <span class="badge text-bg-secondary me-1"><?= h((string)$tg) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($c['numbers'])): ?>
                  <ul class="list-unstyled m-0">
                    <?php foreach ($c['numbers'] as $n): ?>
                      <li><span class="text-muted"><?= h($n['label']) ?>:</span> <code><?= h($n['number']) ?></code></li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <?php if ($canEdit): ?>
                  <a class="btn btn-sm btn-outline-primary" href="contact_edit.php?id=<?= (int)$c['id'] ?>" title="Bearbeiten"><i class="bi bi-pencil"></i></a>
                  <form method="post" action="contact_delete.php" class="d-inline" onsubmit="return confirm('Kontakt löschen?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Löschen"><i class="bi bi-trash"></i></button>
                  </form>
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
        $mkUrl = function(int $p) use ($baseParams): string {
            return 'contacts.php?' . http_build_query(array_merge($baseParams, ['page' => $p]));
        };
        $window = 2;
        $start = max(1, $page - $window);
        $end = min($pages, $page + $window);
      ?>
      <nav aria-label="Kontaktseiten" class="mt-3">
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
          <?= (int)$total ?> Kontakte • Seite <?= (int)$page ?> von <?= (int)$pages ?>
        </div>
      </nav>
    <?php endif; ?>

  </div>
</div>

<?php render_footer(); ?>
