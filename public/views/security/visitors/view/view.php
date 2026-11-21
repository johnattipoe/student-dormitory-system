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
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#securityVisitorDetailEdit">
                    <i class="bi bi-pencil-square"></i> Edit
                </button>
                <?php if (in_array($status, ['registered', 'approved'], true)): ?>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#securityVisitorDetailCheckIn">
                        <i class="bi bi-box-arrow-in-right"></i> Check in
                    </button>
                <?php elseif ($status === 'inside'): ?>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#securityVisitorDetailCheckOut">
                        <i class="bi bi-box-arrow-right"></i> Check out
                    </button>
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
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#securityVisitorDetailEdit">Update visitor</button>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#securityVisitorDetailDelete">Delete record</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="securityVisitorDetailEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/security/visitors/edit/edit.php?id=' . urlencode($id)) ?>" class="security-modal-form">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <div class="modal-header"><h5 class="modal-title">Edit Visitor</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <label class="form-label">Visitor Name</label><input name="visitorName" class="form-control mb-3" value="<?= e($visitor['visitorName'] ?? '') ?>" required>
                    <label class="form-label">Phone</label><input name="phone" class="form-control mb-3" value="<?= e($visitor['phone'] ?? '') ?>">
                    <label class="form-label">Purpose</label><textarea name="purpose" class="form-control" rows="4"><?= e($visitor['purpose'] ?? '') ?></textarea>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="securityVisitorDetailDelete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title text-danger">Delete Visitor</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">Delete <strong><?= e($visitorName) ?></strong> from the visitor records?</div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/security/visitors/delete/delete.php?id=' . urlencode($id)) ?>"><input type="hidden" name="id" value="<?= e($id) ?>"><button type="submit" class="btn btn-danger">Delete</button></form></div>
    </div></div>
</div>
<?php if (in_array($status, ['registered', 'approved'], true)): ?>
    <div class="modal fade" id="securityVisitorDetailCheckIn" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="<?= url('views/security/visitor-check-in/visitor-check-in.php') ?>"><input type="hidden" name="returnToVisitorView" value="1"><input type="hidden" name="visitorId" value="<?= e($id) ?>"><div class="modal-header"><h5 class="modal-title">Confirm Visitor Check-in</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">Check in <strong><?= e($visitorName) ?></strong>?</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Confirm Check-in</button></div></form></div></div></div>
<?php elseif ($status === 'inside'): ?>
    <div class="modal fade" id="securityVisitorDetailCheckOut" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="<?= url('views/security/visitor-check-out/visitor-check-out.php') ?>"><input type="hidden" name="returnToVisitorView" value="1"><input type="hidden" name="visitorId" value="<?= e($id) ?>"><div class="modal-header"><h5 class="modal-title">Confirm Visitor Check-out</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">Check out <strong><?= e($visitorName) ?></strong>?</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning">Confirm Check-out</button></div></form></div></div></div>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
