<?php
declare(strict_types=1);

function phonebook_generate_xml(array $contacts): string {
    $format = defined('PHONEBOOK_FORMAT') ? (string)PHONEBOOK_FORMAT : 'yealink';
    if ($format === 'ipphonedirectory') {
        return phonebook_generate_ipphonedirectory($contacts);
    }
    return phonebook_generate_yealink($contacts);
}

function phonebook_generate_yealink(array $contacts): string {
    $title = defined('PHONEBOOK_TITLE') ? (string)PHONEBOOK_TITLE : 'Phonebook';
    $defaultDept = defined('DEFAULT_DEPARTMENT') ? (string)DEFAULT_DEPARTMENT : 'All Contacts';

    // Group by department
    $groups = [];
    foreach ($contacts as $c) {
        $dept = trim((string)($c['department'] ?? ''));
        if ($dept === '') $dept = $defaultDept;
        if (!isset($groups[$dept])) {
            $groups[$dept] = [];
        }
        $groups[$dept][] = $c;
    }
    ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

    $x = [];
    $x[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $x[] = '<YealinkIPPhoneBook>';
    $x[] = '  <Title>' . xml_text($title) . '</Title>';

    foreach ($groups as $dept => $items) {
        $x[] = '  <Menu Name="' . xml_attr($dept) . '">';

        // sort contacts by name
        usort($items, function ($a, $b) {
            return strnatcasecmp((string)$a['name'], (string)$b['name']);
        });

        foreach ($items as $c) {
            $name = (string)($c['name'] ?? '');
            $nums = $c['numbers'] ?? [];
            if (!is_array($nums)) $nums = [];

            usort($nums, function ($a, $b) {
                return ((int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0));
            });

            $phone1 = $nums[0]['number'] ?? '';
            $phone2 = $nums[1]['number'] ?? '';
            $phone3 = $nums[2]['number'] ?? '';

            $x[] = '    <Unit Name="' . xml_attr($name) . '" Phone1="' . xml_attr((string)$phone1) . '" Phone2="' . xml_attr((string)$phone2) . '" Phone3="' . xml_attr((string)$phone3) . '" default_photo="Resource:"/>';
        }
        $x[] = '  </Menu>';
    }

    $x[] = '</YealinkIPPhoneBook>';
    return implode("\n", $x) . "\n";
}

function phonebook_generate_ipphonedirectory(array $contacts): string {
    $title = defined('PHONEBOOK_TITLE') ? (string)PHONEBOOK_TITLE : 'Phonebook';
    $prefix = defined('IPPHONEDIRECTORY_PREFIX') ? (string)IPPHONEDIRECTORY_PREFIX : 'Company';
    $root = preg_replace('/[^A-Za-z0-9_\-]/', '', $prefix) . 'IPPhoneDirectory';
    if ($root === 'IPPhoneDirectory') $root = 'CompanyIPPhoneDirectory';

    $clearlight = defined('IPPHONEDIRECTORY_CLEARLIGHT') ? (bool)IPPHONEDIRECTORY_CLEARLIGHT : true;
    $prompt = defined('IPPHONEDIRECTORY_PROMPT') ? (string)IPPHONEDIRECTORY_PROMPT : 'Directory';

    // sort contacts by name
    usort($contacts, function ($a, $b) {
        return strnatcasecmp((string)$a['name'], (string)$b['name']);
    });

    $attr = $clearlight ? ' clearlight="true"' : '';
    $x = [];
    $x[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $x[] = '<' . $root . $attr . '>';
    $x[] = '  <Title>' . xml_text($title) . '</Title>';
    $x[] = '  <Prompt>' . xml_text($prompt) . '</Prompt>';

    foreach ($contacts as $c) {
        $name = (string)($c['name'] ?? '');
        $x[] = '  <DirectoryEntry>';
        $x[] = '    <Name>' . xml_text($name) . '</Name>';

        $nums = $c['numbers'] ?? [];
        if (!is_array($nums)) $nums = [];

        usort($nums, function ($a, $b) {
            return ((int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0));
        });

        foreach ($nums as $n) {
            $label = (string)($n['label'] ?? '');
            $number = (string)($n['number'] ?? '');
            if (trim($number) === '') continue;
            if ($label !== '') {
                $x[] = '    <Telephone label="' . xml_attr($label) . '">' . xml_text($number) . '</Telephone>';
            } else {
                $x[] = '    <Telephone>' . xml_text($number) . '</Telephone>';
            }
        }

        $x[] = '  </DirectoryEntry>';
    }

    $x[] = '</' . $root . '>';
    return implode("\n", $x) . "\n";
}

function xml_attr(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}
function xml_text(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}
