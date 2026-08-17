<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

$pageTitle = 'Visitor Check-Out';
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
            <h5 class="mb-3">Visitor Check-Out</h5>
            <form method="POST" action="<?= url('views/visitors/check-out.php') ?>">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Visitor ID</label>
                        <input type="text" name="visitorId" class="form-control" required>
                    </div>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary" type="submit">Check Out</button>
                    <a href="<?= url('views/visitors/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
