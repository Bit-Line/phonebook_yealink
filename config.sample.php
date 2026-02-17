<?php
// Copy this file to ../config.php and adjust settings.

declare(strict_types=1);

// --- Database ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'yealink_phonebook');
define('DB_USER', 'yealink_pb');
define('DB_PASS', 'CHANGE_ME_STRONG_PASSWORD');

// --- App ---
define('APP_NAME', 'Yealink Phonebook Manager');

// Yealink XML output format.
// Supported:
//   - 'yealink': <YealinkIPPhoneBook><Menu><Unit .../></Menu></YealinkIPPhoneBook>
//   - 'ipphonedirectory': <CompanyIPPhoneDirectory><DirectoryEntry>...</DirectoryEntry></CompanyIPPhoneDirectory>
define('PHONEBOOK_FORMAT', 'yealink');

// Title shown on the phone (XML <Title> field)
define('PHONEBOOK_TITLE', 'Company Phonebook');

// If a contact has no department, it will be placed into this department/menu:
define('DEFAULT_DEPARTMENT', 'All Contacts');

// Only used for PHONEBOOK_FORMAT = 'ipphonedirectory'
define('IPPHONEDIRECTORY_PREFIX', 'Company');     // results in <CompanyIPPhoneDirectory ...>
define('IPPHONEDIRECTORY_CLEARLIGHT', true);      // clearlight="true"
define('IPPHONEDIRECTORY_PROMPT', 'Directory');

// Security / session settings
define('SESSION_COOKIE_SECURE', false); // set true if you serve the UI over HTTPS
define('SESSION_COOKIE_SAMESITE', 'Lax'); // 'Lax' or 'Strict'
