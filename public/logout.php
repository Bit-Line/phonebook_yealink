<?php
require_once __DIR__ . '/../src/bootstrap.php';

logout();
flash_set('success', 'Logout erfolgreich.');
redirect('login.php');
