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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\NotificationService;

$uid = current_user()['uid'] ?? current_user()['id'] ?? null;
$id = sanitize($_GET['id'] ?? '');
$service = new NotificationService();
$notification = $service->findForUser($id, $uid);

if (!$notification) { 
    flash('error', 'Notification not found for your account.'); 
    redirect(url('views/house-master/notifications/index/index.php')); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($notification['read'])) { 
    $result = $service->markAsRead($id, $uid); 
    flash($result['success'] ? 'success' : 'error', $result['message']); 
    redirect(url('views/house-master/notifications/view/view.php?id=' . urlencode($id))); 
}

$pageTitle = 'Notification Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php'; 
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-bell-fill text-primary me-2"></i>Notification Details</h4>
                <p class="text-muted mb-0">Review system broadcast and message details</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/notifications/index/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i>Back to Notifications
            </a>
        </div>

        <div class="card stat-card shadow-sm border-0 mb-4" style="max-width: 760px;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="badge bg-primary-subtle text-primary border text-uppercase">
                    <?= e($notification['type'] ?? 'General') ?>
                </span>
                <span class="badge <?= !empty($notification['read']) ? 'bg-success' : 'bg-warning text-dark' ?>">
                    <?= !empty($notification['read']) ? 'Read' : 'Unread' ?>
                </span>
            </div>
            <div class="card-body p-4">
                <h5 class="fw-bold mb-2"><?= e($notification['title'] ?? 'Notification') ?></h5>
                <div class="small text-muted mb-4">
                    <i class="bi bi-clock me-1"></i> <?= e($notification['createdAt'] ?? '—') ?>
                </div>

                <div class="p-3 bg-light rounded text-dark mb-4" style="line-height: 1.8; white-space: pre-line;">
                    <?= nl2br(e($notification['message'] ?? 'No message body provided.')) ?>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/notifications/index/index.php') ?>">
                        Back to List
                    </a>
                    <?php if (empty($notification['read'])): ?>
                        <form method="POST">
                            <button class="btn btn-primary btn-sm">
                                <i class="bi bi-check2-all me-1"></i>Mark as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>