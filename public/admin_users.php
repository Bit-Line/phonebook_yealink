<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_admin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 25;

$total = 0;
$pages = 1;
$offset = 0;
$users = [];

try {
    $total = users_count();
    $pages = (int)max(1, (int)ceil($total / $perPage));
    if ($page > $pages) $page = $pages;
    $offset = ($page - 1) * $perPage;
    $users = users_list_page($perPage, $offset);
} catch (Throwable $e) {
    flash_set('danger', 'DB-Fehler: ' . $e->getMessage());
    $users = [];
}

$currentId = (int)($_SESSION['user_id'] ?? 0);
$adminCount = 0;
try { $adminCount = admins_count(); } catch (Throwable $e) { $adminCount = 0; }

render_header('Benutzer');
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Benutzer</h1>
  <a class="btn btn-primary" href="admin_user_edit.php"><i class="bi bi-plus-lg"></i> Neuer Benutzer</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>Username</th>
            <th>Rolle</th>
            <th>Erstellt</th>
            <th class="text-end">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$users): ?>
            <tr><td colspan="4" class="text-muted">Keine Benutzer.</td></tr>
          <?php endif; ?>

          <?php foreach ($users as $u): ?>
            <tr>
              <td class="fw-semibold"><?= h((string)$u['username']) ?><?php if ((int)$u['id'] === $currentId): ?> <span class="badge text-bg-info">du</span><?php endif; ?></td>
              <td><code><?= h((string)$u['role']) ?></code></td>
              <td class="text-muted"><?= h((string)$u['created_at']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="admin_user_edit.php?id=<?= (int)$u['id'] ?>"><i class="bi bi-pencil"></i></a>

                <?php
                  $isSelf = ((int)$u['id'] === $currentId);
                  $isAdmin = ((string)$u['role'] === 'admin');
                  $disableDelete = $isSelf || ($isAdmin && $adminCount <= 1);
                ?>
                <form method="post" action="admin_user_delete.php" class="d-inline" onsubmit="return confirm('Benutzer wirklich löschen?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit" <?= $disableDelete ? 'disabled' : '' ?> title="<?= $disableDelete ? 'Nicht möglich (Self/letzter Admin)' : 'Löschen' ?>"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <?php
        $mkUrl = function(int $p): string {
            return 'admin_users.php?' . http_build_query(['page' => $p]);
        };
        $window = 2;
        $start = max(1, $page - $window);
        $end = min($pages, $page + $window);
      ?>
      <nav aria-label="Benutzerseiten" class="mt-3">
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
          <?= (int)$total ?> Benutzer • Seite <?= (int)$page ?> von <?= (int)$pages ?>
        </div>
      </nav>
    <?php endif; ?>

  </div>
</div>

<?php render_footer(); ?>
