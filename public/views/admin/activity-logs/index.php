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
$firebase = FirebaseService::getInstance();
$search = strtolower(sanitize($_GET['search'] ?? ''));
$categoryFilter = sanitize($_GET['category'] ?? '');
$fromDate = sanitize($_GET['from_date'] ?? '');
$toDate = sanitize($_GET['to_date'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$hasFilters = $search !== '' || $categoryFilter !== '' || $fromDate !== '' || $toDate !== '';
$totalCount = $hasFilters ? null : $firebase->count(COL_ACTIVITY_LOGS);
if (!$hasFilters) {
    $page = min($page, max(1, (int) ceil($totalCount / $limit)));
}
$allLogs = $hasFilters
    ? $firebase->getCollection(COL_ACTIVITY_LOGS, [], 500)
    : $firebase->getCollection(COL_ACTIVITY_LOGS, [], $limit, ($page - 1) * $limit, false, [['createdAt', 'DESC']]);

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

$actorMapsLoaded = false;
$loadActorMaps = function () use (&$userMap, &$actorMapsLoaded): void {
    if ($actorMapsLoaded) {
        return;
    }

    $actorMapsLoaded = true;
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
    } catch (\Throwable $e) {
        error_log('Activity log user map unavailable: ' . $e->getMessage());
    }

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
    } catch (\Throwable $e) {
        error_log('Activity log student map unavailable: ' . $e->getMessage());
    }
};

$resolveLogUser = function (array $log) use (&$userMap, $loadActorMaps): string {
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

    $loadActorMaps();
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

$logs = array_values(array_filter($allLogs, function ($log) use ($search, $categoryFilter, $fromDate, $toDate, $resolveLogUser) {
    $event = strtolower((string) ($log['event'] ?? $log['action'] ?? $log['type'] ?? ''));
    $desc = strtolower((string) ($log['details'] ?? $log['description'] ?? $log['message'] ?? ''));
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
        if (str_contains($event, $search) || str_contains($desc, $search) || str_contains($ip, $search)) {
            return true;
        }
        $user = strtolower($resolveLogUser($log));
        return str_contains($user, $search);
    }
    return true;
}));
$totalLogs = $hasFilters ? count($logs) : $totalCount;
$totalPages = max(1, (int) ceil($totalLogs / $limit));
$page = min($page, $totalPages);
if ($hasFilters) {
    $logs = array_slice($logs, ($page - 1) * $limit, $limit);
}

// Statistics
$totalCount = $totalCount ?? $firebase->count(COL_ACTIVITY_LOGS);
$todayDate = date('Y-m-d');
$tomorrowDate = date('Y-m-d', strtotime('+1 day'));
$todayCount = $firebase->count(COL_ACTIVITY_LOGS, [
    ['timestamp', '>=', $todayDate],
    ['timestamp', '<', $tomorrowDate],
]);
$manualCount = $firebase->count(COL_ACTIVITY_LOGS, [['isManual', '=', true]]);

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
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adminCreateActivityLogModal">
                    <i class="bi bi-plus-circle me-1"></i> Log System Note
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#adminActivityLogExportModal">
                    <i class="bi bi-download me-1"></i> Export Full CSV
                </button>
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
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#logViewModal-<?= e($logId) ?>">
                                                <i class="bi bi-eye"></i> View
                                            </button>
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
            <?php
            $paginationQuery = [];
            foreach (['search' => $search, 'category' => $categoryFilter, 'from_date' => $fromDate, 'to_date' => $toDate] as $key => $value) {
                if ($value !== '') $paginationQuery[] = $key . '=' . urlencode($value);
            }
            $paginationBaseUrl = url('views/admin/activity-logs/index.php' . (!empty($paginationQuery) ? '?' . implode('&', $paginationQuery) : ''));
            require APP_ROOT . '/app/views/components/pagination/pagination.php';
            ?>
        </div>
    </div>
</div>

