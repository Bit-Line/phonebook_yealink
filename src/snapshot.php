<?php
declare(strict_types=1);

/**
 * Snapshot helpers
 *
 * A snapshot is a portable representation of contacts (including department, tags, numbers)
 * used for:
 *  - diff before publishing
 *  - exporting revisions as CSV
 *  - rollback: restoring the DB to a previous revision
 */

/**
 * Convert contact rows (from DB) into snapshot contacts.
 *
 * Input contact format:
 *  [
 *    ['name'=>..., 'department'=>..., 'numbers'=>[['label'=>..,'number'=>..,'sort_order'=>..], ...], 'tags'=>['tag1',...]]
 *  ]
 */
function snapshot_from_contacts(array $contacts): array {
    $out = [];
    foreach ($contacts as $c) {
        $nums = [];
        $rawNums = isset($c['numbers']) && is_array($c['numbers']) ? $c['numbers'] : [];
        foreach ($rawNums as $n) {
            if (!is_array($n)) continue;
            $num = trim((string)($n['number'] ?? ''));
            if ($num === '') continue;
            $nums[] = [
                'label' => (string)($n['label'] ?? ''),
                'number' => $num,
                'sort_order' => isset($n['sort_order']) ? (int)$n['sort_order'] : 0,
            ];
        }
        usort($nums, function ($a, $b) {
            return ((int)$a['sort_order'] <=> (int)$b['sort_order']);
        });

        $tags = [];
        $rawTags = isset($c['tags']) && is_array($c['tags']) ? $c['tags'] : [];
        foreach ($rawTags as $t) {
            $t = trim((string)$t);
            if ($t === '') continue;
            $tags[] = $t;
        }
        // unique + sort
        $seen = [];
        $uniq = [];
        foreach ($tags as $t) {
            $k = strtolower($t);
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $uniq[] = $t;
        }
        sort($uniq, SORT_NATURAL | SORT_FLAG_CASE);

        $out[] = [
            'name' => (string)($c['name'] ?? ''),
            'department' => (string)($c['department'] ?? ''),
            'tags' => $uniq,
            'numbers' => $nums,
        ];
    }

    // stable sorting (dept, name)
    usort($out, function ($a, $b) {
        $da = strtolower((string)($a['department'] ?? ''));
        $db = strtolower((string)($b['department'] ?? ''));
        if ($da !== $db) return $da <=> $db;
        return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });

    return $out;
}

function snapshot_encode_contacts(array $contacts): string {
    $payload = [
        'schema' => 'yealink-phonebook-snapshot',
        'schema_version' => 1,
        'generated_at' => date('c'),
        'contacts' => $contacts,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        // last resort
        return '{"schema":"yealink-phonebook-snapshot","schema_version":1,"contacts":[]}';
    }
    return $json;
}

function snapshot_decode_contacts(?string $snapshotJson): array {
    if ($snapshotJson === null) return [];
    $snapshotJson = trim($snapshotJson);
    if ($snapshotJson === '') return [];

    $data = json_decode($snapshotJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) return [];

    // new format: { contacts: [...] }
    if (is_array($data) && isset($data['contacts']) && is_array($data['contacts'])) {
        return $data['contacts'];
    }
    // legacy: list of contacts
    if (is_array($data)) return $data;
    return [];
}

