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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\StudentService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'firstName' => sanitize($_POST['firstName'] ?? ''),
        'lastName' => sanitize($_POST['lastName'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'admissionNo' => sanitize($_POST['admissionNo'] ?? ''),
        'course' => sanitize($_POST['course'] ?? ''),
        'level' => sanitize($_POST['level'] ?? ''),
        'gender' => sanitize($_POST['gender'] ?? ''),
        'houseId' => sanitize($_POST['houseId'] ?? ''),
        'roomId' => sanitize($_POST['roomId'] ?? ''),
        'guardianName' => sanitize($_POST['guardianName'] ?? ''),
        'guardianPhone' => sanitize($_POST['guardianPhone'] ?? ''),
        'guardianEmail' => sanitize($_POST['guardianEmail'] ?? ''),
        'status' => sanitize($_POST['status'] ?? 'active'),
    ];

    $errors = validate_required($data, ['firstName', 'email']);
    if (!empty($data['email']) && !validate_email($data['email'])) {
        $errors['email'] = 'Email is invalid.';
    }

    if (!empty($errors)) {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = $data;
        flash('error', 'Please fix the highlighted fields.');
        redirect(base_url('index.php?route=/views/admin/students/create.php'));
    }

    try {
        $id = StudentService::create($data);
        flash('success', 'Student created successfully.');
        redirect(base_url('index.php?route=/views/admin/students/index.php&created=1'));
    } catch (\Throwable $e) {
        $_SESSION['_errors'] = ['general' => 'Unable to create student: ' . $e->getMessage()];
        $_SESSION['_old'] = $data;
        flash('error', 'Unable to create student.');
        redirect(base_url('index.php?route=/views/admin/students/create.php'));
    }
}

$pageTitle = 'Add Student';
$errors = $_SESSION['_errors'] ?? []; unset($_SESSION['_errors']);
$old = $_SESSION['_old'] ?? []; unset($_SESSION['_old']);
$houses = FirebaseService::getInstance()->getCollection(COL_HOUSES, [], 100);

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
            <h5 class="mb-3">Add Student</h5>
            <form method="POST" action="<?= url('views/admin/students/create.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input name="firstName" class="form-control" value="<?= e($old['firstName'] ?? '') ?>" required>
                        <?php if (!empty($errors['firstName'])): ?><div class="text-danger small"><?= e($errors['firstName'][0]) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input name="lastName" class="form-control" value="<?= e($old['lastName'] ?? '') ?>" required>
                        <?php if (!empty($errors['lastName'])): ?><div class="text-danger small"><?= e($errors['lastName']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($old['email'] ?? '') ?>" required>
                        <?php if (!empty($errors['email'])): ?><div class="text-danger small"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input name="phone" class="form-control" value="<?= e($old['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Admission No.</label>
                        <input name="admissionNo" class="form-control" value="<?= e($old['admissionNo'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Course</label>
                        <input name="course" class="form-control" value="<?= e($old['course'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select select2">
                            <option value="male" <?= ($old['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= ($old['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">House</label>
                        <select name="houseId" class="form-select select2">
                            <option value="">— None —</option>
                            <?php foreach ($houses as $h): ?>
                                <option value="<?= e($h['id']) ?>" <?= ($old['houseId'] ?? '') === ($h['id'] ?? '') ? 'selected' : '' ?>><?= e($h['name'] ?? $h['id']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Guardian Name</label>
                        <input name="guardianName" class="form-control" value="<?= e($old['guardianName'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Guardian Phone</label>
                        <input name="guardianPhone" class="form-control" value="<?= e($old['guardianPhone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Guardian Email</label>
                        <input type="email" name="guardianEmail" class="form-control" value="<?= e($old['guardianEmail'] ?? '') ?>">
                    </div>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Student</button>
                    <a href="<?= url('views/admin/students/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
