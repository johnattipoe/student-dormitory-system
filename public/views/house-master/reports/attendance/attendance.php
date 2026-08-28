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

use App\Services\AttendanceService;
use App\Services\StudentService;
use App\Services\UserService;
use App\Services\HouseService;
use App\Services\FirebaseService;

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}

$records = AttendanceService::forDate($date, $houseId);
$summary = AttendanceService::summary($date, $houseId);

// Build markedBy resolver map
$userMap = [];
try {
    foreach ((new UserService())->all() as $u) {
        $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
        if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? $u['email'] ?? '';
        if ($name !== '') {
            $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
            $displayName = $name . $roleLabel;
            foreach ([$u['id'] ?? null, $u['uid'] ?? null, $u['userId'] ?? null, $u['firebaseUid'] ?? null, $u['email'] ?? null] as $key) {
                if ($key !== null && (string)$key !== '') {
                    $userMap[(string)$key] = $displayName;
                }
            }
        }
    }
} catch (\Throwable $e) {}

$getMarkedByName = function (array $record) use (&$userMap): string {
    if (!empty($record['markedByName']) && !str_starts_with((string)$record['markedByName'], 'Staff/User')) {
        return (string) $record['markedByName'];
    }
    $raw = (string) ($record['markedBy'] ?? $record['marked_by'] ?? $record['recordedBy'] ?? '');
    if ($raw === '') return 'System';
    if (isset($userMap[$raw])) return $userMap[$raw];

    try {
        $u = FirebaseService::getInstance()->getDocument('users', $raw);
        if ($u) {
            $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
            if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? '';
            if ($name !== '') {
                $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                $userMap[$raw] = $name . $roleLabel;
                return $userMap[$raw];
            }
        }
    } catch (\Throwable $e) {}

    return $raw;
};

$pageTitle = 'Attendance Report';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">House Attendance Report (<?= e($houseName) ?>)</h5>
                <p class="text-muted mb-0">Daily roll call details for <?= e(date('F d, Y', strtotime($date))) ?>.</p>
            </div>
            <div class="d-flex gap-2">
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>">
                    <button class="btn btn-primary btn-sm">Filter</button>
                </form>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/reports/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i> Reports Overview
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <?php foreach ([
                'total' => ['Total Roll Call', 'primary'],
                'present' => ['Present', 'success'],
                'absent' => ['Absent', 'danger'],
                'late' => ['Late', 'warning'],
                'excused' => ['Excused', 'info'],
            ] as $key => [$label, $color]): ?>
                <div class="col">
                    <div class="card stat-card p-3">
                        <small class="text-muted"><?= e($label) ?></small>
                        <strong class="fs-3 text-<?= e($color) ?>"><?= e((string) ($summary[$key] ?? 0)) ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-bold">Student Roll Call Logs</h6>
                <a class="btn btn-sm btn-outline-success" href="<?= url('views/house-master/reports/export/export.php?type=attendance&date=' . urlencode($date)) ?>">
                    <i class="bi bi-filetype-csv me-1"></i> Export Attendance CSV
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover data-table w-100">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Admission No.</th>
                            <th>Class / Form</th>
                            <th>Status</th>
                            <th>Marked By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No attendance records found for <?= e($date) ?>.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $record): ?>
                                <?php 
                                    $student = $studentMap[(string) ($record['studentId'] ?? '')] ?? []; 
                                    $sName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($record['studentName'] ?? $record['studentId'] ?? 'Student');
                                    $status = strtolower((string) ($record['status'] ?? 'unknown'));
                                    $badgeColor = match($status) {
                                        'present' => 'success',
                                        'absent' => 'danger',
                                        'late' => 'warning',
                                        'excused' => 'info',
                                        default => 'secondary'
                                    };
                                    $markedByName = $getMarkedByName($record);
                                ?>
                                <tr>
                                    <td><strong><?= e($sName) ?></strong></td>
                                    <td><?= e($student['admissionNo'] ?? '—') ?></td>
                                    <td><?= e($student['class'] ?? $student['level'] ?? '—') ?></td>
                                    <td>
                                        <span class="badge bg-<?= e($badgeColor) ?>">
                                            <?= e(ucfirst($status)) ?>
                                        </span>
                                    </td>
                                    <td><?= e($markedByName) ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/attendance/view/view.php?id=' . urlencode((string) ($record['id'] ?? ''))) ?>">
                                            <i class="bi bi-eye"></i> Details
                                        </a>
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