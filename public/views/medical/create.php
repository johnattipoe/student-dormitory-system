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
$allowedRoles = [ROLE_ADMIN, ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$pageTitle = 'Create Medical Record';
$students = FirebaseService::getInstance()->getCollection(COL_STUDENTS, [], 500);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-heart-pulse', 'label' => 'Medical Records', 'href' => url('views/medical/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px;">
            <h5 class="mb-3">Create Medical Record</h5>
            <form method="POST" action="<?= url('views/medical/create.php') ?>">
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
                        <label class="form-label">Severity</label>
                        <select name="severity" class="form-select">
                            <option value="normal">Normal</option>
                            <option value="moderate">Moderate</option>
                            <option value="severe">Severe</option>
                            <option value="emergency">Emergency</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Diagnosis</label>
                        <input type="text" name="diagnosis" class="form-control" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Treatment</label>
                        <textarea name="treatment" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary" type="submit">Save Record</button>
                    <a href="<?= url('views/medical/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