function snapshot_from_revision_xml(string $xml, string $format): array {
    $format = (string)$format;

    libxml_use_internal_errors(true);
    $sx = simplexml_load_string($xml);
    if ($sx === false) {
        return [];
    }

    $contacts = [];

    if ($format === 'ipphonedirectory') {
        // Root could be <CompanyIPPhoneDirectory> etc.
        foreach ($sx->DirectoryEntry as $entry) {
            $name = trim((string)$entry->Name);
            if ($name === '') continue;

            $nums = [];
            $sort = 1;
            foreach ($entry->Telephone as $tel) {
                $num = trim((string)$tel);
                if ($num === '') continue;
                $label = '';
                $attrs = $tel->attributes();
                if ($attrs && isset($attrs['label'])) {
                    $label = (string)$attrs['label'];
                }
                $nums[] = ['label' => $label, 'number' => $num, 'sort_order' => $sort++];
            }

            $contacts[] = [
                'name' => $name,
                'department' => '',
                'tags' => [],
                'numbers' => $nums,
            ];
        }

        return $contacts;
    }

    // Default: YealinkIPPhoneBook format
    foreach ($sx->Menu as $menu) {
        $dept = '';
        $attrs = $menu->attributes();
        if ($attrs && isset($attrs['Name'])) {
            $dept = (string)$attrs['Name'];
        }

        foreach ($menu->Unit as $unit) {
            $a = $unit->attributes();
            if (!$a) continue;
            $name = isset($a['Name']) ? trim((string)$a['Name']) : '';
            if ($name === '') continue;

            $nums = [];
            $p1 = isset($a['Phone1']) ? trim((string)$a['Phone1']) : '';
            $p2 = isset($a['Phone2']) ? trim((string)$a['Phone2']) : '';
            $p3 = isset($a['Phone3']) ? trim((string)$a['Phone3']) : '';

            $sort = 1;
            if ($p1 !== '') $nums[] = ['label' => 'Office', 'number' => $p1, 'sort_order' => $sort++];
            if ($p2 !== '') $nums[] = ['label' => 'Mobile', 'number' => $p2, 'sort_order' => $sort++];
            if ($p3 !== '') $nums[] = ['label' => 'Other',  'number' => $p3, 'sort_order' => $sort++];

            $contacts[] = [
                'name' => $name,
                'department' => $dept,
                'tags' => [],
                'numbers' => $nums,
            ];
        }
    }

    return $contacts;
}

function snapshot_contact_key(array $c): string {
    $name = strtolower(trim((string)($c['name'] ?? '')));
    $dept = strtolower(trim((string)($c['department'] ?? '')));
    return $name . '|' . $dept;
}

function snapshot_contact_fingerprint(array $c): string {
    $nums = [];
    $rawNums = isset($c['numbers']) && is_array($c['numbers']) ? $c['numbers'] : [];
    foreach ($rawNums as $n) {
        if (!is_array($n)) continue;
        $lab = strtolower(trim((string)($n['label'] ?? '')));
        $num = trim((string)($n['number'] ?? ''));
        if ($num === '') continue;
        $nums[] = $lab . ':' . $num;
    }
    sort($nums);

    $tags = [];
    $rawTags = isset($c['tags']) && is_array($c['tags']) ? $c['tags'] : [];
    foreach ($rawTags as $t) {
        $t = strtolower(trim((string)$t));
        if ($t === '') continue;
        $tags[] = $t;
    }
    sort($tags);

    return sha1(implode('|', $nums) . '||' . implode('|', $tags));
}

/**
 * Compute diff between two snapshots.
 *
 * Returns:
 *  [
 *    'added' => [contact...],
 *    'removed' => [contact...],
 *    'changed' => [ ['before'=>..., 'after'=>... , 'diff'=>...], ...]
 *  ]
 */
