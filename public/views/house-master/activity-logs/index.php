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

use App\Services\FirebaseService;
use App\Services\UserService;
use App\Services\StudentService;
use App\Services\RoomService;
use App\Services\HouseService;

$pageTitle = 'Activity Logs';
$houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

$houseStudents = StudentService::all($houseId);
$houseStudentIds = [];
foreach ($houseStudents as $st) {
    foreach ([$st['id'] ?? null, $st['studentId'] ?? null, $st['admissionNo'] ?? null, $st['userId'] ?? null, $st['uid'] ?? null] as $key) {
        if ($key !== null && (string) $key !== '') {
            $houseStudentIds[(string) $key] = true;
        }
    }
}

$houseRooms = RoomService::all($houseId);
$houseRoomIds = [];
foreach ($houseRooms as $rm) {
    if (!empty($rm['id'])) {
        $houseRoomIds[(string) $rm['id']] = true;
    }
}

$currUser = current_user();
$currUserIds = array_filter([(string) ($currUser['uid'] ?? ''), (string) ($currUser['id'] ?? '')]);

$allLogs = FirebaseService::getInstance()->getCollection(COL_ACTIVITY_LOGS, [], 500);

$logs = array_values(array_filter($allLogs, function ($log) use ($houseId, $houseStudentIds, $currUserIds, $houseRoomIds) {
    if (!empty($log['houseId']) && (string) $log['houseId'] === $houseId) return true;
    if (!empty($log['house_id']) && (string) $log['house_id'] === $houseId) return true;
    foreach (['userId', 'user_id', 'user', 'performedBy', 'actorId', 'actor'] as $k) {
        $actorId = (string) ($log[$k] ?? '');
        if ($actorId !== '' && in_array($actorId, $currUserIds, true)) return true;
    }
    foreach (['studentId', 'student_id', 'targetId', 'target_id', 'userId', 'user_id'] as $k) {
        $targetId = (string) ($log[$k] ?? '');
        if ($targetId !== '' && isset($houseStudentIds[$targetId])) return true;
    }
    foreach (['roomId', 'room_id', 'bedId', 'bed_id'] as $k) {
        $roomId = (string) ($log[$k] ?? '');
        if ($roomId !== '' && isset($houseRoomIds[$roomId])) return true;
    }
    return false;
}));

usort($logs, function ($a, $b) {
    $tA = strtotime((string) ($a['timestamp'] ?? $a['createdAt'] ?? ''));
    $tB = strtotime((string) ($b['timestamp'] ?? $b['createdAt'] ?? ''));
    return $tB <=> $tA;
});

$userMap = ['default-admin' => 'Administrator (Admin)', 'system' => 'System'];
try {
    foreach ((new UserService())->all() as $user) {
        $name = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''))));
        if ($name === '') $name = $user['displayName'] ?? $user['username'] ?? $user['email'] ?? null;
        if ($name) {
            $roleLabel = !empty($user['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $user['role'])) . ')' : '';
            $displayName = $name . $roleLabel;
            foreach ([$user['id'] ?? null, $user['uid'] ?? null, $user['userId'] ?? null, $user['firebaseUid'] ?? null, $user['email'] ?? null] as $key) {
                if ($key !== null && $key !== '') $userMap[(string) $key] = $displayName;
            }
        }
    }
} catch (\Throwable $e) {}
try {
    foreach (StudentService::all() as $student) {
        $studentName = trim(($student['name'] ?? '') ?: (($student['fullName'] ?? '') ?: (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))));
        if ($studentName) {
            $adm = !empty($student['admissionNo']) ? ' [' . $student['admissionNo'] . ']' : '';
            $displayName = $studentName . $adm . ' (Student)';
            foreach ([$student['id'] ?? null, $student['uid'] ?? null, $student['userId'] ?? null, $student['studentId'] ?? null, $student['admissionNo'] ?? null] as $key) {
                if ($key !== null && $key !== '') $userMap[(string) $key] = $displayName;
            }
        }
    }
} catch (\Throwable $e) {}

