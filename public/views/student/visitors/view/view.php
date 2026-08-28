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

use App\Services\FirebaseService;

$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? null;
$id = sanitize($_GET['id'] ?? '');
$visitor = FirebaseService::getInstance()->getDocument(COL_VISITOR_REQUESTS, $id);

if (!$visitor || ((string) ($visitor['studentId'] ?? '')) !== ((string) $studentId)) {
    flash('error', 'Visitor request not found.');
    redirect(url('views/student/visitors/index/index.php'));
}

$pageTitle = 'Visitor Request Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><?= e($visitor['visitorName'] ?? 'Visitor') ?></h5>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/visitors/index/index.php') ?>">Back</a>
            </div>
            <dl class="row mt-4">
                <dt class="col-sm-4">Relationship</dt>
                <dd class="col-sm-8"><?= e($visitor['relationship'] ?? '—') ?></dd>

                <dt class="col-sm-4">Visit Date</dt>
                <dd class="col-sm-8"><?= e($visitor['visitDate'] ?? '—') ?></dd>

                <dt class="col-sm-4">Purpose</dt>
                <dd class="col-sm-8"><?= e($visitor['purpose'] ?? '—') ?></dd>

                <dt class="col-sm-4">Status</dt>
                <dd class="col-sm-8">
                    <span class="badge bg-<?= ($visitor['status'] ?? '') === 'approved' ? 'success' : (($visitor['status'] ?? '') === 'rejected' ? 'danger' : 'warning') ?>">
                        <?= e(ucfirst($visitor['status'] ?? 'pending')) ?>
                    </span>
                </dd>
            </dl>
            <div class="mt-4">
                <?php if (($visitor['status'] ?? 'pending') === 'pending'): ?>
                    <a class="btn btn-primary" href="<?= url('views/student/visitors/edit/edit.php?id=' . urlencode($id)) ?>"><i class="bi bi-pencil me-1"></i> Edit Request</a>
                    <a class="btn btn-outline-danger ms-1" href="<?= url('views/student/visitors/delete/delete.php?id=' . urlencode($id)) ?>"><i class="bi bi-trash me-1"></i> Delete</a>
                <?php endif; ?>
                <a class="btn btn-outline-secondary ms-1" href="<?= url('views/student/visitors/index/index.php') ?>">Back to list</a>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>