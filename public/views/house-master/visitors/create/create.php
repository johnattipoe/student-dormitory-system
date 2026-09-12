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
use App\Services\VisitorService;

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = (new VisitorService())->register([
        'visitorName' => sanitize($_POST['visitorName'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'studentId' => sanitize($_POST['studentId'] ?? ''),
        'purpose' => sanitize($_POST['purpose'] ?? ''),
        'registeredBy' => current_user()['uid'] ?? current_user()['id'] ?? null,
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) {
        redirect(url('views/house-master/visitors/index/index.php'));
    }
}

$pageTitle = 'Register Visitor';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-person-plus-fill text-primary me-2"></i>Register House Visitor</h4>
                <p class="text-muted mb-0">Log an incoming guest visiting a student in your house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/visitors/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back to Visitors
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0" style="max-width: 760px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clipboard-plus me-2 text-primary"></i>Visitor Registration Form</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/house-master/visitors/create/create.php') ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Visitor Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input name="visitorName" class="form-control" placeholder="e.g. Mr. Kofi Acheampong" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone / Contact Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-telephone"></i></span>
                                <input name="phone" class="form-control" placeholder="+233 XX XXX XXXX">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Student Being Visited <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-mortarboard"></i></span>
                                <select name="studentId" class="form-select" required>
                                    <option value="">Select resident student</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= e((string) ($student['id'] ?? '')) ?>">
                                            <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (<?= e($student['admissionNo'] ?? '') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Purpose of Visit</label>
                            <textarea name="purpose" class="form-control" rows="3" placeholder="Reason for visiting the student..."></textarea>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/visitors/index/index.php') ?>">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-check me-1"></i>Register Visitor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>