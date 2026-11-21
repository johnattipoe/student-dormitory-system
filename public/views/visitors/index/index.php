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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\VisitorService;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register_visitor') {
    if (!in_array(current_role(), [ROLE_ADMIN, ROLE_SECURITY], true)) {
        http_response_code(403);
        exit('You are not authorized to register visitors.');
    }
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'The form expired. Please refresh the page and try again.');
        redirect(url('views/visitors/index/index.php'));
    }

    $result = (new VisitorService())->register([
        'visitorName' => sanitize($_POST['visitorName'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'studentId' => sanitize($_POST['studentId'] ?? ''),
        'purpose' => sanitize($_POST['purpose'] ?? ''),
        'registeredBy' => current_user()['uid'] ?? null,
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('views/visitors/index/index.php'));
}

$pageTitle = 'Visitors';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$allVisitors = FirebaseService::getInstance()->getCollection(COL_VISITORS, [], 200);
$totalVisitors = count($allVisitors);
$totalPages = max(1, (int) ceil($totalVisitors / $limit));
$visitors = array_slice($allVisitors, ($page - 1) * $limit, $limit);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-person-badge', 'label' => 'Visitors', 'href' => url('views/visitors/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Visitors</h5>
            <?php if (in_array(current_role(), [ROLE_ADMIN, ROLE_SECURITY], true)): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#visitorCheckInModal">Register Visitor</button>
            <?php endif; ?>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Student</th>
                    <th>Purpose</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($visitors as $visitor): ?>
                    <tr>
                        <td><?= e($visitor['visitorName'] ?? '') ?></td>
                        <td><?= e($visitor['studentId'] ?? '-') ?></td>
                        <td><?= e($visitor['purpose'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= ($visitor['status'] ?? '') === 'inside' ? 'success' : 'secondary' ?>"><?= e($visitor['status'] ?? '-') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php $paginationBaseUrl = url('views/visitors/index/index.php'); require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
        </div>
    </div>
</div>
<div class="modal fade" id="visitorCheckInModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST" action="<?= url('views/visitors/index/index.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="register_visitor">
            <div class="modal-header"><h5 class="modal-title">Register Visitor</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Visitor Name</label><input name="visitorName" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Student ID</label><input name="studentId" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" type="tel"></div>
                <div><label class="form-label">Purpose</label><input name="purpose" class="form-control" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Register</button></div>
        </form>
    </div></div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
