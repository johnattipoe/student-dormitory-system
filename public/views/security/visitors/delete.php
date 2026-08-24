<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';

$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\VisitorService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$visitor = (new VisitorService())->find($id);

if (!$visitor) {
    flash('error', 'Visitor not found.');
    redirect(url('views/security/visitors/visitors.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    FirebaseService::getInstance()->deleteDocument(COL_VISITORS, $id);
    flash('success', 'Visitor deleted.');
    redirect(url('views/security/visitors/visitors.php'));
}

$pageTitle = 'Delete Visitor';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content security-portal">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>

    <div class="content-wrapper">
        <section class="security-hero mb-4">
            <div>
                <span class="security-eyebrow"><i class="bi bi-trash3"></i> Visitor management</span>
                <h1>Delete visitor record</h1>
                <p>Confirm the exact visitor before removing the record from the security log.</p>
            </div>
            <a class="btn btn-light" href="<?= url('views/security/visitors/view.php?id=' . urlencode($id)) ?>">
                <i class="bi bi-arrow-left"></i> Back to details
            </a>
        </section>

        <div class="security-card border-danger">
            <div class="security-card-header">
                <div>
                    <h2>Delete <?= e($visitor['visitorName'] ?? 'visitor') ?>?</h2>
                    <p>This removes the visitor entry from the system.</p>
                </div>
                <span class="badge bg-danger">Requires confirmation</span>
            </div>

            <dl class="row security-detail-list">
                <dt class="col-md-3">Visitor</dt>
                <dd class="col-md-9"><?= e($visitor['visitorName'] ?? 'Visitor') ?></dd>
                <dt class="col-md-3">Student / host</dt>
                <dd class="col-md-9"><?= e($visitor['studentId'] ?? 'Not assigned') ?></dd>
                <dt class="col-md-3">Status</dt>
                <dd class="col-md-9"><?= e(ucfirst((string) ($visitor['status'] ?? 'pending'))) ?></dd>
            </dl>

            <form method="POST" class="d-flex flex-wrap gap-2">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <button class="btn btn-danger" type="submit">
                    <i class="bi bi-trash3"></i> Confirm delete
                </button>
                <a class="btn btn-outline-secondary" href="<?= url('views/security/visitors/visitors.php') ?>">Cancel</a>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
