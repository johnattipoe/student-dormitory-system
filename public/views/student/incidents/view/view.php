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

$editIncidentId = (string) ($_SESSION['_student_incident_edit_id'] ?? '');
$editIncidentOld = $_SESSION['_student_incident_edit_old'] ?? [];
$editIncidentErrors = $_SESSION['_student_incident_edit_errors'] ?? [];
$editValues = $editIncidentId === $id ? array_merge($incident, $editIncidentOld) : $incident;
unset($_SESSION['_student_incident_edit_id'], $_SESSION['_student_incident_edit_old'], $_SESSION['_student_incident_edit_errors']);

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
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentIncidentDetailEdit"><i class="bi bi-pencil me-1"></i> Edit</button>
                    <button type="button" class="btn btn-outline-danger ms-1" data-bs-toggle="modal" data-bs-target="#studentIncidentDetailDelete"><i class="bi bi-trash me-1"></i> Delete</button>
                <?php endif; ?>
                <a class="btn btn-outline-secondary ms-1" href="<?= url('views/student/incidents/index/index.php') ?>">Back to list</a>
            </div>
        </div>
    </div>
</div>
<?php if (($incident['status'] ?? 'open') === 'open'): ?>
    <div class="modal fade" id="studentIncidentDetailEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
            <form method="POST" action="<?= url('views/student/incidents/edit/edit.php?id=' . urlencode($id)) ?>" class="student-modal-form" data-open-on-load="<?= !empty($_GET['openEditModal']) ? 'true' : 'false' ?>">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <input type="hidden" name="returnToIncidentView" value="1">
                <div class="modal-header"><h5 class="modal-title">Edit Incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <?php if ($editIncidentId === $id && !empty($editIncidentErrors['general'])): ?><div class="alert alert-danger"><?= e($editIncidentErrors['general']) ?></div><?php endif; ?>
                    <label class="form-label">Title</label><input name="title" class="form-control mb-3" value="<?= e($editValues['title'] ?? '') ?>" required>
                    <label class="form-label">Category</label><select name="type" class="form-select mb-3"><?php foreach (['discipline', 'medical', 'safety', 'property', 'other'] as $type): ?><option value="<?= e($type) ?>" <?= ($editValues['type'] ?? 'other') === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option><?php endforeach; ?></select>
                    <label class="form-label">Priority</label><select name="priority" class="form-select mb-3"><?php foreach (['low', 'medium', 'high'] as $priority): ?><option value="<?= e($priority) ?>" <?= ($editValues['priority'] ?? 'medium') === $priority ? 'selected' : '' ?>><?= e(ucfirst($priority)) ?></option><?php endforeach; ?></select>
                    <label class="form-label">Description</label><textarea name="description" class="form-control" rows="5" required><?= e($editValues['description'] ?? $editValues['notes'] ?? '') ?></textarea>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div></div>
    </div>
    <div class="modal fade" id="studentIncidentDetailDelete" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title text-danger">Delete Incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">Delete <strong><?= e($incident['title'] ?? $incident['type'] ?? 'this incident') ?></strong>?</div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/student/incidents/delete/delete.php') ?>"><input type="hidden" name="id" value="<?= e($id) ?>"><button type="submit" class="btn btn-danger">Delete Incident</button></form></div>
        </div></div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('#studentIncidentDetailEdit form[data-open-on-load="true"]');
        if (form) {
            bootstrap.Modal.getOrCreateInstance(form.closest('.modal')).show();
        }
    });
    </script>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>