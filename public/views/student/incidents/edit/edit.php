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
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

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
    redirect(url('views/student/incidents/index/index.php'));
}
$studentProfile = StudentService::find($studentId);
$studentName = $studentProfile
    ? trim(($studentProfile['firstName'] ?? '') . ' ' . ($studentProfile['lastName'] ?? ''))
    : (current_user()['name'] ?? 'My account');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnParams = [
        'search' => sanitize($_POST['returnSearch'] ?? ''),
        'dateFrom' => sanitize($_POST['returnDateFrom'] ?? ''),
        'dateTo' => sanitize($_POST['returnDateTo'] ?? ''),
        'severity' => sanitize($_POST['returnSeverity'] ?? ''),
        'status' => sanitize($_POST['returnStatus'] ?? ''),
        'page' => max(1, (int) ($_POST['returnPage'] ?? 1)),
        'limit' => max(1, min(150, (int) ($_POST['returnLimit'] ?? app_config()['pagination_per_page'] ?? 25))),
    ];
    $returnQuery = http_build_query(array_filter($returnParams, static fn($value): bool => $value !== ''));
    $incidentListPath = 'views/student/incidents/index/index.php' . ($returnQuery !== '' ? '?' . $returnQuery : '');
    $result = $service->update($id, [
        'title' => sanitize($_POST['title'] ?? ''),
        'description' => sanitize($_POST['description'] ?? ''),
        'type' => sanitize($_POST['type'] ?? 'other'),
        'priority' => sanitize($_POST['priority'] ?? 'medium'),
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) {
        redirect(!empty($_POST['returnToIncidentList'])
            ? url($incidentListPath)
            : url('views/student/incidents/view/view.php?id=' . urlencode($id)));
    }
    if (!empty($_POST['returnToIncidentList']) || !empty($_POST['returnToIncidentView'])) {
        $_SESSION['_student_incident_edit_id'] = $id;
        $_SESSION['_student_incident_edit_old'] = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'type' => $_POST['type'] ?? 'other',
            'priority' => $_POST['priority'] ?? 'medium',
        ];
        $_SESSION['_student_incident_edit_errors'] = ['general' => $result['message']];
        redirect(!empty($_POST['returnToIncidentList'])
            ? url($incidentListPath . (strpos($incidentListPath, '?') === false ? '?' : '&') . 'openIncidentEdit=1')
            : url('views/student/incidents/view/view.php?id=' . urlencode($id) . '&openEditModal=1'));
    }
    $incident['title'] = $_POST['title'] ?? '';
    $incident['description'] = $_POST['description'] ?? '';
    $incident['type'] = $_POST['type'] ?? 'other';
    $incident['priority'] = $_POST['priority'] ?? 'medium';
}

$pageTitle = 'Edit Incident';
$navItems = [['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index/index.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
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
                <div class="mt-4"><button class="btn btn-primary">Save changes</button> <a class="btn btn-outline-secondary" href="<?= url('views/student/incidents/view/view.php?id=' . urlencode($id)) ?>">Cancel</a></div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
