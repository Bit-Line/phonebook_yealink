<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_admin();

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $restoreContacts = !empty($_POST['restore_contacts']);
    $restoreRevisions = !empty($_POST['restore_revisions']);
    $restoreSettings = !empty($_POST['restore_settings']);
    $restoreUsers = !empty($_POST['restore_users']);
    $wipe = !empty($_POST['wipe']);

    // Merge is not supported for contacts/revisions/users
    if (!$wipe && ($restoreContacts || $restoreRevisions || $restoreUsers)) {
        $err = 'Merge ist nicht implementiert. Bitte "Vorhandene Daten vorher löschen" (Replace) aktivieren.';
    } elseif (!$restoreContacts && !$restoreRevisions && !$restoreSettings && !$restoreUsers) {
        $err = 'Bitte mindestens einen Bereich zum Restore auswählen.';
    } elseif (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $err = 'Backup-Datei fehlt oder Upload fehlgeschlagen.';
    } else {
        try {
            $json = file_get_contents($_FILES['backup_file']['tmp_name']);
            if ($json === false || trim($json) === '') {
                throw new RuntimeException('Backup-Datei ist leer.');
            }
            $payload = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
                throw new RuntimeException('JSON konnte nicht gelesen werden.');
            }

            if (!isset($payload['data']) || !is_array($payload['data'])) {
                throw new RuntimeException('Ungültiges Backup-Format (data fehlt).');
            }

            $data = $payload['data'];

            $pdo = db();
            $pdo->beginTransaction();
            try {
                if ($restoreUsers) {
                    $users = isset($data['users']) && is_array($data['users']) ? $data['users'] : [];
                    $hasAdmin = false;
                    foreach ($users as $u) {
                        if (!is_array($u)) continue;
                        if (normalize_role((string)($u['role'] ?? '')) === 'admin') {
                            $hasAdmin = true;
                            break;
                        }
                    }
                    if (!$hasAdmin) {
                        throw new RuntimeException('Backup enthält keinen Admin-User. Restore abgebrochen (Lockout-Schutz).');
                    }
                    if ($wipe) {
                        $pdo->exec('DELETE FROM users');
                    }
                    $stU = $pdo->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)');
                    foreach ($users as $u) {
                        if (!is_array($u)) continue;
                        $un = trim((string)($u['username'] ?? ''));
                        if ($un === '') continue;
                        $ph = (string)($u['password_hash'] ?? '');
                        $role = normalize_role((string)($u['role'] ?? 'viewer'));
                        $created = (string)($u['created_at'] ?? date('Y-m-d H:i:s'));
                        $stU->execute([$un, $ph, $role, $created]);
                    }
                }

                if ($restoreSettings) {
                    $settings = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : [];
                    if ($wipe) {
                        $pdo->exec('DELETE FROM settings');
                    }
                    $stS = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
                    foreach ($settings as $s) {
                        if (!is_array($s)) continue;
                        $k = (string)($s['key'] ?? '');
                        if ($k === '') continue;
                        $v = isset($s['value']) ? (string)$s['value'] : null;
                        $stS->execute([$k, $v]);
                    }
                }

                if ($restoreContacts) {
                    $contacts = isset($data['contacts']) && is_array($data['contacts']) ? $data['contacts'] : [];
                    if ($wipe) {
                        $pdo->exec('DELETE FROM contacts');
                        $pdo->exec('DELETE FROM tags');
                    }

                    // Restore snapshot contacts -> contacts + numbers + tags
                    $stC = $pdo->prepare('INSERT INTO contacts (name, department) VALUES (?, ?)');
                    $stN = $pdo->prepare('INSERT INTO contact_numbers (contact_id, label, number, sort_order) VALUES (?, ?, ?, ?)');
                    $stMap = $pdo->prepare('INSERT IGNORE INTO contact_tags (contact_id, tag_id) VALUES (?, ?)');

                    foreach ($contacts as $c) {
                        if (!is_array($c)) continue;
                        $name = trim((string)($c['name'] ?? ''));
                        if ($name === '') continue;
                        $dept = trim((string)($c['department'] ?? ''));

                        $stC->execute([$name, ($dept !== '' ? $dept : null)]);
                        $cid = (int)$pdo->lastInsertId();

                        $nums = isset($c['numbers']) && is_array($c['numbers']) ? $c['numbers'] : [];
                        $sort = 1;
                        foreach ($nums as $n) {
                            if (!is_array($n)) continue;
                            $label = trim((string)($n['label'] ?? ''));
                            $number = trim((string)($n['number'] ?? ''));
                            if ($number === '') continue;
                            if ($label === '') $label = 'Number ' . $sort;
                            $stN->execute([$cid, $label, $number, $sort]);
                            $sort++;
                        }

                        $tags = isset($c['tags']) && is_array($c['tags']) ? $c['tags'] : [];
                        $tagNames = [];
                        foreach ($tags as $t) {
                            $t = trim((string)$t);
                            if ($t === '') continue;
                            $tagNames[] = $t;
                        }
                        if ($tagNames) {
                            $tagIds = tags_ensure_ids($tagNames);
                            foreach ($tagIds as $tid) {
                                $stMap->execute([$cid, $tid]);
                            }
                        }
                    }
                }

                if ($restoreRevisions) {
                    $revs = isset($data['revisions']) && is_array($data['revisions']) ? $data['revisions'] : [];
                    if ($wipe) {
                        $pdo->exec('DELETE FROM revisions');
                    }

                    $activeNo = null;
                    foreach ($revs as $r) {
                        if (!is_array($r)) continue;
                        if (!empty($r['active'])) {
                            $rn = (int)($r['revision_number'] ?? 0);
                            if ($rn > 0) {
                                if ($activeNo === null || $rn > $activeNo) $activeNo = $rn;
                            }
                        }
                    }

                    $stR = $pdo->prepare('INSERT INTO revisions (revision_number, comment, created_at, active, format, xml, xml_sha256, snapshot_json) VALUES (?, ?, ?, 0, ?, ?, ?, ?)');
                    foreach ($revs as $r) {
                        if (!is_array($r)) continue;
                        $rn = (int)($r['revision_number'] ?? 0);
                        if ($rn <= 0) continue;
                        $comment = isset($r['comment']) ? (string)$r['comment'] : null;
                        if ($comment !== null && trim($comment) === '') $comment = null;
                        $created = (string)($r['created_at'] ?? date('Y-m-d H:i:s'));
                        $format = (string)($r['format'] ?? (defined('PHONEBOOK_FORMAT') ? PHONEBOOK_FORMAT : 'yealink'));
                        $xml = (string)($r['xml'] ?? '');
                        $sha = (string)($r['xml_sha256'] ?? hash('sha256', $xml));
                        $snap = isset($r['snapshot_json']) ? (string)$r['snapshot_json'] : null;
                        $stR->execute([$rn, $comment, $created, $format, $xml, $sha, $snap]);
                    }

                    if ($activeNo !== null) {
                        $stA = $pdo->prepare('UPDATE revisions SET active = 1 WHERE revision_number = ?');
                        $stA->execute([(int)$activeNo]);
                    }
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            audit_log_event('backup_restore', 'backup', null, [
                'restore_contacts' => $restoreContacts,
                'restore_revisions' => $restoreRevisions,
                'restore_settings' => $restoreSettings,
                'restore_users' => $restoreUsers,
                'wipe' => $wipe,
            ]);

            flash_set('success', 'Restore abgeschlossen.');

            if ($restoreUsers) {
                // session might be invalid now
                logout();
                flash_set('success', 'Restore abgeschlossen. Bitte neu einloggen.');
                redirect('login.php');
            }

            redirect('admin_backup.php');
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
}

render_header('Backup/Restore');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Backup/Restore</h1>
  <a class="btn btn-outline-primary" href="admin_backup_download.php"><i class="bi bi-download"></i> Backup herunterladen (JSON)</a>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger"><?= h($err) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12 col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5">Restore</h2>
        <p class="text-muted">Lade ein zuvor erzeugtes JSON-Backup hoch und stelle ausgewählte Bereiche wieder her.</p>

        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label">Backup-Datei (JSON)</label>
            <input class="form-control" type="file" name="backup_file" accept="application/json,.json" required>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-12 col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="restore_contacts" id="rc" checked>
                <label class="form-check-label" for="rc">Kontakte + Tags</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="restore_revisions" id="rr" checked>
                <label class="form-check-label" for="rr">Revisionen (XML/Snapshots)</label>
              </div>
            </div>
            <div class="col-12 col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="restore_settings" id="rs" checked>
                <label class="form-check-label" for="rs">Einstellungen</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="restore_users" id="ru">
                <label class="form-check-label" for="ru">Benutzer (Achtung: du wirst ausgeloggt)</label>
              </div>
            </div>
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="wipe" id="wipe" checked>
            <label class="form-check-label" for="wipe">Vorhandene Daten vorher löschen (Replace)</label>
            <div class="form-text">Empfohlen. Merge ist nicht implementiert.</div>
          </div>

          <button class="btn btn-danger" type="submit" onclick="return confirm('Restore wirklich ausführen?');"><i class="bi bi-arrow-clockwise"></i> Restore ausführen</button>
        </form>

      </div>
    </div>
  </div>

  <div class="col-12 col-lg-5">
    <div class="alert alert-warning">
      <strong>Hinweis:</strong> Restore überschreibt je nach Auswahl deine aktuellen Daten.
      Nutze vorher ein Backup.
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h5">Was ist im Backup enthalten?</h2>
        <ul class="mb-0">
          <li>Kontakte, Nummern, Tags (als Snapshot)</li>
          <li>Revisionen inkl. XML und Snapshots</li>
          <li>Einstellungen (z.B. IP-Allowlist)</li>
          <li>Benutzer (optional beim Restore)</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php render_footer(); ?>
