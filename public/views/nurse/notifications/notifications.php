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
$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\NotificationService;

$pageTitle = 'Nurse Notifications';
$notificationService = new NotificationService();
$userId = current_user_id();
$csrfToken = $_SESSION['nurse_notifications_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['nurse_notifications_csrf'] = $csrfToken;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        flash('error', 'The form expired. Please refresh the page and try again.');
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $result = $action === 'mark_all'
            ? $notificationService->markAllAsRead($userId)
            : ($action === 'mark_read' ? $notificationService->markAsRead(sanitize($_POST['id'] ?? ''), $userId) : null);
        if ($result) {
            flash($result['success'] ? 'success' : 'error', $result['message']);
        }
    }
    redirect(url('views/nurse/notifications/notifications.php'));
}

$notifications = $notificationService->forUser($userId);
$filter = strtolower(sanitize($_GET['filter'] ?? 'all'));
$typeFilter = strtolower(sanitize($_GET['type'] ?? 'all'));
$notifications = array_values(array_filter($notifications, static function (array $notification) use ($filter, $typeFilter): bool {
    $isRead = !empty($notification['read']);
    $type = strtolower((string) ($notification['type'] ?? 'info'));
    return ($filter === 'all' || ($filter === 'unread' && !$isRead) || ($filter === 'read' && $isRead))
        && ($typeFilter === 'all' || $type === $typeFilter);
}));
usort($notifications, static fn(array $first, array $second): int => strcmp((string) ($second['createdAt'] ?? ''), (string) ($first['createdAt'] ?? '')));
$allUserNotifications = $notificationService->forUser($userId);
$unreadCount = count(array_filter($allUserNotifications, static fn(array $notification): bool => empty($notification['read'])));
$readCount = count(array_filter($allUserNotifications, static fn(array $notification): bool => !empty($notification['read'])));
$types = array_values(array_unique(array_filter(array_map(static fn(array $notification): string => strtolower((string) ($notification['type'] ?? 'info')), $allUserNotifications))));
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php'), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-bell-fill text-info me-2"></i>Nurse Notifications</h4>
                <p class="text-muted mb-0">Stay updated with clinical alerts, patient follow-ups, and campus medical notices</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="mark_all">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button class="btn btn-outline-primary btn-sm" type="submit" <?= $unreadCount === 0 ? 'disabled' : '' ?>>
                        <i class="bi bi-check2-all me-1"></i>Mark All as Read
                    </button>
                </form>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Notifications</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) count($allUserNotifications)) ?></h3>
                            <span class="small text-muted">All messages</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-bell fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Unread</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $unreadCount) ?></h3>
                            <span class="small text-muted">Pending review</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-envelope fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Read</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $readCount) ?></h3>
                            <span class="small text-muted">Acknowledged</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-envelope-open fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Read Status</label>
                        <select name="filter" class="form-select form-select-sm">
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All statuses</option>
                            <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>Unread only</option>
                            <option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>Read only</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Notification Type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="all">All notification types</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?= e($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a href="<?= url('views/nurse/notifications/notifications.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Notifications Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-bell me-2"></i>Notifications Inbox</h6>
                <small class="text-muted">Showing <strong><?= count($notifications) ?></strong> message(s)</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Type</th>
                            <th>Created</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $note): ?>
                                <?php
                                $noteType = strtolower((string) ($note['type'] ?? 'info'));
                                $isRead = !empty($note['read']);
                                ?>
                                <tr class="<?= $isRead ? '' : 'table-light' ?>">
                                    <td>
                                        <strong class="d-block text-dark"><?= e($note['title'] ?? 'Notification') ?></strong>
                                    </td>
                                    <td class="small"><?= e($note['message'] ?? 'No message provided.') ?></td>
                                    <td>
                                        <span class="badge bg-<?= match($noteType) {
                                            'danger', 'emergency' => 'danger',
                                            'warning', 'critical' => 'warning text-dark',
                                            'success' => 'success',
                                            default => 'info'
                                        } ?>">
                                            <?= e(ucfirst($noteType)) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted text-nowrap"><?= e($note['createdAt'] ?? '—') ?></td>
                                    <td class="text-end text-nowrap">
                                        <?php if (!$isRead && !empty($note['id'])): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="id" value="<?= e($note['id']) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <button class="btn btn-sm btn-outline-success" type="submit">
                                                    <i class="bi bi-check2 me-1"></i>Mark Read
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small"><i class="bi bi-check2-all me-1"></i>Read</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5"><i class="bi bi-bell-slash fs-3 d-block mb-2"></i>No notifications match this view.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
