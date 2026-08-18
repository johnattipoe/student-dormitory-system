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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\NotificationService;

// Handle bulk actions
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $notificationIds = (array) ($_POST['notificationIds'] ?? []);
    
    if ($action === 'bulk_mark_read' && !empty($notificationIds)) {
        $notificationService = new NotificationService();
        try {
            foreach ($notificationIds as $nId) {
                $notificationService->markAsReadById($nId);
            }
            flash('success', 'Marked ' . count($notificationIds) . ' notification(s) as read');
            redirect(url('views/student/notifications/index.php'));
        } catch (Exception $e) {
            $errors[] = 'Failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Student Notifications';
$notifications = (new NotificationService())->all();
$unreadNotifications = array_filter($notifications, fn($n) => empty($n['read']));
$readNotifications = array_filter($notifications, fn($n) => !empty($n['read']));
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index.php'), 'active' => true],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-0">Notifications</h5>
                <small class="text-muted">
                    Unread: <strong><?= count($unreadNotifications) ?></strong> | 
                    Read: <strong><?= count($readNotifications) ?></strong>
                </small>
            </div>
            <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#bulkActionModal" id="bulkActionBtn" disabled>
                <i class="bi bi-check2"></i> Mark as Read
            </button>
        </div>

        <div class="card stat-card p-3">
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div>
                    <strong id="selectedCount">0</strong> notification(s) selected
                    <a href="#" id="selectAllLink" class="ms-2 small">Select all</a> | 
                    <a href="#" id="clearAllLink" class="ms-2 small">Clear all</a>
                </div>
            </div>

            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th style="width: 40px;">
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
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input notification-checkbox" data-notification-id="<?= e((string) ($note['id'] ?? '')) ?>">
                                </td>
                                <td><?= e($note['title'] ?? '') ?></td>
                                <td><?= e($note['type'] ?? '') ?></td>
                                <td>
                                    <?php if (empty($note['read'])): ?>
                                        <span class="badge bg-warning">Unread</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Read</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($note['createdAt'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No notifications available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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

        // Select all checkbox
        document.getElementById('selectAllCheckbox')?.addEventListener('change', function() {
            document.querySelectorAll('.notification-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelection();
        });

        // Individual checkboxes
        document.querySelectorAll('.notification-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelection);
        });

        // Select all link
        document.getElementById('selectAllLink')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.notification-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelection();
        });

        // Clear all link
        document.getElementById('clearAllLink')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.notification-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
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
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
