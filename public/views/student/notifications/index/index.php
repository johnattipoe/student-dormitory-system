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

use App\Services\NotificationService;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $notificationIds = (array) ($_POST['notificationIds'] ?? []);
    if ($action === 'bulk_mark_read' && !empty($notificationIds)) {
        $notificationService = new NotificationService();
        $userId = current_user()['uid'] ?? current_user()['id'] ?? null;
        $allowedNotificationIds = array_map(fn($notification) => (string) ($notification['id'] ?? ''), $notificationService->forUser($userId));
        $notificationIds = array_values(array_intersect(array_map('strval', $notificationIds), $allowedNotificationIds));
        try {
            foreach ($notificationIds as $nId) {
                $notificationService->markAsRead((string) $nId, $userId);
            }
            flash('success', 'Marked ' . count($notificationIds) . ' notification(s) as read');
            redirect(url('views/student/notifications/index/index.php'));
        } catch (Exception $e) {
            $errors[] = 'Failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Student Notifications';
$userId = current_user()['uid'] ?? null;
$notificationService = new NotificationService();
$notifications = $userId ? $notificationService->forUser($userId) : [];
$notifications = array_values(array_filter($notifications, fn($note) => (string) ($note['userId'] ?? '') === (string) $userId));
$notificationSearch = strtolower(sanitize($_GET['search'] ?? ''));
$notificationRead = sanitize($_GET['read'] ?? '');
$notifications = array_values(array_filter($notifications, function ($notification) use ($notificationSearch, $notificationRead) {
    return ($notificationSearch === '' || str_contains(strtolower((string) ($notification['title'] ?? '')), $notificationSearch) || str_contains(strtolower((string) ($notification['message'] ?? '')), $notificationSearch))
        && ($notificationRead === '' || ($notificationRead === 'unread' ? empty($notification['read']) : !empty($notification['read'])));
}));
$unreadNotifications = array_values(array_filter($notifications, fn($n) => empty($n['read'])));
$readNotifications = array_values(array_filter($notifications, fn($n) => !empty($n['read'])));

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index/index.php'), 'active' => true],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index/index.php')],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-bell-fill text-info me-2"></i>Notifications</h4>
                <p class="text-muted mb-0">Unread: <strong><?= count($unreadNotifications) ?></strong> | Read: <strong><?= count($readNotifications) ?></strong></p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('views/student/settings/notification-preferences/notification-preferences.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-sliders me-1"></i>Preferences
                </a>
                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#bulkActionModal" id="bulkActionBtn" disabled>
                    <i class="bi bi-check2 me-1"></i>Mark as Read
                </button>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) count($notifications)) ?></h3>
                            <span class="small text-muted">All notifications</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-bell fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Unread</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) count($unreadNotifications)) ?></h3>
                            <span class="small text-muted">Pending review</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-envelope fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Read</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) count($readNotifications)) ?></h3>
                            <span class="small text-muted">Already reviewed</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-envelope-open fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Priority</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) count(array_filter($notifications, fn($n) => ($n['type'] ?? '') === 'urgent'))) ?></h3>
                            <span class="small text-muted">Urgent alerts</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-exclamation-triangle fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input name="search" class="form-control form-control-sm" placeholder="Search notifications..." value="<?= e($notificationSearch) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="read" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="unread" <?= $notificationRead === 'unread' ? 'selected' : '' ?>>Unread</option>
                            <option value="read" <?= $notificationRead === 'read' ? 'selected' : '' ?>>Read</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/notifications/index/index.php') ?>"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Notifications Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold d-inline"><i class="bi bi-bell me-2"></i>All Notifications</h6>
                    <span class="ms-2 small text-muted"><strong id="selectedCount">0</strong> selected</span>
                    <a href="#" id="selectAllLink" class="ms-2 small">Select all</a> |
                    <a href="#" id="clearAllLink" class="ms-1 small">Clear</a>
                </div>
                <small class="text-muted">Showing <?= count($notifications) ?> records</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;">
                                <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                            </th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $note): ?>
                                <tr class="<?= empty($note['read']) ? 'table-light' : '' ?>">
                                    <td>
                                        <input type="checkbox" class="form-check-input notification-checkbox" data-notification-id="<?= e((string) ($note['id'] ?? '')) ?>">
                                    </td>
                                    <td><a class="fw-semibold text-decoration-none" href="<?= url('views/student/notifications/view/view.php?id=' . urlencode((string) ($note['id'] ?? ''))) ?>"><?= e((string) ($note['title'] ?? '')) ?></a></td>
                                    <td>
                                        <?php $typeBadge = match($note['type'] ?? 'info') { 'danger' => 'danger', 'warning' => 'warning text-dark', 'success' => 'success', 'urgent' => 'danger', default => 'info' }; ?>
                                        <span class="badge bg-<?= $typeBadge ?>"><?= e(ucfirst($note['type'] ?? 'info')) ?></span>
                                    </td>
                                    <td>
                                        <?php if (empty($note['read'])): ?>
                                            <span class="badge bg-warning-subtle text-warning border"><i class="bi bi-circle-fill me-1" style="font-size:0.4rem;vertical-align:middle;"></i>Unread</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border"><i class="bi bi-check2 me-1"></i>Read</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= e((string) ($note['createdAt'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No notifications available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bulk Action Modal -->
    <div class="modal fade" id="bulkActionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mark as Read</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_mark_read">
                        <div id="selectedNotificationsList"></div>
                        <p class="text-muted mt-3">Mark <strong id="confirmCount">0</strong> notification(s) as read?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark as Read</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const selectedNotifications = new Set();
        document.getElementById('selectAllCheckbox')?.addEventListener('change', function() {
            document.querySelectorAll('.notification-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelection();
        });
        document.querySelectorAll('.notification-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelection);
        });
        document.getElementById('selectAllLink')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.notification-checkbox').forEach(checkbox => { checkbox.checked = true; });
            updateSelection();
        });
        document.getElementById('clearAllLink')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.notification-checkbox').forEach(checkbox => { checkbox.checked = false; });
            updateSelection();
        });
        function updateSelection() {
            selectedNotifications.clear();
            const form = document.querySelector('#bulkActionModal form');
            let html = '';
            document.querySelectorAll('.notification-checkbox:checked').forEach(checkbox => {
                selectedNotifications.add(checkbox.getAttribute('data-notification-id'));
                html += '<input type="hidden" name="notificationIds[]" value="' + checkbox.getAttribute('data-notification-id') + '">';
            });
            form.querySelectorAll('input[name="notificationIds[]"]').forEach(input => input.remove());
            form.insertAdjacentHTML('afterbegin', html);
            document.getElementById('selectedCount').textContent = selectedNotifications.size;
            document.getElementById('confirmCount').textContent = selectedNotifications.size;
            document.getElementById('bulkActionBtn').disabled = selectedNotifications.size === 0;
        }
        updateSelection();
    </script>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
