<?php
declare(strict_types=1);

function revision_count(): int {
    $pdo = db();
    return (int)$pdo->query('SELECT COUNT(*) FROM revisions')->fetchColumn();
}

function revision_list_page(int $limit, int $offset): array {
    $pdo = db();
    if ($limit < 1) $limit = 25;
    if ($offset < 0) $offset = 0;

    $st = $pdo->prepare(
        'SELECT id, revision_number, comment, created_at, active, format, xml_sha256,
                (snapshot_json IS NOT NULL AND snapshot_json <> "") AS has_snapshot
         FROM revisions
         ORDER BY revision_number DESC
         LIMIT ? OFFSET ?'
    );
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->bindValue(2, $offset, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function revision_get(int $id): ?array {
    $pdo = db();
    $st = $pdo->prepare('SELECT id, revision_number, comment, created_at, active, format, xml, xml_sha256, snapshot_json FROM revisions WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function revision_get_active(): ?array {
    $pdo = db();
    $st = $pdo->query('SELECT id, revision_number, comment, created_at, active, format, xml_sha256, (snapshot_json IS NOT NULL AND snapshot_json <> "") AS has_snapshot FROM revisions WHERE active = 1 ORDER BY revision_number DESC LIMIT 1');
    $r = $st->fetch();
    return $r ?: null;
}

function revision_get_active_full(): ?array {
    $pdo = db();
    $st = $pdo->query('SELECT id, revision_number, comment, created_at, active, format, xml, xml_sha256, snapshot_json FROM revisions WHERE active = 1 ORDER BY revision_number DESC LIMIT 1');
    $r = $st->fetch();
    return $r ?: null;
}

function revision_get_active_xml(): ?string {
    $pdo = db();
    $st = $pdo->query('SELECT xml FROM revisions WHERE active = 1 ORDER BY revision_number DESC LIMIT 1');
    $r = $st->fetchColumn();
    if ($r === false) return null;
    return (string)$r;
}

/**
 * Create a new revision from the current working set.
 * IMPORTANT: This is the only way revisions are created (no auto-revision).
 */
function revision_create(?string $comment = null): int {
    $comment = trim((string)$comment);
    if ($comment === '') $comment = null;

    $contactsDb = contacts_fetch_all_for_export();
    $snapshotContacts = snapshot_from_contacts($contactsDb);
    $snapshotJson = snapshot_encode_contacts($snapshotContacts);

    $xml = phonebook_generate_xml($contactsDb);
    $sha = hash('sha256', $xml);
    $format = defined('PHONEBOOK_FORMAT') ? (string)PHONEBOOK_FORMAT : 'yealink';

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $next = (int)$pdo->query('SELECT COALESCE(MAX(revision_number), 0) + 1 FROM revisions')->fetchColumn();

        // deactivate old
        $pdo->exec('UPDATE revisions SET active = 0 WHERE active = 1');

        $st = $pdo->prepare('INSERT INTO revisions (revision_number, comment, active, format, xml, xml_sha256, snapshot_json) VALUES (?, ?, 1, ?, ?, ?, ?)');
        $st->execute([$next, $comment, $format, $xml, $sha, $snapshotJson]);

        $pdo->commit();

        audit_log_event('revision_publish', 'revision', $next, ['comment' => $comment]);
        return $next;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function revision_activate(int $id): void {
    $pdo = db();
    $stGet = $pdo->prepare('SELECT revision_number FROM revisions WHERE id = ?');
    $stGet->execute([$id]);
    $revNo = $stGet->fetchColumn();
    if ($revNo === false) throw new RuntimeException('Revision nicht gefunden.');

    $pdo->beginTransaction();
    try {
        $pdo->exec('UPDATE revisions SET active = 0 WHERE active = 1');
        $st = $pdo->prepare('UPDATE revisions SET active = 1 WHERE id = ?');
        $st->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    audit_log_event('revision_activate', 'revision', (int)$revNo, null);
}

/**
 * Delete a revision by id.
 * Safety: active revision cannot be deleted.
 */
function revision_delete(int $id): void {
    $pdo = db();
    $st = $pdo->prepare('SELECT active, revision_number FROM revisions WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        throw new RuntimeException('Revision nicht gefunden.');
    }
    if ((int)$row['active'] === 1) {
        throw new RuntimeException('Aktive Revision kann nicht gelöscht werden.');
    }

    $revNo = (int)$row['revision_number'];

    $stDel = $pdo->prepare('DELETE FROM revisions WHERE id = ?');
    $stDel->execute([$id]);

    audit_log_event('revision_delete', 'revision', $revNo, null);
}

/**
 * Get snapshot contacts for a revision.
 *
 * - prefers snapshot_json
 * - fallback: parse XML (department + up to 3 numbers, no tags)
 */
function revision_snapshot_contacts(array $rev): array {
    $format = isset($rev['format']) ? (string)$rev['format'] : 'yealink';

    $contacts = [];
    $snapJson = isset($rev['snapshot_json']) ? $rev['snapshot_json'] : null;
    if ($snapJson !== null && trim((string)$snapJson) !== '') {
        $contacts = snapshot_decode_contacts((string)$snapJson);
        if (is_array($contacts) && $contacts) {
            return $contacts;
        }
    }

    // Fallback
    $xml = isset($rev['xml']) ? (string)$rev['xml'] : '';
    if ($xml !== '') {
        return snapshot_from_revision_xml($xml, $format);
    }

    return [];
}

/**
 * Rollback: restore DB contacts (working set) to a given revision snapshot.
 * Does NOT publish a new revision automatically.
 */
function revision_rollback_to_contacts(int $revisionId): int {
    $rev = revision_get($revisionId);
    if (!$rev) throw new RuntimeException('Revision nicht gefunden.');

    $revNo = (int)$rev['revision_number'];
    $contacts = revision_snapshot_contacts($rev);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // wipe contacts (cascades numbers + contact_tags)
        $pdo->exec('DELETE FROM contacts');
        // clean tags to prevent stale entries
        $pdo->exec('DELETE FROM tags');

        $stC = $pdo->prepare('INSERT INTO contacts (name, department) VALUES (?, ?)');
        $stN = $pdo->prepare('INSERT INTO contact_numbers (contact_id, label, number, sort_order) VALUES (?, ?, ?, ?)');
        $stMap = $pdo->prepare('INSERT IGNORE INTO contact_tags (contact_id, tag_id) VALUES (?, ?)');

        foreach ($contacts as $c) {
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

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    audit_log_event('revision_rollback', 'revision', $revNo, ['restored_contacts' => count($contacts)]);
    return $revNo;
}

