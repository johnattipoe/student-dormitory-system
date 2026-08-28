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
use App\Services\StudentService;
use App\Services\UserService;
use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? '');
$houseId = current_user()['houseId'] ?? null;
$incident = null;
foreach ((new IncidentService())->byHouse($houseId) as $record) {
    if (($record['id'] ?? '') === $id) {
        $incident = $record;
        break;
    }
}

if (!$incident) {
    flash('error', 'Incident not found.');
    redirect(url('views/senior-houseparent/incidents/index/index.php'));
}

$rawId = trim((string) ($incident['reportedBy'] ?? ''));
$studentId = trim((string) ($incident['studentId'] ?? ''));
$reporterName = trim((string) ($incident['reportedByName'] ?? ''));

// Resolve Involved Student
$student = StudentService::find($studentId);
$studentName = $student ? trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) : ($studentId ?: '—');
if ($student && !empty($student['admissionNo'])) {
    $studentName .= ' [' . $student['admissionNo'] . ']';
}

// Resolve Reporter Name
if ($reporterName === '' || str_starts_with($reporterName, 'Staff/User')) {
    if ($rawId !== '' && $studentId !== '' && ($rawId === $studentId || $studentName !== '—')) {
        $reporterName = $studentName . ' (Student)';
    }

    if ($reporterName === '' || str_starts_with($reporterName, 'Staff/User')) {
        $db = FirebaseService::getInstance();

        // Check users
        try {
            $u = $db->getDocument('users', $rawId);
            if ($u) {
                $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
                if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? $u['email'] ?? '';
                if ($name !== '') {
                    $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                    $reporterName = $name . $roleLabel;
                }
            }
        } catch (\Throwable $e) {}

        if ($reporterName === '' || str_starts_with($reporterName, 'Staff/User')) {
            $reporterName = $studentName !== '—' ? ($studentName . ' (Student)') : ($rawId ?: '—');
        }
    }
}

$priority = $incident['priority'] ?? $incident['severity'] ?? 'medium';
$pageTitle = 'Incident Details';
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
        <div class="card stat-card p-4" style="max-width:760px">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1"><?= e($incident['title'] ?? $incident['type'] ?? 'Incident') ?></h5>
                    <p class="text-muted mb-0">Reported <?= e(substr((string) ($incident['reportedAt'] ?? $incident['createdAt'] ?? ''), 0, 19)) ?></p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/incidents/index/index.php') ?>">Back</a>
            </div>
            <dl class="row mt-4">
                <dt class="col-sm-3">Involved Student</dt>
                <dd class="col-sm-9"><?= e($studentName) ?></dd>

                <dt class="col-sm-3">Reported By</dt>
                <dd class="col-sm-9"><?= e($reporterName) ?></dd>

                <dt class="col-sm-3">Priority</dt>
                <dd class="col-sm-9">
                    <span class="badge bg-<?= ($priority === 'high' ? 'danger' : ($priority === 'medium' ? 'warning text-dark' : 'secondary')) ?>">
                        <?= e(ucfirst((string) $priority)) ?>
                    </span>
                </dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    <span class="badge bg-<?= ($incident['status'] ?? 'open') === 'resolved' ? 'success' : (($incident['status'] ?? '') === 'investigating' ? 'warning text-dark' : 'danger') ?>">
                        <?= e(ucfirst((string) ($incident['status'] ?? 'open'))) ?>
                    </span>
                </dd>

                <dt class="col-sm-3">Description</dt>
                <dd class="col-sm-9"><?= nl2br(e($incident['description'] ?? $incident['notes'] ?? '—')) ?></dd>
            </dl>
            <div class="mt-4">
                <a class="btn btn-primary" href="<?= url('views/senior-houseparent/incidents/edit/edit.php?id=' . urlencode($id)) ?>"><i class="bi bi-pencil me-1"></i> Edit Incident</a>
                <a class="btn btn-outline-secondary ms-1" href="<?= url('views/senior-houseparent/incidents/index/index.php') ?>">Back to list</a>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>