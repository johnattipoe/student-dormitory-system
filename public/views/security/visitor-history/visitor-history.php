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
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\VisitorService;
use App\Services\StudentService;

$allVisitors = (new VisitorService())->history();
$students = [];
foreach (StudentService::all() as $student) {
    $students[(string) ($student['id'] ?? '')] = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
}
$search = strtolower(sanitize($_GET['search'] ?? ''));
$statusFilter = strtolower(sanitize($_GET['status'] ?? 'all'));
$dateFrom = sanitize($_GET['dateFrom'] ?? '');
$dateTo = sanitize($_GET['dateTo'] ?? '');
$visitors = array_values(array_filter($allVisitors, static function (array $visitor) use ($search, $statusFilter, $dateFrom, $dateTo): bool {
    $status = strtolower((string) ($visitor['status'] ?? 'checked_out'));
    $visitDate = substr((string) ($visitor['checkInTime'] ?? $visitor['visitDate'] ?? $visitor['createdAt'] ?? ''), 0, 10);
    $haystack = strtolower(implode(' ', [(string) ($visitor['visitorName'] ?? ''), (string) ($visitor['studentId'] ?? ''), (string) ($visitor['purpose'] ?? '')]));
    return ($search === '' || str_contains($haystack, $search))
        && ($statusFilter === 'all' || $status === $statusFilter)
        && ($dateFrom === '' || $visitDate >= $dateFrom)
        && ($dateTo === '' || $visitDate <= $dateTo);
}));
usort($visitors, static fn(array $first, array $second): int => strcmp((string) ($second['checkOutTime'] ?? $second['checkInTime'] ?? $second['createdAt'] ?? ''), (string) ($first['checkOutTime'] ?? $first['checkInTime'] ?? $first['createdAt'] ?? '')));
$checkedOutCount = count(array_filter($allVisitors, static fn(array $visitor): bool => strtolower((string) ($visitor['status'] ?? '')) === 'checked_out'));
$insideCount = count(array_filter($allVisitors, static fn(array $visitor): bool => strtolower((string) ($visitor['status'] ?? '')) === 'inside'));
$todayCount = count(array_filter($allVisitors, static fn(array $visitor): bool => str_starts_with((string) ($visitor['checkInTime'] ?? $visitor['visitDate'] ?? ''), date('Y-m-d'))));

$pageTitle = 'Visitor History';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php'), 'active' => true],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents/incidents.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php')],
    ['icon' => 'bi-person-plus', 'label' => 'Register Visitor', 'href' => url('views/security/register-visitor/register-visitor.php')],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-journal-text text-primary me-2"></i>Visitor Movement History</h4>
                <p class="text-muted mb-0">Review completed visits, entry logs, and movement archives from security checkpoints</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary btn-sm" href="<?= url('views/security/visitors/visitors/visitors.php') ?>">
                    <i class="bi bi-people me-1"></i>Live Active Visitors
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Visits</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) count($allVisitors)) ?></h3>
                            <span class="small text-muted">All logs</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-journal-text fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Today's Visits</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $todayCount) ?></h3>
                            <span class="small text-muted">Logged today</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-calendar-check fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-secondary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Completed</span>
                            <h3 class="fw-bold my-1 text-secondary"><?= e((string) $checkedOutCount) ?></h3>
                            <span class="small text-muted">Checked out</span>
                        </div>
                        <div class="rounded-3 bg-secondary bg-opacity-10 p-2 text-secondary"><i class="bi bi-box-arrow-right fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Inside Now</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $insideCount) ?></h3>
                            <span class="small text-muted">On campus</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Search</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input name="search" class="form-control form-control-sm" placeholder="Visitor name, host, purpose..." value="<?= e($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="all">All statuses</option>
                            <option value="checked_out" <?= $statusFilter === 'checked_out' ? 'selected' : '' ?>>Checked out</option>
                            <option value="inside" <?= $statusFilter === 'inside' ? 'selected' : '' ?>>Inside</option>
                            <option value="registered" <?= $statusFilter === 'registered' ? 'selected' : '' ?>>Registered</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Date From</label>
                        <input type="date" name="dateFrom" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Date To</label>
                        <input type="date" name="dateTo" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a href="<?= url('views/security/visitor-history/visitor-history.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- History Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Movement Records</h6>
                <small class="text-muted">Showing <strong><?= count($visitors) ?></strong> record(s)</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Visitor</th>
                            <th>Host Student</th>
                            <th>Purpose</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($visitors)): ?>
                            <?php foreach ($visitors as $visitor): ?>
                                <?php
                                $checkIn = strtotime((string) ($visitor['checkInTime'] ?? ''));
                                $checkOut = strtotime((string) ($visitor['checkOutTime'] ?? ''));
                                $duration = ($checkIn && $checkOut) ? max(0, round(($checkOut - $checkIn) / 3600, 1)) . ' hrs' : '—';
                                $visitorStatus = strtolower((string) ($visitor['status'] ?? 'checked_out'));
                                ?>
                                <tr>
                                    <td>
                                        <strong class="d-block text-dark"><?= e($visitor['visitorName'] ?? 'Visitor') ?></strong>
                                        <small class="text-muted"><?= e($visitor['phone'] ?? 'No phone') ?></small>
                                    </td>
                                    <td>
                                        <strong class="d-block text-dark"><?= e($students[(string) ($visitor['studentId'] ?? '')] ?? 'Student ' . ($visitor['studentId'] ?? '—')) ?></strong>
                                        <small class="text-muted">ID: <?= e($visitor['studentId'] ?? 'No ID') ?></small>
                                    </td>
                                    <td class="small"><?= e($visitor['purpose'] ?? '—') ?></td>
                                    <td class="small text-nowrap"><?= e($visitor['checkInTime'] ?? $visitor['visitDate'] ?? '—') ?></td>
                                    <td class="small text-nowrap"><?= e($visitor['checkOutTime'] ?? '—') ?></td>
                                    <td class="small"><?= e((string) $duration) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $visitorStatus === 'inside' ? 'success' : ($visitorStatus === 'checked_out' ? 'secondary' : 'warning text-dark') ?> text-capitalize">
                                            <?= e(str_replace('_', ' ', $visitorStatus)) ?>
                                        </span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?php if (!empty($visitor['id'])): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/security/visitors/view/view.php?id=' . urlencode((string) $visitor['id'])) ?>">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5"><i class="bi bi-journal-x fs-3 d-block mb-2"></i>No visitor history matches these filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
