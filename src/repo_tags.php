<?php
declare(strict_types=1);

function tags_list_with_counts(): array {
    $pdo = db();
    // only return tags that are used at least once
    $sql = 'SELECT t.name, COUNT(ct.contact_id) AS cnt
            FROM tags t
            LEFT JOIN contact_tags ct ON ct.tag_id = t.id
            GROUP BY t.id
            HAVING cnt > 0
            ORDER BY t.name';
    $st = $pdo->query($sql);
    return $st->fetchAll();
}

function departments_list_with_counts(): array {
    $pdo = db();
    $sql = 'SELECT COALESCE(department, "") AS department, COUNT(*) AS cnt
            FROM contacts
            GROUP BY COALESCE(department, "")
            ORDER BY department';
    $st = $pdo->query($sql);
    return $st->fetchAll();
}

function normalize_tag_list($raw): array {
    if (is_array($raw)) {
        $rawStr = implode(',', $raw);
    } else {
        $rawStr = (string)$raw;
    }

    $parts = preg_split('/[,;\n\r\t]+/', $rawStr);
    if (!$parts) return [];

    $out = [];
    foreach ($parts as $p) {
        $t = trim((string)$p);
        if ($t === '') continue;
        // limit length
        if (strlen($t) > 64) {
            $t = substr($t, 0, 64);
        }
        $out[] = $t;
    }

    // unique (case-insensitive) but keep original casing from first occurrence
    $seen = [];
    $uniq = [];
    foreach ($out as $t) {
        $k = strtolower($t);
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $uniq[] = $t;
        if (count($uniq) >= 20) break;
    }

    sort($uniq, SORT_NATURAL | SORT_FLAG_CASE);
    return $uniq;
}

/**
 * Ensure tags exist and return their IDs.
 * @return int[]
 */
function tags_ensure_ids(array $tagNames): array {
    $pdo = db();

    $ids = [];
    $stSel = $pdo->prepare('SELECT id FROM tags WHERE name = ?');
    $stIns = $pdo->prepare('INSERT INTO tags (name) VALUES (?)');

    foreach ($tagNames as $name) {
        $name = trim((string)$name);
        if ($name === '') continue;
        $stSel->execute([$name]);
        $id = $stSel->fetchColumn();
        if ($id === false) {
            $stIns->execute([$name]);
            $id = $pdo->lastInsertId();
        }
        $ids[] = (int)$id;
    }

    // unique ids
    $ids = array_values(array_unique($ids));
    return $ids;
}

