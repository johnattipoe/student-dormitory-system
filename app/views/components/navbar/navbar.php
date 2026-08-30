<?php
use App\Services\NotificationService;

$user = current_user();
$role = (string) (current_role() ?? '');
$profileRoutes = [
    ROLE_ADMIN => 'views/admin/profile.php',
    ROLE_HOUSE_MASTER => 'views/house-master/profile.php',
    ROLE_HOUSE_MISTRESS => 'views/house-master/profile.php',
    ROLE_SENIOR_HOUSEPARENT => 'views/senior-houseparent/profile.php',
    ROLE_SECURITY => 'views/security/profile.php',
    ROLE_NURSE => 'views/nurse/profile.php',
    ROLE_STUDENT => 'views/student/profile/index/index.php',
];
$profileRoute = $profileRoutes[$role] ?? 'views/dashboard/dashboard.php';
$roleLabel = $role === ROLE_SENIOR_HOUSEPARENT ? 'Senior Houseparent' : ucwords(str_replace('_', ' ', $role));

$notificationRoutes = [
    ROLE_ADMIN => 'views/admin/notifications/index/index.php',
    ROLE_HOUSE_MASTER => 'views/house-master/notifications/index/index.php',
    ROLE_HOUSE_MISTRESS => 'views/house-master/notifications/index/index.php',
    ROLE_SENIOR_HOUSEPARENT => 'views/senior-houseparent/notifications/index/index.php',
    ROLE_NURSE => 'views/nurse/notifications/notifications.php',
    ROLE_SECURITY => 'views/security/notifications/notifications.php',
    ROLE_STUDENT => 'views/student/notifications/index/index.php',
];
$notificationRoute = $notificationRoutes[$role] ?? 'views/dashboard/dashboard.php';
$currentUserId = (string) ($user['uid'] ?? $user['id'] ?? '');
$topbarNotifications = [];

try {
    $notificationService = new NotificationService();
    $topbarNotifications = $role === ROLE_ADMIN
        ? $notificationService->all()
        : $notificationService->forUser($currentUserId);
} catch (Throwable $e) {
    $topbarNotifications = [];
}

usort($topbarNotifications, static fn(array $first, array $second): int => strcmp(
    (string) ($second['createdAt'] ?? ''),
    (string) ($first['createdAt'] ?? '')
));
$topbarUnreadCount = count(array_filter($topbarNotifications, static fn(array $notification): bool => empty($notification['read'])));
$recentNotifications = array_slice($topbarNotifications, 0, 5);
?>
<nav class="topbar navbar navbar-expand navbar-light bg-white border-bottom px-3 w-100">
    <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <span class="fw-semibold ms-2"><?= e($pageTitle ?? '') ?></span>

    <div class="ms-auto d-flex align-items-center gap-3">
        <div class="dropdown">
            <button class="btn btn-sm btn-light position-relative" id="notifBell" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Open notifications">
                <i class="bi <?= $topbarUnreadCount > 0 ? 'bi-bell-fill' : 'bi-bell' ?>"></i>
                <?php if ($topbarUnreadCount > 0): ?>
                    <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" id="notifCount"><?= $topbarUnreadCount > 99 ? '99+' : $topbarUnreadCount ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end topbar-notification-menu p-0" aria-labelledby="notifBell">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <strong>Notifications</strong>
                    <span class="badge <?= $topbarUnreadCount > 0 ? 'bg-danger' : 'bg-secondary' ?>"><?= $topbarUnreadCount ?> unread</span>
                </div>
                <div class="topbar-notification-list">
                    <?php if ($recentNotifications): ?>
                        <?php foreach ($recentNotifications as $notification): ?>
                            <?php
                            $isUnread = empty($notification['read']);
                            $type = strtolower((string) ($notification['type'] ?? 'info'));
                            $typeIcon = match ($type) {
                                'danger', 'error', 'warning' => 'bi-exclamation-triangle',
                                'success' => 'bi-check-circle',
                                default => 'bi-info-circle',
                            };
                            ?>
                            <a class="topbar-notification-item <?= $isUnread ? 'is-unread' : '' ?>" href="<?= url($notificationRoute) ?>">
                                <i class="bi <?= $typeIcon ?> text-<?= in_array($type, ['danger', 'error'], true) ? 'danger' : ($type === 'success' ? 'success' : ($type === 'warning' ? 'warning' : 'primary')) ?>"></i>
                                <span>
                                    <strong><?= e((string) ($notification['title'] ?? 'Notification')) ?></strong>
                                    <small><?= e((string) ($notification['message'] ?? '')) ?></small>
                                    <em><?= e(substr((string) ($notification['createdAt'] ?? ''), 0, 16) ?: 'Recently') ?></em>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="topbar-notification-empty"><i class="bi bi-bell-slash"></i><span>No notifications yet.</span></div>
                    <?php endif; ?>
                </div>
                <a class="d-block text-center fw-semibold text-decoration-none border-top px-3 py-2" href="<?= url($notificationRoute) ?>">
                    View all notifications <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm btn-light dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle"></i>
                <span><?= e($user['name'] ?? 'User') ?></span>
                <span class="badge bg-secondary"><?= e($roleLabel) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= url($profileRoute) ?>">My Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
<?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
