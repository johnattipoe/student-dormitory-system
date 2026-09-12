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

$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? current_user()['id'] ?? null;
$id = sanitize($_GET['id'] ?? '');
$incident = null;

foreach ((new IncidentService())->studentIncidents($studentId) as $record) {
    if ((string) ($record['id'] ?? '') === $id) {
        $incident = $record;
        break;
    }
}

if (!$incident) {
    flash('error', 'Incident not found.');
    redirect(url('views/student/incidents/index/index.php'));
}

$student = StudentService::find((string) $studentId);
$studentName = $student ? trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) : (current_user()['name'] ?? 'My account');
if ($student && !empty($student['admissionNo'])) {
    $studentName .= ' [' . $student['admissionNo'] . ']';
}

$priority = $incident['priority'] ?? $incident['severity'] ?? 'medium';
$pageTitle = 'Incident Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1"><?= e($incident['title'] ?? $incident['type'] ?? 'Incident') ?></h5>
                    <p class="text-muted mb-0">Reported <?= e(substr((string) ($incident['reportedAt'] ?? $incident['createdAt'] ?? ''), 0, 19)) ?></p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/incidents/index/index.php') ?>">Back</a>
            </div>
            <dl class="row mt-4">
                <dt class="col-sm-4">Reported By / Student</dt>
                <dd class="col-sm-8"><?= e($studentName) ?></dd>

                <dt class="col-sm-4">Category</dt>
                <dd class="col-sm-8"><?= e(ucfirst((string) ($incident['type'] ?? 'other'))) ?></dd>

                <dt class="col-sm-4">Priority</dt>
                <dd class="col-sm-8">
                    <span class="badge bg-<?= ($priority === 'high' ? 'danger' : ($priority === 'medium' ? 'warning text-dark' : 'secondary')) ?>">
                        <?= e(ucfirst((string) $priority)) ?>
                    </span>
                </dd>

                <dt class="col-sm-4">Status</dt>
                <dd class="col-sm-8">
                    <span class="badge bg-<?= ($incident['status'] ?? 'open') === 'resolved' ? 'success' : (($incident['status'] ?? '') === 'investigating' ? 'warning text-dark' : 'danger') ?>">
                        <?= e(ucfirst((string) ($incident['status'] ?? 'open'))) ?>
                    </span>
                </dd>

                <dt class="col-sm-4">Description</dt>
                <dd class="col-sm-8"><?= nl2br(e($incident['description'] ?? $incident['notes'] ?? '—')) ?></dd>
            </dl>
            <div class="mt-4">
                <?php if (($incident['status'] ?? 'open') === 'open'): ?>
                    <a class="btn btn-primary" href="<?= url('views/student/incidents/edit/edit.php?id=' . urlencode($id)) ?>"><i class="bi bi-pencil me-1"></i> Edit</a>
                    <a class="btn btn-outline-danger ms-1" href="<?= url('views/student/incidents/delete/delete.php?id=' . urlencode($id)) ?>"><i class="bi bi-trash me-1"></i> Delete</a>
                <?php endif; ?>
                <a class="btn btn-outline-secondary ms-1" href="<?= url('views/student/incidents/index/index.php') ?>">Back to list</a>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>