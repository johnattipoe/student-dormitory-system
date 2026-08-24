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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\VisitorService;

$pageTitle = 'Visitor Request';
$errors = $_SESSION['_errors'] ?? []; unset($_SESSION['_errors']);
$old = $_SESSION['_old'] ?? []; unset($_SESSION['_old']);
$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? current_user()['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'studentId' => $studentId,
        'visitorName' => sanitize($_POST['visitorName'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'relationship' => sanitize($_POST['relationship'] ?? ''),
        'visitDate' => sanitize($_POST['visitDate'] ?? ''),
        'purpose' => sanitize($_POST['purpose'] ?? ''),
    ];
    $errors = validate_required($data, ['visitorName', 'visitDate']);

    if (empty($errors) && $studentId) {
        $result = (new VisitorService())->request($data);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        if ($result['success']) {
            redirect(url('views/student/visitors/index.php'));
        }
    } elseif (!$studentId) {
        flash('error', 'Student profile not found.');
        redirect(url('views/student/visitors/index.php'));
    }

    $_SESSION['_errors'] = $errors;
    $_SESSION['_old'] = $data;
    redirect(url('views/visitors/request.php'));
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-person-badge', 'label' => 'Visitor Request', 'href' => url('views/visitors/request.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:720px">
            <h5 class="mb-3">Submit Visitor Request</h5>
            <form method="POST" action="<?= url('views/visitors/request.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Visitor Name</label>
                        <input name="visitorName" class="form-control" value="<?= e($old['visitorName'] ?? '') ?>" required>
                        <?php if (!empty($errors['visitorName'])): ?><div class="text-danger small"><?= e($errors['visitorName']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input name="phone" class="form-control" value="<?= e($old['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Relationship</label>
                        <input name="relationship" class="form-control" value="<?= e($old['relationship'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Visit Date</label>
                        <input type="date" name="visitDate" class="form-control" value="<?= e($old['visitDate'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Purpose</label>
                        <textarea name="purpose" class="form-control"><?= e($old['purpose'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">Submit Request</button>
                    <a href="<?= url('views/student/visitors/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>