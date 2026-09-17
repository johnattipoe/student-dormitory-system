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

$search = sanitize($_GET['search'] ?? '');
$dateFrom = sanitize($_GET['dateFrom'] ?? '');
$dateTo = sanitize($_GET['dateTo'] ?? '');
$severity = sanitize($_GET['severity'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$externalStatus = sanitize($_GET['externalStatus'] ?? '');

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$studentMap = [];
$reporterMap = [];

foreach ($students as $student) {
    $sName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
    $adm = !empty($student['admissionNo']) ? ' [' . $student['admissionNo'] . ']' : '';
    $displayName = $sName . $adm;
    $studentMap[(string) ($student['id'] ?? '')] = $student;
    foreach ([$student['id'] ?? null, $student['studentId'] ?? null, $student['admissionNo'] ?? null, $student['userId'] ?? null, $student['uid'] ?? null] as $key) {
        if ($key !== null && $key !== '') {
            $reporterMap[(string) $key] = $displayName . ' (Student)';
        }
    }
}

try {
    foreach ((new UserService())->all() as $user) {
        $name = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''))));
        if ($name === '') $name = $user['displayName'] ?? $user['username'] ?? $user['email'] ?? null;
        if ($name) {
            $roleLabel = !empty($user['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $user['role'])) . ')' : '';
            $displayName = $name . $roleLabel;
            foreach ([$user['id'] ?? null, $user['uid'] ?? null, $user['userId'] ?? null, $user['email'] ?? null] as $key) {
                if ($key !== null && $key !== '') {
                    $reporterMap[(string) $key] = $displayName;
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
    if ($rawId !== '' && $studentId !== '' && ($rawId === $studentId || isset($studentMap[$rawId]))) {
        $s = $studentMap[$rawId] ?? $studentMap[$studentId] ?? [];
        $sName = trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''));
        return ($sName ?: 'Student') . ' (Student)';
    }
    if ($studentId !== '' && isset($studentMap[$studentId])) {
        $s = $studentMap[$studentId];
        $sName = trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''));
        return ($sName ?: 'Student') . ' (Student)';
    }
    return $rawId !== '' ? $rawId : '—';
};

$incidents = (new IncidentService())->byHouse($houseId);
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$totalIncidents = count($incidents);
$totalPages = max(1, (int) ceil($totalIncidents / $limit));
$page = min($page, $totalPages);
$incidents = array_slice($incidents, ($page - 1) * $limit, $limit);

$externalIncidents = [];
$externalDir = APP_ROOT . '/public/uploads/external-incidents';
if (is_dir($externalDir)) {
    $jsonFiles = glob($externalDir . '/*.json');
    if (is_array($jsonFiles)) {
        rsort($jsonFiles);
        foreach ($jsonFiles as $jsonFile) {
            $content = json_decode((string) file_get_contents($jsonFile), true);
            if (!is_array($content)) {
                continue;
            }
            $content['sourceFile'] = basename((string) ($content['fileName'] ?? ''));
            $externalIncidents[] = $content;
        }
    }
}

if (!empty($externalStatus)) {
    $externalIncidents = array_values(array_filter($externalIncidents, function ($incident) use ($externalStatus) {
        return strtolower((string) ($incident['status'] ?? 'submitted')) === strtolower((string) $externalStatus);
    }));
}

