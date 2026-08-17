<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT, ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\StudentService;

$id = $_GET['id'] ?? '';
$student = $id ? StudentService::find($id) : null;
$pageTitle = 'Student Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/students/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <h5 class="mb-3">Student Details</h5>
            <?php if ($student): ?>
                <dl class="row mb-0">
                    <dt class="col-sm-3">Name</dt><dd class="col-sm-9"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></dd>
                    <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= e($student['email'] ?? '') ?></dd>
                    <dt class="col-sm-3">Admission No.</dt><dd class="col-sm-9"><?= e($student['admissionNo'] ?? '') ?></dd>
                    <dt class="col-sm-3">Course</dt><dd class="col-sm-9"><?= e($student['course'] ?? '') ?></dd>
                    <dt class="col-sm-3">House</dt><dd class="col-sm-9"><?= e($student['houseId'] ?? '-') ?></dd>
                    <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= e($student['status'] ?? '') ?></dd>
                </dl>
            <?php else: ?>
                <div class="alert alert-warning">No student selected.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
