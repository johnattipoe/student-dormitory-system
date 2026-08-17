<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\FirebaseService;

$errors = $_SESSION['_errors'] ?? [];
unset($_SESSION['_errors']);
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);

$id = $_GET['id'] ?? '';
$student = $id ? StudentService::find($id) : null;
if (!$student) { http_response_code(404); echo 'Student not found.'; exit; }

$houses = FirebaseService::getInstance()->getCollection(COL_HOUSES, [], 100);
$pageTitle = 'Edit Student';

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
        <div class="card stat-card p-4" style="max-width:720px">
            <h5 class="mb-3">Edit Student</h5>
            <form method="POST" action="<?= url('students/' . urlencode($student['id'])) ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input name="firstName" class="form-control" value="<?= e($old['firstName'] ?? $student['firstName'] ?? '') ?>" required>
                        <?php if (!empty($errors['firstName'])): ?><div class="text-danger small"><?= e($errors['firstName']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input name="lastName" class="form-control" value="<?= e($old['lastName'] ?? $student['lastName'] ?? '') ?>" required>
                        <?php if (!empty($errors['lastName'])): ?><div class="text-danger small"><?= e($errors['lastName']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($old['email'] ?? $student['email'] ?? '') ?>" required>
                        <?php if (!empty($errors['email'])): ?><div class="text-danger small"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">House</label>
                        <select name="houseId" class="form-select select2">
                            <option value="">— None —</option>
                            <?php foreach ($houses as $h): ?>
                                <option value="<?= e($h['id']) ?>" <?= (($old['houseId'] ?? $student['houseId'] ?? '') === $h['id']) ? 'selected' : '' ?>><?= e($h['name'] ?? $h['id']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= ($old['status'] ?? $student['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($old['status'] ?? $student['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="graduated" <?= ($old['status'] ?? $student['status'] ?? '') === 'graduated' ? 'selected' : '' ?>>Graduated</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
                    <a href="<?= url('views/admin/students/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
