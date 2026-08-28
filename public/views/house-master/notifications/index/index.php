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

$notificationService = new NotificationService();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['mark_read', 'mark_all_read'], true)) {
    $currentUserId = current_user()['uid'] ?? current_user()['id'] ?? null;
    $result = ($_POST['action'] ?? '') === 'mark_all_read'
        ? $notificationService->markAllAsRead($currentUserId)
        : $notificationService->markAsRead(sanitize($_POST['id'] ?? ''), $currentUserId);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/house-master/notifications/index/index.php'));
}

$pageTitle = 'Notifications';
$currentUserId = current_user()['uid'] ?? current_user()['id'] ?? null;
$notifications = $currentUserId ? $notificationService->forUser($currentUserId) : [];
$notificationType = sanitize($_GET['type'] ?? '');
$notificationRead = sanitize($_GET['read'] ?? '');
$notificationSearch = strtolower(sanitize($_GET['search'] ?? ''));
$notifications = array_values(array_filter($notifications, function ($note) use ($notificationType, $notificationRead, $notificationSearch) {
    return ($notificationType === '' || ($note['type'] ?? '') === $notificationType)
        && ($notificationRead === '' || (($notificationRead === 'unread') === empty($note['read'])))
        && ($notificationSearch === '' || str_contains(strtolower((string) ($note['title'] ?? '')), $notificationSearch) || str_contains(strtolower((string) ($note['message'] ?? '')), $notificationSearch));
}));
$unreadCount = count(array_filter($notifications, fn($note) => empty($note['read'])));
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index/index.php'), 'active' => true],
    ['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/create/create.php')],
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
                <p class="text-muted mb-0">Stay updated with alerts and system messages</p>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= count($notifications) ?> items</span>
                <span class="badge bg-warning text-dark"><?= $unreadCount ?> unread</span>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check-all me-1"></i>Mark All Read</button>
                </form>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total</span>
                            <h3 class="fw-bold my-1 text-info"><?= count($notifications) ?></h3>
                            <span class="small text-muted">All notifications</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-bell fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Unread</span>
                            <h3 class="fw-bold my-1 text-warning"><?= $unreadCount ?></h3>
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
                            <h3 class="fw-bold my-1 text-success"><?= count($notifications) - $unreadCount ?></h3>
                            <span class="small text-muted">Already reviewed</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-envelope-open fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input name="search" class="form-control form-control-sm" placeholder="Search notifications..." value="<?= e($notificationSearch) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="type" class="form-select form-select-sm">
                            <option value="">All types</option>
                            <?php foreach (['info','success','warning','danger'] as $type): ?>
                                <option value="<?= e($type) ?>" <?= $notificationType === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="read" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="unread" <?= $notificationRead === 'unread' ? 'selected' : '' ?>>Unread</option>
                            <option value="read" <?= $notificationRead === 'read' ? 'selected' : '' ?>>Read</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/notifications/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Notifications Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-bell me-2"></i>All Notifications</h6>
                <small class="text-muted">Showing <?= count($notifications) ?> records</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $note): ?>
                                <tr class="<?= empty($note['read']) ? 'table-light' : '' ?>">
                                    <td class="fw-medium"><?= e($note['title'] ?? '') ?></td>
                                    <td>
                                        <?php
                                            $typeBadge = match($note['type'] ?? 'info') {
                                                'danger' => 'danger', 'warning' => 'warning text-dark', 'success' => 'success', default => 'info'
                                            };
                                        ?>
                                        <span class="badge bg-<?= $typeBadge ?>"><?= e(ucfirst($note['type'] ?? 'info')) ?></span>
                                    </td>
                                    <td class="small text-muted"><?= e(mb_strimwidth((string)($note['message'] ?? ''), 0, 80, '...')) ?></td>
                                    <td>
                                        <?php if (!empty($note['read'])): ?>
                                            <span class="badge bg-success-subtle text-success border"><i class="bi bi-check2 me-1"></i>Read</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning border"><i class="bi bi-circle-fill me-1" style="font-size:0.4rem;vertical-align:middle;"></i>Unread</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/notifications/view/view.php?id=' . urlencode((string) ($note['id'] ?? ''))) ?>"><i class="bi bi-eye me-1"></i>View</a>
                                        <?php if (empty($note['read'])): ?>
                                            <form method="POST" action="<?= url('views/house-master/notifications/index/index.php') ?>" class="d-inline">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="id" value="<?= e((string) ($note['id'] ?? '')) ?>">
                                                <button class="btn btn-sm btn-outline-success"><i class="bi bi-check me-1"></i>Mark Read</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No notifications available for your account.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
