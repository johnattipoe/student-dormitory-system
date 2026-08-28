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

use App\Services\FirebaseService;
use App\Services\UserService;
use App\Services\StudentService;
use App\Services\RoomService;
use App\Services\HouseService;

$pageTitle = 'Activity Logs & Audit Trail';
$houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

// Collect all student and room IDs for strict house scoping
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

// Fetch raw logs
$allLogs = FirebaseService::getInstance()->getCollection(COL_ACTIVITY_LOGS, [], 500);

// Filter strictly to the assigned house or general dorm activities
$logs = array_values(array_filter($allLogs, function ($log) use ($houseId, $houseStudentIds, $currUserIds, $houseRoomIds) {
    // Direct houseId match
    if (!empty($log['houseId']) && (string) $log['houseId'] === $houseId) {
        return true;
    }
    if (!empty($log['house_id']) && (string) $log['house_id'] === $houseId) {
        return true;
    }

    // Action performed by current Senior Houseparent
    foreach (['userId', 'user_id', 'user', 'performedBy', 'actorId', 'actor'] as $k) {
        $actorId = (string) ($log[$k] ?? '');
        if ($actorId !== '' && in_array($actorId, $currUserIds, true)) {
            return true;
        }
    }

    // Subject is a student in this house
    foreach (['studentId', 'student_id', 'targetId', 'target_id', 'userId', 'user_id'] as $k) {
        $targetId = (string) ($log[$k] ?? '');
        if ($targetId !== '' && isset($houseStudentIds[$targetId])) {
            return true;
        }
    }

    // Subject is a room in this house
    foreach (['roomId', 'room_id', 'bedId', 'bed_id'] as $k) {
        $roomId = (string) ($log[$k] ?? '');
        if ($roomId !== '' && isset($houseRoomIds[$roomId])) {
            return true;
        }
    }

    return false;
}));

// Sort newest logs first
usort($logs, function ($a, $b) {
    $tA = strtotime((string) ($a['timestamp'] ?? $a['createdAt'] ?? ''));
    $tB = strtotime((string) ($b['timestamp'] ?? $b['createdAt'] ?? ''));
    return $tB <=> $tA;
});

// Build user & student resolution maps
$userMap = [
    'default-admin' => 'Administrator (Admin)',
    'system' => 'System',
];

try {
    foreach ((new UserService())->all() as $user) {
        $name = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''))));
        if ($name === '') {
            $name = $user['displayName'] ?? $user['username'] ?? $user['email'] ?? null;
        }
        if ($name) {
            $roleLabel = !empty($user['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $user['role'])) . ')' : '';
            $displayName = $name . $roleLabel;
            foreach ([$user['id'] ?? null, $user['uid'] ?? null, $user['userId'] ?? null, $user['firebaseUid'] ?? null, $user['email'] ?? null] as $key) {
                if ($key !== null && (string)$key !== '') {
                    $userMap[(string) $key] = $displayName;
                }
            }
        }
    }
} catch (\Throwable $e) {}

try {
    foreach (StudentService::all() as $student) {
        $sName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
        $adm = !empty($student['admissionNo']) ? ' [' . $student['admissionNo'] . ']' : '';
        $displayName = ($sName ?: 'Student') . $adm . ' (Student)';
        foreach ([$student['id'] ?? null, $student['studentId'] ?? null, $student['admissionNo'] ?? null, $student['userId'] ?? null, $student['uid'] ?? null] as $key) {
            if ($key !== null && (string)$key !== '') {
                $userMap[(string) $key] = $displayName;
            }
        }
    }
} catch (\Throwable $e) {}

$getUserDisplayName = function (array $log) use (&$userMap): string {
    $userName = trim((string) ($log['userName'] ?? $log['user_name'] ?? $log['performedByName'] ?? ''));
    if ($userName !== '' && !str_starts_with($userName, 'Staff/User') && !str_starts_with($userName, 'User #')) {
        return $userName;
    }
    foreach (['userId', 'user_id', 'user', 'performedBy', 'actorId'] as $key) {
        $rawId = trim((string) ($log[$key] ?? ''));
        if ($rawId !== '') {
            if (isset($userMap[$rawId])) {
                return $userMap[$rawId];
            }
            try {
                $db = FirebaseService::getInstance();
                $u = $db->getDocument('users', $rawId);
                if ($u) {
                    $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
                    if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? $u['email'] ?? '';
                    if ($name !== '') {
                        $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                        $userMap[$rawId] = $name . $roleLabel;
                        return $userMap[$rawId];
                    }
                }
                $s = $db->getDocument('students', $rawId);
                if ($s) {
                    $sName = trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''));
                    $adm = !empty($s['admissionNo']) ? ' [' . $s['admissionNo'] . ']' : '';
                    $userMap[$rawId] = ($sName ?: 'Student') . $adm . ' (Student)';
                    return $userMap[$rawId];
                }
            } catch (\Throwable $e) {}
            return $rawId;
        }
    }
    return 'System';
};

// Filter handling
$search = strtolower(sanitize($_GET['search'] ?? ''));
$categoryFilter = sanitize($_GET['category'] ?? '');
$fromDate = sanitize($_GET['from_date'] ?? '');
$toDate = sanitize($_GET['to_date'] ?? '');