$getUserDisplayName = function (array $log) use (&$userMap): string {
    $actorName = (string) ($log['userName'] ?? $log['performedByName'] ?? $log['user_name'] ?? '');
    if ($actorName !== '' && $actorName !== 'default-admin' && !str_starts_with($actorName, 'Staff/User') && !str_starts_with($actorName, 'User #')) return $actorName;
    $rawId = '';
    foreach (['userId', 'user_id', 'user', 'performedBy', 'actorId', 'actor'] as $key) {
        if (!empty($log[$key])) { $rawId = (string) $log[$key]; break; }
    }
    if ($rawId === '' || $rawId === 'system') return 'System';
    if ($rawId === 'default-admin') return 'Administrator (Admin)';
    if (isset($userMap[$rawId])) return $userMap[$rawId];
    try {
        $db = FirebaseService::getInstance();
        $u = $db->getDocument('users', $rawId);
        if ($u) {
            $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
            if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? '';
            if ($name !== '') {
                $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                $userMap[$rawId] = $name . $roleLabel;
                return $userMap[$rawId];
            }
        }
        $s = $db->getDocument('students', $rawId);
        if ($s) {
            $sName = trim(($s['name'] ?? '') ?: (($s['fullName'] ?? '') ?: (($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''))));
            if ($sName !== '') {
                $adm = !empty($s['admissionNo']) ? ' [' . $s['admissionNo'] . ']' : '';
                $userMap[$rawId] = $sName . $adm . ' (Student)';
                return $userMap[$rawId];
            }
        }
    } catch (\Throwable $e) {}
    return $rawId;
};

$search = strtolower(sanitize($_GET['search'] ?? ''));
$categoryFilter = sanitize($_GET['category'] ?? '');
$fromDate = sanitize($_GET['from_date'] ?? '');
$toDate = sanitize($_GET['to_date'] ?? '');

$filteredLogs = array_values(array_filter($logs, function ($log) use ($search, $categoryFilter, $fromDate, $toDate, $getUserDisplayName) {
    $action = strtolower((string) ($log['event'] ?? $log['action'] ?? $log['type'] ?? ''));
    $details = strtolower((string) ($log['details'] ?? $log['description'] ?? $log['message'] ?? ''));
    $userName = strtolower($getUserDisplayName($log));
    if ($categoryFilter !== '' && !str_contains($action, $categoryFilter) && !str_contains($details, $categoryFilter)) return false;
    $rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? $log['time'] ?? '');
    $logDate = substr($rawTime, 0, 10);
    if ($fromDate !== '' && $logDate < $fromDate) return false;
    if ($toDate !== '' && $logDate > $toDate) return false;
    if ($search !== '') return str_contains($userName, $search) || str_contains($action, $search) || str_contains($details, $search);
    return true;
}));

