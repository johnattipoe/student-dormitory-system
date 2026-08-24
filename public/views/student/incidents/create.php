<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\IncidentService;

$currentUser = current_user();
$studentId = $currentUser['studentId'] ?? $currentUser['uid'] ?? $currentUser['id'] ?? null;
if (!$studentId) {
    flash('error', 'Student profile not found.');
    redirect(url('views/student/incidents/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = (new IncidentService())->create([
        'title' => sanitize($_POST['title'] ?? ''),
        'description' => sanitize($_POST['description'] ?? ''),
        'type' => sanitize($_POST['type'] ?? 'other'),
        'priority' => sanitize($_POST['priority'] ?? 'medium'),
        'studentId' => $studentId,
        'reportedBy' => current_user()['uid'] ?? current_user()['id'] ?? $studentId,
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) {
        redirect(url('views/student/incidents/index.php'));
    }
}

$pageTitle = 'Create Incident';
$navItems = [['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Create Incident</h5>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/incidents/index.php') ?>">Back</a>
            </div>
            <form method="POST">
                <label class="form-label">Title</label>
                <input name="title" class="form-control mb-3" value="<?= e($_POST['title'] ?? '') ?>" required>
                <label class="form-label">Type</label>
                <select name="type" class="form-select mb-3">
                    <?php foreach (['discipline', 'medical', 'safety', 'property', 'other'] as $type): ?>
                        <option value="<?= e($type) ?>" <?= ($_POST['type'] ?? 'other') === $type ? 'selected' : '' ?>><?= ucfirst($type) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select mb-3">
                    <?php foreach (['low', 'medium', 'high'] as $priority): ?>
                        <option value="<?= e($priority) ?>" <?= ($_POST['priority'] ?? 'medium') === $priority ? 'selected' : '' ?>><?= ucfirst($priority) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="6" required><?= e($_POST['description'] ?? '') ?></textarea>
                <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-send me-1" aria-hidden="true"></i> Submit incident</button> <a class="btn btn-outline-secondary" href="<?= url('views/student/incidents/index.php') ?>">Cancel</a></div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
