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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\StudentService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValue = sanitize($_POST['form'] ?? $_POST['level'] ?? '');
    $data = [
        'firstName'     => sanitize($_POST['firstName'] ?? ''),
        'lastName'      => sanitize($_POST['lastName'] ?? ''),
        'admissionNo'   => sanitize($_POST['admissionNo'] ?? ''),
        'class'         => sanitize($_POST['class'] ?? ''),
        'form'          => $formValue,
        'level'         => $formValue,
        'gender'        => sanitize($_POST['gender'] ?? ''),
        'nhisNumber'    => sanitize($_POST['nhisNumber'] ?? ''),
        'course'        => sanitize($_POST['course'] ?? ''),
        'houseId'       => sanitize($_POST['houseId'] ?? ''),
        'roomId'        => sanitize($_POST['roomId'] ?? ''),
        'status'        => sanitize($_POST['status'] ?? 'active'),
        'guardianName'  => sanitize($_POST['guardianName'] ?? ''),
        'guardianPhone' => sanitize($_POST['guardianPhone'] ?? ''),
        'guardianEmail' => sanitize($_POST['guardianEmail'] ?? ''),
    ];

    $errors = validate_required($data, ['firstName', 'lastName']);

    if (!empty($data['guardianEmail']) && !validate_email($data['guardianEmail'])) {
        $errors['guardianEmail'] = 'Guardian email is invalid.';
    }

    if (!empty($errors)) {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = $data;
        flash('error', 'Please fix the highlighted fields.');
        redirect(url('views/admin/students/create/create.php'));
    }

    try {
        $id = StudentService::create($data);
        flash('success', 'Student created successfully.');
        redirect(url('views/admin/students/index/index.php?created=1'));
    } catch (\Throwable $e) {
        $_SESSION['_errors'] = ['general' => 'Unable to create student: ' . $e->getMessage()];
        $_SESSION['_old'] = $data;
        flash('error', 'Unable to create student.');
        redirect(url('views/admin/students/create/create.php'));
    }
}

$pageTitle = 'Add Student';
$errors = $_SESSION['_errors'] ?? [];
unset($_SESSION['_errors']);
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);

$houses = FirebaseService::getInstance()->getCollection(COL_HOUSES, [], 100);

$classRecords = FirebaseService::getInstance()->getCollection('classes', [], 500);
$classOptions = array_values(array_unique(array_filter(array_map(
    static fn(array $class): string => (string) ($class['className'] ?? ''),
    array_filter($classRecords, static fn(array $class): bool => ($class['status'] ?? 'active') === 'active')
))));
if (empty($classOptions)) {
    $classOptions = ['SHS 1', 'SHS 2', 'SHS 3'];
}
$currentClass = $old['class'] ?? '';
if ($currentClass !== '' && !in_array($currentClass, $classOptions, true)) {
    $classOptions[] = $currentClass;
}

$formOptions = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];
$currentForm = $old['form'] ?? $old['level'] ?? '';
if ($currentForm !== '' && !in_array($currentForm, $formOptions, true)) {
    $formOptions[] = $currentForm;
}

