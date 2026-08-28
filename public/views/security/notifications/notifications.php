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
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\NotificationService;

$pageTitle = 'Security Notifications';
$currentUserId = current_user()['uid'] ?? current_user()['id'] ?? null;
$notifications = $currentUserId ? (new NotificationService())->forUser($currentUserId) : [];
$unreadCount = count(array_filter($notifications, static fn(array $notification): bool => empty($notification['read'])));
$readCount = count(array_filter($notifications, static fn(array $notification): bool => !empty($notification['read'])));

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents/incidents.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php'), 'active' => true],
    ['icon' => 'bi-person-plus', 'label' => 'Register Visitor', 'href' => url('views/security/register-visitor/register-visitor.php')],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-bell-fill text-info me-2"></i>Security Desk Notifications</h4>
                <p class="text-muted mb-0">Stay informed about incidents, visitor approvals, and security alerts</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary btn-sm" href="<?= url('views/security/dashboard/dashboard.php') ?>">
                    <i class="bi bi-speedometer2 me-1"></i>Dashboard
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Notifications</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) count($notifications)) ?></h3>
                            <span class="small text-muted">All incoming alerts</span>
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
                            <span class="small text-muted">Awaiting review</span>
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

        <!-- Notifications Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-inbox me-2"></i>Recent Notifications</h6>
                <small class="text-muted">Showing <strong><?= count($notifications) ?></strong> message(s)</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $note): ?>
                                <?php
                                $isRead = !empty($note['read']);
                                $type = strtolower((string) ($note['type'] ?? 'info'));
                                ?>
                                <tr class="<?= $isRead ? '' : 'table-light' ?>">
                                    <td>
                                        <strong class="d-block text-dark"><?= e($note['title'] ?? 'Notification') ?></strong>
                                        <?php if (!empty($note['message'])): ?>
                                            <small class="text-muted"><?= e($note['message']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= match($type) {
                                            'danger', 'emergency' => 'danger',
                                            'warning', 'incident' => 'warning text-dark',
                                            'success' => 'success',
                                            default => 'info'
                                        } ?>">
                                            <?= e(ucfirst($type)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $isRead ? 'bg-secondary' : 'bg-warning text-dark' ?>">
                                            <?= $isRead ? 'Read' : 'Unread' ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted text-nowrap"><?= e($note['createdAt'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5"><i class="bi bi-bell-slash fs-3 d-block mb-2"></i>No notifications available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
