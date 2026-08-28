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
use App\Services\UserService;
use App\Services\StudentService;
use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? '');
$incident = (new IncidentService())->find($id);
if (!$incident) {
    flash('error', 'Incident not found.');
    redirect(url('views/admin/incidents/index/index.php'));
}

$rawId = trim((string) ($incident['reportedBy'] ?? ''));
$studentId = trim((string) ($incident['studentId'] ?? ''));
$reporterName = trim((string) ($incident['reportedByName'] ?? ''));

// Resolve Involved Student
$studentName = '—';
if ($studentId !== '') {
    try {
        $student = StudentService::find($studentId);
        if ($student) {
            $sName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
            if ($sName === '') $sName = $student['name'] ?? '';
            $adm = !empty($student['admissionNo']) ? ' [' . $student['admissionNo'] . ']' : '';
            $studentName = $sName !== '' ? ($sName . $adm) : $studentId;
        } else {
            $studentName = $studentId;
        }
    } catch (\Throwable $e) {
        $studentName = $studentId;
    }
}

// Resolve Reporter Name
if ($reporterName === '' || str_starts_with($reporterName, 'Staff/User')) {
    if ($rawId === 'default-admin' || $rawId === 'admin') {
        $reporterName = 'Administrator (Admin)';
    } elseif ($rawId !== '' && $studentId !== '' && ($rawId === $studentId || $studentName !== '—')) {
        $reporterName = $studentName . ' (Student)';
    } else {
        $db = FirebaseService::getInstance();
        if ($rawId !== '') {
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
        }

        if ($reporterName === '' || str_starts_with($reporterName, 'Staff/User')) {
            $reporterName = $studentName !== '—' ? ($studentName . ' (Student)') : ($rawId ?: '—');
        }
    }
}

$iPri = strtolower((string) ($incident['priority'] ?? 'medium'));
$pBadge = match($iPri) {
    'high', 'critical', 'urgent' => 'bg-danger',
    'medium' => 'bg-warning text-dark',
    default => 'bg-secondary',
};
$iSt = strtolower((string) ($incident['status'] ?? 'open'));
$sBadge = match($iSt) {
    'open' => 'bg-danger',
    'resolved' => 'bg-success',
    'closed' => 'bg-secondary',
    default => 'bg-info',
};

$pageTitle = 'Incident Details';
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
                    <i class="bi bi-flag-fill text-danger me-2"></i><?= e($incident['title'] ?? 'Incident Details') ?>
                </h4>
                <p class="text-muted mb-0">
                    Status: <span class="badge <?= $sBadge ?> me-1"><?= ucfirst(e($iSt)) ?></span> &bull; 
                    Priority: <span class="badge <?= $pBadge ?>"><?= ucfirst(e($iPri)) ?></span>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-primary btn-sm" href="<?= url('views/admin/incidents/edit/edit.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-pencil me-1"></i> Edit Incident
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/incidents/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i> Back to Incidents
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card stat-card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Incident Report Record</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Incident Title</span>
                                <strong class="text-dark"><?= e($incident['title'] ?? '—') ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Involved Student</span>
                                <span class="fw-semibold text-primary"><?= e($studentName) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Reported By</span>
                                <span><i class="bi bi-person me-1 text-muted"></i><?= e($reporterName ?: '—') ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Priority Level</span>
                                <span class="badge <?= $pBadge ?>"><?= ucfirst(e($iPri)) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Resolution Status</span>
                                <span class="badge <?= $sBadge ?>"><?= ucfirst(e($iSt)) ?></span>
                            </li>
                            <li class="list-group-item py-3">
                                <span class="text-muted fw-semibold small d-block mb-2">Detailed Description &amp; Notes</span>
                                <div class="p-3 bg-light rounded-3 text-dark border">
                                    <?= nl2br(e($incident['description'] ?? 'No additional details logged.')) ?>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card stat-card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-lightning me-2 text-warning"></i>Incident Actions</h6>
                    </div>
                    <div class="card-body p-3 d-grid gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="<?= url('views/admin/incidents/edit/edit.php?id=' . urlencode($id)) ?>">
                            <i class="bi bi-pencil me-1"></i> Edit / Update Status
                        </a>
                        <a class="btn btn-outline-danger btn-sm" href="<?= url('views/admin/incidents/delete/delete.php?id=' . urlencode($id)) ?>">
                            <i class="bi bi-trash me-1"></i> Delete Incident
                        </a>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/incidents/index/index.php') ?>">
                            <i class="bi bi-arrow-left me-1"></i> Back to Incident List
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>