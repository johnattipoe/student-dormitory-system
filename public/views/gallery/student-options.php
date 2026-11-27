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

$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;

$role = current_role();
$user = current_user() ?? [];
$assignedHouseId = trim((string) ($user['houseId'] ?? $user['house_id'] ?? ''));
$requestedHouseId = trim((string) sanitize($_GET['houseId'] ?? ''));

if ($role === ROLE_HOUSE_MASTER || $role === ROLE_HOUSE_MISTRESS) {
    $houseId = $assignedHouseId;
} elseif ($role === ROLE_SENIOR_HOUSEPARENT) {
    $houseId = $requestedHouseId !== '' ? $requestedHouseId : $assignedHouseId;
} else {
    $houseId = $requestedHouseId;
}

$students = $houseId !== ''
    ? StudentService::all($houseId)
    : (($role === ROLE_ADMIN || $role === ROLE_SENIOR_HOUSEPARENT) ? StudentService::all() : []);

$options = [];
foreach ($students as $student) {
    $id = trim((string) ($student['id'] ?? ''));
    if ($id === '') {
        continue;
    }

    $name = trim((string) ($student['firstName'] ?? '') . ' ' . (string) ($student['lastName'] ?? ''));
    $label = $name !== '' ? $name : 'Student';
    if (!empty($student['admissionNo'])) {
        $label .= ' (' . (string) $student['admissionNo'] . ')';
    }

    $options[] = ['id' => $id, 'label' => $label];
}

json_success('Student options', ['students' => $options]);
