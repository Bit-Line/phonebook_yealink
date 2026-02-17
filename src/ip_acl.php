<?php
declare(strict_types=1);

function ip_allowlist_allows(?string $clientIp, ?string $allowlistText): bool {
    if ($clientIp === null || trim($clientIp) === '') {
        return false;
    }

    $allowlistText = $allowlistText !== null ? (string)$allowlistText : '';
    $allowlistText = str_replace(["\r\n", "\r"], "\n", $allowlistText);
    $lines = preg_split('/\n+/', $allowlistText);
    if (!$lines) return true; // empty => allow all

    // If allowlist has no meaningful entries => allow all
    $hasEntry = false;
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        $hasEntry = true;
        break;
    }
    if (!$hasEntry) return true;

    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || strpos($line, '#') === 0) continue;

        // exact match
        if (strpos($line, '/') === false && strpos($line, '*') === false) {
            if (strcasecmp($clientIp, $line) === 0) return true;
            continue;
        }

        // IPv4 CIDR
        if (strpos($line, '/') !== false) {
            if (ip_in_ipv4_cidr($clientIp, $line)) return true;
            continue;
        }

        // Wildcards (IPv4)
        if (strpos($line, '*') !== false) {
            if (ip_in_ipv4_wildcard($clientIp, $line)) return true;
            continue;
        }
    }

    return false;
}

function ip_in_ipv4_cidr(string $ip, string $cidr): bool {
    if (strpos($ip, ':') !== false) return false; // no v6 here

    $parts = explode('/', $cidr, 2);
    if (count($parts) !== 2) return false;

    $subnet = trim($parts[0]);
    $maskBits = (int)trim($parts[1]);
    if ($maskBits < 0 || $maskBits > 32) return false;

    $ipLong = ip2long($ip);
    $subLong = ip2long($subnet);
    if ($ipLong === false || $subLong === false) return false;

    // php ip2long is signed; normalize to unsigned
    $ipLongU = (int)sprintf('%u', $ipLong);
    $subLongU = (int)sprintf('%u', $subLong);

    $mask = $maskBits === 0 ? 0 : (~0 << (32 - $maskBits)) & 0xFFFFFFFF;
    return (($ipLongU & $mask) === ($subLongU & $mask));
}

function ip_in_ipv4_wildcard(string $ip, string $pattern): bool {
    if (strpos($ip, ':') !== false) return false; // no v6 here

    $pattern = trim($pattern);
    $parts = explode('.', $pattern);
    $ipParts = explode('.', $ip);
    if (count($parts) !== 4 || count($ipParts) !== 4) return false;

    for ($i = 0; $i < 4; $i++) {
        if ($parts[$i] === '*') continue;
        if ($parts[$i] !== $ipParts[$i]) return false;
    }
    return true;
}