$courseOptions = array_values(array_unique(array_filter(array_map(
    static fn(array $c): string => trim((string) ($c['classCode'] ?? '')),
    $classRecords
))));
$currentCourse = $old['course'] ?? '';
if ($currentCourse !== '' && !in_array($currentCourse, $courseOptions, true)) {
    $courseOptions[] = $currentCourse;
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/admin/students/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-person-plus-fill text-primary me-2"></i>Add New Student
                </h4>
                <p class="text-muted mb-0">Register a new resident student into the dormitory system</p>
            </div>
            <div>
                <a href="<?= url('views/admin/students/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Students
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0" style="max-width: 860px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Student Registration Form</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/admin/students/create/create.php') ?>">
                    <h6 class="text-muted text-uppercase small fw-bold mb-3"><i class="bi bi-person me-1"></i>Personal Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input name="firstName" class="form-control" value="<?= e($old['firstName'] ?? '') ?>" placeholder="Enter first name" required>
                            <?php if (!empty($errors['firstName'])): ?><div class="text-danger small mt-1"><?= e(is_array($errors['firstName']) ? ($errors['firstName'][0] ?? '') : $errors['firstName']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input name="lastName" class="form-control" value="<?= e($old['lastName'] ?? '') ?>" placeholder="Enter last name" required>
                            <?php if (!empty($errors['lastName'])): ?><div class="text-danger small mt-1"><?= e(is_array($errors['lastName']) ? ($errors['lastName'][0] ?? '') : $errors['lastName']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Admission No.</label>
                            <input name="admissionNo" class="form-control" value="<?= e($old['admissionNo'] ?? '') ?>" placeholder="e.g. ADM-2026-001">
                            <?php if (!empty($errors['admissionNo'])): ?><div class="text-danger small mt-1"><?= e(is_array($errors['admissionNo']) ? ($errors['admissionNo'][0] ?? '') : $errors['admissionNo']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Gender</label>
                            <select name="gender" class="form-select select2">
                                <option value="">Select Gender</option>
                                <option value="male" <?= strtolower((string) ($old['gender'] ?? '')) === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= strtolower((string) ($old['gender'] ?? '')) === 'female' ? 'selected' : '' ?>>Female</option>
                                <option value="other" <?= strtolower((string) ($old['gender'] ?? '')) === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                            <?php if (!empty($errors['gender'])): ?><div class="text-danger small mt-1"><?= e(is_array($errors['gender']) ? ($errors['gender'][0] ?? '') : $errors['gender']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Class</label>
                            <select name="class" class="form-select select2">
                                <option value="">Select Class</option>
                                <?php foreach ($classOptions as $classOption): ?>
                                    <option value="<?= e($classOption) ?>" <?= ($old['class'] ?? '') === $classOption ? 'selected' : '' ?>>
                                        <?= e($classOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['class'])): ?><div class="text-danger small mt-1"><?= e(is_array($errors['class']) ? ($errors['class'][0] ?? '') : $errors['class']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Form / Level</label>
                            <select name="form" class="form-select select2">
                                <option value="">Select Form</option>
                                <?php foreach ($formOptions as $formOption): ?>
                                    <option value="<?= e($formOption) ?>" <?= ($currentForm === $formOption) ? 'selected' : '' ?>>
                                        <?= e($formOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['form'])): ?><div class="text-danger small mt-1"><?= e(is_array($errors['form']) ? ($errors['form'][0] ?? '') : $errors['form']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Class Code</label>
                            <select name="course" class="form-select select2">
                                <option value="">Select Class Code</option>
                                <?php foreach ($courseOptions as $courseOption): ?>
                                    <option value="<?= e($courseOption) ?>" <?= ($currentCourse === $courseOption) ? 'selected' : '' ?>>
                                        <?= e($courseOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['course'])): ?><div class="text-danger small mt-1"><?= e(is_array($errors['course']) ? ($errors['course'][0] ?? '') : $errors['course']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">NHIS Number</label>
                            <input name="nhisNumber" class="form-control" value="<?= e($old['nhisNumber'] ?? '') ?>" placeholder="Enter NHIS number">
                            <?php if (!empty($errors['nhisNumber'])): ?><div class="text-danger small mt-1"><?= e(is_array($errors['nhisNumber']) ? ($errors['nhisNumber'][0] ?? '') : $errors['nhisNumber']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase small fw-bold mb-3"><i class="bi bi-building me-1"></i>Housing & Status</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">House</label>
                            <select name="houseId" class="form-select select2">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($houses as $h): ?>
                                    <option value="<?= e($h['id']) ?>" <?= (($old['houseId'] ?? '') === $h['id']) ? 'selected' : '' ?>><?= e($h['name'] ?? $h['id']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= ($old['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="suspended" <?= ($old['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                <option value="graduated" <?= ($old['status'] ?? '') === 'graduated' ? 'selected' : '' ?>>Graduated</option>
                            </select>
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase small fw-bold mb-3"><i class="bi bi-person-heart me-1"></i>Parent / Guardian Details</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Guardian Name</label>
                            <input name="guardianName" class="form-control" value="<?= e($old['guardianName'] ?? '') ?>" placeholder="Enter guardian full name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Guardian Phone</label>
                            <input name="guardianPhone" class="form-control" value="<?= e($old['guardianPhone'] ?? '') ?>" placeholder="e.g. +233 24 000 0000">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Guardian Email</label>
                            <input type="email" name="guardianEmail" class="form-control" value="<?= e($old['guardianEmail'] ?? '') ?>" placeholder="guardian@example.com">
                            <?php if (!empty($errors['guardianEmail'])): ?><div class="text-danger small mt-1"><?= e(is_array($errors['guardianEmail']) ? ($errors['guardianEmail'][0] ?? '') : $errors['guardianEmail']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Student</button>
                        <a href="<?= url('views/admin/students/index/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
