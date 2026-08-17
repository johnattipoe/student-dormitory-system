<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT, ROLE_SECURITY, ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$id = $_GET['id'] ?? '';
$incident = $id ? FirebaseService::getInstance()->getDocument(COL_INCIDENTS, $id) : null;
$pageTitle = 'Incident Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/incidents/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <h5 class="mb-3">Incident Details</h5>
            <?php if ($incident): ?>
                <dl class="row mb-0">
                    <dt class="col-sm-3">Title</dt>
                    <dd class="col-sm-9"><?= e($incident['title'] ?? '') ?></dd>
                    <dt class="col-sm-3">Priority</dt>
                    <dd class="col-sm-9"><?= e($incident['priority'] ?? '') ?></dd>
                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9"><?= e($incident['status'] ?? '') ?></dd>
                    <dt class="col-sm-3">Student</dt>
                    <dd class="col-sm-9"><?= e($incident['studentId'] ?? '-') ?></dd>
                    <dt class="col-sm-3">Description</dt>
                    <dd class="col-sm-9"><?= e($incident['description'] ?? '') ?></dd>
                </dl>
            <?php else: ?>
                <div class="alert alert-warning">No incident selected.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
