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
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\VisitorService;
use App\Services\StudentService;

$students = StudentService::all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = (new VisitorService())->register([
        'visitorName' => sanitize($_POST['visitorName'] ?? ''),
        'studentId' => sanitize($_POST['studentId'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'idType' => sanitize($_POST['idType'] ?? ''),
        'idNumber' => sanitize($_POST['idNumber'] ?? ''),
        'purpose' => sanitize($_POST['purpose'] ?? ''),
        'registeredBy' => current_user()['uid'] ?? current_user()['id'] ?? null,
    ]);

    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/security/visitors/visitors/visitors.php'));
}

$pageTitle = 'Register Visitor';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents/incidents.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php')],
    ['icon' => 'bi-person-plus', 'label' => 'Register Visitor', 'href' => url('views/security/register-visitor/register-visitor.php'), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-person-plus-fill text-primary me-2"></i>Register New Visitor</h4>
                <p class="text-muted mb-0">Record gate intake details and create a verified visitor record</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/security/visitors/visitors/visitors.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back to Visitors
                </a>
            </div>
        </div>

        <!-- Form Card -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-card-checklist me-2 text-primary"></i>Visitor Intake Details</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/security/register-visitor/register-visitor.php') ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Visitor Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input type="text" name="visitorName" class="form-control" placeholder="e.g. John Doe" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Host Student <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-mortarboard"></i></span>
                                <select name="studentId" class="form-select" required>
                                    <option value="">Select student being visited</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= e((string) ($student['id'] ?? '')) ?>">
                                            <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (<?= e($student['admissionNo'] ?? 'No ID') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-telephone"></i></span>
                                <input type="text" name="phone" class="form-control" placeholder="+233 XX XXX XXXX" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Identification Type <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-card-heading"></i></span>
                                <input type="text" name="idType" class="form-control" placeholder="National ID, Passport, Driver's License" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">ID / Document Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-hash"></i></span>
                                <input type="text" name="idNumber" class="form-control" placeholder="e.g. GHA-XXXXXXXXX-X" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Purpose of Visit <span class="text-danger">*</span></label>
                            <textarea name="purpose" class="form-control" rows="3" placeholder="Describe the reason for the visit (e.g., Parent meeting, parcel delivery)..." required></textarea>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-top d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-check me-1"></i>Register Visitor
                        </button>
                        <a href="<?= url('views/security/visitors/visitors/visitors.php') ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
