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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\NotificationService;

$pageTitle = 'Security Notifications';
$currentUserId = current_user()['uid'] ?? current_user()['id'] ?? null;
$notifications = $currentUserId ? (new NotificationService())->forUser($currentUserId) : [];
$unreadCount = count(array_filter($notifications, static fn(array $notification): bool => empty($notification['read'])));
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php'), 'active' => true],
    ['icon' => 'bi-person-plus', 'label' => 'Register Visitor', 'href' => url('views/security/register-visitor/register-visitor.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper security-portal">
        <section class="security-hero mb-4"><div class="security-hero-icon"><i class="bi bi-bell"></i></div><div><span class="security-kicker">Security desk</span><h1>Notifications</h1><p>Stay informed about incidents, visitor approvals, and operational updates.</p><div class="security-badges"><span class="badge bg-danger"><i class="bi bi-envelope me-1"></i><?= e((string) $unreadCount) ?> unread</span><span class="badge bg-success"><i class="bi bi-check2-all me-1"></i><?= e((string) count($notifications)) ?> total</span></div></div><a class="btn btn-light" href="<?= url('views/security/dashboard/dashboard.php') ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></section>
        <div class="security-card"><div class="security-card-header"><div><span class="security-kicker">Inbox</span><h2>Recent updates</h2><p>Your security account notifications.</p></div></div><table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Read</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $note): ?>
                            <tr class="<?= empty($note['read']) ? 'notification-unread' : '' ?>">
                                <td><?= e($note['title'] ?? '') ?></td>
                                <td><?= e($note['type'] ?? '') ?></td>
                                <td><span class="badge <?= !empty($note['read']) ? 'bg-secondary' : 'bg-danger' ?>"><?= !empty($note['read']) ? 'Read' : 'Unread' ?></span></td>
                                <td><?= e($note['createdAt'] ?? '') ?></td>
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
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
