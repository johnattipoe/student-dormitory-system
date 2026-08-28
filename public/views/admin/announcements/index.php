<?php
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
use App\Services\NotificationService;

$firebase = FirebaseService::getInstance();
$errors = [];
$roles = defined('ALL_ROLES') ? ALL_ROLES : [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY, ROLE_NURSE, ROLE_STUDENT];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) sanitize($_POST['title'] ?? ''));
    $message = trim((string) sanitize($_POST['message'] ?? ''));
    $audience = sanitize($_POST['audience'] ?? 'all');
    $targetRole = sanitize($_POST['targetRole'] ?? '');
    $type = sanitize($_POST['type'] ?? 'info');
    $status = sanitize($_POST['status'] ?? 'published');
    $sendNotification = !empty($_POST['sendNotification']);
    $isUrgent = !empty($_POST['isUrgent']);

    if ($title === '') $errors['title'] = 'Title is required.';
    if ($message === '') $errors['message'] = 'Message is required.';
    if (!in_array($audience, ['all', 'role'], true)) $audience = 'all';
    if ($audience === 'role' && !in_array($targetRole, $roles, true)) $errors['targetRole'] = 'Select a valid role.';
    if (!in_array($type, ['info', 'success', 'warning', 'danger'], true)) $type = 'info';
    if (!in_array($status, ['published', 'draft'], true)) $status = 'published';

    if (empty($errors)) {
        try {
            $announcement = [
                'title' => $title,
                'message' => $message,
                'audience' => $audience,
                'targetRole' => $audience === 'role' ? $targetRole : '',
                'type' => $type,
                'status' => $status,
                'isUrgent' => $isUrgent,
                'createdBy' => current_user_id(),
                'publishedAt' => $status === 'published' ? date(DATE_ATOM) : null,
            ];

            $firebase->addDocument('announcements', $announcement);

            if ($status === 'published' && $sendNotification) {
                $notification = [
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'isUrgent' => $isUrgent,
                ];
                $result = $audience === 'role'
                    ? (new NotificationService())->notifyRole($targetRole, $notification)
                    : (new NotificationService())->notifyAll($notification);

                flash($result['success'] ? 'success' : 'warning', 'Announcement saved. ' . ($result['message'] ?? ''));
            } else {
                flash('success', 'Announcement saved.');
            }

            redirect(url('views/admin/announcements/index.php'));
        } catch (Throwable $e) {
            $errors['general'] = 'Unable to save announcement: ' . $e->getMessage();
        }
    }
}

$announcements = $firebase->getCollection('announcements', [], 500);
usort($announcements, static fn(array $first, array $second): int => strcmp((string) ($second['createdAt'] ?? ''), (string) ($first['createdAt'] ?? '')));

$totalAnnouncements = count($announcements);
$publishedAnnouncements = count(array_filter($announcements, fn($a) => ($a['status'] ?? 'published') === 'published'));
$urgentAnnouncements = count(array_filter($announcements, fn($a) => !empty($a['isUrgent'])));

$shortText = static function (string $text, int $limit = 80): string {
    return strlen($text) > $limit ? substr($text, 0, $limit - 3) . '...' : $text;
};

$displayDate = static function (?string $value): string {
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M d, Y H:i', $timestamp) : $value;
};

