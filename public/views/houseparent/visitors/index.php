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
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\VisitorService;

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}
$visitors = (new VisitorService())->byHouse($houseId);
$insideVisitors = array_values(array_filter($visitors, fn($visitor) => ($visitor['status'] ?? '') === 'inside'));
$pendingVisitors = array_values(array_filter($visitors, fn($visitor) => ($visitor['status'] ?? '') === 'pending'));
$visitorSearch = strtolower(sanitize($_GET['search'] ?? ''));
if ($visitorSearch !== '') {
    $visitors = array_values(array_filter($visitors, function ($visitor) use ($visitorSearch, $studentMap) {
        $student = $studentMap[(string) ($visitor['studentId'] ?? '')] ?? [];
        return str_contains(strtolower((string) ($visitor['visitorName'] ?? '')), $visitorSearch)
            || str_contains(strtolower(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))), $visitorSearch);
    }));
}

$pageTitle = 'Houseparent Visitors';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/houseparent/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/houseparent/attendance/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/houseparent/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/houseparent/visitors/index.php'), 'active' => true],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/houseparent/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Visitor Log</h5>
            <div><a href="<?= url('views/houseparent/reports/visitors.php') ?>" class="btn btn-outline-primary btn-sm">Visitor report</a> <a href="<?= url('views/houseparent/visitors/requests.php') ?>" class="btn btn-outline-secondary btn-sm">View requests</a></div>
        </div>

        <div class="card stat-card p-3 mb-3"><form method="GET" class="row g-2"><div class="col-md-9"><input name="search" class="form-control form-control-sm" placeholder="Search visitor or student" value="<?= e($visitorSearch) ?>"></div><div class="col-md-3"><button class="btn btn-primary btn-sm">Filter</button> <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/houseparent/visitors/index.php') ?>">Reset</a></div></form></div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Total</div>
                    <div class="fs-2 fw-bold"><?= e((string) count($visitors)) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Inside</div>
                    <div class="fs-2 fw-bold"><?= e((string) count($insideVisitors)) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Pending</div>
                    <div class="fs-2 fw-bold"><?= e((string) count($pendingVisitors)) ?></div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Student</th>
                        <th>Purpose</th>
                        <th>Visit Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($visitors)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No visitors found for your house.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($visitors as $visitor): ?>
                            <?php $visitorStudent = $studentMap[(string) ($visitor['studentId'] ?? '')] ?? null; ?>
                            <tr>
                                <td><?= e($visitor['visitorName'] ?? '—') ?></td>
                                <td><?= e(trim((($visitorStudent['firstName'] ?? '') . ' ' . ($visitorStudent['lastName'] ?? '')))) ?: e($visitor['studentId'] ?? '—') ?></td>
                                <td><?= e($visitor['purpose'] ?? $visitor['relationship'] ?? '—') ?></td>
                                <td><?= e($visitor['visitDate'] ?? ($visitor['checkInTime'] ?? '—')) ?></td>
                                <td><span class="badge bg-<?= ($visitor['status'] ?? '') === 'inside' || ($visitor['status'] ?? '') === 'checked_in' ? 'success' : (($visitor['status'] ?? '') === 'pending' ? 'warning' : 'secondary') ?>"><?= e($visitor['status'] ?? 'pending') ?></span></td>
                                <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/houseparent/visitors/view.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/houseparent/visitors/edit.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/houseparent/visitors/delete.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
