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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\IncidentService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$houseId = current_user()['houseId'] ?? null;
$service = new IncidentService();
$incident = null;
foreach ($service->byHouse($houseId) as $record) {
    if (($record['id'] ?? '') === $id) {
        $incident = $record;
        break;
    }
}

if (!$incident) {
    flash('error', 'Incident not found.');
    redirect(url('views/house-master/incidents/index/index.php'));
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
        redirect(url('views/house-master/incidents/view/view.php?id=' . urlencode($id)));
    }
}

$pageTitle = 'Edit Incident';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Incident Record</h4>
                <p class="text-muted mb-0">Update case investigation status, priority, and notes</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/incidents/view/view.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-eye me-1"></i>View Incident
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/incidents/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <!-- Form Card -->
        <div class="card stat-card shadow-sm border-0" style="max-width: 860px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-shield-exclamation me-2 text-warning"></i>Incident Details</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/house-master/incidents/edit/edit.php?id=' . urlencode($id)) ?>">
                    <input type="hidden" name="id" value="<?= e($id) ?>">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Incident Title <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-type"></i></span>
                                <input name="title" class="form-control" value="<?= e($incident['title'] ?? $incident['type'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Priority Level</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-exclamation-triangle"></i></span>
                                <select name="priority" class="form-select">
                                    <option value="low" <?= ($incident['priority'] ?? $incident['severity'] ?? '') === 'low' ? 'selected' : '' ?>>Low Priority</option>
                                    <option value="medium" <?= ($incident['priority'] ?? $incident['severity'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium Priority</option>
                                    <option value="high" <?= ($incident['priority'] ?? $incident['severity'] ?? '') === 'high' ? 'selected' : '' ?>>High Priority</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Case Status</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-check2-circle"></i></span>
                                <select name="status" class="form-select">
                                    <option value="open" <?= ($incident['status'] ?? '') === 'open' ? 'selected' : '' ?>>Open (Under Review)</option>
                                    <option value="investigating" <?= ($incident['status'] ?? '') === 'investigating' ? 'selected' : '' ?>>Investigating</option>
                                    <option value="resolved" <?= ($incident['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved / Closed</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Incident Description & Investigation Notes</label>
                            <textarea name="description" class="form-control" rows="5"><?= e($incident['description'] ?? $incident['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/incidents/view/view.php?id=' . urlencode($id)) ?>">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>