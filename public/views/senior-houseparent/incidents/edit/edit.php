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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
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
    redirect(url('views/senior-houseparent/incidents/index/index.php'));
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
        redirect(url('views/senior-houseparent/incidents/view/view.php?id=' . urlencode($id)));
    }
}

$pageTitle = 'Edit Incident';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/senior-houseparent/incidents/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:720px">
            <h5 class="mb-3">Edit Incident</h5>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input name="title" class="form-control" value="<?= e($incident['title'] ?? $incident['type'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="low" <?= ($incident['priority'] ?? $incident['severity'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= ($incident['priority'] ?? $incident['severity'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= ($incident['priority'] ?? $incident['severity'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="open" <?= ($incident['status'] ?? '') === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="investigating" <?= ($incident['status'] ?? '') === 'investigating' ? 'selected' : '' ?>>Investigating</option>
                        <option value="resolved" <?= ($incident['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="5"><?= e($incident['description'] ?? $incident['notes'] ?? '') ?></textarea>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
                    <a class="btn btn-outline-secondary ms-1" href="<?= url('views/senior-houseparent/incidents/view/view.php?id=' . urlencode($id)) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>