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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

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
    redirect(base_url('index.php?route=/views/security/visitors/visitors.php'));
}

$pageTitle = 'Register Visitor';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php')],
    ['icon' => 'bi-person-plus', 'label' => 'Register Visitor', 'href' => url('views/security/register-visitor/register-visitor.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper security-portal">
        <section class="security-hero mb-4"><div class="security-hero-icon"><i class="bi bi-person-plus"></i></div><div><span class="security-kicker">Gate intake</span><h1>Register visitor</h1><p>Create a verified visitor record before arrival is approved.</p></div><a class="btn btn-light" href="<?= url('views/security/visitors/visitors.php') ?>"><i class="bi bi-arrow-left me-1"></i>Visitor directory</a></section>
        <div class="security-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Register Visitor</h5>
                <a href="<?= url('views/security/visitors/visitors.php') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
            </div>
            <form method="POST" action="<?= url('views/security/register-visitor/register-visitor.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Visitor Name</label><input type="text" name="visitorName" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Host Student</label><select name="studentId" class="form-select" required><option value="">Select student</option><?php foreach ($students as $student): ?><option value="<?= e((string) ($student['id'] ?? '')) ?>"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (<?= e($student['admissionNo'] ?? 'No ID') ?>)</option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">ID Type</label><input type="text" name="idType" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">ID Number</label><input type="text" name="idNumber" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Purpose</label><textarea name="purpose" class="form-control" rows="4" required></textarea></div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-person-check me-1"></i>Register visitor</button>
                    <a href="<?= url('views/security/visitors/visitors.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
