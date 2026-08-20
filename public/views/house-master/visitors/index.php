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

use App\Services\StudentService;
use App\Services\VisitorService;

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}
$visitors = (new VisitorService())->byHouse($houseId);
$visitorSearch = strtolower(sanitize($_GET['search'] ?? ''));
$visitorStatus = sanitize($_GET['status'] ?? '');
if ($visitorSearch !== '' || $visitorStatus !== '') {
    $visitors = array_values(array_filter($visitors, function ($visitor) use ($visitorSearch, $visitorStatus, $studentMap) {
        $student = $studentMap[(string) ($visitor['studentId'] ?? '')] ?? [];
        $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
        return ($visitorSearch === '' || str_contains(strtolower((string) ($visitor['visitorName'] ?? '')), $visitorSearch) || str_contains(strtolower($studentName), $visitorSearch))
            && ($visitorStatus === '' || ($visitor['status'] ?? '') === $visitorStatus);
    }));
}
$visitorTotal = count($visitors);
$visitorInside = count(array_filter($visitors, fn($visitor) => ($visitor['status'] ?? '') === 'inside'));

$pageTitle = 'House Master Visitors';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index.php'), 'active' => true],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index.php')],
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
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><div><h5 class="mb-1">Visitors</h5><p class="text-muted mb-0">Review visitor activity for students in your house.</p></div><div><a href="<?= url('views/house-master/reports/export.php?type=visitors') ?>" class="btn btn-success btn-sm"><i class="bi bi-filetype-csv"></i> CSV</a> <a href="<?= url('views/house-master/visitors/create.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Register visitor</a></div></div>
        <div class="row g-3 mb-3"><div class="col-md-4"><div class="card stat-card p-3"><small class="text-muted">Total visitors</small><strong class="fs-3"><?= e((string) $visitorTotal) ?></strong></div></div><div class="col-md-4"><div class="card stat-card p-3"><small class="text-muted">Currently inside</small><strong class="fs-3 text-success"><?= e((string) $visitorInside) ?></strong></div></div><div class="col-md-4"><div class="card stat-card p-3"><small class="text-muted">Other statuses</small><strong class="fs-3"><?= e((string) max(0, $visitorTotal - $visitorInside)) ?></strong></div></div></div>
        <div class="card stat-card p-3 mb-3"><form method="GET" class="row g-2"><div class="col-md-5"><input name="search" class="form-control form-control-sm" placeholder="Visitor or student name" value="<?= e($visitorSearch) ?>"></div><div class="col-md-4"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option><option value="registered" <?= $visitorStatus === 'registered' ? 'selected' : '' ?>>Registered</option><option value="inside" <?= $visitorStatus === 'inside' ? 'selected' : '' ?>>Inside</option><option value="checked_out" <?= $visitorStatus === 'checked_out' ? 'selected' : '' ?>>Checked out</option></select></div><div class="col-md-3"><button class="btn btn-primary btn-sm">Filter</button> <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/visitors/index.php') ?>">Reset</a></div></form></div>
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Student</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($visitors)): ?>
                        <?php foreach ($visitors as $visitor): ?>
                            <?php $visitorStudent = $studentMap[(string) ($visitor['studentId'] ?? '')] ?? null; ?>
                            <tr>
                                <td><?= e($visitor['visitorName'] ?? '') ?></td>
                                <td><?= e(trim((($visitorStudent['firstName'] ?? '') . ' ' . ($visitorStudent['lastName'] ?? '')))) ?: e($visitor['studentId'] ?? '—') ?></td>
                                <td><?= e($visitor['purpose'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= ($visitor['status'] ?? '') === 'inside' ? 'success' : (($visitor['status'] ?? '') === 'checked_out' ? 'dark' : 'secondary') ?>"><?= e($visitor['status'] ?? 'pending') ?></span></td>
                                <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/visitors/view.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/house-master/visitors/edit.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/house-master/visitors/delete.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No visitors found for your house.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
