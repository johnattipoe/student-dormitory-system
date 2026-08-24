<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';

$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\IncidentService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$service = new IncidentService();
$incident = $service->find($id);

if (!$incident) {
    flash('error', 'Incident not found.');
    redirect(url('views/security/incidents/incidents.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $service->update($id, [
        'title' => sanitize($_POST['title'] ?? ''),
        'description' => sanitize($_POST['description'] ?? ''),
        'priority' => sanitize($_POST['priority'] ?? 'medium'),
        'status' => sanitize($_POST['status'] ?? 'open'),
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) {
        redirect(url('views/security/incidents/view.php?id=' . urlencode($id)));
    }
}

$pageTitle = 'Edit Incident';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php'), 'active' => true],
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
                <span class="security-eyebrow"><i class="bi bi-pencil-square"></i> Incident management</span>
                <h1>Edit incident</h1>
                <p>Update priority, status, and notes as the case changes.</p>
            </div>
            <a class="btn btn-light" href="<?= url('views/security/incidents/view.php?id=' . urlencode($id)) ?>">
                <i class="bi bi-arrow-left"></i> Back to details
            </a>
        </section>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="security-card">
                    <div class="security-card-header">
                        <div>
                            <h2>Incident record</h2>
                            <p>Keep this record clear for follow-up by management.</p>
                        </div>
                    </div>

                    <form method="POST" class="row g-3">
                        <input type="hidden" name="id" value="<?= e($id) ?>">
                        <div class="col-12">
                            <label class="form-label">Title</label>
                            <input name="title" class="form-control" value="<?= e($incident['title'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <?php foreach (['low', 'medium', 'high'] as $priority): ?>
                                    <option value="<?= e($priority) ?>" <?= (($incident['priority'] ?? 'medium') === $priority) ? 'selected' : '' ?>>
                                        <?= e(ucfirst($priority)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['open', 'investigating', 'resolved'] as $status): ?>
                                    <option value="<?= e($status) ?>" <?= (($incident['status'] ?? 'open') === $status) ? 'selected' : '' ?>>
                                        <?= e(ucfirst($status)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="6"><?= e($incident['description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-check2-circle"></i> Save incident
                            </button>
                            <a class="btn btn-outline-secondary" href="<?= url('views/security/incidents/view.php?id=' . urlencode($id)) ?>">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="security-side-card">
                    <h3>Case snapshot</h3>
                    <ul class="security-info-list">
                        <li><span>Student</span><strong><?= e($incident['studentId'] ?? 'Not linked') ?></strong></li>
                        <li><span>Priority</span><strong><?= e(ucfirst((string) ($incident['priority'] ?? 'medium'))) ?></strong></li>
                        <li><span>Status</span><strong><?= e(ucfirst((string) ($incident['status'] ?? 'open'))) ?></strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
