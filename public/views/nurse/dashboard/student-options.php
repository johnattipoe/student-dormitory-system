<?php
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) {
            require $dir . '/bootstrap.php';
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
}

$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;

$students = StudentService::all(null, 150);
$options = [];
foreach ($students as $student) {
    $id = trim((string) ($student['id'] ?? ''));
    if ($id === '') {
        continue;
    }

    $name = trim((string) ($student['firstName'] ?? '') . ' ' . (string) ($student['lastName'] ?? ''));
    $admissionNumber = trim((string) ($student['admissionNo'] ?? $student['studentId'] ?? $id));
    $label = $name !== '' ? $name : 'Unnamed student';
    if ($admissionNumber !== '') {
        $label .= ' (' . $admissionNumber . ')';
    }

    $options[] = [
        'id' => $id,
        'label' => $label,
        'guardianName' => trim((string) ($student['guardianName'] ?? '')),
        'guardianPhone' => (string) ($student['guardianPhone'] ?? ''),
    ];
}

json_success('Student options', ['students' => $options]);