<?php foreach ($logs as $log): ?>
    <?php
    $logId = (string) ($log['id'] ?? '');
    $logEvent = (string) ($log['event'] ?? $log['action'] ?? $log['type'] ?? 'system_activity');
    $logDetails = (string) ($log['details'] ?? $log['description'] ?? $log['message'] ?? '—');
    $logUser = $resolveLogUser($log);
    $logIP = (string) ($log['ip'] ?? $log['ipAddress'] ?? '—');
    $logTimestamp = (string) ($log['timestamp'] ?? $log['createdAt'] ?? $log['time'] ?? '');
    ?>
    <div class="modal fade" id="logViewModal-<?= e($logId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Activity Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Timestamp</dt><dd class="col-sm-8"><?= e($logTimestamp !== '' ? date('M d, Y H:i:s', strtotime($logTimestamp)) : '—') ?></dd>
                        <dt class="col-sm-4">Actor</dt><dd class="col-sm-8"><?= e($logUser) ?></dd>
                        <dt class="col-sm-4">Event</dt><dd class="col-sm-8"><?= e(ucwords(str_replace(['_', '-'], ' ', $logEvent))) ?></dd>
                        <dt class="col-sm-4">IP Address</dt><dd class="col-sm-8"><?= e($logIP) ?></dd>
                        <dt class="col-sm-4">Description</dt><dd class="col-sm-8"><?= e($logDetails) ?></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<div class="modal fade" id="adminCreateActivityLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
        <form method="POST" action="<?= url('views/admin/activity-logs/create/create.php') ?>">
            <div class="modal-header"><h5 class="modal-title">Record Administrative Audit Log</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Directive / Action Type</label><input class="form-control" name="event" placeholder="e.g. System Configuration Audit" required></div>
                    <div class="col-md-4"><label class="form-label">Priority</label><select class="form-select" name="priority"><option value="normal">Normal</option><option value="important">Important</option><option value="urgent">Urgent / Critical</option></select></div>
                    <div class="col-md-6"><label class="form-label">Target Role</label><select class="form-select" name="targetRole"><option value="all">All roles / system-wide</option><?php foreach (ALL_ROLES as $role): ?><option value="<?= e((string) $role) ?>"><?= e(ucwords(str_replace(['_', '-'], ' ', (string) $role))) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label">Details &amp; Notes</label><textarea class="form-control" name="details" rows="5" required></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save to Audit Log</button></div>
        </form>
    </div></div>
</div>
<div class="modal fade" id="adminActivityLogExportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Export System Activity Logs</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><p>Download the complete system audit log as a CSV without leaving this page.</p><div class="alert alert-danger d-none mb-0" data-admin-log-export-error role="alert"></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" data-admin-log-export-url="<?= e(url('views/admin/activity-logs/export/export.php')) ?>"><i class="bi bi-download me-1"></i>Download CSV</button></div>
    </div></div>
</div>
<script>
document.querySelectorAll('[data-admin-log-export-url]').forEach(function (button) {
    button.addEventListener('click', async function () {
        const errorBox = document.querySelector('[data-admin-log-export-error]');
        errorBox.classList.add('d-none');
        SDS.showButtonLoading(button);
        try {
            const response = await fetch(button.dataset.adminLogExportUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const contentType = response.headers.get('content-type') || '';
            if (!response.ok || !contentType.includes('text/csv')) {
                throw new Error('The activity log CSV could not be generated. Please try again.');
            }
            const blob = await response.blob();
            const disposition = response.headers.get('content-disposition') || '';
            const match = disposition.match(/filename="?([^";]+)"?/i);
            const download = document.createElement('a');
            download.href = URL.createObjectURL(blob);
            download.download = match ? match[1] : 'system-activity-logs.csv';
            document.body.appendChild(download);
            download.click();
            download.remove();
            setTimeout(function () { URL.revokeObjectURL(download.href); }, 1000);
        } catch (error) {
            errorBox.textContent = error.message || 'The activity log CSV could not be generated.';
            errorBox.classList.remove('d-none');
        } finally {
            SDS.hideButtonLoading(button);
        }
    });
});
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>