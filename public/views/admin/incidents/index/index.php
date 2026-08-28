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

$pageTitle = 'Incidents';
$incidentService = new IncidentService();
$incidents = $incidentService->all();
$totalIncidents = count($incidents);
$openIncidents = count(array_filter($incidents, fn($i) => ($i['status'] ?? 'open') === 'open'));
$resolvedIncidents = count(array_filter($incidents, fn($i) => ($i['status'] ?? '') === 'resolved' || ($i['status'] ?? '') === 'closed'));
$urgentIncidents = count(array_filter($incidents, fn($i) => in_array(strtolower((string)($i['priority'] ?? '')), ['high', 'critical', 'urgent'], true)));

$reporterMap = [];
$studentMap = [];

// 1. Map all users
try {
    $allUsers = (new UserService())->all();
    foreach ($allUsers as $user) {
        $name = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''))));
        if ($name === '') {
            $name = $user['displayName'] ?? $user['username'] ?? $user['email'] ?? null;
        }
        if ($name) {
            $roleLabel = !empty($user['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $user['role'])) . ')' : '';
            $displayName = $name . $roleLabel;
            foreach ([$user['id'] ?? null, $user['uid'] ?? null, $user['userId'] ?? null, $user['firebaseUid'] ?? null, $user['email'] ?? null] as $key) {
                if ($key !== null && $key !== '') {
                    $reporterMap[(string) $key] = $displayName;
                }
            }
        }
    }
} catch (\Throwable $e) {}

// 2. Map all students
try {
    $allStudents = StudentService::all();
    foreach ($allStudents as $student) {
        $studentName = trim(($student['name'] ?? '') ?: (($student['fullName'] ?? '') ?: (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))));
        if ($studentName !== '') {
            $adm = !empty($student['admissionNo']) ? ' [' . $student['admissionNo'] . ']' : '';
            $displayName = $studentName . $adm;
            foreach ([$student['id'] ?? null, $student['studentId'] ?? null, $student['admissionNo'] ?? null, $student['userId'] ?? null, $student['uid'] ?? null, $student['email'] ?? null] as $key) {
                if ($key !== null && $key !== '') {
                    $reporterMap[(string) $key] = $displayName . ' (Student)';
                    $studentMap[(string) $key] = $displayName;
                }
            }
        }
    }
} catch (\Throwable $e) {}

$getReporterName = function (array $incident) use (&$reporterMap, $studentMap): string {
    if (!empty($incident['reportedByName']) && trim((string) $incident['reportedByName']) !== '' && !str_starts_with((string) $incident['reportedByName'], 'Staff/User')) {
        return (string) $incident['reportedByName'];
    }

    $rawId = trim((string) ($incident['reportedBy'] ?? ''));
    $studentId = trim((string) ($incident['studentId'] ?? ''));

    if ($rawId !== '' && isset($reporterMap[$rawId])) {
        return $reporterMap[$rawId];
    }

    if ($rawId === 'default-admin' || $rawId === 'admin') {
        return 'Administrator (Admin)';
    }

    if ($studentId !== '' && isset($studentMap[$studentId])) {
        return $studentMap[$studentId] . ' (Student)';
    }

    return $rawId !== '' ? $rawId : '—';
};

$search = strtolower(sanitize($_GET['search'] ?? ''));
if ($search !== '') {
    $incidents = array_values(array_filter($incidents, function ($incident) use ($search, $getReporterName, $studentMap) {
        $title = strtolower((string) ($incident['title'] ?? ''));
        $studentName = strtolower($studentMap[(string) ($incident['studentId'] ?? '')] ?? (string) ($incident['studentId'] ?? ''));
        $reporter = strtolower($getReporterName($incident));
        return str_contains($title, $search) || str_contains($studentName, $search) || str_contains($reporter, $search);
    }));
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/admin/incidents/index/index.php'), 'active' => true],
    ['icon' => 'bi-bar-chart', 'label' => 'Reports', 'href' => url('views/admin/incidents/reports/reports.php')],
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
                    <i class="bi bi-flag-fill text-danger me-2"></i>Campus Disciplinary &amp; Safety Incidents
                </h4>
                <p class="text-muted mb-0">Track behavioral infractions, security occurrences, and corrective resolutions</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/admin/incidents/reports/reports.php') ?>" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i> Incident Reports
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Logged</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalIncidents) ?></h3>
                            <span class="small text-muted">All-time records</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-journal-text fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Open Incidents</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $openIncidents) ?></h3>
                            <span class="small text-muted">Pending resolution</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-octagon fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Urgent Priority</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $urgentIncidents) ?></h3>
                            <span class="small text-muted">High priority cases</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-shield-exclamation fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Resolved</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $resolvedIncidents) ?></h3>
                            <span class="small text-muted">Cases closed</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-9">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input name="search" class="form-control form-control-sm border-start-0" placeholder="Search by incident title, student name, or reporter..." value="<?= e($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-filter me-1"></i> Filter</button> 
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/incidents/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Incidents Table Card -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-flag me-2 text-danger"></i>Incident Log Registry</h6>
                <small class="text-muted">Showing <?= count($incidents) ?> entries</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Incident Title</th>
                            <th>Involved Student</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Reported By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($incidents)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-shield-check fs-3 d-block text-secondary mb-1"></i>
                                    No incident records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($incidents as $incident): ?>
                                <?php 
                                    $studentId = (string) ($incident['studentId'] ?? '');
                                    $involvedStudent = $studentMap[$studentId] ?? ($studentId !== '' ? $studentId : '—');
                                    $reporterDisplayName = $getReporterName($incident);
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
                                    $incId = (string) ($incident['id'] ?? '');
                                ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark d-block"><?= e($incident['title'] ?? 'Untitled Incident') ?></strong>
                                        <small class="text-muted"><?= e(mb_strimwidth((string)($incident['description'] ?? ''), 0, 50, '…')) ?></small>
                                    </td>
                                    <td><?= e($involvedStudent) ?></td>
                                    <td><span class="badge <?= $pBadge ?>"><?= ucfirst(e($iPri)) ?></span></td>
                                    <td><span class="badge <?= $sBadge ?>"><?= ucfirst(e($iSt)) ?></span></td>
                                    <td><small class="text-muted"><i class="bi bi-person me-1"></i><?= e($reporterDisplayName) ?></small></td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/admin/incidents/view/view.php?id=' . urlencode($incId)) ?>" title="View"><i class="bi bi-eye"></i></a>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/admin/incidents/edit/edit.php?id=' . urlencode($incId)) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a class="btn btn-sm btn-outline-danger" href="<?= url('views/admin/incidents/delete/delete.php?id=' . urlencode($incId)) ?>" title="Delete"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>