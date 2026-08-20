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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\IncidentService;
use App\Services\StudentService;

$search = sanitize($_GET['search'] ?? '');
$dateFrom = sanitize($_GET['dateFrom'] ?? '');
$dateTo = sanitize($_GET['dateTo'] ?? '');
$severity = sanitize($_GET['severity'] ?? '');
$status = sanitize($_GET['status'] ?? '');

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}
$incidents = (new IncidentService())->byHouse($houseId);

if (!empty($search)) {
    $searchLower = strtolower($search);
    $incidents = array_values(array_filter($incidents, function ($incident) use ($searchLower, $studentMap) {
        $student = $studentMap[(string) ($incident['studentId'] ?? '')] ?? [];
        $studentName = trim((($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')));
        return str_contains(strtolower((string) ($incident['type'] ?? '')), $searchLower)
            || str_contains(strtolower((string) ($incident['description'] ?? '')), $searchLower)
            || str_contains(strtolower((string) ($incident['notes'] ?? '')), $searchLower)
            || str_contains(strtolower($studentName), $searchLower);
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

$pageTitle = 'House Master Incidents';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index.php'), 'active' => true],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Incident Log</h5>
        <div class="d-flex justify-content-end mb-3"><a class="btn btn-success btn-sm me-2" href="<?= url('views/house-master/reports/export.php?type=incidents') ?>"><i class="bi bi-filetype-csv"></i> CSV</a><a class="btn btn-primary btn-sm" href="<?= url('views/house-master/incidents/create.php') ?>"><i class="bi bi-plus-lg"></i> Report incident</a></div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Total</div>
                    <div class="fs-3 fw-bold"><?= e((string) count($incidents)) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Open</div>
                    <div class="fs-3 fw-bold text-warning"><?= e((string) $openCount) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Resolved</div>
                    <div class="fs-3 fw-bold text-success"><?= e((string) $resolvedCount) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">High Severity</div>
                    <div class="fs-3 fw-bold text-danger"><?= e((string) $highCount) ?></div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-3">
                <input type="hidden" name="route" value="/views/house-master/incidents/index.php">

                <div class="col-md-3">
                    <label class="form-label small">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Student, type, notes..." value="<?= e($search) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Date From</label>
                    <input type="date" name="dateFrom" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Date To</label>
                    <input type="date" name="dateTo" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Severity</label>
                    <select name="severity" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="low" <?= $severity === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= $severity === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= $severity === 'high' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="investigating" <?= $status === 'investigating' ? 'selected' : '' ?>>Investigating</option>
                        <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    </select>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                </div>
            </form>
            <div class="text-end mt-2">
                <a href="<?= url('views/house-master/incidents/index.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>

        <div class="card stat-card p-3">
            <div class="mb-3 small">
                Showing <strong><?= count($incidents) ?></strong> incident(s)
                <?php if (!empty($search) || !empty($dateFrom) || !empty($dateTo) || !empty($severity) || !empty($status)): ?>
                    (filtered)
                <?php endif; ?>
            </div>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Type</th>
                        <th>Summary</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Reported</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($incidents)): ?>
                        <?php foreach ($incidents as $incident): ?>
                            <?php $incidentStudent = $studentMap[(string) ($incident['studentId'] ?? '')] ?? null; ?>
                            <tr>
                                <td><?= e(trim((($incidentStudent['firstName'] ?? '') . ' ' . ($incidentStudent['lastName'] ?? '')))) ?: e($incident['studentId'] ?? '—') ?></td>
                                <td><?= e($incident['type'] ?? '') ?></td>
                                <td><?= e(substr((string) ($incident['description'] ?? ($incident['notes'] ?? '')), 0, 80)) ?: '—' ?></td>
                                <td>
                                    <?php $priority = $incident['priority'] ?? $incident['severity'] ?? 'low'; ?>
                                    <span class="badge bg-<?= match ($priority) {
                                        'high' => 'danger',
                                        'medium' => 'warning',
                                        'low' => 'info',
                                        default => 'secondary'
                                    } ?>\"><?= e(ucfirst((string) $priority)) ?></span>
                                </td>
                                <td><span class="badge bg-<?= ($incident['status'] ?? 'open') === 'resolved' ? 'success' : (($incident['status'] ?? '') === 'investigating' ? 'warning' : 'secondary') ?>"><?= e(ucfirst((string) ($incident['status'] ?? 'open'))) ?></span></td>
                                <td><?= e(substr((string) ($incident['createdAt'] ?? ''), 0, 10)) ?></td>
                                <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/incidents/view.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/house-master/incidents/edit.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/house-master/incidents/delete.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No incidents matching your filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
