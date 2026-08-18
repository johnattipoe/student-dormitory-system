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
$allowedRoles = [ROLE_ADMIN, ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

$pageTitle = 'Visitor Check-In';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-person-badge', 'label' => 'Visitors', 'href' => url('views/visitors/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:720px;">
            <h5 class="mb-3">Visitor Check-In</h5>
            <form method="POST" action="<?= url('views/visitors/check-in.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Visitor Name</label>
                        <input type="text" name="visitorName" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Student</label>
                        <input type="text" name="studentId" class="form-control" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Purpose</label>
                        <input type="text" name="purpose" class="form-control" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary" type="submit">Check In</button>
                    <a href="<?= url('views/visitors/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
