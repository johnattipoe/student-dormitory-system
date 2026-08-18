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

use App\Services\FirebaseService;

// Get filter parameters
$search = sanitize($_GET['search'] ?? '');
$dateFrom = sanitize($_GET['dateFrom'] ?? '');
$dateTo = sanitize($_GET['dateTo'] ?? '');
$status = sanitize($_GET['status'] ?? '');

$studentId = current_user()['uid'];
$firebaseService = FirebaseService::getInstance();
$allVisitors = $firebaseService->getCollection(COL_VISITORS) ?? [];
$visitors = array_filter($allVisitors, fn($v) => ((string) ($v['studentId'] ?? '')) === ((string) $studentId));

// Apply search filter
if (!empty($search)) {
    $search_lower = strtolower($search);
    $visitors = array_filter($visitors, fn($v) => 
        strpos(strtolower($v['visitorName'] ?? ''), $search_lower) !== false ||
        strpos(strtolower($v['relationship'] ?? ''), $search_lower) !== false ||
        strpos(strtolower($v['purpose'] ?? ''), $search_lower) !== false
    );
}

// Apply date range filter
if (!empty($dateFrom)) {
    $visitors = array_filter($visitors, fn($v) => strtotime($v['visitDate'] ?? '') >= strtotime($dateFrom));
}
if (!empty($dateTo)) {
    $visitors = array_filter($visitors, fn($v) => strtotime($v['visitDate'] ?? '') <= strtotime($dateTo) + 86400);
}

// Apply status filter
if (!empty($status)) {
    $visitors = array_filter($visitors, fn($v) => ($v['status'] ?? '') === $status);
}

// Count by status for header
$approved = count(array_filter($visitors, fn($v) => ($v['status'] ?? '') === 'approved'));
$pending = count(array_filter($visitors, fn($v) => ($v['status'] ?? '') === 'pending'));
$rejected = count(array_filter($visitors, fn($v) => ($v['status'] ?? '') === 'rejected'));

$pageTitle = 'Student Visitors';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php'), 'active' => true],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php')],
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-0">My Visitors</h5>
                <small class="text-muted">
                    Approved: <strong><?= $approved ?></strong> | 
                    Pending: <strong><?= $pending ?></strong> | 
                    Rejected: <strong><?= $rejected ?></strong>
                </small>
            </div>
            <a href="<?= url('views/student/visitors/requests.php') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Request Visitor
            </a>
        </div>

        <!-- Advanced Filters -->
        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-3">
                <input type="hidden" name="route" value="/views/student/visitors/index.php">
                
                <div class="col-md-3">
                    <label class="form-label small">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Name, relationship..." value="<?= e($search) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Date From</label>
                    <input type="date" name="dateFrom" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Date To</label>
                    <input type="date" name="dateTo" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                </div>
            </form>
            <div class="text-end mt-2">
                <a href="<?= url('views/student/visitors/index.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </div>

        <div class="card stat-card p-3">
            <div class="mb-3 small">
                Showing <strong><?= count($visitors) ?></strong> visitor record(s)
                <?php if (!empty($search) || !empty($dateFrom) || !empty($dateTo) || !empty($status)): ?>
                    (filtered)
                <?php endif; ?>
            </div>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Relationship</th>
                        <th>Visit Date</th>
                        <th>Purpose</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($visitors)): ?>
                        <?php foreach ($visitors as $visitor): ?>
                            <tr>
                                <td><?= e($visitor['visitorName'] ?? '') ?></td>
                                <td><?= e($visitor['relationship'] ?? '—') ?></td>
                                <td><?= e($visitor['visitDate'] ?? '') ?></td>
                                <td><?= e(substr($visitor['purpose'] ?? '', 0, 40)) ?></td>
                                <td>
                                    <span class="badge bg-<?= match(($visitor['status'] ?? 'pending')) {
                                        'approved' => 'success',
                                        'pending' => 'warning',
                                        'rejected' => 'danger',
                                        default => 'secondary'
                                    } ?>">
                                        <?= e(ucfirst($visitor['status'] ?? 'pending')) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No visitor records matching your filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
