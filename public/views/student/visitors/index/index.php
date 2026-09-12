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

use App\Services\FirebaseService;

$search = sanitize($_GET['search'] ?? '');
$dateFrom = sanitize($_GET['dateFrom'] ?? '');
$dateTo = sanitize($_GET['dateTo'] ?? '');
$status = sanitize($_GET['status'] ?? '');

$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? null;
$firebaseService = FirebaseService::getInstance();
$allVisitors = $firebaseService->getCollection(COL_VISITOR_REQUESTS) ?? [];
$visitors = array_values(array_filter($allVisitors, fn($v) => ((string) ($v['studentId'] ?? '')) === ((string) $studentId)));

if (!empty($search)) {
    $searchLower = strtolower($search);
    $visitors = array_values(array_filter($visitors, fn($v) =>
        str_contains(strtolower((string) ($v['visitorName'] ?? '')), $searchLower) ||
        str_contains(strtolower((string) ($v['relationship'] ?? '')), $searchLower) ||
        str_contains(strtolower((string) ($v['purpose'] ?? '')), $searchLower)
    ));
}

if (!empty($dateFrom)) {
    $visitors = array_values(array_filter($visitors, fn($v) => strtotime((string) ($v['visitDate'] ?? '')) >= strtotime($dateFrom)));
}
if (!empty($dateTo)) {
    $visitors = array_values(array_filter($visitors, fn($v) => strtotime((string) ($v['visitDate'] ?? '')) <= strtotime($dateTo) + 86400));
}

if (!empty($status)) {
    $visitors = array_values(array_filter($visitors, fn($v) => ($v['status'] ?? '') === $status));
}

$approved = count(array_filter($visitors, fn($v) => ($v['status'] ?? '') === 'approved'));
$pending = count(array_filter($visitors, fn($v) => ($v['status'] ?? '') === 'pending'));
$rejected = count(array_filter($visitors, fn($v) => ($v['status'] ?? '') === 'rejected'));
$total = count($visitors);

$pageTitle = 'Student Visitors';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index/index.php'), 'active' => true],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index/index.php')],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-people-fill text-primary me-2"></i>My Visitors</h4>
                <p class="text-muted mb-0">Keep track of all visitor requests and approvals</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/visitors/request/request.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Request Visitor
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Requests</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $total) ?></h3>
                            <span class="small text-muted">All recorded visits</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Approved</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $approved) ?></h3>
                            <span class="small text-muted">Authorized entry</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Pending</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $pending) ?></h3>
                            <span class="small text-muted">Awaiting approval</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-hourglass-split fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Rejected</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $rejected) ?></h3>
                            <span class="small text-muted">Declined requests</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-x-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <input type="hidden" name="route" value="/views/student/visitors/index/index.php">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Search</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, relationship..." value="<?= e($search) ?>">
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
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a href="<?= url('views/student/visitors/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Visitors Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Visitor Records</h6>
                <small class="text-muted">
                    Showing <strong><?= count($visitors) ?></strong> record(s)
                    <?php if (!empty($search) || !empty($dateFrom) || !empty($dateTo) || !empty($status)): ?>
                        (filtered)
                    <?php endif; ?>
                </small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Relationship</th>
                            <th>Visit Date</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($visitors)): ?>
                            <?php foreach ($visitors as $visitor): ?>
                                <tr>
                                    <td class="fw-medium"><?= e($visitor['visitorName'] ?? '') ?></td>
                                    <td><?= e($visitor['relationship'] ?? '—') ?></td>
                                    <td class="small text-muted"><?= e($visitor['visitDate'] ?? '') ?></td>
                                    <td class="small"><?= e(substr($visitor['purpose'] ?? '', 0, 40)) ?></td>
                                    <td>
                                        <span class="badge bg-<?= match(($visitor['status'] ?? 'pending')) {
                                            'approved' => 'success',
                                            'pending' => 'warning text-dark',
                                            'rejected' => 'danger',
                                            default => 'secondary'
                                        } ?>">
                                            <?= e(ucfirst($visitor['status'] ?? 'pending')) ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/student/visitors/view/view.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>"><i class="bi bi-eye me-1"></i>View</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/student/visitors/edit/edit.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
                                        <?php if (($visitor['status'] ?? 'pending') === 'pending'): ?>
                                            <form method="POST" action="<?= url('views/student/visitors/delete/delete.php') ?>" class="d-inline">
                                                <input type="hidden" name="id" value="<?= e((string) ($visitor['id'] ?? '')) ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Delete this pending visitor request?')"><i class="bi bi-trash me-1"></i>Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No visitor records matching your filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
