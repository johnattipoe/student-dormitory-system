<?php
if (!defined('APP_ROOT')) {
    require dirname(__DIR__, 3) . '/bootstrap.php';
}

$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY, ROLE_NURSE];
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
    $options[] = [
        'id' => $id,
        'label' => $name !== '' ? $name : 'Unnamed student',
    ];
}

json_success('Student options', ['students' => $options]);
