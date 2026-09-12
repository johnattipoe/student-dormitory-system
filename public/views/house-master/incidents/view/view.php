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
use App\Services\StudentService;
use App\Services\UserService;
use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? '');
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
    flash('error', 'Incident not found in your house.');
    redirect(url('views/house-master/incidents/index/index.php'));
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

        // 1. Check users
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

        // 2. Check students
        if ($reporterName === '' || str_starts_with($reporterName, 'Staff/User')) {
            try {
                $s = $db->getDocument('students', $rawId);
                if ($s) {
                    $sName = trim(($s['name'] ?? '') ?: (($s['fullName'] ?? '') ?: (($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''))));
                    if ($sName !== '') {
                        $adm = !empty($s['admissionNo']) ? ' [' . $s['admissionNo'] . ']' : '';
                        $reporterName = $sName . $adm . ' (Student)';
                    }
                }
            } catch (\Throwable $e) {}
        }

        if ($reporterName === '' || str_starts_with($reporterName, 'Staff/User')) {
            $reporterName = $studentName !== '—' ? ($studentName . ' (Student)') : ($rawId ?: '—');
        }
    }
}

$priority = $incident['priority'] ?? $incident['severity'] ?? 'medium';
$priorityBadge = match($priority) {
    'high', 'critical', 'emergency' => 'bg-danger text-white',
    'medium' => 'bg-warning text-dark',
    default => 'bg-secondary text-white',
};

$status = $incident['status'] ?? 'open';
$statusBadge = match($status) {
    'resolved', 'closed' => 'bg-success text-white',
    'investigating', 'in_progress' => 'bg-warning text-dark',
    default => 'bg-danger text-white',
};

$pageTitle = 'Incident Details';
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-shield-exclamation text-danger me-2"></i>Incident Record Details</h4>
                <p class="text-muted mb-0">Reported <?= e(substr((string) ($incident['reportedAt'] ?? $incident['createdAt'] ?? ''), 0, 19)) ?></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/incidents/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
                <a class="btn btn-warning btn-sm" href="<?= url('views/house-master/incidents/edit/edit.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-pencil me-1"></i>Edit Incident
                </a>
                <a class="btn btn-outline-danger btn-sm" href="<?= url('views/house-master/incidents/delete/delete.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-trash me-1"></i>Delete
                </a>
            </div>
        </div>

        <!-- Details Card -->
        <div class="card stat-card shadow-sm border-0 mb-4" style="max-width: 860px;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-file-text me-2 text-primary"></i><?= e($incident['title'] ?? 'Incident') ?></h6>
                <div class="d-flex gap-2">
                    <span class="badge <?= $priorityBadge ?>"><?= e(ucfirst((string) $priority)) ?> Priority</span>
                    <span class="badge <?= $statusBadge ?>"><?= e(ucfirst((string) $status)) ?></span>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Involved Student</span>
                        <strong class="fs-6"><?= e($studentName) ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Reported By</span>
                        <strong class="fs-6"><?= e($reporterName) ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Date & Time Logged</span>
                        <strong><?= e($incident['reportedAt'] ?? $incident['createdAt'] ?? '—') ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Incident ID</span>
                        <code><?= e($id) ?></code>
                    </div>
                </div>

                <div class="pt-3 border-top">
                    <span class="text-muted small d-block mb-2 fw-semibold">Description & Incident Notes</span>
                    <div class="p-3 bg-light rounded text-dark" style="line-height: 1.8; white-space: pre-line;">
                        <?= e($incident['description'] ?? $incident['notes'] ?? 'No description provided.') ?>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/incidents/index/index.php') ?>">
                        Back to Incident Log
                    </a>
                    <a class="btn btn-primary btn-sm" href="<?= url('views/house-master/incidents/edit/edit.php?id=' . urlencode($id)) ?>">
                        <i class="bi bi-pencil me-1"></i>Edit Incident
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>