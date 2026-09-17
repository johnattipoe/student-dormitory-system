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
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$incidentService = new IncidentService();
$incidents = $incidentService->all($limit);
$totalIncidents = $incidentService->count();
$totalPages = max(1, (int) ceil($totalIncidents / $limit));
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

if ($search !== '') {
    $incidents = array_values(array_filter($incidents, function ($incident) use ($search, $getReporterName, $studentMap) {
        $title = strtolower((string) ($incident['title'] ?? ''));
        $studentName = strtolower($studentMap[(string) ($incident['studentId'] ?? '')] ?? (string) ($incident['studentId'] ?? ''));
        $reporter = strtolower($getReporterName($incident));
        return str_contains($title, $search) || str_contains($studentName, $search) || str_contains($reporter, $search);
    }));

    $externalIncidents = array_values(array_filter($externalIncidents, function ($incident) use ($search) {
        $studentName = strtolower((string) ($incident['studentName'] ?? ''));
        $incidentType = strtolower((string) ($incident['incidentType'] ?? ''));
        $status = strtolower((string) ($incident['status'] ?? ''));
        $fileName = strtolower((string) ($incident['fileName'] ?? ''));
        return str_contains($studentName, $search)
            || str_contains($incidentType, $search)
            || str_contains($status, $search)
            || str_contains($fileName, $search);
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

        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-arrow-up me-2 text-info"></i>External Incident Records</h6>
                <small class="text-muted">Showing <?= count($externalIncidents) ?> uploaded forms</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Incident Type</th>
                            <th>Incident Date</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th class="text-end">Document</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($externalIncidents)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block text-secondary mb-1"></i>
                                    No external incident forms have been uploaded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($externalIncidents as $external): ?>
                                <?php $fileName = (string) ($external['fileName'] ?? ''); ?>
                                <tr>
                                    <td><?= e((string) ($external['studentName'] ?? 'Unknown Student')) ?></td>
                                    <td><?= e((string) ($external['incidentType'] ?? 'External Incident')) ?></td>
                                    <td><?= e((string) ($external['incidentDate'] ?? '—')) ?></td>
                                    <td><small class="text-muted"><?= e((string) ($external['uploadedAt'] ?? '—')) ?></small></td>
                                    <td>
                                        <span class="badge bg-<?= (($external['status'] ?? 'submitted') === 'resolved' ? 'success' : (($external['status'] ?? '') === 'reviewed' ? 'warning text-dark' : 'info')) ?>">
                                            <?= e(ucfirst((string) ($external['status'] ?? 'submitted'))) ?>
                                        </span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?php if ($fileName !== ''): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#externalIncidentViewModal-<?= e(md5($fileName)) ?>" title="View form"><i class="bi bi-eye"></i></button>
                                            <a class="btn btn-sm btn-outline-success" href="<?= url('uploads/external-incidents/' . rawurlencode($fileName)) ?>" target="_blank" rel="noopener" title="Open file"><i class="bi bi-download"></i></a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
                                        <button type="button" class="btn btn-sm btn-outline-primary" title="View" data-bs-toggle="modal" data-bs-target="#incidentViewModal-<?= e($incId) ?>"><i class="bi bi-eye"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit" data-bs-toggle="modal" data-bs-target="#incidentEditModal-<?= e($incId) ?>"><i class="bi bi-pencil"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#incidentDeleteModal-<?= e($incId) ?>"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
        </div>

    </div>
</div>

<?php foreach ($incidents as $incident): ?>
    <?php
    $incId = (string) ($incident['id'] ?? '');
    $incidentTitle = (string) ($incident['title'] ?? 'Untitled Incident');
    $incidentStatus = (string) ($incident['status'] ?? 'open');
    $incidentPriority = (string) ($incident['priority'] ?? 'medium');
    $incidentDescription = (string) ($incident['description'] ?? '');
    ?>
    <div class="modal fade" id="incidentViewModal-<?= e($incId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Incident Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Title</dt><dd class="col-sm-8"><?= e($incidentTitle) ?></dd>
                        <dt class="col-sm-4">Priority</dt><dd class="col-sm-8"><?= e(ucfirst($incidentPriority)) ?></dd>
                        <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><?= e(ucfirst($incidentStatus)) ?></dd>
                        <dt class="col-sm-4">Details</dt><dd class="col-sm-8"><?= e($incidentDescription ?: '—') ?></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="incidentEditModal-<?= e($incId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST" action="<?= url('views/admin/incidents/edit/edit.php?id=' . urlencode($incId)) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Incident</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label">Title</label><input name="title" class="form-control" value="<?= e($incidentTitle) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Priority</label><select name="priority" class="form-select"><option value="low" <?= strtolower($incidentPriority) === 'low' ? 'selected' : '' ?>>Low</option><option value="medium" <?= strtolower($incidentPriority) === 'medium' ? 'selected' : '' ?>>Medium</option><option value="high" <?= strtolower($incidentPriority) === 'high' ? 'selected' : '' ?>>High</option></select></div>
                            <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="open" <?= strtolower($incidentStatus) === 'open' ? 'selected' : '' ?>>Open</option><option value="investigating" <?= strtolower($incidentStatus) === 'investigating' ? 'selected' : '' ?>>Investigating</option><option value="resolved" <?= strtolower($incidentStatus) === 'resolved' ? 'selected' : '' ?>>Resolved</option></select></div>
                            <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4"><?= e($incidentDescription) ?></textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="incidentDeleteModal-<?= e($incId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger">Delete Incident</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete <strong><?= e($incidentTitle) ?></strong>?</p>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="<?= url('views/admin/incidents/delete/delete.php?id=' . urlencode($incId)) ?>">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php foreach ($externalIncidents as $external): ?>
    <?php $externalFileName = (string) ($external['fileName'] ?? ''); if ($externalFileName === '') continue; ?>
    <div class="modal fade" id="externalIncidentViewModal-<?= e(md5($externalFileName)) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">External Incident Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-5">Student</dt><dd class="col-sm-7"><?= e($external['studentName'] ?? 'Unknown Student') ?></dd><dt class="col-sm-5">Incident Type</dt><dd class="col-sm-7"><?= e($external['incidentType'] ?? 'External Incident') ?></dd><dt class="col-sm-5">Incident Date</dt><dd class="col-sm-7"><?= e($external['incidentDate'] ?? '—') ?></dd><dt class="col-sm-5">Status</dt><dd class="col-sm-7"><?= e(ucfirst((string) ($external['status'] ?? 'submitted'))) ?></dd></dl></div><div class="modal-footer"><a class="btn btn-outline-success" href="<?= url('uploads/external-incidents/' . rawurlencode($externalFileName)) ?>" target="_blank" rel="noopener"><i class="bi bi-download me-1"></i>Open file</a><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
<?php endforeach; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>