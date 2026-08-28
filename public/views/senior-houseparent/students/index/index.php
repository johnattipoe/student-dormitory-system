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
use App\Services\RoomService;

$students = StudentService::all(current_user()['houseId'] ?? null);
$rooms = RoomService::all(current_user()['houseId'] ?? null);
$roomMap = [];
foreach ($rooms as $room) {
    $roomMap[(string) ($room['id'] ?? '')] = (string) ($room['roomNumber'] ?? $room['id'] ?? '');
}

$studentSearch = strtolower(sanitize($_GET['search'] ?? ''));
if ($studentSearch !== '') {
    $students = array_values(array_filter($students, function ($student) use ($studentSearch) {
        $haystack = strtolower(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' ' . ($student['admissionNo'] ?? '') . ' ' . ($student['class'] ?? '') . ' ' . ($student['course'] ?? '')));
        return str_contains($haystack, $studentSearch);
    }));
}

$totalStudents = count($students);
$activeStudents = count(array_filter($students, fn($s) => ($s['status'] ?? '') === 'active'));
$assignedRooms = count(array_filter($students, fn($s) => !empty($s['roomId'])));
$unassignedRooms = max(0, $totalStudents - $assignedRooms);

$pageTitle = 'Senior Houseparent Students';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php'), 'active' => true],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/senior-houseparent/attendance/index/index.php')],
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
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-mortarboard-fill text-primary me-2"></i>Campus Residents Registry
                </h4>
                <p class="text-muted mb-0">Monitor student residential profiles, pastoral welfare, and dorm room placements</p>
            </div>
            <span class="badge bg-primary fs-6"><?= e((string) $totalStudents) ?> Students</span>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Residents</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalStudents) ?></h3>
                            <span class="small text-muted">Enrolled students</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Active In Residence</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $activeStudents) ?></h3>
                            <span class="small text-muted">Good standing</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Bed Assigned</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $assignedRooms) ?></h3>
                            <span class="small text-muted">Roomed residents</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-door-open fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Unassigned</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $unassignedRooms) ?></h3>
                            <span class="small text-muted">Pending room</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-person-exclamation fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Search Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-9">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input name="search" class="form-control form-control-sm border-start-0" placeholder="Search by name, admission number, class, or academic course..." value="<?= e($studentSearch) ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-filter me-1"></i> Filter</button> 
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/students/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Students Table Card -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-mortarboard me-2 text-primary"></i>Resident Students</h6>
                <small class="text-muted">Showing <?= count($students) ?> records</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Admission No.</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Form</th>
                                <th>Class Code</th>
                                <th>Room Block</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($students)): ?>
                                <?php foreach ($students as $student): ?>
                                    <?php 
                                    $st = strtolower((string)($student['status'] ?? 'active'));
                                    $stBadge = match($st) { 'active' => 'bg-success', 'suspended' => 'bg-danger', default => 'bg-secondary' };
                                    $sId = (string)($student['id'] ?? '');
                                    ?>
                                    <tr>
                                        <td><span class="font-monospace text-muted"><?= e($student['admissionNo'] ?? '—') ?></span></td>
                                        <td>
                                            <a href="<?= url('views/senior-houseparent/students/profile/profile.php?studentId=' . urlencode($sId)) ?>" class="text-decoration-none fw-bold text-dark">
                                                <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?>
                                            </a>
                                        </td>
                                        <td><?= e($student['class'] ?? '—') ?></td>
                                        <td><?= e($student['form'] ?? $student['level'] ?? '—') ?></td>
                                        <td><small class="text-muted"><?= e($student['course'] ?? '—') ?></small></td>
                                        <td><span class="badge bg-light text-dark border">Room <?= e($roomMap[(string) ($student['roomId'] ?? '')] ?? ($student['roomId'] ?? '—')) ?></span></td>
                                        <td><span class="badge <?= $stBadge ?>"><?= ucfirst(e($st)) ?></span></td>
                                        <td class="text-end">
                                            <?php if ($sId !== ''): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="<?= url('views/senior-houseparent/students/profile/profile.php?studentId=' . urlencode($sId)) ?>" title="View Profile">
                                                    <i class="bi bi-eye me-1"></i> Profile
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No student records found matching your query.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
