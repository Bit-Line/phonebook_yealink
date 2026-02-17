<?php
declare(strict_types=1);

// Show errors in homelab/dev environments. In production you may want to disable display_errors.
error_reporting(E_ALL);
ini_set('display_errors', '1');

// --- Load config ---
$cfg = __DIR__ . '/../config.php';
$cfgSample = __DIR__ . '/../config.sample.php';
if (file_exists($cfg)) {
    require_once $cfg;
} elseif (file_exists($cfgSample)) {
    // Keep the app runnable even if config.php wasn't created yet.
    require_once $cfgSample;
} else {
    http_response_code(500);
    echo 'Missing config.php (and config.sample.php).';
    exit;
}

// Defaults if config didn't define them
if (!defined('SESSION_COOKIE_SECURE')) {
    define('SESSION_COOKIE_SECURE', false);
}
if (!defined('SESSION_COOKIE_SAMESITE')) {
    define('SESSION_COOKIE_SAMESITE', 'Lax');
}

$cookieSecure = (bool)SESSION_COOKIE_SECURE;
$cookieSameSite = (string)SESSION_COOKIE_SAMESITE;

// sanitize SameSite value to avoid malformed headers
$cookieSameSite = trim($cookieSameSite);
if (!in_array($cookieSameSite, ['Lax', 'Strict', 'None', ''], true)) {
    $cookieSameSite = 'Lax';
}


// --- Sessions (PHP 7.2 compatible) ---
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (PHP_VERSION_ID >= 70300) {
        // PHP 7.3+ supports array options + SameSite
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $cookieSecure,
            'httponly' => true,
            'samesite' => $cookieSameSite,
        ]);
    } else {
        // PHP 7.2: session_set_cookie_params() does NOT support the array signature.
        // We set the important bits (secure/httponly). SameSite is injected via a manual Set-Cookie header below.
        $p = session_get_cookie_params();
        $domain = isset($p['domain']) ? (string)$p['domain'] : '';
        session_set_cookie_params(0, '/', $domain, $cookieSecure, true);
    }

    session_start();

    // PHP 7.2: manually append SameSite to the session cookie (best effort).
    if (PHP_VERSION_ID < 70300 && $cookieSameSite !== '') {
        $p = session_get_cookie_params();
        $cookie = session_name() . '=' . session_id();
        $cookie .= '; Path=' . ((isset($p['path']) && $p['path'] !== '') ? $p['path'] : '/');
        if (!empty($p['domain'])) {
            $cookie .= '; Domain=' . $p['domain'];
        }
        if (!empty($p['secure'])) {
            $cookie .= '; Secure';
        }
        // Force HttpOnly
        $cookie .= '; HttpOnly';
        $cookie .= '; SameSite=' . $cookieSameSite;

        // Do not replace other cookies, just add another Set-Cookie header.
        header('Set-Cookie: ' . $cookie, false);
    }
}

// --- Common helpers ---
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}

function flash_set(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

// --- DB ---
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

// --- Includes ---
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/ip_acl.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/repo_users.php';
require_once __DIR__ . '/repo_tags.php';
require_once __DIR__ . '/repo_contacts.php';
require_once __DIR__ . '/repo_revisions.php';
require_once __DIR__ . '/snapshot.php';
require_once __DIR__ . '/xml_generator.php';
