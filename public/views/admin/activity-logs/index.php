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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\UserService;
use App\Services\StudentService;

$pageTitle = 'System Activity Logs & Audit Trail';
$allLogs = FirebaseService::getInstance()->getCollection(COL_ACTIVITY_LOGS, [], 500);

// Sort newest logs first
usort($allLogs, function ($a, $b) {
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
                if ($key !== null && $key !== '') {
                    $userMap[(string) $key] = $displayName;
                }
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
                if ($key !== null && $key !== '') {
                    $userMap[(string) $key] = $displayName;
                }
            }
        }
    }
} catch (\Throwable $e) {}

$resolveLogUser = function (array $log) use (&$userMap): string {
    $actorName = (string) ($log['userName'] ?? $log['performedByName'] ?? $log['user_name'] ?? '');
    if ($actorName !== '' && $actorName !== 'default-admin' && !str_starts_with($actorName, 'Staff/User') && !str_starts_with($actorName, 'User #')) {
        return $actorName;
    }

    $rawId = '';
    foreach (['userId', 'user_id', 'user', 'performedBy', 'actorId', 'actor', 'adminId'] as $key) {
        if (!empty($log[$key])) {
            $rawId = (string) $log[$key];
            break;
        }
    }

    if ($rawId === '' || $rawId === 'system') return 'System';
    if ($rawId === 'default-admin') return 'Administrator (Admin)';
    if (isset($userMap[$rawId])) return $userMap[$rawId];

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

// Filter parameters
$search = strtolower(sanitize($_GET['search'] ?? ''));
$categoryFilter = sanitize($_GET['category'] ?? '');
$fromDate = sanitize($_GET['from_date'] ?? '');
$toDate = sanitize($_GET['to_date'] ?? '');

$logs = array_values(array_filter($allLogs, function ($log) use ($search, $categoryFilter, $fromDate, $toDate, $resolveLogUser) {
    $event = strtolower((string) ($log['event'] ?? $log['action'] ?? $log['type'] ?? ''));
    $desc = strtolower((string) ($log['details'] ?? $log['description'] ?? $log['message'] ?? ''));
    $user = strtolower($resolveLogUser($log));
    $ip = strtolower((string) ($log['ip'] ?? $log['ipAddress'] ?? ''));

    if ($categoryFilter !== '') {
        if (!str_contains($event, $categoryFilter) && !str_contains($desc, $categoryFilter)) {
            return false;
        }
    }

    $rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? $log['time'] ?? '');
    $logDate = substr($rawTime, 0, 10);
    if ($fromDate !== '' && $logDate < $fromDate) return false;
    if ($toDate !== '' && $logDate > $toDate) return false;

    if ($search !== '') {
        return str_contains($event, $search) || str_contains($desc, $search) || str_contains($user, $search) || str_contains($ip, $search);
    }
    return true;
}));

// Statistics
$totalCount = count($allLogs);
$todayDate = date('Y-m-d');
$todayCount = count(array_filter($allLogs, fn($l) => str_starts_with((string)($l['timestamp'] ?? $l['createdAt'] ?? ''), $todayDate)));
$manualCount = count(array_filter($allLogs, fn($l) => !empty($l['isManual'])));

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-list-check', 'label' => 'Activity Logs', 'href' => url('views/admin/activity-logs/index.php'), 'active' => true],
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
                <h5 class="mb-1">System Activity Logs & Security Audit Trail</h5>
                <p class="text-muted mb-0">System-wide transactional logs, user actions, and administrative records.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary btn-sm" href="<?= url('views/admin/activity-logs/create/create.php') ?>">
                    <i class="bi bi-plus-circle me-1"></i> Log System Note
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/activity-logs/export/export.php') ?>">
                    <i class="bi bi-download me-1"></i> Export Full CSV
                </a>
            </div>
        </div>

        <!-- KPI Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-primary">
                    <small class="text-muted">Total System Audit Logs</small>
                    <strong class="fs-2 text-primary my-1"><?= e((string) $totalCount) ?></strong>
                    <span class="small text-muted">All system events</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-success">
                    <small class="text-muted">Events Recorded Today</small>
                    <strong class="fs-2 text-success my-1"><?= e((string) $todayCount) ?></strong>
                    <span class="small text-muted"><?= e(date('F d, Y')) ?></span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-info">
                    <small class="text-muted">Admin & Directive Notes</small>
                    <strong class="fs-2 text-info my-1"><?= e((string) $manualCount) ?></strong>
                    <span class="small text-muted">Manual administrative entries</span>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="card stat-card p-3 mb-4">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Search Keywords</label>
                    <input name="search" class="form-control form-control-sm" placeholder="Search by actor, event, or keyword..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Event Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <option value="auth" <?= $categoryFilter === 'auth' ? 'selected' : '' ?>>Authentication & Login</option>
                        <option value="user" <?= $categoryFilter === 'user' ? 'selected' : '' ?>>User Management</option>
                        <option value="student" <?= $categoryFilter === 'student' ? 'selected' : '' ?>>Student Records</option>
                        <option value="attendance" <?= $categoryFilter === 'attendance' ? 'selected' : '' ?>>Attendance</option>
                        <option value="room" <?= $categoryFilter === 'room' ? 'selected' : '' ?>>Rooms & Beds</option>
                        <option value="incident" <?= $categoryFilter === 'incident' ? 'selected' : '' ?>>Incidents</option>
                        <option value="system" <?= $categoryFilter === 'system' ? 'selected' : '' ?>>System & Settings</option>
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
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/activity-logs/index.php') ?>" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>

        <!-- Audit Table -->
        <div class="card stat-card p-3">
            <div class="table-responsive">
                <table class="table table-hover data-table w-100 align-middle">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Actor / Staff</th>
                            <th>Event / Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                    $rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? $log['time'] ?? '');
                                    $formattedTime = $rawTime !== '' ? (date('M d, Y H:i:s', strtotime($rawTime)) ?: $rawTime) : '—';
                                    $userName = $resolveLogUser($log);
                                    $event = (string) ($log['event'] ?? $log['action'] ?? $log['type'] ?? 'system_activity');
                                    $details = (string) ($log['details'] ?? $log['description'] ?? $log['message'] ?? '—');
                                    $ip = (string) ($log['ip'] ?? $log['ipAddress'] ?? '—');
                                    $logId = (string) ($log['id'] ?? '');

                                    $eventLower = strtolower($event);
                                    $badgeColor = match(true) {
                                        str_contains($eventLower, 'delete') || str_contains($eventLower, 'danger') || str_contains($eventLower, 'error') => 'danger',
                                        str_contains($eventLower, 'update') || str_contains($eventLower, 'warning') => 'warning',
                                        str_contains($eventLower, 'create') || str_contains($eventLower, 'login') || str_contains($eventLower, 'success') => 'success',
                                        default => 'primary'
                                    };
                                ?>
                                <tr>
                                    <td class="text-nowrap small text-muted"><?= e($formattedTime) ?></td>
                                    <td><strong><?= e($userName) ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= e($badgeColor) ?>-subtle text-<?= e($badgeColor) ?> border">
                                            <?= e(ucwords(str_replace(['_', '-'], ' ', $event))) ?>
                                        </span>
                                    </td>
                                    <td><?= e(mb_strimwidth($details, 0, 80, '...')) ?></td>
                                    <td class="small text-muted font-monospace"><?= e($ip) ?></td>
                                    <td class="text-end">
                                        <?php if ($logId !== ''): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/admin/activity-logs/view/view.php?id=' . urlencode($logId)) ?>">
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
                                <td colspan="6" class="text-center text-muted py-4">No activity logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>