$totalCount = count($logs);
$todayDate = date('Y-m-d');
$todayCount = count(array_filter($logs, fn($l) => str_starts_with((string)($l['timestamp'] ?? $l['createdAt'] ?? ''), $todayDate)));
$manualCount = count(array_filter($logs, fn($l) => !empty($l['isManual'])));

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Activity Logs', 'href' => url('views/house-master/activity-logs/index.php'), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-journal-text text-secondary me-2"></i>Activity Logs & Audit Trail</h4>
                <p class="text-muted mb-0">Operational history and supervisory records for <?= e($houseName) ?></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary btn-sm" href="<?= url('views/house-master/activity-logs/create/create.php') ?>">
                    <i class="bi bi-plus-circle me-1"></i>Log Activity
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/activity-logs/export/export.php') ?>">
                    <i class="bi bi-download me-1"></i>Export CSV
                </a>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Records</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalCount) ?></h3>
                            <span class="small text-muted">Scoped to <?= e($houseName) ?></span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-journal-text fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Today</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $todayCount) ?></h3>
                            <span class="small text-muted"><?= e(date('F d, Y')) ?></span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-calendar-check fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Manual Notes</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $manualCount) ?></h3>
                            <span class="small text-muted">Supervisory observations</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-pencil-square fs-4"></i></div>
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
                            <input name="search" class="form-control form-control-sm" placeholder="Staff, student, or details..." value="<?= e($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Category</label>
                        <select name="category" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="attendance" <?= $categoryFilter === 'attendance' ? 'selected' : '' ?>>Attendance</option>
                            <option value="incident" <?= $categoryFilter === 'incident' ? 'selected' : '' ?>>Incidents</option>
                            <option value="visitor" <?= $categoryFilter === 'visitor' ? 'selected' : '' ?>>Visitors</option>
                            <option value="exeat" <?= $categoryFilter === 'exeat' ? 'selected' : '' ?>>Exeats</option>
                            <option value="room" <?= $categoryFilter === 'room' ? 'selected' : '' ?>>Rooms & Beds</option>
                            <option value="supervision" <?= $categoryFilter === 'supervision' ? 'selected' : '' ?>>Supervisory</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">From</label>
                        <input type="date" name="from_date" class="form-control form-control-sm" value="<?= e($fromDate) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">To</label>
                        <input type="date" name="to_date" class="form-control form-control-sm" value="<?= e($toDate) ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/activity-logs/index.php') ?>"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Activity Logs Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Audit Records</h6>
                <small class="text-muted">Showing <?= e((string) count($filteredLogs)) ?> records</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 data-table w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>Actor / Staff</th>
                                <th>Action / Event</th>
                                <th>Details</th>
                                <th>IP Address</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($filteredLogs)): ?>
                                <?php foreach ($filteredLogs as $log): ?>
                                    <?php
                                        $rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? $log['time'] ?? '');
                                        $formattedTime = $rawTime !== '' ? (date('M d, Y H:i', strtotime($rawTime)) ?: $rawTime) : '—';
                                        $userName = $getUserDisplayName($log);
                                        $action = (string) ($log['event'] ?? $log['action'] ?? $log['type'] ?? 'activity');
                                        $details = (string) ($log['details'] ?? $log['description'] ?? $log['message'] ?? '—');
                                        $ip = (string) ($log['ip'] ?? $log['ipAddress'] ?? '—');
                                        $logId = (string) ($log['id'] ?? '');
                                        $actionLower = strtolower($action);
                                        $badgeColor = match(true) {
                                            str_contains($actionLower, 'delete') || str_contains($actionLower, 'danger') => 'danger',
                                            str_contains($actionLower, 'warning') || str_contains($actionLower, 'incident') => 'warning',
                                            str_contains($actionLower, 'create') || str_contains($actionLower, 'success') => 'success',
                                            default => 'primary'
                                        };
                                    ?>
                                    <tr>
                                        <td class="text-nowrap small text-muted"><i class="bi bi-clock me-1"></i><?= e($formattedTime) ?></td>
                                        <td class="fw-medium"><?= e($userName) ?></td>
                                        <td>
                                            <span class="badge bg-<?= e($badgeColor) ?>-subtle text-<?= e($badgeColor) ?> border">
                                                <?= e(ucwords(str_replace(['_', '-'], ' ', $action))) ?>
                                            </span>
                                            <?php if (!empty($log['isManual'])): ?>
                                                <span class="badge bg-info ms-1">Manual</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small"><?= e(mb_strimwidth($details, 0, 75, '...')) ?></td>
                                        <td class="small text-muted font-monospace"><?= e($ip) ?></td>
                                        <td class="text-end">
                                            <?php if ($logId !== ''): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/activity-logs/view/view.php?id=' . urlencode($logId)) ?>">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No activity logs recorded for <?= e($houseName) ?>.</td>
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
