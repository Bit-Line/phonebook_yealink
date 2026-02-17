<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_editor();

$mode = isset($_GET['mode']) ? strtolower(trim((string)$_GET['mode'])) : 'numbers';
if ($mode !== 'numbers' && $mode !== 'names') $mode = 'numbers';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$perPage = 10; // groups per page

$err = null;

// Merge action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $primaryId = (int)($_POST['primary_id'] ?? 0);
    $mergeIds = isset($_POST['merge_ids']) && is_array($_POST['merge_ids']) ? $_POST['merge_ids'] : [];
    $fillEmpty = !empty($_POST['fill_empty']);

    $mergeIdsClean = [];
    foreach ($mergeIds as $mid) {
        $mid = (int)$mid;
        if ($mid > 0 && $mid !== $primaryId) $mergeIdsClean[] = $mid;
    }
    $mergeIdsClean = array_values(array_unique($mergeIdsClean));

    if ($primaryId <= 0 || !$mergeIdsClean) {
        flash_set('warning', 'Bitte Primary auswählen und mindestens einen Kontakt zum Mergen markieren.');
        redirect('dedupe.php?mode=' . urlencode($mode));
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $primary = contact_get($primaryId);
            if (!$primary) {
                throw new RuntimeException('Primary Kontakt nicht gefunden.');
            }

            // build set of existing numbers (exact match)
            $existing = [];
            foreach (($primary['numbers'] ?? []) as $n) {
                $num = trim((string)($n['number'] ?? ''));
                if ($num !== '') $existing[$num] = true;
            }

            $maxSort = 0;
            foreach (($primary['numbers'] ?? []) as $n) {
                $so = (int)($n['sort_order'] ?? 0);
                if ($so > $maxSort) $maxSort = $so;
            }

            $stN = $pdo->prepare('INSERT INTO contact_numbers (contact_id, label, number, sort_order) VALUES (?, ?, ?, ?)');
            $stDel = $pdo->prepare('DELETE FROM contacts WHERE id = ?');

            foreach ($mergeIdsClean as $mid) {
                $other = contact_get($mid);
                if (!$other) continue;

                // fill empty department
                if ($fillEmpty) {
                    $pDept = trim((string)($primary['department'] ?? ''));
                    $oDept = trim((string)($other['department'] ?? ''));
                    if ($pDept === '' && $oDept !== '') {
                        $stU = $pdo->prepare('UPDATE contacts SET department = ? WHERE id = ?');
                        $stU->execute([$oDept, $primaryId]);
                        $primary['department'] = $oDept;
                    }
                }

                // merge tags
                $tags = [];
                foreach (($primary['tags'] ?? []) as $t) { $tags[(string)$t] = true; }
                foreach (($other['tags'] ?? []) as $t) { $tags[(string)$t] = true; }
                $tagNames = array_keys($tags);
                if ($tagNames) {
                    $tagIds = tags_ensure_ids($tagNames);
                    $stMap = $pdo->prepare('INSERT IGNORE INTO contact_tags (contact_id, tag_id) VALUES (?, ?)');
                    foreach ($tagIds as $tid) {
                        $stMap->execute([$primaryId, $tid]);
                    }
                    $primary['tags'] = $tagNames;
                }

                // merge numbers (unique by number)
                foreach (($other['numbers'] ?? []) as $n) {
                    $num = trim((string)($n['number'] ?? ''));
                    if ($num === '') continue;
                    if (isset($existing[$num])) continue;
                    $label = trim((string)($n['label'] ?? ''));
                    if ($label === '') $label = 'Merged';
                    $maxSort++;
                    $stN->execute([$primaryId, $label, $num, $maxSort]);
                    $existing[$num] = true;
                }

                // delete merged contact
                $stDel->execute([$mid]);
            }

            // bump updated_at
            $pdo->prepare('UPDATE contacts SET name = name WHERE id = ?')->execute([$primaryId]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        audit_log_event('dedupe_merge', 'contact', $primaryId, ['merged_ids' => $mergeIdsClean, 'mode' => $mode]);
        flash_set('success', 'Merge abgeschlossen.');
        redirect('dedupe.php?mode=' . urlencode($mode));
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$pdo = db();

$total = 0;
$pages = 1;
$offset = 0;
$groups = [];

try {
    if ($mode === 'numbers') {
        $stC = $pdo->query('SELECT COUNT(*) FROM (SELECT number FROM contact_numbers GROUP BY number HAVING COUNT(DISTINCT contact_id) > 1) x');
        $total = (int)$stC->fetchColumn();

        $pages = (int)max(1, (int)ceil($total / $perPage));
        if ($page > $pages) $page = $pages;
        $offset = ($page - 1) * $perPage;

        $st = $pdo->prepare('SELECT number, COUNT(DISTINCT contact_id) AS cnt
                             FROM contact_numbers
                             GROUP BY number
                             HAVING cnt > 1
                             ORDER BY cnt DESC, number ASC
                             LIMIT ? OFFSET ?');
        $st->bindValue(1, $perPage, PDO::PARAM_INT);
        $st->bindValue(2, $offset, PDO::PARAM_INT);
        $st->execute();
        $dups = $st->fetchAll();

        foreach ($dups as $d) {
            $number = (string)$d['number'];
            $cnt = (int)$d['cnt'];

            // MySQL 5.7: with DISTINCT, ORDER BY expressions must also be in the SELECT list.
            // We select c.name for ordering but still fetch only the id column below.
            $stIds = $pdo->prepare('SELECT DISTINCT c.id, c.name
                                    FROM contacts c
                                    JOIN contact_numbers n ON n.contact_id = c.id
                                    WHERE n.number = ?
                                    ORDER BY c.name ASC, c.id ASC');
            $stIds->execute([$number]);
            // fetch only the first column (c.id)
            $ids = $stIds->fetchAll(PDO::FETCH_COLUMN, 0);

            $contacts = [];
            foreach ($ids as $cid) {
                $c = contact_get((int)$cid);
                if ($c) $contacts[] = $c;
            }

            $groups[] = [
                'title' => 'Nummer: ' . $number,
                'key' => $number,
                'count' => $cnt,
                'contacts' => $contacts,
            ];
        }
    } else {
        $stC = $pdo->query('SELECT COUNT(*) FROM (SELECT LOWER(name) AS lname, COALESCE(department, "") AS dept
                                FROM contacts
                                GROUP BY lname, dept
                                HAVING COUNT(*) > 1) x');
        $total = (int)$stC->fetchColumn();

        $pages = (int)max(1, (int)ceil($total / $perPage));
        if ($page > $pages) $page = $pages;
        $offset = ($page - 1) * $perPage;

        $st = $pdo->prepare('SELECT LOWER(name) AS lname, COALESCE(department, "") AS dept, COUNT(*) AS cnt
                             FROM contacts
                             GROUP BY lname, dept
                             HAVING cnt > 1
                             ORDER BY cnt DESC, lname ASC
                             LIMIT ? OFFSET ?');
        $st->bindValue(1, $perPage, PDO::PARAM_INT);
        $st->bindValue(2, $offset, PDO::PARAM_INT);
        $st->execute();
        $dups = $st->fetchAll();

        foreach ($dups as $d) {
            $lname = (string)$d['lname'];
            $dept = (string)$d['dept'];
            $cnt = (int)$d['cnt'];

            $stIds = $pdo->prepare('SELECT id FROM contacts WHERE LOWER(name) = ? AND COALESCE(department, "") = ? ORDER BY name');
            $stIds->execute([$lname, $dept]);
            $ids = $stIds->fetchAll(PDO::FETCH_COLUMN);

            $contacts = [];
            foreach ($ids as $cid) {
                $c = contact_get((int)$cid);
                if ($c) $contacts[] = $c;
            }

            $title = 'Name: ' . $lname;
            if ($dept !== '') $title .= ' (' . $dept . ')';

            $groups[] = [
                'title' => $title,
                'key' => $lname . '|' . $dept,
                'count' => $cnt,
                'contacts' => $contacts,
            ];
        }
    }
} catch (Throwable $e) {
    $err = $e->getMessage();
    $groups = [];
}

render_header('Dedupe');

$renderNums = function(array $c): string {
    $nums = isset($c['numbers']) && is_array($c['numbers']) ? $c['numbers'] : [];
    if (!$nums) return '<span class="text-muted">—</span>';
    $parts = [];
    foreach ($nums as $n) {
        $lab = (string)($n['label'] ?? '');
        $num = (string)($n['number'] ?? '');
        if ($num === '') continue;
        $parts[] = h($lab) . ': <code>' . h($num) . '</code>';
    }
    return implode(' • ', $parts);
};
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Dedupe-Assistent</h1>
  <div class="btn-group">
    <a class="btn btn-sm <?= $mode === 'numbers' ? 'btn-primary' : 'btn-outline-primary' ?>" href="dedupe.php?mode=numbers">Doppelte Nummern</a>
    <a class="btn btn-sm <?= $mode === 'names' ? 'btn-primary' : 'btn-outline-primary' ?>" href="dedupe.php?mode=names">Doppelte Namen</a>
  </div>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger"><?= h($err) ?></div>
<?php endif; ?>

<div class="alert alert-info">
  Wähle pro Gruppe einen <strong>Primary</strong>-Kontakt aus. Die ausgewählten Kontakte werden in den Primary gemerged (Nummern & Tags werden übernommen),
  anschließend werden die gemergten Kontakte gelöscht.
</div>

<?php if (!$groups): ?>
  <div class="text-muted">Keine Duplikate gefunden.</div>
<?php endif; ?>

<?php foreach ($groups as $g): ?>
  <?php $contacts = $g['contacts']; ?>
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <h2 class="h6 m-0"><?= h($g['title']) ?></h2>
        <span class="badge text-bg-secondary"><?= (int)$g['count'] ?></span>
      </div>

      <?php if (count($contacts) < 2): ?>
        <div class="text-muted mt-2">(Gruppe zu klein)</div>
      <?php else: ?>
        <form method="post" class="mt-3">
          <?= csrf_field() ?>
          <input type="hidden" name="mode" value="<?= h($mode) ?>">

          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Primary</th>
                  <th>Mergen</th>
                  <th>Name</th>
                  <th>Abteilung</th>
                  <th>Tags</th>
                  <th>Nummern</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($contacts as $idx => $c): ?>
                  <tr>
                    <td><input class="form-check-input" type="radio" name="primary_id" value="<?= (int)$c['id'] ?>" <?= $idx === 0 ? 'checked' : '' ?>></td>
                    <td><input class="form-check-input" type="checkbox" name="merge_ids[]" value="<?= (int)$c['id'] ?>" <?= $idx === 0 ? '' : 'checked' ?>></td>
                    <td class="fw-semibold"><?= h((string)$c['name']) ?></td>
                    <td><?= h((string)($c['department'] !== '' ? $c['department'] : '—')) ?></td>
                    <td>
                      <?php if (!empty($c['tags'])): ?>
                        <?php foreach ($c['tags'] as $t): ?>
                          <span class="badge text-bg-secondary me-1"><?= h((string)$t) ?></span>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="small"><?= $renderNums($c) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="d-flex flex-wrap gap-2 align-items-center">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="fill_empty" id="fill_empty_<?= h(md5($g['key'])) ?>" checked>
              <label class="form-check-label" for="fill_empty_<?= h(md5($g['key'])) ?>">Leere Felder im Primary auffüllen (z.B. Abteilung)</label>
            </div>

            <div class="ms-auto">
              <button class="btn btn-sm btn-warning" type="submit" onclick="return confirm('Merge wirklich ausführen?');"><i class="bi bi-union"></i> Merge</button>
            </div>
          </div>
        </form>
      <?php endif; ?>

    </div>
  </div>
<?php endforeach; ?>

<?php if ($pages > 1): ?>
  <?php
    $mkUrl = function(int $p) use ($mode): string {
        return 'dedupe.php?' . http_build_query(['mode' => $mode, 'page' => $p]);
    };
    $window = 2;
    $start = max(1, $page - $window);
    $end = min($pages, $page + $window);
  ?>
  <nav aria-label="Dedupe Seiten" class="mt-3">
    <ul class="pagination justify-content-center flex-wrap">
      <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>">
        <a class="page-link" href="<?= h($mkUrl(max(1, $page - 1))) ?>" aria-label="Vorherige"><span aria-hidden="true">&laquo;</span></a>
      </li>
      <?php for ($p = $start; $p <= $end; $p++): ?>
        <li class="page-item <?= ($p === $page ? 'active' : '') ?>">
          <a class="page-link" href="<?= h($mkUrl($p)) ?>"><?= (int)$p ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?= ($page >= $pages ? 'disabled' : '') ?>">
        <a class="page-link" href="<?= h($mkUrl(min($pages, $page + 1))) ?>" aria-label="Nächste"><span aria-hidden="true">&raquo;</span></a>
      </li>
    </ul>
    <div class="text-center text-muted small"><?= (int)$total ?> Gruppen • Seite <?= (int)$page ?> von <?= (int)$pages ?></div>
  </nav>
<?php endif; ?>

<?php render_footer(); ?>
