<?php
declare(strict_types=1);

/**
 * Contacts Repository
 *
 * Supports:
 * - multiple numbers per contact
 * - optional department
 * - optional tags (many-to-many)
 * - search + filters + pagination
 */

/**
 * Backwards compatible convenience function (no pagination). Avoid using for very large datasets.
 */
function contacts_list(?string $q = null): array {
    return contacts_list_page_filtered($q, null, null, 1000000, 0);
}

/**
 * Count contacts with optional search and filters.
 */
function contacts_count_filtered(?string $q, ?string $department, ?string $tag): int {
    $pdo = db();

    $q = $q !== null ? trim($q) : '';
    $department = $department !== null ? trim($department) : '';
    $tag = $tag !== null ? trim($tag) : '';

    $needNumberJoin = ($q !== '');
    $needTagJoin = ($q !== '' || $tag !== '');

    $sql = 'SELECT COUNT(DISTINCT c.id) FROM contacts c';
    if ($needNumberJoin) {
        $sql .= ' LEFT JOIN contact_numbers n ON n.contact_id = c.id';
    }
    if ($needTagJoin) {
        $sql .= ' LEFT JOIN contact_tags ct ON ct.contact_id = c.id'
              . ' LEFT JOIN tags t ON t.id = ct.tag_id';
    }

    $where = [];
    $params = [];

    if ($q !== '') {
        $like = '%' . $q . '%';
        if ($needTagJoin) {
            $where[] = '(c.name LIKE ? OR c.department LIKE ? OR n.number LIKE ? OR t.name LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        } else {
            $where[] = '(c.name LIKE ? OR c.department LIKE ? OR n.number LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
    }

    if ($department !== '') {
        if ($department === '__none__') {
            $where[] = "(c.department IS NULL OR c.department = '')";
        } else {
            $where[] = 'c.department = ?';
            $params[] = $department;
        }
    }

    if ($tag !== '') {
        $where[] = 't.name = ?';
        $params[] = $tag;
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $st = $pdo->prepare($sql);
    foreach ($params as $i => $v) {
        $st->bindValue($i + 1, $v, PDO::PARAM_STR);
    }
    $st->execute();
    return (int)$st->fetchColumn();
}


function contacts_count(?string $q = null): int {
    return contacts_count_filtered($q, null, null);
}

/**
 * Fetch a single page of contacts with optional search and filters.
 */
function contacts_list_page_filtered(?string $q, ?string $department, ?string $tag, int $limit, int $offset): array {
    $pdo = db();

    $q = $q !== null ? trim($q) : '';
    $department = $department !== null ? trim($department) : '';
    $tag = $tag !== null ? trim($tag) : '';

    if ($limit < 1) $limit = 20;
    if ($offset < 0) $offset = 0;

    $needNumberJoin = ($q !== '');
    $needTagJoin = ($q !== '' || $tag !== '');

    $sql = 'SELECT DISTINCT c.id, c.name, c.department, c.created_at, c.updated_at FROM contacts c';
    if ($needNumberJoin) {
        $sql .= ' LEFT JOIN contact_numbers n ON n.contact_id = c.id';
    }
    if ($needTagJoin) {
        $sql .= ' LEFT JOIN contact_tags ct ON ct.contact_id = c.id'
              . ' LEFT JOIN tags t ON t.id = ct.tag_id';
    }

    $where = [];
    $params = [];

    if ($q !== '') {
        $like = '%' . $q . '%';
        if ($needTagJoin) {
            $where[] = '(c.name LIKE ? OR c.department LIKE ? OR n.number LIKE ? OR t.name LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        } else {
            $where[] = '(c.name LIKE ? OR c.department LIKE ? OR n.number LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
    }

    if ($department !== '') {
        if ($department === '__none__') {
            $where[] = "(c.department IS NULL OR c.department = '')";
        } else {
            $where[] = 'c.department = ?';
            $params[] = $department;
        }
    }

    if ($tag !== '') {
        $where[] = 't.name = ?';
        $params[] = $tag;
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY c.name LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;

    $st = $pdo->prepare($sql);
    $i = 1;
    foreach ($params as $v) {
        if ($i > count($params) - 2) {
            $st->bindValue($i, (int)$v, PDO::PARAM_INT);
        } else {
            $st->bindValue($i, $v, PDO::PARAM_STR);
        }
        $i++;
    }
    $st->execute();
    $contacts = $st->fetchAll();
    return contacts_attach_details($contacts);
}


function contacts_list_page(?string $q, int $limit, int $offset): array {
    return contacts_list_page_filtered($q, null, null, $limit, $offset);
}

/**
 * Attach numbers + tags to the given contact rows.
 */
function contacts_attach_details(array $contacts): array {
    $pdo = db();

    $ids = array_map(function ($r) {
        return (int)$r['id'];
    }, $contacts);

    // Numbers
    $numbersByContact = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st2 = $pdo->prepare('SELECT contact_id, label, number, sort_order FROM contact_numbers WHERE contact_id IN (' . $in . ') ORDER BY contact_id, sort_order, id');
        $st2->execute($ids);
        while ($row = $st2->fetch()) {
            $cid = (int)$row['contact_id'];
            $numbersByContact[$cid][] = [
                'label' => (string)$row['label'],
                'number' => (string)$row['number'],
                'sort_order' => (int)$row['sort_order'],
            ];
        }
    }

    // Tags
    $tagsByContact = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stT = $pdo->prepare('SELECT ct.contact_id, t.name
                              FROM contact_tags ct
                              JOIN tags t ON t.id = ct.tag_id
                              WHERE ct.contact_id IN (' . $in . ')
                              ORDER BY ct.contact_id, t.name');
        $stT->execute($ids);
        while ($row = $stT->fetch()) {
            $cid = (int)$row['contact_id'];
            $tagsByContact[$cid][] = (string)$row['name'];
        }
    }

    $out = [];
    foreach ($contacts as $c) {
        $cid = (int)$c['id'];
        $out[] = [
            'id' => $cid,
            'name' => (string)$c['name'],
            'department' => $c['department'] !== null ? (string)$c['department'] : '',
            'created_at' => (string)$c['created_at'],
            'updated_at' => (string)$c['updated_at'],
            'numbers' => $numbersByContact[$cid] ?? [],
            'tags' => $tagsByContact[$cid] ?? [],
        ];
    }

    return $out;
}

function contact_get(int $id): ?array {
    $pdo = db();
    $st = $pdo->prepare('SELECT id, name, department, created_at, updated_at FROM contacts WHERE id = ?');
    $st->execute([$id]);
    $c = $st->fetch();
    if (!$c) return null;

    $st2 = $pdo->prepare('SELECT id, label, number, sort_order FROM contact_numbers WHERE contact_id = ? ORDER BY sort_order, id');
    $st2->execute([$id]);
    $nums = [];
    while ($n = $st2->fetch()) {
        $nums[] = [
            'id' => (int)$n['id'],
            'label' => (string)$n['label'],
            'number' => (string)$n['number'],
            'sort_order' => (int)$n['sort_order'],
        ];
    }

    $stT = $pdo->prepare('SELECT t.name
                          FROM contact_tags ct
                          JOIN tags t ON t.id = ct.tag_id
                          WHERE ct.contact_id = ?
                          ORDER BY t.name');
    $stT->execute([$id]);
    $tags = [];
    while ($t = $stT->fetchColumn()) {
        $tags[] = (string)$t;
    }

    return [
        'id' => (int)$c['id'],
        'name' => (string)$c['name'],
        'department' => $c['department'] !== null ? (string)$c['department'] : '',
        'created_at' => (string)$c['created_at'],
        'updated_at' => (string)$c['updated_at'],
        'numbers' => $nums,
        'tags' => $tags,
    ];
}

/**
 * Save (insert/update) a contact + numbers + tags.
 *
 * $data = [
 *   'id' => optional int,
 *   'name' => string,
 *   'department' => string,
 *   'numbers' => array,
 *   'tags' => string|array (comma separated) OR array of tag names
 * ]
 */
function contact_save(array $data): int {
    $pdo = db();

    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $name = trim((string)($data['name'] ?? ''));
    $department = trim((string)($data['department'] ?? ''));

    if ($name === '') {
        throw new RuntimeException('Name darf nicht leer sein.');
    }

    $numbers = normalize_numbers($data['numbers'] ?? []);
    $tags = normalize_tag_list($data['tags'] ?? '');

    $before = null;
    if ($id > 0) {
        $before = contact_get($id);
    }

    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $st = $pdo->prepare('UPDATE contacts SET name = ?, department = ? WHERE id = ?');
            $st->execute([$name, ($department !== '' ? $department : null), $id]);
        } else {
            $st = $pdo->prepare('INSERT INTO contacts (name, department) VALUES (?, ?)');
            $st->execute([$name, ($department !== '' ? $department : null)]);
            $id = (int)$pdo->lastInsertId();
        }

        // Replace numbers
        $stDel = $pdo->prepare('DELETE FROM contact_numbers WHERE contact_id = ?');
        $stDel->execute([$id]);

        if ($numbers) {
            $stIns = $pdo->prepare('INSERT INTO contact_numbers (contact_id, label, number, sort_order) VALUES (?, ?, ?, ?)');
            foreach ($numbers as $n) {
                $stIns->execute([$id, $n['label'], $n['number'], $n['sort_order']]);
            }
        }

        // Replace tags
        $stDelT = $pdo->prepare('DELETE FROM contact_tags WHERE contact_id = ?');
        $stDelT->execute([$id]);

        if ($tags) {
            $tagIds = tags_ensure_ids($tags);
            if ($tagIds) {
                $stMap = $pdo->prepare('INSERT IGNORE INTO contact_tags (contact_id, tag_id) VALUES (?, ?)');
                foreach ($tagIds as $tid) {
                    $stMap->execute([$id, $tid]);
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // Audit (after commit)
    $after = contact_get($id);
    if ($before === null) {
        audit_log_event('contact_create', 'contact', $id, ['after' => $after]);
    } else {
        audit_log_event('contact_update', 'contact', $id, ['before' => $before, 'after' => $after]);
    }

    return $id;
}

function contact_delete(int $id): void {
    $before = contact_get($id);

    $pdo = db();
    $st = $pdo->prepare('DELETE FROM contacts WHERE id = ?');
    $st->execute([$id]);

    audit_log_event('contact_delete', 'contact', $id, ['before' => $before]);
}

function normalize_numbers($numbers): array {
    if (!is_array($numbers)) return [];

    $out = [];
    $i = 1;
    foreach ($numbers as $n) {
        if (!is_array($n)) continue;
        $label = trim((string)($n['label'] ?? ''));
        $number = trim((string)($n['number'] ?? ''));
        if ($label === '' && $number === '') continue;
        if ($label === '') $label = 'Number ' . $i;
        if ($number === '') continue; // ignore empty numbers
        $out[] = ['label' => $label, 'number' => $number, 'sort_order' => $i];
        $i++;
    }
    return $out;
}

/**
 * Used by XML generator (current working set).
 */
function contacts_fetch_all_for_export(): array {
    $pdo = db();
    $st = $pdo->query('SELECT c.id, c.name, c.department, c.created_at, c.updated_at FROM contacts c ORDER BY c.name');
    $contacts = $st->fetchAll();
    return contacts_attach_details($contacts);
}

/**
 * Import contacts from a CSV file content.
 * Returns array with stats.
 */
function contacts_import_csv(string $csvContent, bool $replaceExisting = false): array {
    $pdo = db();

    $lines = preg_split('/\r\n|\n|\r/', $csvContent);
    if (!$lines || count($lines) < 2) {
        throw new RuntimeException('CSV scheint leer zu sein.');
    }

    // Detect delimiter (comma vs semicolon)
    $firstLine = $lines[0] ?? '';
    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $csvContent);
    rewind($fh);

    $header = fgetcsv($fh, 0, $delimiter);
    if (!$header) {
        throw new RuntimeException('CSV Header konnte nicht gelesen werden.');
    }

    // Normalize header
    $map = [];
    foreach ($header as $idx => $col) {
        $key = strtolower(trim((string)$col));
        $map[$key] = $idx;
    }

    $col = function(array $row, array $map, array $aliases): string {
        foreach ($aliases as $a) {
            $k = strtolower($a);
            if (isset($map[$k])) {
                $v = $row[$map[$k]] ?? '';
                return trim((string)$v);
            }
        }
        return '';
    };

    $pdo->beginTransaction();
    try {
        $deleted = 0;
        if ($replaceExisting) {
            $pdo->exec('DELETE FROM contacts');
            // contact_numbers + contact_tags are deleted via FK cascade
            $pdo->exec('DELETE FROM tags');
            $deleted = 1;
        }

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $name = $col($row, $map, ['name', 'contact name', 'display name']);
            if ($name === '') {
                $skipped++;
                continue;
            }
            $department = $col($row, $map, ['department', 'group', 'department/group']);
            $tagsStr = $col($row, $map, ['tags', 'tag']);
            $tags = normalize_tag_list($tagsStr);

            $office = $col($row, $map, ['office number', 'office', 'business']);
            $mobile = $col($row, $map, ['mobile number', 'mobile', 'cell']);
            $other  = $col($row, $map, ['other number', 'other']);

            $st = $pdo->prepare('INSERT INTO contacts (name, department) VALUES (?, ?)');
            $st->execute([$name, ($department !== '' ? $department : null)]);
            $cid = (int)$pdo->lastInsertId();

            $nums = [];
            $sort = 1;
            if ($office !== '') $nums[] = ['label' => 'Office', 'number' => $office, 'sort_order' => $sort++];
            if ($mobile !== '') $nums[] = ['label' => 'Mobile', 'number' => $mobile, 'sort_order' => $sort++];
            if ($other  !== '') $nums[] = ['label' => 'Other',  'number' => $other,  'sort_order' => $sort++];

            // Optional extra numbers (Template/Export Format)
            for ($i = 1; $i <= 5; $i++) {
                $lbl = $col($row, $map, [
                    'extra ' . $i . ' label',
                    'extra' . $i . ' label',
                    'additional ' . $i . ' label',
                ]);
                $num = $col($row, $map, [
                    'extra ' . $i . ' number',
                    'extra' . $i . ' number',
                    'additional ' . $i . ' number',
                    'extra ' . $i,
                    'extra' . $i,
                ]);
                if ($num === '') continue;
                if ($lbl === '') $lbl = 'Extra ' . $i;
                $nums[] = ['label' => $lbl, 'number' => $num, 'sort_order' => $sort++];
            }

            if ($nums) {
                $stN = $pdo->prepare('INSERT INTO contact_numbers (contact_id, label, number, sort_order) VALUES (?, ?, ?, ?)');
                foreach ($nums as $n) {
                    $stN->execute([$cid, $n['label'], $n['number'], $n['sort_order']]);
                }
            }

            if ($tags) {
                $tagIds = tags_ensure_ids($tags);
                if ($tagIds) {
                    $stMap = $pdo->prepare('INSERT IGNORE INTO contact_tags (contact_id, tag_id) VALUES (?, ?)');
                    foreach ($tagIds as $tid) {
                        $stMap->execute([$cid, $tid]);
                    }
                }
            }

            $imported++;
        }

        $pdo->commit();

        $stats = ['imported' => $imported, 'skipped' => $skipped, 'replaced' => $replaceExisting, 'deleted' => $deleted];
        audit_log_event('import_csv', 'contacts', null, $stats);
        return $stats;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    } finally {
        fclose($fh);
    }
}

