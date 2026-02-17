<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (is_logged_in()) {
    redirect('contacts.php');
}
redirect('login.php');
