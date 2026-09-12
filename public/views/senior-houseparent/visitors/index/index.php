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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
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

$pageTitle = 'Senior Houseparent Visitors';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/senior-houseparent/attendance/index/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/senior-houseparent/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/senior-houseparent/visitors/index/index.php'), 'active' => true],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/senior-houseparent/incidents/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/senior-houseparent/notifications/index/index.php')],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-people-fill text-primary me-2"></i>Visitor Log</h4>
                <p class="text-muted mb-0">Track and manage visitor records for your house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/senior-houseparent/reports/visitors/visitors.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i>Visitor Report
                </a>
                <a href="<?= url('views/senior-houseparent/visitors/requests/requests.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-inbox me-1"></i>View Requests
                </a>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Visitors</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) count($visitors)) ?></h3>
                            <span class="small text-muted">All records</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Currently Inside</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) count($insideVisitors)) ?></h3>
                            <span class="small text-muted">On premises</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Pending</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) count($pendingVisitors)) ?></h3>
                            <span class="small text-muted">Awaiting approval</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-hourglass-split fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-9">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input name="search" class="form-control form-control-sm" placeholder="Search visitor or student name..." value="<?= e($visitorSearch) ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/visitors/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Visitors Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Visitor Records</h6>
                <small class="text-muted">Showing <?= e((string) count($visitors)) ?> records</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
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
                                <td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No visitors found for your house.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($visitors as $visitor): ?>
                                <?php $visitorStudent = $studentMap[(string) ($visitor['studentId'] ?? '')] ?? null; ?>
                                <tr>
                                    <td class="fw-medium"><?= e($visitor['visitorName'] ?? '—') ?></td>
                                    <td><?= e(trim((($visitorStudent['firstName'] ?? '') . ' ' . ($visitorStudent['lastName'] ?? '')))) ?: e($visitor['studentId'] ?? '—') ?></td>
                                    <td class="small"><?= e($visitor['purpose'] ?? $visitor['relationship'] ?? '—') ?></td>
                                    <td class="small text-muted"><?= e($visitor['visitDate'] ?? ($visitor['checkInTime'] ?? '—')) ?></td>
                                    <td><span class="badge bg-<?= ($visitor['status'] ?? '') === 'inside' || ($visitor['status'] ?? '') === 'checked_in' ? 'success' : (($visitor['status'] ?? '') === 'pending' ? 'warning text-dark' : 'secondary') ?>"><?= e(ucfirst($visitor['status'] ?? 'pending')) ?></span></td>
                                    <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/senior-houseparent/visitors/view/view.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>"><i class="bi bi-eye me-1"></i>View</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
