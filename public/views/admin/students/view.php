<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT, ROLE_NURSE, ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\StudentService;

$id = $_GET['id'] ?? '';
$student = $id ? StudentService::find($id) : null;
if (!$student) { http_response_code(404); echo 'Student not found.'; exit; }
$pageTitle = 'Student Profile';

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/admin/students/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <h5><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></h5>
            <table class="table table-borderless w-auto">
                <tr><th>Admission No.</th><td><?= e($student['admissionNo'] ?? '—') ?></td></tr>
                <tr><th>Email</th><td><?= e($student['email'] ?? '—') ?></td></tr>
                <tr><th>Phone</th><td><?= e($student['phone'] ?? '—') ?></td></tr>
                <tr><th>Course</th><td><?= e($student['course'] ?? '—') ?></td></tr>
                <tr><th>House</th><td><?= e($student['houseId'] ?? '—') ?></td></tr>
                <tr><th>Room</th><td><?= e($student['roomId'] ?? '—') ?></td></tr>
                <tr><th>Status</th><td><?= e($student['status'] ?? '—') ?></td></tr>
                <tr><th>Guardian</th><td><?= e($student['guardianName'] ?? '—') ?> (<?= e($student['guardianPhone'] ?? '—') ?>)</td></tr>
            </table>
            <a href="<?= url('views/admin/students/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back to list</a>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
