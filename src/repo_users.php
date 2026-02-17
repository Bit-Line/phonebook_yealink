<?php
declare(strict_types=1);

function users_count(): int {
    $pdo = db();
    return (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function users_list_page(int $limit, int $offset): array {
    $pdo = db();
    if ($limit < 1) $limit = 25;
    if ($offset < 0) $offset = 0;

    $st = $pdo->prepare('SELECT id, username, role, created_at FROM users ORDER BY username LIMIT ? OFFSET ?');
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->bindValue(2, $offset, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function user_get(int $id): ?array {
    $pdo = db();
    $st = $pdo->prepare('SELECT id, username, role, created_at FROM users WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch();
    return $u ?: null;
}

function user_get_by_username(string $username): ?array {
    $pdo = db();
    $st = $pdo->prepare('SELECT id, username, role, password_hash, created_at FROM users WHERE username = ?');
    $st->execute([$username]);
    $u = $st->fetch();
    return $u ?: null;
}

function user_create(string $username, string $password, string $role): int {
    $pdo = db();
    $username = trim($username);
    if ($username === '') throw new RuntimeException('Benutzername darf nicht leer sein.');

    $role = normalize_role($role);

    if (strlen($password) < 6) {
        throw new RuntimeException('Passwort muss mindestens 6 Zeichen haben.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('Passwort-Hash konnte nicht erzeugt werden.');
    }

    $st = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
    $st->execute([$username, $hash, $role]);
    return (int)$pdo->lastInsertId();
}

function user_update(int $id, string $username, string $role): void {
    $pdo = db();
    $username = trim($username);
    if ($username === '') throw new RuntimeException('Benutzername darf nicht leer sein.');
    $role = normalize_role($role);

    $st = $pdo->prepare('UPDATE users SET username = ?, role = ? WHERE id = ?');
    $st->execute([$username, $role, $id]);
}

function user_set_password(int $id, string $password): void {
    if (strlen($password) < 6) {
        throw new RuntimeException('Passwort muss mindestens 6 Zeichen haben.');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('Passwort-Hash konnte nicht erzeugt werden.');
    }
    $pdo = db();
    $st = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $st->execute([$hash, $id]);
}

function user_change_password(int $id, string $oldPassword, string $newPassword): void {
    $pdo = db();
    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$id]);
    $hash = $st->fetchColumn();
    if ($hash === false) throw new RuntimeException('User nicht gefunden.');

    if (!password_verify($oldPassword, (string)$hash)) {
        throw new RuntimeException('Aktuelles Passwort ist falsch.');
    }
    user_set_password($id, $newPassword);
}

function user_delete(int $id): void {
    $pdo = db();
    $st = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $st->execute([$id]);
}

function normalize_role(string $role): string {
    $role = strtolower(trim($role));
    if (!in_array($role, ['admin', 'editor', 'viewer'], true)) {
        $role = 'viewer';
    }
    return $role;
}

function role_rank(string $role): int {
    $role = normalize_role($role);
    if ($role === 'admin') return 3;
    if ($role === 'editor') return 2;
    return 1;
}

function roles_all(): array {
    return [
        'viewer' => 'Viewer (Read-only)',
        'editor' => 'Editor (Kontakte/Revisionen)',
        'admin'  => 'Admin (alles)',
    ];
}

function admins_count(): int {
    $pdo = db();
    $st = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    return (int)$st->fetchColumn();
}