if (!empty($search)) {
    $searchLower = strtolower($search);
    $incidents = array_values(array_filter($incidents, function ($incident) use ($searchLower, $studentMap, $getReporterName) {
        $student = $studentMap[(string) ($incident['studentId'] ?? '')] ?? [];
        $studentName = trim((($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')));
        $reporter = $getReporterName($incident);
        return str_contains(strtolower((string) ($incident['title'] ?? '')), $searchLower)
            || str_contains(strtolower((string) ($incident['type'] ?? '')), $searchLower)
            || str_contains(strtolower((string) ($incident['description'] ?? '')), $searchLower)
            || str_contains(strtolower($studentName), $searchLower)
            || str_contains(strtolower($reporter), $searchLower);
    }));
}

if (!empty($dateFrom)) {
    $incidents = array_values(array_filter($incidents, fn($incident) => strtotime((string) ($incident['createdAt'] ?? '')) >= strtotime($dateFrom)));
}

if (!empty($dateTo)) {
    $incidents = array_values(array_filter($incidents, fn($incident) => strtotime((string) ($incident['createdAt'] ?? '')) <= strtotime($dateTo) + 86400));
}

if (!empty($severity)) {
    $incidents = array_values(array_filter($incidents, fn($incident) => ($incident['priority'] ?? $incident['severity'] ?? '') === $severity));
}

if (!empty($status)) {
    $incidents = array_values(array_filter($incidents, fn($incident) => ($incident['status'] ?? '') === $status));
}

$openCount = count(array_filter($incidents, fn($incident) => ($incident['status'] ?? 'open') === 'open'));
$resolvedCount = count(array_filter($incidents, fn($incident) => ($incident['status'] ?? '') === 'resolved'));
$highCount = count(array_filter($incidents, fn($incident) => ($incident['priority'] ?? $incident['severity'] ?? 'low') === 'high'));

$pageTitle = 'House Incidents';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index/index.php'), 'active' => true],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index/index.php')],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Incidents Log</h4>
                <p class="text-muted mb-0">Track and manage incidents reported in your house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/house-master/reports/incidents/incidents.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i>Reports
                </a>
                <a href="<?= url('views/house-master/incidents/upload/upload.php') ?>" class="btn btn-outline-info btn-sm">
                    <i class="bi bi-upload me-1"></i>Upload External Incident
                </a>
                <a href="<?= url('views/house-master/incidents/create/create.php') ?>" class="btn btn-danger btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Report Incident
                </a>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Open Incidents</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $openCount) ?></h3>
                            <span class="small text-muted">Require attention</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Resolved</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $resolvedCount) ?></h3>
                            <span class="small text-muted">Successfully closed</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">High Severity</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $highCount) ?></h3>
                            <span class="small text-muted">Critical priority</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-lightning fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Search</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Title, student, reporter..." value="<?= e($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Date From</label>
                        <input type="date" name="dateFrom" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Date To</label>
                        <input type="date" name="dateTo" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Severity</label>
                        <select name="severity" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="low" <?= $severity === 'low' ? 'selected' : '' ?>>Low</option>
                            <option value="medium" <?= $severity === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="high" <?= $severity === 'high' ? 'selected' : '' ?>>High</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="investigating" <?= $status === 'investigating' ? 'selected' : '' ?>>Investigating</option>
                            <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i></button>
                        <a href="<?= url('views/house-master/incidents/index/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-arrow-up me-2"></i>External Incident Records</h6>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <small class="text-muted">Showing <?= e((string) count($externalIncidents)) ?> uploaded forms</small>
                    <form method="GET" class="d-flex gap-2 align-items-center">
                        <input type="hidden" name="search" value="<?= e($search) ?>">
                        <input type="hidden" name="dateFrom" value="<?= e($dateFrom) ?>">
                        <input type="hidden" name="dateTo" value="<?= e($dateTo) ?>">
                        <input type="hidden" name="severity" value="<?= e($severity) ?>">
                        <input type="hidden" name="status" value="<?= e($status) ?>">
                        <select name="externalStatus" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All statuses</option>
                            <option value="submitted" <?= $externalStatus === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                            <option value="reviewed" <?= $externalStatus === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
                            <option value="resolved" <?= $externalStatus === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                        </select>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Incident Type</th>
                            <th>Incident Date</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Document</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($externalIncidents)): ?>
                            <?php foreach ($externalIncidents as $external): ?>
                                <?php $fileName = (string) ($external['fileName'] ?? ''); $externalViewUrl = $fileName !== '' ? url('views/house-master/incidents/external-view/external-view.php?file=' . urlencode($fileName)) : '#'; ?>
                                <tr>
                                    <td class="fw-medium"><?= e((string) ($external['studentName'] ?? 'Unknown Student')) ?></td>
                                    <td><?= e((string) ($external['incidentType'] ?? 'External Incident')) ?></td>
                                    <td><?= e((string) ($external['incidentDate'] ?? '—')) ?></td>
                                    <td><span class="small text-muted"><?= e((string) ($external['uploadedAt'] ?? '—')) ?></span></td>
                                    <td>
                                        <span class="badge bg-success">
                                            <?= e((string) ($external['status'] ?? 'Submitted')) ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php if ($fileName !== ''): ?>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#houseExternalIncidentViewModal-<?= e(md5($fileName)) ?>">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#houseExternalIncidentEditModal-<?= e(md5($fileName)) ?>">
                                                    <i class="bi bi-pencil me-1"></i>Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#houseExternalIncidentDeleteModal-<?= e(md5($fileName)) ?>">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                                <a class="btn btn-sm btn-outline-success" href="<?= url('uploads/external-incidents/' . $fileName) ?>" target="_blank" rel="noopener">
                                                    <i class="bi bi-download me-1"></i>Open
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No external incident forms have been uploaded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Incidents Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-flag me-2"></i>Incident Records</h6>
                <small class="text-muted">Showing <?= e((string) count($incidents)) ?> records</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Involved Student</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Reported By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($incidents)): ?>
                            <?php foreach ($incidents as $incident): ?>
                                <?php 
                                    $incidentStudent = $studentMap[(string) ($incident['studentId'] ?? '')] ?? null; 
                                    $studentName = $incidentStudent ? trim((($incidentStudent['firstName'] ?? '') . ' ' . ($incidentStudent['lastName'] ?? ''))) : ($incident['studentId'] ?? '—');
                                    $priority = $incident['priority'] ?? $incident['severity'] ?? 'medium';
                                    $reporterName = $getReporterName($incident);
                                ?>
                                <tr>
                                    <td class="fw-medium"><?= e($incident['title'] ?? $incident['type'] ?? 'Incident') ?></td>
                                    <td><?= e($studentName) ?></td>
                                    <td>
                                        <span class="badge bg-<?= ($priority === 'high' ? 'danger' : ($priority === 'medium' ? 'warning text-dark' : 'secondary')) ?>">
                                            <?= e(ucfirst((string) $priority)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= ($incident['status'] ?? 'open') === 'resolved' ? 'success' : (($incident['status'] ?? '') === 'investigating' ? 'warning text-dark' : 'danger') ?>">
                                            <?= e(ucfirst((string) ($incident['status'] ?? 'open'))) ?>
                                        </span>
                                    </td>
                                    <td><?= e($reporterName) ?></td>
                                    <td><span class="small text-muted"><?= e(substr((string) ($incident['createdAt'] ?? $incident['reportedAt'] ?? ''), 0, 10)) ?></span></td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#houseIncidentViewModal-<?= e((string) ($incident['id'] ?? '')) ?>"><i class="bi bi-eye"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#houseIncidentEditModal-<?= e((string) ($incident['id'] ?? '')) ?>"><i class="bi bi-pencil"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#houseIncidentDeleteModal-<?= e((string) ($incident['id'] ?? '')) ?>"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No incidents matching your filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
    </div>
</div>
<?php foreach ($incidents as $incident): ?>
    <?php $modalIncidentId = (string) ($incident['id'] ?? ''); $modalIncidentPriority = (string) ($incident['priority'] ?? $incident['severity'] ?? 'medium'); $modalIncidentStatus = (string) ($incident['status'] ?? 'open'); ?>
    <div class="modal fade" id="houseIncidentViewModal-<?= e($modalIncidentId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Incident Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-4">Title</dt><dd class="col-sm-8"><?= e($incident['title'] ?? $incident['type'] ?? 'Incident') ?></dd><dt class="col-sm-4">Student</dt><dd class="col-sm-8"><?= e($studentName) ?></dd><dt class="col-sm-4">Priority</dt><dd class="col-sm-8"><?= e(ucfirst($modalIncidentPriority)) ?></dd><dt class="col-sm-4">Status</dt><dd class="col-sm-8"><?= e(ucwords(str_replace('_', ' ', $modalIncidentStatus))) ?></dd><dt class="col-sm-4">Details</dt><dd class="col-sm-8"><?= e($incident['description'] ?? $incident['notes'] ?? '—') ?></dd></dl></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
    <div class="modal fade" id="houseIncidentEditModal-<?= e($modalIncidentId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form method="POST" action="<?= url('views/house-master/incidents/edit/edit.php?id=' . urlencode($modalIncidentId)) ?>"><div class="modal-header"><h5 class="modal-title">Edit Incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><input type="hidden" name="id" value="<?= e($modalIncidentId) ?>"><label class="form-label">Title</label><input name="title" class="form-control mb-3" value="<?= e($incident['title'] ?? $incident['type'] ?? '') ?>" required><div class="row g-3"><div class="col-md-6"><label class="form-label">Priority</label><select name="priority" class="form-select"><option value="low" <?= $modalIncidentPriority === 'low' ? 'selected' : '' ?>>Low</option><option value="medium" <?= $modalIncidentPriority === 'medium' ? 'selected' : '' ?>>Medium</option><option value="high" <?= $modalIncidentPriority === 'high' ? 'selected' : '' ?>>High</option></select></div><div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="open" <?= $modalIncidentStatus === 'open' ? 'selected' : '' ?>>Open</option><option value="investigating" <?= $modalIncidentStatus === 'investigating' ? 'selected' : '' ?>>Investigating</option><option value="resolved" <?= $modalIncidentStatus === 'resolved' ? 'selected' : '' ?>>Resolved</option></select></div></div><label class="form-label mt-3">Description</label><textarea name="description" class="form-control" rows="5"><?= e($incident['description'] ?? $incident['notes'] ?? '') ?></textarea></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div></form></div></div></div>
    <div class="modal fade" id="houseIncidentDeleteModal-<?= e($modalIncidentId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header border-0"><h5 class="modal-title text-danger">Delete Incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="mb-0">Delete <strong><?= e($incident['title'] ?? $incident['type'] ?? 'this incident') ?></strong>?</p></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/house-master/incidents/delete/delete.php?id=' . urlencode($modalIncidentId)) ?>"><input type="hidden" name="id" value="<?= e($modalIncidentId) ?>"><button type="submit" class="btn btn-danger">Delete</button></form></div></div></div></div>
<?php endforeach; ?>
<?php foreach ($externalIncidents as $external): ?>
    <?php $modalExternalFile = (string) ($external['fileName'] ?? ''); if ($modalExternalFile === '') continue; $modalExternalKey = md5($modalExternalFile); ?>
    <div class="modal fade" id="houseExternalIncidentViewModal-<?= e($modalExternalKey) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">External Incident Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-5">Student</dt><dd class="col-sm-7"><?= e($external['studentName'] ?? 'Unknown Student') ?></dd><dt class="col-sm-5">Incident Type</dt><dd class="col-sm-7"><?= e($external['incidentType'] ?? 'External Incident') ?></dd><dt class="col-sm-5">Incident Date</dt><dd class="col-sm-7"><?= e($external['incidentDate'] ?? '—') ?></dd><dt class="col-sm-5">Submitted</dt><dd class="col-sm-7"><?= e($external['uploadedAt'] ?? '—') ?></dd><dt class="col-sm-5">Status</dt><dd class="col-sm-7"><?= e($external['status'] ?? 'Submitted') ?></dd></dl></div><div class="modal-footer"><a class="btn btn-outline-success" href="<?= url('uploads/external-incidents/' . basename($modalExternalFile)) ?>" target="_blank" rel="noopener"><i class="bi bi-download me-1"></i>Open file</a><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
    <div class="modal fade" id="houseExternalIncidentEditModal-<?= e($modalExternalKey) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form method="POST" action="<?= url('views/house-master/incidents/external-edit/external-edit.php?file=' . urlencode($modalExternalFile)) ?>"><div class="modal-header"><h5 class="modal-title">Edit External Incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><label class="form-label">Student Name</label><input name="studentName" class="form-control mb-3" value="<?= e($external['studentName'] ?? '') ?>" required><label class="form-label">Incident Type</label><input name="incidentType" class="form-control mb-3" value="<?= e($external['incidentType'] ?? '') ?>" required><label class="form-label">Incident Date</label><input type="date" name="incidentDate" class="form-control mb-3" value="<?= e($external['incidentDate'] ?? '') ?>" required><label class="form-label">Status</label><select name="status" class="form-select"><option value="submitted" <?= ($external['status'] ?? 'submitted') === 'submitted' ? 'selected' : '' ?>>Submitted</option><option value="reviewed" <?= ($external['status'] ?? '') === 'reviewed' ? 'selected' : '' ?>>Reviewed</option><option value="resolved" <?= ($external['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option></select></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div></form></div></div></div>
    <div class="modal fade" id="houseExternalIncidentDeleteModal-<?= e($modalExternalKey) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header border-0"><h5 class="modal-title text-danger">Delete External Incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="mb-0">Delete the uploaded incident record for <strong><?= e($external['studentName'] ?? 'this student') ?></strong>?</p></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/house-master/incidents/external-delete/external-delete.php?file=' . urlencode($modalExternalFile)) ?>"><button type="submit" class="btn btn-danger">Delete</button></form></div></div></div></div>
<?php endforeach; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
