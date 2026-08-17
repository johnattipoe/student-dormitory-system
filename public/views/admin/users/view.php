<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\UserService;

$pageTitle = 'View User';
$id = $_GET['id'] ?? null;
$userService = new UserService();
$user = $id ? $userService->find($id) : null;
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Users', 'href' => url('views/admin/users/index.php')],
    ['icon' => 'bi-eye', 'label' => 'View User', 'href' => url('views/admin/users/view.php?id=' . urlencode($id)), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <?php if (!$user): ?>
            <div class="alert alert-warning">User not found.</div>
        <?php else: ?>
            <div class="card stat-card p-4" style="max-width:720px;">
                <h5 class="mb-3">User Details</h5>
                <dl class="row">
                    <?php foreach (['Name' => 'name', 'Email' => 'email', 'Role' => 'role', 'Status' => 'status', 'House' => 'houseId'] as $label => $key): ?>
                        <dt class="col-sm-4 text-muted"><?= e($label) ?></dt>
                        <dd class="col-sm-8"><?= e($user[$key] ?? '-') ?></dd>
                    <?php endforeach; ?>
                </dl>
                <a href="<?= url('views/admin/users/index.php') ?>" class="btn btn-outline-secondary">Back to users</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>