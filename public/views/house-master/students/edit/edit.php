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

use App\Services\StudentService;
use App\Services\FirebaseService;

$id = sanitize($_GET['studentId'] ?? $_GET['id'] ?? $_POST['studentId'] ?? $_POST['id'] ?? '');
$currentUser = current_user() ?? [];
$houseId = $currentUser['houseId'] ?? null;
$student = $id ? StudentService::find($id) : null;
if (!is_array($student) || ($student['houseId'] ?? null) !== $houseId) {
    flash('error', 'Student not found in your assigned house.');
    redirect(url('views/house-master/students/index/index.php'));
}

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
    static function (array $class): string {
        $code = trim((string) ($class['classCode'] ?? ''));
        return $code !== '' ? $code : trim((string) ($class['className'] ?? ''));
    },
    array_filter($classRecords, static fn(array $class): bool => ($class['status'] ?? 'active') === 'active')
))));
if (empty($classOptions)) {
    $classOptions = ['SHS 1A', 'SHS 1B', 'SHS 2A', 'SHS 2B', 'SHS 3A', 'SHS 3B'];
}
if (($student['class'] ?? '') !== '' && !in_array($student['class'], $classOptions, true)) {
    $classOptions[] = $student['class'];
}
$formOptions = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formVal = sanitize($_POST['form'] ?? $_POST['level'] ?? ($student['form'] ?? $student['level'] ?? 'Form 1'));
    $data = [
        'firstName' => sanitize($_POST['firstName'] ?? ''),
        'lastName' => sanitize($_POST['lastName'] ?? ''),
        'class' => sanitize($_POST['class'] ?? ''),
        'form' => $formVal,
        'level' => $formVal,
        'nhisNumber' => sanitize($_POST['nhisNumber'] ?? ''),
        'admissionNo' => sanitize($_POST['admissionNo'] ?? ''),
        'course' => sanitize($_POST['course'] ?? ''),
        'gender' => sanitize($_POST['gender'] ?? 'Male'),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'status' => sanitize($_POST['status'] ?? 'active'),
        'guardianName' => sanitize($_POST['guardianName'] ?? ''),
        'guardianPhone' => sanitize($_POST['guardianPhone'] ?? ''),
        'guardianEmail' => sanitize($_POST['guardianEmail'] ?? ''),
    ];

    $errors = validate_required($data, ['firstName', 'lastName', 'admissionNo']);
    if ($data['guardianEmail'] !== '' && !validate_email($data['guardianEmail'])) {
        $errors['guardianEmail'] = 'Guardian email is invalid.';
    }

    if (!empty($errors)) {
        flash('error', 'Please fix the highlighted errors.');
    } else {
        StudentService::update($id, $data);
        flash('success', 'Student profile updated successfully.');
        redirect(url('views/house-master/students/profile/profile.php?studentId=' . urlencode($id)));
    }
    $student = array_merge($student, $data);
}

$pageTitle = 'Edit Student';
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Student Profile</h4>
                <p class="text-muted mb-0">Modifying profile of <strong><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></strong></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/students/profile/profile.php?studentId=' . urlencode($id)) ?>">
                    <i class="bi bi-eye me-1"></i>View Profile
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/students/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <!-- Form Card -->
        <div class="card stat-card shadow-sm border-0" style="max-width: 880px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Update Student Information</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/house-master/students/edit/edit.php?studentId=' . urlencode($id)) ?>">
                    <input type="hidden" name="studentId" value="<?= e($id) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input name="firstName" class="form-control" value="<?= e($student['firstName'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input name="lastName" class="form-control" value="<?= e($student['lastName'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Admission No. <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-hash"></i></span>
                                <input name="admissionNo" class="form-control font-monospace" value="<?= e($student['admissionNo'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-gender-ambiguous"></i></span>
                                <select name="gender" class="form-select" required>
                                    <option value="Male" <?= ($student['gender'] ?? 'Male') === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($student['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Form / Level <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-mortarboard"></i></span>
                                <select name="form" class="form-select" required>
                                    <?php foreach ($formOptions as $form): ?>
                                        <option value="<?= e($form) ?>" <?= ($student['form'] ?? $student['level'] ?? 'Form 1') === $form ? 'selected' : '' ?>><?= e($form) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Class / Section</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-journal-bookmark"></i></span>
                                <select name="class" class="form-select">
                                    <option value="">Select Class / Section</option>
                                    <?php foreach ($classOptions as $opt): ?>
                                        <option value="<?= e($opt) ?>" <?= ($student['class'] ?? '') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Course / Programme</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-book"></i></span>
                                <select name="course" class="form-select">
                                    <option value="">Select Programme</option>
                                    <?php foreach ($courseOptions as $course): ?>
                                        <option value="<?= e($course) ?>" <?= ($student['course'] ?? '') === $course ? 'selected' : '' ?>><?= e($course) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">NHIS Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-card-text"></i></span>
                                <input name="nhisNumber" class="form-control" placeholder="NHIS Number" value="<?= e($student['nhisNumber'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Student Phone</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-telephone"></i></span>
                                <input name="phone" class="form-control" value="<?= e($student['phone'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Residence Status</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-check2-circle"></i></span>
                                <select name="status" class="form-select">
                                    <option value="active" <?= ($student['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= ($student['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="suspended" <?= ($student['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12 mt-4 pt-3 border-top">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-shield-check me-2 text-primary"></i>Guardian / Emergency Contact</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Guardian Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input name="guardianName" class="form-control" value="<?= e($student['guardianName'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Guardian Phone</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-telephone"></i></span>
                                <input name="guardianPhone" class="form-control" value="<?= e($student['guardianPhone'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Guardian Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="guardianEmail" class="form-control" value="<?= e($student['guardianEmail'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/students/profile/profile.php?studentId=' . urlencode($id)) ?>">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
