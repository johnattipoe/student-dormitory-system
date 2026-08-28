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

$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\VisitorService;

$id = sanitize($_GET['id'] ?? '');
$visitor = (new VisitorService())->find($id);

if (!$visitor) {
    flash('error', 'Visitor not found.');
    redirect(url('views/security/visitors/visitors/visitors.php'));
}

$visitorName = $visitor['visitorName'] ?? 'Visitor';
$status = strtolower((string) ($visitor['status'] ?? 'pending'));
$pageTitle = 'Visitor Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors/visitors.php'), 'active' => true],
    ['icon' => 'bi-box-arrow-in-right', 'label' => 'Check In', 'href' => url('views/security/visitor-check-in/visitor-check-in.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents/incidents.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content security-portal">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">
        <section class="security-hero mb-4">
            <div>
                <span class="security-eyebrow"><i class="bi bi-person-vcard"></i> Visitor profile</span>
                <h1><?= e($visitorName) ?></h1>
                <p>Review visitor identity, purpose, contact details, and current movement status.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-light" href="<?= url('views/security/visitors/visitors/visitors.php') ?>">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <a class="btn btn-warning" href="<?= url('views/security/visitors/edit/edit.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-pencil-square"></i> Edit
                </a>
                <?php if ($status !== 'inside'): ?>
                    <a class="btn btn-success" href="<?= url('views/security/visitor-check-in/visitor-check-in.php?id=' . urlencode($id)) ?>">
                        <i class="bi bi-box-arrow-in-right"></i> Check in
                    </a>
                <?php else: ?>
                    <a class="btn btn-danger" href="<?= url('views/security/visitor-check-out/visitor-check-out.php?id=' . urlencode($id)) ?>">
                        <i class="bi bi-box-arrow-right"></i> Check out
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="security-card">
                    <div class="security-card-header">
                        <div>
                            <h2>Visitor information</h2>
                            <p>Primary record details kept by the security desk.</p>
                        </div>
                        <span class="badge <?= $status === 'inside' ? 'bg-success' : ($status === 'pending' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                            <?= e(ucfirst($status ?: 'pending')) ?>
                        </span>
                    </div>

                    <dl class="row security-detail-list">
                        <dt class="col-md-4">Full name</dt>
                        <dd class="col-md-8"><?= e($visitorName) ?></dd>

                        <dt class="col-md-4">Student / host</dt>
                        <dd class="col-md-8"><?= e($visitor['studentId'] ?? 'Not assigned') ?></dd>

                        <dt class="col-md-4">Phone number</dt>
                        <dd class="col-md-8"><?= e($visitor['phone'] ?? 'Not provided') ?></dd>

                        <dt class="col-md-4">Purpose of visit</dt>
                        <dd class="col-md-8"><?= nl2br(e($visitor['purpose'] ?? 'Not provided')) ?></dd>

                        <dt class="col-md-4">Created</dt>
                        <dd class="col-md-8"><?= e($visitor['createdAt'] ?? 'Not recorded') ?></dd>
                    </dl>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="security-side-card">
                    <h3>Movement timeline</h3>
                    <ul class="security-info-list">
                        <li><span>Check-in time</span><strong><?= e($visitor['checkInTime'] ?? 'Not checked in') ?></strong></li>
                        <li><span>Check-out time</span><strong><?= e($visitor['checkOutTime'] ?? 'Not checked out') ?></strong></li>
                        <li><span>Current status</span><strong><?= e(ucfirst($status ?: 'pending')) ?></strong></li>
                    </ul>
                </div>

                <div class="security-side-card mt-4">
                    <h3>Record actions</h3>
                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-primary" href="<?= url('views/security/visitors/edit/edit.php?id=' . urlencode($id)) ?>">Update visitor</a>
                        <a class="btn btn-outline-danger" href="<?= url('views/security/visitors/delete/delete.php?id=' . urlencode($id)) ?>">Delete record</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
