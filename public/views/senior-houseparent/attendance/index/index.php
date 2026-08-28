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

use App\Services\AttendanceService;
use App\Services\BedService;
use App\Services\HouseService;
use App\Services\StudentService;
use App\Services\UserService;

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$searchQuery = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$houseFilter = sanitize($_GET['house'] ?? '');

$attendance = AttendanceService::forDate($date);
$students = StudentService::all();
$beds = BedService::all();
$bedMap = [];
foreach ($beds as $bed) {
    if (!empty($bed['studentId'])) $bedMap[(string) $bed['studentId']] = (string) ($bed['bedNumber'] ?? '—');
}
$houses = HouseService::all();
$markedByMap = [];
foreach ((new UserService())->all() as $user) {
    $markedByName = trim((string) ($user['name'] ?? '')) ?: (string) ($user['email'] ?? '');
    foreach ([(string) ($user['id'] ?? ''), (string) ($user['uid'] ?? '')] as $userKey) {
        if ($userKey !== '' && $markedByName !== '') {
            $markedByMap[$userKey] = $markedByName;
        }
    }
}

if (!empty($searchQuery)) {
    $attendance = array_filter($attendance, function($record) use ($searchQuery, $students) {
        $student = current(array_filter($students, fn($s) => ((string) ($s['id'] ?? '')) === ((string) ($record['studentId'] ?? ''))));
        $name = ($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '');
        $admNo = $student['admissionNo'] ?? '';
        return stripos($name, $searchQuery) !== false || stripos($admNo, $searchQuery) !== false;
    });
}
if (!empty($statusFilter)) {
    $attendance = array_filter($attendance, fn($record) => ($record['status'] ?? 'present') === $statusFilter);
}
if (!empty($houseFilter)) {
    $attendance = array_filter($attendance, function($record) use ($houseFilter, $students) {
        $student = current(array_filter($students, fn($s) => ((string) ($s['id'] ?? '')) === ((string) ($record['studentId'] ?? ''))));
        return ((string) ($student['houseId'] ?? '')) === $houseFilter;
    });
}

$summary = AttendanceService::summary($date);
$houseMap = [];
foreach ($houses as $house) {
    $houseMap[(string) ($house['id'] ?? '')] = $house['name'] ?? 'House';
}

$pageTitle = 'Senior Houseparent Attendance';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/senior-houseparent/attendance/index/index.php'), 'active' => true],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/senior-houseparent/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/senior-houseparent/visitors/index/index.php')],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-calendar-check-fill text-success me-2"></i>Attendance Overview</h4>
                <p class="text-muted mb-0">View and manage attendance records across all houses for <?= e(date('F d, Y', strtotime($date))) ?></p>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Present</span>
                            <h3 class="fw-bold my-1 text-success"><?= e($summary['present'] ?? 0) ?></h3>
                            <span class="small text-muted">On time</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Absent</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e($summary['absent'] ?? 0) ?></h3>
                            <span class="small text-muted">Not accounted</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-x-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Late</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e($summary['late'] ?? 0) ?></h3>
                            <span class="small text-muted">Arrived late</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-clock fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Records</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) count($attendance)) ?></h3>
                            <span class="small text-muted">For <?= e($date) ?></span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-list-check fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <input type="hidden" name="route" value="/views/senior-houseparent/attendance/index/index.php">
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Date</label>
                        <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Search Student</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Name or admission no." value="<?= e($searchQuery) ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present</option>
                            <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                            <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late</option>
                            <option value="excused" <?= $statusFilter === 'excused' ? 'selected' : '' ?>>Excused</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">House</label>
                        <select name="house" class="form-select form-select-sm">
                            <option value="">All Houses</option>
                            <?php foreach ($houses as $h): ?>
                                <option value="<?= e((string) ($h['id'] ?? '')) ?>" <?= $houseFilter === ((string) ($h['id'] ?? '')) ? 'selected' : '' ?>>
                                    <?= e($h['name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a href="<?= url('views/senior-houseparent/attendance/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2"></i>Attendance Records</h6>
                <small class="text-muted">
                    Showing <strong><?= count($attendance) ?></strong> record(s)
                    <?php if (!empty($searchQuery) || !empty($statusFilter) || !empty($houseFilter)): ?>
                        (filtered)
                    <?php endif; ?>
                </small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>House</th>
                            <th>Bed</th>
                            <th>Status</th>
                            <th>Marked By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendance)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No attendance records found matching your filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attendance as $record): ?>
                                <?php
                                $student = null;
                                foreach ($students as $s) {
                                    if (($s['id'] ?? '') === ($record['studentId'] ?? '')) {
                                        $student = $s;
                                        break;
                                    }
                                }
                                ?>
                                <tr>
                                    <td class="small text-muted"><?= e($record['date'] ?? '-') ?></td>
                                    <td class="fw-medium"><?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' (' . ($student['admissionNo'] ?? '') . ')') ?: e($record['studentId'] ?? '-') ?></td>
                                    <td><?= e($houseMap[(string) ($record['houseId'] ?? '')] ?? ($student['houseId'] ?? ($record['houseId'] ?? '—'))) ?></td>
                                    <td><?= e($bedMap[(string) ($record['studentId'] ?? '')] ?? '—') ?></td>
                                    <td><span class="badge bg-<?= ($record['status'] ?? 'present') === 'present' ? 'success' : (($record['status'] ?? '') === 'absent' ? 'danger' : 'warning') ?>"><?= e($record['status'] ?? 'present') ?></span></td>
                                    <td class="small"><?= e($markedByMap[(string) ($record['markedBy'] ?? '')] ?? ($record['markedBy'] ?? '—')) ?></td>
                                    <td>
                                        <?php if (!empty($record['id'])): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/senior-houseparent/attendance/view/view.php?id=' . urlencode((string) $record['id'])) ?>"><i class="bi bi-eye me-1"></i>View</a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
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