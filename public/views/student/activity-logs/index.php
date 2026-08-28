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
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$pageTitle = 'My Activity & Dormitory History';
$user = current_user() ?? [];
$userId = (string) ($user['uid'] ?? $user['id'] ?? '');
$studentId = (string) ($user['studentId'] ?? $userId);

$firebase = FirebaseService::getInstance();
$allLogs = $firebase->getCollection(COL_ACTIVITY_LOGS, [], 500);

// Filter to this student's events
$myLogs = array_values(array_filter($allLogs, function ($log) use ($userId, $studentId) {
    foreach (['studentId', 'student_id', 'targetId', 'target_id', 'userId', 'user_id'] as $k) {
        $val = (string) ($log[$k] ?? '');
        if ($val !== '' && ($val === $userId || $val === $studentId)) {
            return true;
        }
    }
    return false;
}));

// Also fetch attendance and exeat records for this student to enrich personal activity history
$attendanceRecords = $firebase->getCollection('attendance', [], 300);
foreach ($attendanceRecords as $att) {
    if ((string)($att['studentId'] ?? '') === $studentId || (string)($att['studentId'] ?? '') === $userId) {
        $status = ucfirst((string)($att['status'] ?? 'present'));
        $myLogs[] = [
            'id' => $att['id'] ?? null,
            'event' => 'Daily Roll Call',
            'action' => 'Attendance: ' . $status,
            'type' => 'attendance',
            'details' => 'Roll call recorded as "' . $status . '" for date ' . ($att['date'] ?? 'today'),
            'performedByName' => $att['markedByName'] ?? 'House Master',
            'timestamp' => $att['createdAt'] ?? $att['date'] ?? date(DATE_ATOM),
        ];
    }
}

$exeatRecords = $firebase->getCollection('exeats', [], 100);
foreach ($exeatRecords as $ex) {
    if ((string)($ex['studentId'] ?? '') === $studentId || (string)($ex['studentId'] ?? '') === $userId) {
        $status = ucfirst((string)($ex['status'] ?? 'pending'));
        $myLogs[] = [
            'id' => $ex['id'] ?? null,
            'event' => 'Exeat Request',
            'action' => 'Exeat: ' . $status,
            'type' => 'exeat',
            'details' => 'Exeat application for "' . ($ex['reason'] ?? 'Leave') . '" is currently ' . $status,
            'performedByName' => $ex['approvedByName'] ?? 'House Master / Senior Staff',
            'timestamp' => $ex['updatedAt'] ?? $ex['createdAt'] ?? date(DATE_ATOM),
        ];
    }
}

usort($myLogs, function ($a, $b) {
    $tA = strtotime((string) ($a['timestamp'] ?? $a['createdAt'] ?? ''));
    $tB = strtotime((string) ($b['timestamp'] ?? $b['createdAt'] ?? ''));
    return $tB <=> $tA;
});

$search = strtolower(sanitize($_GET['search'] ?? ''));
$categoryFilter = sanitize($_GET['category'] ?? '');

$logs = array_values(array_filter($myLogs, function ($log) use ($search, $categoryFilter) {
    $event = strtolower((string) ($log['event'] ?? $log['action'] ?? ''));
    $details = strtolower((string) ($log['details'] ?? $log['description'] ?? ''));

    if ($categoryFilter !== '') {
        if (!str_contains($event, $categoryFilter) && !str_contains($details, $categoryFilter)) {
            return false;
        }
    }

    if ($search !== '') {
        return str_contains($event, $search) || str_contains($details, $search);
    }
    return true;
}));

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index/index.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Activity History', 'href' => url('views/student/activity-logs/index.php'), 'active' => true],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index/index.php')],
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
                <h5 class="mb-1">My Dormitory Activity History</h5>
                <p class="text-muted mb-0">Complete chronological record of your attendance, room status, exeats, and visits.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/activity-logs/export/export.php') ?>">
                <i class="bi bi-download me-1"></i> Export My History
            </a>
        </div>

        <div class="card stat-card p-3 mb-4">
            <form method="GET" class="row g-2">
                <div class="col-md-6">
                    <input name="search" class="form-control form-control-sm" placeholder="Search my activity history..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-4">
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Activities</option>
                        <option value="attendance" <?= $categoryFilter === 'attendance' ? 'selected' : '' ?>>Roll Call Attendance</option>
                        <option value="exeat" <?= $categoryFilter === 'exeat' ? 'selected' : '' ?>>Exeat & Leave</option>
                        <option value="visitor" <?= $categoryFilter === 'visitor' ? 'selected' : '' ?>>Visitors</option>
                        <option value="room" <?= $categoryFilter === 'room' ? 'selected' : '' ?>>Bed / Room Status</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button class="btn btn-primary btn-sm flex-fill">Filter</button>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/activity-logs/index.php') ?>">Reset</a>
                </div>
            </form>
        </div>

        <div class="card stat-card p-3">
            <div class="table-responsive">
                <table class="table table-hover data-table w-100 align-middle">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Activity Event</th>
                            <th>Details</th>
                            <th>Recorded By</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                    $rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? '');
                                    $formattedTime = $rawTime !== '' ? (date('M d, Y H:i', strtotime($rawTime)) ?: $rawTime) : '—';
                                    $event = (string) ($log['event'] ?? $log['action'] ?? 'Activity');
                                    $details = (string) ($log['details'] ?? $log['description'] ?? '—');
                                    $recorder = (string) ($log['performedByName'] ?? 'Staff');
                                    $logId = (string) ($log['id'] ?? '');
                                ?>
                                <tr>
                                    <td class="text-nowrap small text-muted"><i class="bi bi-clock me-1"></i><?= e($formattedTime) ?></td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border">
                                            <?= e(ucwords(str_replace(['_', '-'], ' ', $event))) ?>
                                        </span>
                                    </td>
                                    <td><?= e($details) ?></td>
                                    <td class="small text-muted"><?= e($recorder) ?></td>
                                    <td class="text-end">
                                        <?php if ($logId !== ''): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/student/activity-logs/view/view.php?id=' . urlencode($logId)) ?>">
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
                                <td colspan="5" class="text-center text-muted py-4">No activity history recorded.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

