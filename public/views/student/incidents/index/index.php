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

$search = sanitize($_GET['search'] ?? '');
$dateFrom = sanitize($_GET['dateFrom'] ?? '');
$dateTo = sanitize($_GET['dateTo'] ?? '');
$severity = sanitize($_GET['severity'] ?? '');
$status = sanitize($_GET['status'] ?? '');

$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? null;
$studentProfile = $studentId ? StudentService::find($studentId) : null;
$studentName = $studentProfile
    ? trim(($studentProfile['firstName'] ?? '') . ' ' . ($studentProfile['lastName'] ?? ''))
    : (current_user()['name'] ?? 'My account');
$incidents = $studentId ? (IncidentService::byStudent($studentId) ?? []) : [];

if (!empty($search)) {
    $searchLower = strtolower($search);
    $incidents = array_values(array_filter($incidents, fn($i) =>
        str_contains(strtolower((string) ($i['type'] ?? '')), $searchLower) ||
        str_contains(strtolower((string) ($i['description'] ?? '')), $searchLower) ||
        str_contains(strtolower((string) ($i['notes'] ?? '')), $searchLower)
    ));
}

if (!empty($dateFrom)) {
    $incidents = array_values(array_filter($incidents, fn($i) => strtotime((string) ($i['createdAt'] ?? '')) >= strtotime($dateFrom)));
}
if (!empty($dateTo)) {
    $incidents = array_values(array_filter($incidents, fn($i) => strtotime((string) ($i['createdAt'] ?? '')) <= strtotime($dateTo) + 86400));
}

if (!empty($severity)) {
    $incidents = array_values(array_filter($incidents, fn($i) => ($i['severity'] ?? '') === $severity));
}

if (!empty($status)) {
    $incidents = array_values(array_filter($incidents, fn($i) => ($i['status'] ?? '') === $status));
}

$openCount = count(array_filter($incidents, fn($i) => ($i['status'] ?? 'open') === 'open'));
$resolvedCount = count(array_filter($incidents, fn($i) => ($i['status'] ?? '') === 'resolved'));
$highCount = count(array_filter($incidents, fn($i) => ($i['severity'] ?? 'low') === 'high'));

$pageTitle = 'Student Incidents';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index/index.php'), 'active' => true],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index/index.php')],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-flag-fill text-danger me-2"></i>My Incidents</h4>
                <p class="text-muted mb-0">View reported incidents, infractions, or submit a new report</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-danger btn-sm" href="<?= url('views/student/incidents/create/create.php') ?>">
                    <i class="bi bi-plus-lg me-1"></i>Report Incident
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Reports</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) count($incidents)) ?></h3>
                            <span class="small text-muted">All records</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-flag fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Open</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $openCount) ?></h3>
                            <span class="small text-muted">Pending review</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-exclamation-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Resolved</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $resolvedCount) ?></h3>
                            <span class="small text-muted">Closed cases</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">High Severity</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $highCount) ?></h3>
                            <span class="small text-muted">Critical priority</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-shield-exclamation fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <input type="hidden" name="route" value="/views/student/incidents/index/index.php">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Search</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Type, description..." value="<?= e($search) ?>">
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
                        <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel"></i></button>
                        <a href="<?= url('views/student/incidents/index/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Incidents Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-flag me-2"></i>Incident Records</h6>
                <small class="text-muted">
                    Showing <strong><?= count($incidents) ?></strong> incident(s)
                    <?php if (!empty($search) || !empty($dateFrom) || !empty($dateTo) || !empty($severity) || !empty($status)): ?>
                        (filtered)
                    <?php endif; ?>
                </small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Reported On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($incidents)): ?>
                            <?php foreach ($incidents as $incident): ?>
                                <tr>
                                    <td class="fw-medium"><?= e($studentName) ?></td>
                                    <td><?= e(ucfirst((string) ($incident['type'] ?? 'other'))) ?></td>
                                    <td class="small"><?= e(substr((string) ($incident['description'] ?? ''), 0, 60)) ?></td>
                                    <td>
                                        <span class="badge bg-<?= match(($incident['severity'] ?? 'low')) {
                                            'high' => 'danger',
                                            'medium' => 'warning text-dark',
                                            'low' => 'info',
                                            default => 'secondary'
                                        } ?>">
                                            <?= e(ucfirst((string) ($incident['severity'] ?? 'low'))) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= match(($incident['status'] ?? 'open')) {
                                            'resolved' => 'success',
                                            'investigating' => 'warning text-dark',
                                            'open' => 'danger',
                                            default => 'secondary'
                                        } ?>">
                                            <?= e(ucfirst((string) ($incident['status'] ?? 'open'))) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= e(substr((string) ($incident['createdAt'] ?? ''), 0, 10)) ?></td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/student/incidents/view/view.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>"><i class="bi bi-eye"></i></a>
                                        <a class="btn btn-sm btn-outline-warning" href="<?= url('views/student/incidents/edit/edit.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>"><i class="bi bi-pencil"></i></a>
                                        <a class="btn btn-sm btn-outline-danger" href="<?= url('views/student/incidents/delete/delete.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>"><i class="bi bi-trash"></i></a>
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
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