$filteredLogs = array_values(array_filter($logs, function ($log) use ($search, $categoryFilter, $fromDate, $toDate, $getUserDisplayName) {
    $action = strtolower((string) ($log['event'] ?? $log['action'] ?? $log['type'] ?? ''));
    $details = strtolower((string) ($log['details'] ?? $log['description'] ?? $log['message'] ?? ''));
    $userName = strtolower($getUserDisplayName($log));

    if ($categoryFilter !== '') {
        if (!str_contains($action, $categoryFilter) && !str_contains($details, $categoryFilter)) {
            return false;
        }
    }

    $rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? $log['time'] ?? '');
    $logDate = substr($rawTime, 0, 10);
    if ($fromDate !== '' && $logDate < $fromDate) return false;
    if ($toDate !== '' && $logDate > $toDate) return false;

    if ($search !== '') {
        return str_contains($userName, $search) || str_contains($action, $search) || str_contains($details, $search);
    }
    return true;
}));

// Statistics
$totalLogsCount = count($logs);
$todayDate = date('Y-m-d');
$todayLogsCount = count(array_filter($logs, fn($l) => str_starts_with((string)($l['timestamp'] ?? $l['createdAt'] ?? ''), $todayDate)));
$manualLogsCount = count(array_filter($logs, fn($l) => !empty($l['isManual'])));

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Activity Logs', 'href' => url('views/senior-houseparent/activity-logs/index.php'), 'active' => true],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/senior-houseparent/reports/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">House Activity Logs & Audit Trail</h5>
                <p class="text-muted mb-0">Operational audit logs, supervisory notes, and security events for <?= e($houseName) ?>.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary btn-sm" href="<?= url('views/senior-houseparent/activity-logs/create/create.php') ?>">
                    <i class="bi bi-plus-circle me-1"></i> Log Activity / Observation
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/activity-logs/export/export.php') ?>">
                    <i class="bi bi-download me-1"></i> Export CSV
                </a>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-primary">
                    <small class="text-muted">Total House Audit Records</small>
                    <strong class="fs-2 text-primary my-1"><?= e((string) $totalLogsCount) ?></strong>
                    <span class="small text-muted">Scoped to <?= e($houseName) ?></span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-success">
                    <small class="text-muted">Activities Logged Today</small>
                    <strong class="fs-2 text-success my-1"><?= e((string) $todayLogsCount) ?></strong>
                    <span class="small text-muted"><?= e(date('F d, Y')) ?></span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-info">
                    <small class="text-muted">Supervisory & Manual Logs</small>
                    <strong class="fs-2 text-info my-1"><?= e((string) $manualLogsCount) ?></strong>
                    <span class="small text-muted">Staff observations recorded</span>
                </div>
            </div>
        </div>

        <!-- Filters Form -->
        <div class="card stat-card p-3 mb-4">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Search Keywords</label>
                    <input name="search" class="form-control form-control-sm" placeholder="Search staff, student, or details..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Activity Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <option value="attendance" <?= $categoryFilter === 'attendance' ? 'selected' : '' ?>>Attendance & Roll Call</option>
                        <option value="incident" <?= $categoryFilter === 'incident' ? 'selected' : '' ?>>Incidents & Discipline</option>
                        <option value="visitor" <?= $categoryFilter === 'visitor' ? 'selected' : '' ?>>Visitors & Gate Access</option>
                        <option value="exeat" <?= $categoryFilter === 'exeat' ? 'selected' : '' ?>>Exeats & Leave</option>
                        <option value="room" <?= $categoryFilter === 'room' ? 'selected' : '' ?>>Rooms & Beds</option>
                        <option value="supervision" <?= $categoryFilter === 'supervision' ? 'selected' : '' ?>>Supervisory Inspection</option>
                        <option value="security" <?= $categoryFilter === 'security' ? 'selected' : '' ?>>Security & Emergency</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= e($fromDate) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= e($toDate) ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end gap-1">
                    <button class="btn btn-primary btn-sm flex-fill" title="Filter"><i class="bi bi-funnel"></i></button>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/activity-logs/index.php') ?>" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>

        <!-- Activity Logs Table -->
        <div class="card stat-card p-3">
            <div class="table-responsive">
                <table class="table table-hover data-table w-100 align-middle">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Actor / Staff</th>
                            <th>Action / Event</th>
                            <th>Details & Description</th>
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
                                        str_contains($actionLower, 'delete') || str_contains($actionLower, 'emergency') || str_contains($actionLower, 'danger') => 'danger',
                                        str_contains($actionLower, 'warning') || str_contains($actionLower, 'incident') => 'warning',
                                        str_contains($actionLower, 'create') || str_contains($actionLower, 'approved') || str_contains($actionLower, 'success') => 'success',
                                        default => 'primary'
                                    };
                                ?>
                                <tr>
                                    <td class="text-nowrap small text-muted"><i class="bi bi-clock me-1"></i><?= e($formattedTime) ?></td>
                                    <td><strong><?= e($userName) ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= e($badgeColor) ?>-subtle text-<?= e($badgeColor) ?> border">
                                            <?= e(ucwords(str_replace(['_', '-'], ' ', $action))) ?>
                                        </span>
                                        <?php if (!empty($log['isManual'])): ?>
                                            <span class="badge bg-info ms-1">Manual Note</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e(mb_strimwidth($details, 0, 75, '...')) ?></td>
                                    <td class="small text-muted font-monospace"><?= e($ip) ?></td>
                                    <td class="text-end">
                                        <?php if ($logId !== ''): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/senior-houseparent/activity-logs/view/view.php?id=' . urlencode($logId)) ?>" title="View Complete Log">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No activity logs found matching your criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