$pageTitle = 'Announcements';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => url('views/admin/announcements/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-megaphone-fill text-warning me-2"></i>Campus Bulletins &amp; Broadcasts
                </h4>
                <p class="text-muted mb-0">Publish notices, general memos, and broadcast emergency alerts to dormitory portals</p>
            </div>
            <span class="badge bg-primary fs-6"><?= e((string) $totalAnnouncements) ?> Announcements</span>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Notices</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalAnnouncements) ?></h3>
                            <span class="small text-muted">All-time bulletins</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-megaphone fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Published Live</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $publishedAnnouncements) ?></h3>
                            <span class="small text-muted">Active broadcasts</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-broadcast fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Urgent Alerts</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $urgentAnnouncements) ?></h3>
                            <span class="small text-muted">Priority notices</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-octagon fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Create Announcement Column -->
            <div class="col-lg-5">
                <div class="card stat-card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Compose Announcement</h6>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Bulletin Title <span class="text-danger">*</span></label>
                                <input name="title" class="form-control" placeholder="e.g. End of Term Roll Call Schedule" required>
                                <?php if (!empty($errors['title'])): ?><div class="text-danger small mt-1"><?= e($errors['title']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Message Content <span class="text-danger">*</span></label>
                                <textarea name="message" class="form-control" rows="5" placeholder="Enter the announcement text..." required></textarea>
                                <?php if (!empty($errors['message'])): ?><div class="text-danger small mt-1"><?= e($errors['message']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Audience Scope</label>
                                <select name="audience" class="form-select">
                                    <option value="all">All Portals &amp; Roles</option>
                                    <option value="role">Targeted Role Group</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Target Role</label>
                                <select name="targetRole" class="form-select select2">
                                    <option value="">-- Select Role --</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= e($role) ?>"><?= e(ucwords(str_replace(['_', '-'], ' ', $role))) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($errors['targetRole'])): ?><div class="text-danger small mt-1"><?= e($errors['targetRole']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Notice Type</label>
                                <select name="type" class="form-select">
                                    <option value="info">Info (Blue)</option>
                                    <option value="success">Success (Green)</option>
                                    <option value="warning">Warning (Yellow)</option>
                                    <option value="danger">Danger / Critical (Red)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Publish Status</label>
                                <select name="status" class="form-select">
                                    <option value="published">Publish Immediately</option>
                                    <option value="draft">Save as Draft</option>
                                </select>
                            </div>
                            <div class="col-12 d-flex flex-column gap-2 bg-light p-3 rounded-3 border">
                                <div class="form-check form-switch">
                                    <input type="checkbox" name="sendNotification" class="form-check-input" id="sendNotification" checked>
                                    <label class="form-check-label fw-semibold small" for="sendNotification">Push instant in-app notification</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input type="checkbox" name="isUrgent" class="form-check-input" id="isUrgent">
                                    <label class="form-check-label fw-semibold small text-danger" for="isUrgent">Mark as Urgent Notice</label>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-send me-1"></i> Save &amp; Publish</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Announcements Table Column -->
            <div class="col-lg-7">
                <div class="card stat-card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-list-check me-2 text-success"></i>Existing Announcements</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Notice Title</th>
                                        <th>Audience</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($announcements)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">No announcements created yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($announcements as $announcement): ?>
                                            <?php 
                                            $annId = (string) ($announcement['id'] ?? '');
                                            $aType = $announcement['type'] ?? 'info';
                                            $aStatus = $announcement['status'] ?? 'published';
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong class="text-dark d-block"><?= e($announcement['title'] ?? 'Untitled') ?></strong>
                                                    <small class="text-muted"><?= e($shortText((string) ($announcement['message'] ?? ''))) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border">
                                                        <?= e(($announcement['audience'] ?? 'all') === 'role' ? ucwords(str_replace('_', ' ', (string) ($announcement['targetRole'] ?? ''))) : 'All Users') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $aStatus === 'published' ? 'success' : 'secondary' ?>">
                                                        <?= ucfirst(e($aStatus)) ?>
                                                    </span>
                                                </td>
                                                <td class="text-nowrap small text-muted"><?= e($displayDate($announcement['createdAt'] ?? null)) ?></td>
                                                <td class="text-end text-nowrap">
                                                    <?php if ($annId !== ''): ?>
                                                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/admin/announcements/view/view.php?id=' . urlencode($annId)) ?>" title="View"><i class="bi bi-eye"></i></a>
                                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/admin/announcements/edit/edit.php?id=' . urlencode($annId)) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                                                        <a class="btn btn-sm btn-outline-danger" href="<?= url('views/admin/announcements/delete/delete.php?id=' . urlencode($annId)) ?>" title="Delete"><i class="bi bi-trash"></i></a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
