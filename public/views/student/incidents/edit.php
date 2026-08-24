<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\IncidentService;
use App\Services\StudentService;

$currentUser = current_user();
$studentId = $currentUser['studentId'] ?? $currentUser['uid'] ?? $currentUser['id'] ?? null;
$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$service = new IncidentService();
$incident = null;
foreach ($service->studentIncidents($studentId) as $record) {
    if ((string) ($record['id'] ?? '') === $id) {
        $incident = $record;
        break;
    }
}
if (!$incident) {
    flash('error', 'Incident not found.');
    redirect(url('views/student/incidents/index.php'));
}
$studentProfile = StudentService::find($studentId);
$studentName = $studentProfile
    ? trim(($studentProfile['firstName'] ?? '') . ' ' . ($studentProfile['lastName'] ?? ''))
    : (current_user()['name'] ?? 'My account');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $service->update($id, [
        'title' => sanitize($_POST['title'] ?? ''),
        'description' => sanitize($_POST['description'] ?? ''),
        'type' => sanitize($_POST['type'] ?? 'other'),
        'priority' => sanitize($_POST['priority'] ?? 'medium'),
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) {
        redirect(url('views/student/incidents/view.php?id=' . urlencode($id)));
    }
    $incident['title'] = $_POST['title'] ?? '';
    $incident['description'] = $_POST['description'] ?? '';
    $incident['type'] = $_POST['type'] ?? 'other';
    $incident['priority'] = $_POST['priority'] ?? 'medium';
}

$pageTitle = 'Edit Incident';
$navItems = [['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px">
            <h5 class="mb-3">Edit Incident</h5>
            <div class="text-muted mb-3">Name: <?= e($studentName) ?></div>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <label class="form-label">Title</label>
                <input name="title" class="form-control mb-3" value="<?= e($incident['title'] ?? '') ?>" required>
                <label class="form-label">Type</label>
                <select name="type" class="form-select mb-3">
                    <?php foreach (['discipline', 'medical', 'safety', 'property', 'other'] as $type): ?>
                        <option value="<?= e($type) ?>" <?= ($incident['type'] ?? 'other') === $type ? 'selected' : '' ?>><?= ucfirst($type) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select mb-3">
                    <?php foreach (['low', 'medium', 'high'] as $priority): ?>
                        <option value="<?= e($priority) ?>" <?= ($incident['priority'] ?? 'medium') === $priority ? 'selected' : '' ?>><?= ucfirst($priority) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="6" required><?= e($incident['description'] ?? '') ?></textarea>
                <div class="mt-4"><button class="btn btn-primary">Save changes</button> <a class="btn btn-outline-secondary" href="<?= url('views/student/incidents/view.php?id=' . urlencode($id)) ?>">Cancel</a></div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
