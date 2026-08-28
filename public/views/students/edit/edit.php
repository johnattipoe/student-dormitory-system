<?php
// Ensure bootstrap is loaded (safe at any view nesting depth)
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) {
            require $dir . '/bootstrap.php';
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;

$pageTitle = 'Edit Student';
$id = $_GET['id'] ?? '';
$student = $id ? StudentService::find($id) : null;
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/students/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px;">
            <h5 class="mb-3">Edit Student</h5>
            <?php if ($student): ?>
                <form method="POST" action="<?= url('students/' . urlencode($id) . '/update') ?>">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="firstName" class="form-control" value="<?= e($student['firstName'] ?? '') ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="lastName" class="form-control" value="<?= e($student['lastName'] ?? '') ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($student['email'] ?? '') ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Class Code</label><input type="text" name="course" class="form-control" value="<?= e($student['course'] ?? '') ?>"></div>
                    </div>
                    <div class="mt-4">
                        <button class="btn btn-primary" type="submit">Update Student</button>
                        <a href="<?= url('views/students/index/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">No student selected.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
