<?php
declare(strict_types=1);

/**
 * Simple key/value settings stored in DB.
 *
 * MySQL table: settings (`key` VARCHAR(64) PK, `value` LONGTEXT)
 *
 * NOTE: Functions are defensive: if the table does not exist yet (upgrade not run),
 * they will return defaults instead of crashing the whole app.
 */

function settings_get(string $key, ?string $default = null): ?string {
    try {
        $pdo = db();
        $st = $pdo->prepare('SELECT `value` FROM settings WHERE `key` = ?');
        $st->execute([$key]);
        $v = $st->fetchColumn();
        if ($v === false) return $default;
        return $v !== null ? (string)$v : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function settings_set(string $key, ?string $value): void {
    try {
        $pdo = db();
        $st = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        $st->execute([$key, $value]);
    } catch (Throwable $e) {
        // ignore (e.g. table missing)
    }
}

function settings_all(): array {
    try {
        $pdo = db();
        $st = $pdo->query('SELECT `key`, `value`, updated_at FROM settings ORDER BY `key`');
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
