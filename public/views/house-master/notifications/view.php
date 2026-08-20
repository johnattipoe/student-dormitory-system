<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\NotificationService;
$uid = current_user()['uid'] ?? current_user()['id'] ?? null;
$id = sanitize($_GET['id'] ?? '');
$service = new NotificationService();
$notification = $service->findForUser($id, $uid);
if (!$notification) { flash('error', 'Notification not found for your account.'); redirect(url('views/house-master/notifications/index.php')); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($notification['read'])) { $result = $service->markAsRead($id, $uid); flash($result['success'] ? 'success' : 'error', $result['message']); redirect(url('views/house-master/notifications/view.php?id=' . urlencode($id))); }
$pageTitle = 'Notification Details';
$navItems = [['icon'=>'bi-bell','label'=>'Notifications','href'=>url('views/house-master/notifications/index.php'),'active'=>true]];
require APP_ROOT . '/app/views/components/header.php'; require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content"><?php require APP_ROOT . '/app/views/components/navbar.php'; ?><div class="content-wrapper"><div class="card stat-card p-4" style="max-width:820px"><div class="d-flex justify-content-between align-items-start gap-3"><div><span class="badge bg-info mb-2"><?= e($notification['type'] ?? 'info') ?></span><h5 class="mb-1"><?= e($notification['title'] ?? 'Notification') ?></h5><small class="text-muted"><?= e($notification['createdAt'] ?? '') ?></small></div><a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/notifications/index.php') ?>">Back</a></div><hr><p class="mb-4"><?= nl2br(e($notification['message'] ?? '')) ?></p><div class="d-flex justify-content-between align-items-center"><span class="badge <?= !empty($notification['read']) ? 'bg-success' : 'bg-warning text-dark' ?>"><?= !empty($notification['read']) ? 'Read' : 'Unread' ?></span><?php if (empty($notification['read'])): ?><form method="POST"><button class="btn btn-primary btn-sm">Mark as read</button></form><?php endif; ?></div></div></div></div><?php require APP_ROOT . '/app/views/components/footer.php'; ?>