function snapshot_diff(array $oldContacts, array $newContacts): array {
    // Group by key
    $oldByKey = [];
    foreach ($oldContacts as $c) {
        $k = snapshot_contact_key($c);
        if (!isset($oldByKey[$k])) $oldByKey[$k] = [];
        $oldByKey[$k][] = $c;
    }

    $newByKey = [];
    foreach ($newContacts as $c) {
        $k = snapshot_contact_key($c);
        if (!isset($newByKey[$k])) $newByKey[$k] = [];
        $newByKey[$k][] = $c;
    }

    $keys = array_unique(array_merge(array_keys($oldByKey), array_keys($newByKey)));

    $added = [];
    $removed = [];
    $changed = [];

    foreach ($keys as $k) {
        $olds = $oldByKey[$k] ?? [];
        $news = $newByKey[$k] ?? [];

        if (!$olds && $news) {
            foreach ($news as $c) $added[] = $c;
            continue;
        }
        if ($olds && !$news) {
            foreach ($olds as $c) $removed[] = $c;
            continue;
        }

        // Try to match identical fingerprints (unchanged)
        $oldPool = [];
        foreach ($olds as $idx => $c) {
            $fp = snapshot_contact_fingerprint($c);
            if (!isset($oldPool[$fp])) $oldPool[$fp] = [];
            $oldPool[$fp][] = $idx;
        }

        $oldUsed = [];
        $newUsed = [];

        foreach ($news as $j => $nc) {
            $fp = snapshot_contact_fingerprint($nc);
            if (isset($oldPool[$fp]) && count($oldPool[$fp]) > 0) {
                $i = array_shift($oldPool[$fp]);
                $oldUsed[$i] = true;
                $newUsed[$j] = true;
            }
        }

        $oldRemain = [];
        foreach ($olds as $i => $oc) {
            if (!isset($oldUsed[$i])) $oldRemain[] = $oc;
        }
        $newRemain = [];
        foreach ($news as $j => $nc) {
            if (!isset($newUsed[$j])) $newRemain[] = $nc;
        }

        $m = min(count($oldRemain), count($newRemain));
        for ($i = 0; $i < $m; $i++) {
            $b = $oldRemain[$i];
            $a = $newRemain[$i];
            $changed[] = [
                'before' => $b,
                'after' => $a,
                'diff' => snapshot_contact_diff_details($b, $a),
            ];
        }

        if (count($oldRemain) > $m) {
            for ($i = $m; $i < count($oldRemain); $i++) {
                $removed[] = $oldRemain[$i];
            }
        }
        if (count($newRemain) > $m) {
            for ($i = $m; $i < count($newRemain); $i++) {
                $added[] = $newRemain[$i];
            }
        }
    }

    // Stable ordering for UI
    usort($added, function ($a, $b) {
        return strnatcasecmp((string)$a['name'], (string)$b['name']);
    });
    usort($removed, function ($a, $b) {
        return strnatcasecmp((string)$a['name'], (string)$b['name']);
    });
    usort($changed, function ($a, $b) {
        return strnatcasecmp((string)$a['after']['name'], (string)$b['after']['name']);
    });

    return ['added' => $added, 'removed' => $removed, 'changed' => $changed];
}

function snapshot_contact_diff_details(array $before, array $after): array {
    $bNums = snapshot_numbers_flat($before);
    $aNums = snapshot_numbers_flat($after);

    $bTags = snapshot_tags_flat($before);
    $aTags = snapshot_tags_flat($after);

    $numsAdded = array_values(array_diff($aNums, $bNums));
    $numsRemoved = array_values(array_diff($bNums, $aNums));

    $tagsAdded = array_values(array_diff($aTags, $bTags));
    $tagsRemoved = array_values(array_diff($bTags, $aTags));

    return [
        'numbers_added' => $numsAdded,
        'numbers_removed' => $numsRemoved,
        'tags_added' => $tagsAdded,
        'tags_removed' => $tagsRemoved,
    ];
}

