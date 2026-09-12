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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\IncidentService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$service = new IncidentService();
$incident = $service->find($id);
if (!$incident) {
    flash('error', 'Incident not found.');
    redirect(url('views/admin/incidents/index/index.php'));
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
        redirect(url('views/admin/incidents/view/view.php?id=' . urlencode($id)));
    }
}

$pageTitle = 'Edit Incident';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/admin/incidents/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-pencil-square text-warning me-2"></i>Edit Incident: <?= e($incident['title'] ?? 'Incident') ?>
                </h4>
                <p class="text-muted mb-0">Update case details, status resolution, or priority escalation</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('views/admin/incidents/view/view.php?id=' . urlencode($id)) ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-eye me-1"></i> View Details
                </a>
                <a href="<?= url('views/admin/incidents/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Incidents
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0" style="max-width: 760px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Incident Modification Form</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="id" value="<?= e($id) ?>">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Incident Title <span class="text-danger">*</span></label>
                            <input name="title" class="form-control" value="<?= e($incident['title'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Priority Level</label>
                            <select name="priority" class="form-select">
                                <option value="low" <?= ($incident['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low Priority</option>
                                <option value="medium" <?= ($incident['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium Priority</option>
                                <option value="high" <?= ($incident['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High Priority (Urgent)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Case Status</label>
                            <select name="status" class="form-select">
                                <option value="open" <?= ($incident['status'] ?? 'open') === 'open' ? 'selected' : '' ?>>Open (Pending Action)</option>
                                <option value="investigating" <?= ($incident['status'] ?? '') === 'investigating' ? 'selected' : '' ?>>Investigating</option>
                                <option value="resolved" <?= ($incident['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved (Closed)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Detailed Description &amp; Investigation Notes</label>
                            <textarea name="description" class="form-control" rows="5" placeholder="Enter findings, disciplinary measures, or resolution details..."><?= e($incident['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
                        <a class="btn btn-outline-secondary" href="<?= url('views/admin/incidents/view/view.php?id=' . urlencode($id)) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>