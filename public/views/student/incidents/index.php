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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\IncidentService;

// Get filter parameters
$search = sanitize($_GET['search'] ?? '');
$dateFrom = sanitize($_GET['dateFrom'] ?? '');
$dateTo = sanitize($_GET['dateTo'] ?? '');
$severity = sanitize($_GET['severity'] ?? '');
$status = sanitize($_GET['status'] ?? '');

$studentId = current_user()['uid'];
$incidents = IncidentService::byStudent($studentId) ?? [];

// Apply search filter
if (!empty($search)) {
    $search_lower = strtolower($search);
    $incidents = array_filter($incidents, fn($i) => 
        strpos(strtolower($i['type'] ?? ''), $search_lower) !== false ||
        strpos(strtolower($i['description'] ?? ''), $search_lower) !== false ||
        strpos(strtolower($i['notes'] ?? ''), $search_lower) !== false
    );
}

// Apply date range filter
if (!empty($dateFrom)) {
    $incidents = array_filter($incidents, fn($i) => strtotime($i['createdAt'] ?? '') >= strtotime($dateFrom));
}
if (!empty($dateTo)) {
    $incidents = array_filter($incidents, fn($i) => strtotime($i['createdAt'] ?? '') <= strtotime($dateTo) + 86400);
}

// Apply severity filter
if (!empty($severity)) {
    $incidents = array_filter($incidents, fn($i) => ($i['severity'] ?? '') === $severity);
}

// Apply status filter
if (!empty($status)) {
    $incidents = array_filter($incidents, fn($i) => ($i['status'] ?? '') === $status);
}

$pageTitle = 'Student Incidents';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php'), 'active' => true],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">My Incidents</h5>

        <!-- Advanced Filters -->
        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-3">
                <input type="hidden" name="route" value="/views/student/incidents/index.php">
                
                <div class="col-md-3">
                    <label class="form-label small">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Type, description..." value="<?= e($search) ?>">
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
                <a href="<?= url('views/student/incidents/index.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
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
                        <th>Type</th>
                        <th>Description</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Reported On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($incidents)): ?>
                        <?php foreach ($incidents as $incident): ?>
                            <tr>
                                <td><?= e($incident['type'] ?? '') ?></td>
                                <td><?= e(substr($incident['description'] ?? '', 0, 60)) ?></td>
                                <td>
                                    <span class="badge bg-<?= match(($incident['severity'] ?? 'low')) {
                                        'high' => 'danger',
                                        'medium' => 'warning',
                                        'low' => 'info',
                                        default => 'secondary'
                                    } ?>">
                                        <?= e(ucfirst($incident['severity'] ?? 'low')) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= match(($incident['status'] ?? 'open')) {
                                        'resolved' => 'success',
                                        'investigating' => 'warning',
                                        'open' => 'secondary',
                                        default => 'secondary'
                                    } ?>">
                                        <?= e(ucfirst($incident['status'] ?? 'open')) ?>
                                    </span>
                                </td>
                                <td><?= e(substr($incident['createdAt'] ?? '', 0, 10)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No incidents matching your filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
