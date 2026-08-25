<?php
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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\HouseService;
use App\Services\StudentService;

$houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
if ($houseId === '' || !$house) {
    access_denied();
}

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
        'houseId' => $houseId,
        'guardianName' => sanitize($_POST['guardianName'] ?? ''),
        'guardianPhone' => sanitize($_POST['guardianPhone'] ?? ''),
        'guardianEmail' => sanitize($_POST['guardianEmail'] ?? ''),
        'status' => 'active',
    ];

    $errors = validate_required($data, ['firstName', 'lastName', 'email']);
    if ($data['email'] !== '' && !validate_email($data['email'])) {
        $errors['email'] = 'Email is invalid.';
    }
    if ($data['guardianEmail'] !== '' && !validate_email($data['guardianEmail'])) {
        $errors['guardianEmail'] = 'Guardian email is invalid.';
    }

    if (!empty($errors)) {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = $data;
        flash('error', 'Please fix the highlighted fields.');
    } else {
        try {
            StudentService::create($data);
            flash('success', 'Student added to ' . ($house['name'] ?? 'your house') . '.');
            redirect(url('views/house-master/students/index.php'));
        } catch (Throwable $e) {
            $_SESSION['_errors'] = ['general' => 'Unable to create student: ' . $e->getMessage()];
            $_SESSION['_old'] = $data;
            flash('error', 'Unable to create student.');
        }
    }
}

$pageTitle = 'Add Student';
$errors = $_SESSION['_errors'] ?? [];
unset($_SESSION['_errors']);
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index.php'), 'active' => true],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:720px">
            <h5 class="mb-1">Add Student</h5>
            <p class="text-muted mb-4">This student will be added to <?= e($house['name'] ?? $houseId) ?>.</p>
            <?php if (!empty($errors['general'])): ?><div class="alert alert-danger"><?= e($errors['general']) ?></div><?php endif; ?>
            <form method="POST" action="<?= url('views/house-master/students/create.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input name="firstName" class="form-control" value="<?= e($old['firstName'] ?? '') ?>" required>
                        <?php if (!empty($errors['firstName'])): ?><div class="text-danger small"><?= e(is_array($errors['firstName']) ? $errors['firstName'][0] : $errors['firstName']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input name="lastName" class="form-control" value="<?= e($old['lastName'] ?? '') ?>" required>
                        <?php if (!empty($errors['lastName'])): ?><div class="text-danger small"><?= e(is_array($errors['lastName']) ? $errors['lastName'][0] : $errors['lastName']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($old['email'] ?? '') ?>" required>
                        <?php if (!empty($errors['email'])): ?><div class="text-danger small"><?= e(is_array($errors['email']) ? $errors['email'][0] : $errors['email']) ?></div><?php endif; ?>
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
                        <label class="form-label">Level</label>
                        <input name="level" class="form-control" value="<?= e($old['level'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="">Select gender</option>
                            <option value="male" <?= ($old['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= ($old['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">House</label>
                        <input class="form-control" value="<?= e($house['name'] ?? $houseId) ?>" readonly>
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
                        <?php if (!empty($errors['guardianEmail'])): ?><div class="text-danger small"><?= e(is_array($errors['guardianEmail']) ? $errors['guardianEmail'][0] : $errors['guardianEmail']) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Student</button>
                    <a href="<?= url('views/house-master/students/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