function snapshot_numbers_flat(array $c): array {
    $out = [];
    $raw = isset($c['numbers']) && is_array($c['numbers']) ? $c['numbers'] : [];
    foreach ($raw as $n) {
        if (!is_array($n)) continue;
        $lab = trim((string)($n['label'] ?? ''));
        $num = trim((string)($n['number'] ?? ''));
        if ($num === '') continue;
        $out[] = ($lab !== '' ? ($lab . ': ') : '') . $num;
    }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

function snapshot_tags_flat(array $c): array {
    $out = [];
    $raw = isset($c['tags']) && is_array($c['tags']) ? $c['tags'] : [];
    foreach ($raw as $t) {
        $t = trim((string)$t);
        if ($t === '') continue;
        $out[] = $t;
    }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

/**
 * Export snapshot contacts to a CSV string (UTF-8, without BOM by default).
 *
 * NOTE: Export endpoints (public/*.php) typically emit the UTF-8 BOM separately
 * to make Excel happy. To avoid a double BOM, this function does NOT emit BOM.
 */
function snapshot_to_csv(array $contacts, string $delimiter = ';'): string {
    $fh = fopen('php://temp', 'r+');

    $header = [
        'Name',
        'Department',
        'Office Number',
        'Mobile Number',
        'Other Number',
        'Tags',
        'Extra 1 Label', 'Extra 1 Number',
        'Extra 2 Label', 'Extra 2 Number',
        'Extra 3 Label', 'Extra 3 Number',
        'Extra 4 Label', 'Extra 4 Number',
        'Extra 5 Label', 'Extra 5 Number',
    ];
    fputcsv($fh, $header, $delimiter);

    foreach ($contacts as $c) {
        $name = (string)($c['name'] ?? '');
        $dept = (string)($c['department'] ?? '');
        $tags = isset($c['tags']) && is_array($c['tags']) ? $c['tags'] : [];
        $tagsStr = implode(',', $tags);

        $nums = isset($c['numbers']) && is_array($c['numbers']) ? $c['numbers'] : [];

        // Copy since we modify
        $numsWork = [];
        foreach ($nums as $n) {
            if (!is_array($n)) continue;
            $numsWork[] = [
                'label' => (string)($n['label'] ?? ''),
                'number' => (string)($n['number'] ?? ''),
            ];
        }

        $pick = function (array &$arr, array $needles) {
            foreach ($arr as $idx => $n) {
                $lab = strtolower((string)($n['label'] ?? ''));
                foreach ($needles as $needle) {
                    if ($needle !== '' && strpos($lab, $needle) !== false) {
                        $found = $n;
                        unset($arr[$idx]);
                        $arr = array_values($arr);
                        return $found;
                    }
                }
            }
            return null;
        };

        $office = '';
        $mobile = '';
        $other  = '';

        $o = $pick($numsWork, ['office', 'work', 'business']);
        if ($o) $office = (string)($o['number'] ?? '');

        $m = $pick($numsWork, ['mobile', 'cell', 'handy']);
        if ($m) $mobile = (string)($m['number'] ?? '');

        $x = $pick($numsWork, ['other', 'home', 'fax']);
        if ($x) $other = (string)($x['number'] ?? '');

        if ($office === '' && !empty($numsWork)) {
            $first = array_shift($numsWork);
            $office = (string)($first['number'] ?? '');
        }
        if ($mobile === '' && !empty($numsWork)) {
            $first = array_shift($numsWork);
            $mobile = (string)($first['number'] ?? '');
        }
        if ($other === '' && !empty($numsWork)) {
            $first = array_shift($numsWork);
            $other = (string)($first['number'] ?? '');
        }

        $extras = [];
        for ($i = 1; $i <= 5; $i++) {
            $extras[] = '';
            $extras[] = '';
        }

        $i = 0;
        foreach ($numsWork as $n) {
            if ($i >= 5) break;
            $extras[$i * 2] = (string)($n['label'] ?? ('Extra ' . ($i + 1)));
            $extras[$i * 2 + 1] = (string)($n['number'] ?? '');
            $i++;
        }

        $row = array_merge([
            $name,
            $dept,
            $office,
            $mobile,
            $other,
            $tagsStr,
        ], $extras);

        fputcsv($fh, $row, $delimiter);
    }

    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    return $csv !== false ? $csv : '';
}

/**
 * Backward-compat wrapper (deprecated): old name used in early revisions.
 */
function snapshot_contacts_to_csv(array $contacts): string {
    return snapshot_to_csv($contacts, ';');
}

