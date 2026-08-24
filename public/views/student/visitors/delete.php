<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? current_user()['id'] ?? null;
$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$firebase = FirebaseService::getInstance();
$request = $id !== '' ? $firebase->getDocument(COL_VISITOR_REQUESTS, $id) : null;

if (!$request || (string) ($request['studentId'] ?? '') !== (string) $studentId || ($request['status'] ?? 'pending') !== 'pending') {
    flash('error', 'Only your pending visitor requests can be deleted.');
    redirect(url('views/student/visitors/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firebase->deleteDocument(COL_VISITOR_REQUESTS, $id);
    flash('success', 'Visitor request deleted.');
    redirect(url('views/student/visitors/index.php'));
}

$pageTitle = 'Delete Visitor Request';
$navItems = [
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width: 600px">
            <h5 class="mb-3">Delete Visitor Request</h5>
            <p>Delete the pending request for <strong><?= e($request['visitorName'] ?? '') ?></strong>?</p>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1"></i>Delete Request</button>
                <a class="btn btn-outline-secondary" href="<?= url('views/student/visitors/index.php') ?>">Cancel</a>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
