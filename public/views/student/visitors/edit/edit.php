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
$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$visitor = FirebaseService::getInstance()->getDocument(COL_VISITOR_REQUESTS, $id);

if (!$visitor || ((string) ($visitor['studentId'] ?? '')) !== ((string) $studentId) || ($visitor['status'] ?? 'pending') !== 'pending') {
    flash('error', 'Only pending visitor requests can be edited.');
    redirect(url('views/student/visitors/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    FirebaseService::getInstance()->updateDocument(COL_VISITOR_REQUESTS, $id, [
        'visitorName' => sanitize($_POST['visitorName'] ?? ''),
        'relationship' => sanitize($_POST['relationship'] ?? ''),
        'visitDate' => sanitize($_POST['visitDate'] ?? ''),
        'purpose' => sanitize($_POST['purpose'] ?? ''),
    ]);
    flash('success', 'Visitor request updated.');
    redirect(url('views/student/visitors/view/view.php?id=' . urlencode($id)));
}

$pageTitle = 'Edit Visitor Request';
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
        <div class="card stat-card p-4" style="max-width:700px">
            <h5 class="mb-3">Edit Visitor Request</h5>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <div class="mb-3">
                    <label class="form-label">Visitor Name <span class="text-danger">*</span></label>
                    <input name="visitorName" class="form-control" value="<?= e($visitor['visitorName'] ?? '') ?>" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Relationship</label>
                        <input name="relationship" class="form-control" value="<?= e($visitor['relationship'] ?? '') ?>" placeholder="e.g. Parent, Sibling, Uncle">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Visit Date <span class="text-danger">*</span></label>
                        <input type="date" name="visitDate" class="form-control" value="<?= e($visitor['visitDate'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Purpose</label>
                    <textarea name="purpose" class="form-control" rows="3"><?= e($visitor['purpose'] ?? '') ?></textarea>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
                    <a class="btn btn-outline-secondary ms-1" href="<?= url('views/student/visitors/view/view.php?id=' . urlencode($id)) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>