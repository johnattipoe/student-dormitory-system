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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\HouseService;
use App\Services\FirebaseService;
use App\Services\StudentService;

$houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
if ($houseId === '' || !$house) {
    access_denied();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formVal = sanitize($_POST['form'] ?? $_POST['level'] ?? 'Form 1');
    $data = [
        'firstName' => sanitize($_POST['firstName'] ?? ''),
        'lastName' => sanitize($_POST['lastName'] ?? ''),
        'class' => sanitize($_POST['class'] ?? ''),
        'form' => $formVal,
        'level' => $formVal,
        'gender' => sanitize($_POST['gender'] ?? 'Male'),
        'nhisNumber' => sanitize($_POST['nhisNumber'] ?? ''),
        'admissionNo' => sanitize($_POST['admissionNo'] ?? ''),
        'course' => sanitize($_POST['course'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'houseId' => $houseId,
        'guardianName' => sanitize($_POST['guardianName'] ?? ''),
        'guardianPhone' => sanitize($_POST['guardianPhone'] ?? ''),
        'guardianEmail' => sanitize($_POST['guardianEmail'] ?? ''),
        'status' => sanitize($_POST['status'] ?? 'active'),
    ];

    $errors = validate_required($data, ['firstName', 'lastName', 'admissionNo']);
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
            redirect(url('views/house-master/students/index/index.php'));
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

$courseOptions = [
    'General Science',
    'General Arts',
    'Business',
    'Home Economics',
    'Visual Arts',
    'Agricultural Science',
    'Technical',
];
$classRecords = FirebaseService::getInstance()->getCollection('classes', [], 500);
$classOptions = array_values(array_unique(array_filter(array_map(
    static fn(array $class): string => (string) ($class['className'] ?? ''),
    array_filter($classRecords, static fn(array $class): bool => ($class['status'] ?? 'active') === 'active')
))));
if (empty($classOptions)) {
    $classOptions = ['SHS 1A', 'SHS 1B', 'SHS 2A', 'SHS 2B', 'SHS 3A', 'SHS 3B'];
}
$formOptions = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php'), 'active' => true],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-person-plus-fill text-primary me-2"></i>Add House Student</h4>
                <p class="text-muted mb-0">Enrolling resident student into <strong><?= e($house['name'] ?? 'Your House') ?></strong></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/house-master/students/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Students
                </a>
            </div>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="card stat-card shadow-sm border-0" style="max-width: 880px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Student Registration Form</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/house-master/students/create/create.php') ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input name="firstName" class="form-control <?= isset($errors['firstName']) ? 'is-invalid' : '' ?>" value="<?= e($old['firstName'] ?? '') ?>" placeholder="e.g. Kwesi" required>
                            </div>
                            <?php if (isset($errors['firstName'])): ?><div class="invalid-feedback d-block"><?= e($errors['firstName']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input name="lastName" class="form-control <?= isset($errors['lastName']) ? 'is-invalid' : '' ?>" value="<?= e($old['lastName'] ?? '') ?>" placeholder="e.g. Mensah" required>
                            </div>
                            <?php if (isset($errors['lastName'])): ?><div class="invalid-feedback d-block"><?= e($errors['lastName']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Admission Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-hash"></i></span>
                                <input name="admissionNo" class="form-control <?= isset($errors['admissionNo']) ? 'is-invalid' : '' ?>" value="<?= e($old['admissionNo'] ?? '') ?>" placeholder="e.g. STU-2026-001" required>
                            </div>
                            <?php if (isset($errors['admissionNo'])): ?><div class="invalid-feedback d-block"><?= e($errors['admissionNo']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-gender-ambiguous"></i></span>
                                <select name="gender" class="form-select" required>
                                    <option value="Male" <?= ($old['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($old['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Form / Level <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-mortarboard"></i></span>
                                <select name="form" class="form-select" required>
                                    <?php foreach ($formOptions as $form): ?>
                                        <option value="<?= e($form) ?>" <?= ($old['form'] ?? 'Form 1') === $form ? 'selected' : '' ?>><?= e($form) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Class / Section</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-journal-bookmark"></i></span>
                                <input name="class" class="form-control" list="classList" placeholder="e.g. Science 1" value="<?= e($old['class'] ?? '') ?>">
                            </div>
                            <datalist id="classList">
                                <?php foreach ($classOptions as $opt): ?>
                                    <option value="<?= e($opt) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Course / Programme</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-book"></i></span>
                                <select name="course" class="form-select">
                                    <option value="">Select Programme</option>
                                    <?php foreach ($courseOptions as $course): ?>
                                        <option value="<?= e($course) ?>" <?= ($old['course'] ?? '') === $course ? 'selected' : '' ?>><?= e($course) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">NHIS Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-card-text"></i></span>
                                <input name="nhisNumber" class="form-control" placeholder="Health Insurance ID" value="<?= e($old['nhisNumber'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Student Phone</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-telephone"></i></span>
                                <input name="phone" class="form-control" placeholder="Optional" value="<?= e($old['phone'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Residence Status</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-check2-circle"></i></span>
                                <select name="status" class="form-select">
                                    <option value="active" <?= ($old['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active (Resident)</option>
                                    <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="suspended" <?= ($old['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                </select>
                            </div>
                        </div>

                        <!-- Guardian Details Section -->
                        <div class="col-12 mt-4 pt-3 border-top">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-shield-check me-2 text-primary"></i>Guardian / Emergency Contact</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Guardian Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input name="guardianName" class="form-control" placeholder="Parent or Guardian" value="<?= e($old['guardianName'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Guardian Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-telephone"></i></span>
                                <input name="guardianPhone" class="form-control" placeholder="+233 XX XXX XXXX" value="<?= e($old['guardianPhone'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Guardian Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="guardianEmail" class="form-control <?= isset($errors['guardianEmail']) ? 'is-invalid' : '' ?>" placeholder="guardian@example.com" value="<?= e($old['guardianEmail'] ?? '') ?>">
                            </div>
                            <?php if (isset($errors['guardianEmail'])): ?><div class="invalid-feedback d-block"><?= e($errors['guardianEmail']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/students/index/index.php') ?>">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus me-1"></i>Add Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
