<?php
declare(strict_types=1);

function render_header(string $title = ''): void {
    $appName = defined('APP_NAME') ? (string)APP_NAME : 'Phonebook';
    $fullTitle = trim($title) !== '' ? ($title . ' — ' . $appName) : $appName;

    $user = null;
    $role = null;
    if (is_logged_in()) {
        $user = current_user();
        if ($user) {
            $role = isset($user['role']) ? (string)$user['role'] : null;
        }
    }

    $activeRev = null;
    if (function_exists('revision_get_active')) {
        try {
            $activeRev = revision_get_active();
        } catch (Throwable $e) {
            $activeRev = null;
        }
    }

    $flash = flash_get();

    echo '<!doctype html><html lang="de"><head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($fullTitle) . '</title>';

    // Bootstrap 5.3 (supports data-bs-theme for dark mode)
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">';
    echo '<link href="assets/app.css" rel="stylesheet">';

    // Apply theme early (before paint)
    echo '<script>(function(){try{var t=localStorage.getItem("theme")||"auto";var r=document.documentElement; if(t==="dark"){r.setAttribute("data-bs-theme","dark");}else if(t==="light"){r.setAttribute("data-bs-theme","light");}else{r.removeAttribute("data-bs-theme");}}catch(e){}})();</script>';

    echo '</head><body>';
    echo '<nav class="navbar navbar-expand-lg border-bottom mb-4">';
    echo '<div class="container-fluid">';
    echo '<a class="navbar-brand" href="index.php">' . h($appName) . '</a>';
    echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>';

    echo '<div class="collapse navbar-collapse" id="nav">';
    echo '<ul class="navbar-nav me-auto mb-2 mb-lg-0">';

    if (is_logged_in()) {
        echo '<li class="nav-item"><a class="nav-link" href="contacts.php"><i class="bi bi-people"></i> Kontakte</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="import.php"><i class="bi bi-upload"></i> Import/Export</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="revisions.php"><i class="bi bi-clock-history"></i> Revisionen</a></li>';

        if (has_min_role('editor')) {
            echo '<li class="nav-item"><a class="nav-link" href="dedupe.php"><i class="bi bi-intersect"></i> Dedupe</a></li>';
        }

        echo '<li class="nav-item"><a class="nav-link" href="phonebook.php" target="_blank" rel="noopener"><i class="bi bi-filetype-xml"></i> Aktives XML</a></li>';
    }

    echo '</ul>';

    // Active revision badge
    if (is_logged_in()) {
        if ($activeRev) {
            echo '<span class="me-3 badge text-bg-success" title="Aktiv publizierte Revision">Rev ' . h((string)$activeRev['revision_number']) . '</span>';
        } else {
            echo '<span class="me-3 badge text-bg-secondary" title="Noch keine publizierte Revision">Rev –</span>';
        }
    }

    // Theme toggle
    echo '<div class="d-flex align-items-center gap-2">';
    echo '<button class="btn btn-outline-secondary btn-sm" type="button" id="themeToggle" title="Dark/Light umschalten"><i class="bi bi-circle-half"></i></button>';

    if ($user) {
        $roleLabel = '';
        if ($role) {
            $r = normalize_role($role);
            if ($r === 'admin') $roleLabel = 'Admin';
            elseif ($r === 'editor') $roleLabel = 'Editor';
            else $roleLabel = 'Viewer';
        }

        echo '<div class="dropdown">';
        echo '<button class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person"></i> ' . h((string)$user['username']) . ($roleLabel !== '' ? ' <span class="badge text-bg-secondary ms-1">' . h($roleLabel) . '</span>' : '') . '</button>';
        echo '<ul class="dropdown-menu dropdown-menu-end">';
        echo '<li><a class="dropdown-item" href="account.php"><i class="bi bi-key"></i> Passwort ändern</a></li>';

        if (has_min_role('admin')) {
            echo '<li><hr class="dropdown-divider"></li>';
            echo '<li><h6 class="dropdown-header">Admin</h6></li>';
            echo '<li><a class="dropdown-item" href="admin_users.php"><i class="bi bi-people"></i> Benutzer</a></li>';
            echo '<li><a class="dropdown-item" href="admin_settings.php"><i class="bi bi-gear"></i> Einstellungen</a></li>';
            echo '<li><a class="dropdown-item" href="admin_backup.php"><i class="bi bi-database"></i> Backup/Restore</a></li>';
            echo '<li><a class="dropdown-item" href="admin_audit.php"><i class="bi bi-journal-text"></i> Audit-Log</a></li>';
        }

        echo '<li><hr class="dropdown-divider"></li>';
        echo '<li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>';
        echo '</ul>';
        echo '</div>';
    }

    echo '</div>';

    echo '</div>'; // collapse
    echo '</div></nav>';

    echo '<main class="container">';
    if ($flash) {
        $type = $flash['type'] ?? 'info';
        $msg  = $flash['message'] ?? '';
        $class = 'info';
        if ($type === 'success') $class = 'success';
        elseif ($type === 'warning') $class = 'warning';
        elseif ($type === 'danger') $class = 'danger';
        echo '<div class="alert alert-' . h($class) . ' alert-dismissible fade show" role="alert">';
        echo h((string)$msg);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

function render_footer(): void {
    echo '</main>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>';
    echo '<script src="assets/app.js"></script>';
    echo '</body></html>';
}
