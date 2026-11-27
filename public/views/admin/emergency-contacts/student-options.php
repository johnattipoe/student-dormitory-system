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

$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;

$students = StudentService::allRecords();
$options = [];
foreach ($students as $student) {
    $id = trim((string) ($student['id'] ?? ''));
    if ($id === '') {
        continue;
    }

    $name = trim((string) ($student['firstName'] ?? '') . ' ' . (string) ($student['lastName'] ?? ''));
    $label = $name !== '' ? $name : 'Student';
    if (!empty($student['admissionNo'])) {
        $label .= ' [' . (string) $student['admissionNo'] . ']';
    }
    $options[] = ['id' => $id, 'label' => $label];
}

json_success('Student options', ['students' => $options]);
