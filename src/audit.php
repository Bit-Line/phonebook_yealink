<?php
declare(strict_types=1);

/**
 * Audit Log
 *
 * Records security-relevant and data-changing actions:
 * - who (user_id)
 * - what (action)
 * - when (created_at)
 * - optionally: entity_type/entity_id, details
 *
 * Table: audit_log
 */

function audit_log_event(string $action, ?string $entityType = null, $entityId = null, $details = null): void {
    // Never break the main flow because of auditing
    try {
        $pdo = db();

        $uid = null;
        if (!empty($_SESSION['user_id'])) {
            $uid = (int)$_SESSION['user_id'];
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : null;
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : null;
        if ($ua !== null && strlen($ua) > 255) {
            $ua = substr($ua, 0, 255);
        }

        $detailsStr = null;
        if ($details !== null) {
            if (is_string($details)) {
                $detailsStr = $details;
            } else {
                $json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($json !== false) {
                    $detailsStr = $json;
                } else {
                    $detailsStr = '[unencodable-details]';
                }
            }
            if ($detailsStr !== null && strlen($detailsStr) > 65535) {
                $detailsStr = substr($detailsStr, 0, 65535);
            }
        }

        $eid = null;
        if ($entityId !== null && $entityId !== '') {
            // allow int or string
            $eid = is_numeric($entityId) ? (string)$entityId : (string)$entityId;
        }

        $st = $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $st->execute([
            $uid,
            $action,
            $entityType,
            $eid,
            $detailsStr,
            $ip,
            $ua,
        ]);
    } catch (Throwable $e) {
        // ignore
    }
}

function audit_parse_details(?string $details): ?array {
    if ($details === null) return null;
    $details = trim($details);
    if ($details === '') return null;
    if ($details[0] !== '{' && $details[0] !== '[') return null;
    $d = json_decode($details, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;
    return $d;
}
