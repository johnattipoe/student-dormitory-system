<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';

$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\VisitorService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$service = new VisitorService();
$visitor = $service->find($id);

if (!$visitor) {
    flash('error', 'Visitor not found.');
    redirect(url('views/security/visitors/visitors.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service->update($id, [
        'visitorName' => sanitize($_POST['visitorName'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'purpose' => sanitize($_POST['purpose'] ?? ''),
    ]);
    flash('success', 'Visitor updated.');
    redirect(url('views/security/visitors/view.php?id=' . urlencode($id)));
}

$pageTitle = 'Edit Visitor';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php'), 'active' => true],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php')],
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
                <span class="security-eyebrow"><i class="bi bi-pencil-square"></i> Visitor management</span>
                <h1>Edit visitor</h1>
                <p>Keep visitor contact details and visit purpose accurate before check-in or check-out.</p>
            </div>
            <a class="btn btn-light" href="<?= url('views/security/visitors/view.php?id=' . urlencode($id)) ?>">
                <i class="bi bi-arrow-left"></i> Back to details
            </a>
        </section>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="security-card">
                    <div class="security-card-header">
                        <div>
                            <h2>Visitor record</h2>
                            <p>Update verified details from the visitor or the host student.</p>
                        </div>
                    </div>

                    <form method="POST" class="row g-3">
                        <input type="hidden" name="id" value="<?= e($id) ?>">
                        <div class="col-md-6">
                            <label class="form-label">Visitor name</label>
                            <input name="visitorName" class="form-control" value="<?= e($visitor['visitorName'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone number</label>
                            <input name="phone" class="form-control" value="<?= e($visitor['phone'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Purpose of visit</label>
                            <textarea name="purpose" class="form-control" rows="5"><?= e($visitor['purpose'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-check2-circle"></i> Save changes
                            </button>
                            <a class="btn btn-outline-secondary" href="<?= url('views/security/visitors/view.php?id=' . urlencode($id)) ?>">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="security-side-card">
                    <h3>Current status</h3>
                    <ul class="security-info-list">
                        <li><span>Student / host</span><strong><?= e($visitor['studentId'] ?? 'Not assigned') ?></strong></li>
                        <li><span>Status</span><strong><?= e(ucfirst((string) ($visitor['status'] ?? 'pending'))) ?></strong></li>
                        <li><span>Check-in</span><strong><?= e($visitor['checkInTime'] ?? 'Not checked in') ?></strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
