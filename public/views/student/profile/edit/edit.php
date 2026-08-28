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
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;

$pageStyles = ['student-profile.css'];
$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? null;
$student = $studentId ? StudentService::find((string) $studentId) : null;

if (!$student && !empty(current_user()['email'])) {
    foreach (StudentService::all() as $candidate) {
        if ((string) ($candidate['email'] ?? '') === (string) current_user()['email']) {
            $student = $candidate;
            $studentId = $candidate['id'];
            break;
        }
    }
}

if (!$student) {
    flash('error', 'Student profile not found.');
    redirect(url('views/student/profile/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'phone' => sanitize($_POST['phone'] ?? ''),
        'guardianName' => sanitize($_POST['guardianName'] ?? ''),
        'guardianPhone' => sanitize($_POST['guardianPhone'] ?? ''),
        'guardianEmail' => sanitize($_POST['guardianEmail'] ?? ''),
    ];

    if ($data['guardianEmail'] !== '' && !validate_email($data['guardianEmail'])) {
        flash('error', 'Please provide a valid guardian email.');
    } else {
        StudentService::update((string) $studentId, $data);
        flash('success', 'Profile updated successfully.');
        redirect(url('views/student/profile/index/index.php'));
    }
}

$pageTitle = 'Edit Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:700px">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Edit Contact Information</h5>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/profile/index/index.php') ?>">Cancel</a>
            </div>

            <div class="p-3 bg-light rounded mb-3 small">
                <strong>Student:</strong> <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> 
                (<?= e($student['admissionNo'] ?? '') ?>) — <?= e($student['class'] ?? 'Class not set') ?>
            </div>

            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Student Contact Phone</label>
                        <input name="phone" class="form-control" value="<?= e($student['phone'] ?? '') ?>" placeholder="Your personal phone number">
                    </div>
                    <div class="col-12"><hr class="my-2"><h6 class="text-primary mb-0">Guardian / Emergency Contact</h6></div>
                    <div class="col-md-6">
                        <label class="form-label">Guardian Name</label>
                        <input name="guardianName" class="form-control" value="<?= e($student['guardianName'] ?? '') ?>" placeholder="Parent or Guardian name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Guardian Phone</label>
                        <input name="guardianPhone" class="form-control" value="<?= e($student['guardianPhone'] ?? '') ?>" placeholder="Guardian phone number">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Guardian Email</label>
                        <input type="email" name="guardianEmail" class="form-control" value="<?= e($student['guardianEmail'] ?? '') ?>" placeholder="guardian@example.com">
                    </div>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
                    <a class="btn btn-outline-secondary ms-1" href="<?= url('views/student/profile/index/index.php') ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>