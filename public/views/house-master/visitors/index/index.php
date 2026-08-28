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
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

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

$pageTitle = 'House Visitors';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index/index.php'), 'active' => true],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index/index.php')],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-person-walking text-info me-2"></i>Visitors</h4>
                <p class="text-muted mb-0">Review visitor activity for students in your house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/house-master/reports/export/export.php?type=visitors') ?>" class="btn btn-success btn-sm"><i class="bi bi-filetype-csv me-1"></i>Export CSV</a>
                <a href="<?= url('views/house-master/visitors/create/create.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>Register Visitor</a>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Visitors</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $visitorTotal) ?></h3>
                            <span class="small text-muted">All records</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Currently Inside</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $visitorInside) ?></h3>
                            <span class="small text-muted">On premises now</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-box-arrow-in-right fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-secondary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Other Statuses</span>
                            <h3 class="fw-bold my-1 text-secondary"><?= e((string) max(0, $visitorTotal - $visitorInside)) ?></h3>
                            <span class="small text-muted">Registered / Checked out</span>
                        </div>
                        <div class="rounded-3 bg-secondary bg-opacity-10 p-2 text-secondary"><i class="bi bi-clock-history fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input name="search" class="form-control form-control-sm" placeholder="Search visitor or student name..." value="<?= e($visitorSearch) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            <option value="registered" <?= $visitorStatus === 'registered' ? 'selected' : '' ?>>Registered</option>
                            <option value="inside" <?= $visitorStatus === 'inside' ? 'selected' : '' ?>>Inside</option>
                            <option value="checked_out" <?= $visitorStatus === 'checked_out' ? 'selected' : '' ?>>Checked out</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/visitors/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Visitors Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-walking me-2"></i>Visitor Registry</h6>
                <small class="text-muted">Showing <?= e((string) $visitorTotal) ?> records</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
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
                                    <td class="fw-medium"><?= e($visitor['visitorName'] ?? '') ?></td>
                                    <td><?= e(trim((($visitorStudent['firstName'] ?? '') . ' ' . ($visitorStudent['lastName'] ?? '')))) ?: e($visitor['studentId'] ?? '—') ?></td>
                                    <td><?= e($visitor['purpose'] ?? '—') ?></td>
                                    <td><span class="badge bg-<?= ($visitor['status'] ?? '') === 'inside' ? 'success' : (($visitor['status'] ?? '') === 'checked_out' ? 'dark' : 'secondary') ?>"><?= e($visitor['status'] ?? 'pending') ?></span></td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/visitors/view/view.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>"><i class="bi bi-eye me-1"></i>View</a>
                                        <a class="btn btn-sm btn-outline-warning" href="<?= url('views/house-master/visitors/edit/edit.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
                                        <a class="btn btn-sm btn-outline-danger" href="<?= url('views/house-master/visitors/delete/delete.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>"><i class="bi bi-trash me-1"></i>Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No visitors found for your house.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
