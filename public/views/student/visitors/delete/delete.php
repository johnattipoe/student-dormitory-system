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

$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? current_user()['id'] ?? null;
$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$firebase = FirebaseService::getInstance();
$request = $id !== '' ? $firebase->getDocument(COL_VISITOR_REQUESTS, $id) : null;

if (!$request || (string) ($request['studentId'] ?? '') !== (string) $studentId || ($request['status'] ?? 'pending') !== 'pending') {
    flash('error', 'Only your pending visitor requests can be deleted.');
    redirect(url('views/student/visitors/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firebase->deleteDocument(COL_VISITOR_REQUESTS, $id);
    flash('success', 'Visitor request deleted.');
    $returnUrl = url('views/student/visitors/index/index.php');
    if (!empty($_POST['returnToStudentVisitors'])) {
        $returnParams = [
            'search' => sanitize($_POST['returnSearch'] ?? ''),
            'dateFrom' => sanitize($_POST['returnDateFrom'] ?? ''),
            'dateTo' => sanitize($_POST['returnDateTo'] ?? ''),
            'status' => sanitize($_POST['returnStatus'] ?? ''),
            'page' => max(1, (int) ($_POST['returnPage'] ?? 1)),
            'limit' => max(1, min(150, (int) ($_POST['returnLimit'] ?? app_config()['pagination_per_page'] ?? 25))),
        ];
        $returnQuery = http_build_query(array_filter($returnParams, static fn($value): bool => $value !== ''));
        $returnUrl = url('views/student/visitors/index/index.php' . ($returnQuery !== '' ? '?' . $returnQuery : ''));
    }
    redirect($returnUrl);
}

$pageTitle = 'Delete Visitor Request';
$navItems = [
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width: 600px">
            <h5 class="mb-3">Delete Visitor Request</h5>
            <p>Delete the pending request for <strong><?= e($request['visitorName'] ?? '') ?></strong>?</p>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1"></i>Delete Request</button>
                <a class="btn btn-outline-secondary" href="<?= url('views/student/visitors/index/index.php') ?>">Cancel</a>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
