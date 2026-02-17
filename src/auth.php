<?php
declare(strict_types=1);

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        flash_set('warning', 'Bitte zuerst einloggen.');
        redirect('login.php');
    }
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    $pdo = db();
    $st = $pdo->prepare('SELECT id, username, role, created_at FROM users WHERE id = ?');
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();
    return $u ?: null;
}

function current_user_role(): string {
    $u = current_user();
    if (!$u) return 'viewer';
    $role = isset($u['role']) ? (string)$u['role'] : 'viewer';
    return normalize_role($role);
}

function has_min_role(string $role): bool {
    if (!is_logged_in()) return false;
    $want = role_rank($role);
    $have = role_rank(current_user_role());
    return $have >= $want;
}

function require_min_role(string $role): void {
    require_login();
    if (!has_min_role($role)) {
        http_response_code(403);
        render_header('Zugriff verweigert');
        echo '<div class="alert alert-danger">Zugriff verweigert (Rolle benötigt: ' . h($role) . ').</div>';
        echo '<a class="btn btn-outline-secondary" href="contacts.php">Zurück</a>';
        render_footer();
        exit;
    }
}

function require_editor(): void {
    require_min_role('editor');
}

function require_admin(): void {
    require_min_role('admin');
}

function login_attempt(string $username, string $password): bool {
    $pdo = db();
    $st = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
    $st->execute([$username]);
    $u = $st->fetch();
    if (!$u) return false;
    if (!password_verify($password, (string)$u['password_hash'])) return false;

    $_SESSION['user_id'] = (int)$u['id'];
    session_regenerate_id(true);

    audit_log_event('login', 'user', (int)$u['id'], ['username' => (string)$u['username']]);
    return true;
}

function logout(): void {
    if (!empty($_SESSION['user_id'])) {
        audit_log_event('logout', 'user', (int)$_SESSION['user_id'], null);
    }
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}
