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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

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
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php'), 'active' => true],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php')],
    ['icon' => 'bi-person-plus', 'label' => 'Register Visitor', 'href' => url('views/security/register-visitor/register-visitor.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper security-portal">
        <section class="security-hero mb-4">
            <div class="security-hero-icon">
                <i class="bi bi-journal-text"></i>
            </div>
            <div>
                <span class="security-kicker">Access records</span>
                <h1>Visitor history</h1>
                <p>Review completed visits and movement records from the security desk.</p>
            </div>
            <a class="btn btn-light" href="<?= url('views/security/visitors/visitors.php') ?>">
                <i class="bi bi-people me-1"></i>Live visitors
            </a>
        </section>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="security-stat">
                    <span class="security-stat-icon blue">
                        <i class="bi bi-journal-text"></i>
                    </span>
                    <div>
                        <small>Total visits</small>
                        <strong><?= e((string) count($allVisitors)) ?></strong>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="security-stat">
                    <span class="security-stat-icon green">
                        <i class="bi bi-calendar-day"></i>
                    </span>
                    <div>
                        <small>Today</small>
                        <strong><?= e((string) $todayCount) ?></strong>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="security-stat">
                    <span class="security-stat-icon orange">
                        <i class="bi bi-box-arrow-right"></i>
                    </span>
                    <div>
                        <small>Completed</small>
                        <strong><?= e((string) $checkedOutCount) ?></strong>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="security-stat">
                    <span class="security-stat-icon red">
                        <i class="bi bi-person-check"></i>
                    </span>
                    <div>
                        <small>Inside now</small>
                        <strong><?= e((string) $insideCount) ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="security-card">
            <div class="security-card-header">
                <div>
                    <span class="security-kicker">Archive</span>
                    <h2>Movement records</h2>
                    <p><?= e((string) count($visitors)) ?> matching records found.</p>
                </div>
                <form method="GET" class="security-filter-bar">
                    <input name="search" class="form-control form-control-sm" placeholder="Search visitor or host" value="<?= e($search) ?>">
                    <select name="status" class="form-select form-select-sm">
                        <option value="all">All statuses</option>
                        <option value="checked_out" <?= $statusFilter === 'checked_out' ? 'selected' : '' ?>>Checked out</option>
                        <option value="inside" <?= $statusFilter === 'inside' ? 'selected' : '' ?>>Inside</option>
                        <option value="registered" <?= $statusFilter === 'registered' ? 'selected' : '' ?>>Registered</option>
                    </select>
                    <input type="date" name="dateFrom" class="form-control form-control-sm" value="<?= e($dateFrom) ?>" aria-label="From date">
                    <input type="date" name="dateTo" class="form-control form-control-sm" value="<?= e($dateTo) ?>" aria-label="To date">
                    <button class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-search"></i>
                        <span class="visually-hidden">Filter</span>
                    </button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle security-data-table w-100">
                    <thead>
                        <tr>
                            <th>Visitor</th>
                            <th>Host student</th>
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
                                <?php $checkIn = strtotime((string) ($visitor['checkInTime'] ?? '')); $checkOut = strtotime((string) ($visitor['checkOutTime'] ?? '')); $duration = ($checkIn && $checkOut) ? max(0, round(($checkOut - $checkIn) / 3600, 1)) . ' hrs' : '—'; $visitorStatus = strtolower((string) ($visitor['status'] ?? 'checked_out')); ?>
                                <tr>
                                <td><strong><?= e($visitor['visitorName'] ?? 'Visitor') ?></strong><small class="d-block text-muted"><?= e($visitor['phone'] ?? 'No phone') ?></small></td>
                                <td><strong><?= e($students[(string) ($visitor['studentId'] ?? '')] ?? 'Student ' . ($visitor['studentId'] ?? '—')) ?></strong><small class="d-block text-muted"><?= e($visitor['studentId'] ?? 'No ID') ?></small></td>
                                <td><?= e($visitor['purpose'] ?? '—') ?></td>
                                <td class="text-nowrap"><?= e($visitor['checkInTime'] ?? $visitor['visitDate'] ?? '—') ?></td>
                                <td class="text-nowrap"><?= e($visitor['checkOutTime'] ?? '—') ?></td>
                                <td><?= e((string) $duration) ?></td>
                                <td><span class="badge bg-<?= $visitorStatus === 'inside' ? 'success' : ($visitorStatus === 'checked_out' ? 'secondary' : 'warning text-dark') ?> text-capitalize"><?= e(str_replace('_', ' ', $visitorStatus)) ?></span></td>
                                <td class="text-end"><?php if (!empty($visitor['id'])): ?><a class="btn btn-sm btn-outline-primary" href="<?= url('views/security/visitors/view.php?id=' . urlencode((string) $visitor['id'])) ?>"><i class="bi bi-eye"></i><span class="visually-hidden">View visitor</span></a><?php endif; ?></td>
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
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
