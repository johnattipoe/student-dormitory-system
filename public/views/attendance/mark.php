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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT, ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$pageTitle = 'Mark Attendance';
$students = FirebaseService::getInstance()->getCollection(COL_STUDENTS, [], 500);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/attendance/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:720px;">
            <h5 class="mb-3">Mark Attendance</h5>
            <form method="POST" action="<?= url('views/attendance/mark.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Student</label>
                        <select name="studentId" class="form-select" required>
                            <option value="">Select student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= e($student['id'] ?? '') ?>"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="late">Late</option>
                            <option value="excused">Excused</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary" type="submit">Save Attendance</button>
                    <a href="<?= url('views/attendance/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
