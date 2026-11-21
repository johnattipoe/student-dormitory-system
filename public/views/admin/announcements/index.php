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
$indexUrl = url('views/admin/announcements/index.php');
$roles = defined('ALL_ROLES') ? ALL_ROLES : [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY, ROLE_NURSE, ROLE_STUDENT];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create_announcement');
    $announcementCreated = false;
    try {
        if ($action === 'delete_announcement') {
            $id = trim((string) sanitize($_POST['id'] ?? ''));
            if ($id === '' || !$firebase->getDocument('announcements', $id)) {
                throw new \InvalidArgumentException('Announcement not found.');
            }
            $firebase->deleteDocument('announcements', $id);
            flash('success', 'Announcement deleted successfully.');
            redirect($indexUrl);
        }

        if ($action === 'update_announcement') {
            $id = trim((string) sanitize($_POST['id'] ?? ''));
            if ($id === '' || !$firebase->getDocument('announcements', $id)) {
                throw new \InvalidArgumentException('Announcement not found.');
            }
        } elseif ($action !== 'create_announcement') {
            throw new \InvalidArgumentException('Unknown announcement action.');
        }

        $title = trim((string) sanitize($_POST['title'] ?? ''));
        $message = trim((string) sanitize($_POST['message'] ?? ''));
        $audience = sanitize($_POST['audience'] ?? 'all');
        $targetRole = sanitize($_POST['targetRole'] ?? '');
        $type = sanitize($_POST['type'] ?? 'info');
        $status = sanitize($_POST['status'] ?? 'published');
        $sendNotification = !empty($_POST['sendNotification']);
        $isUrgent = !empty($_POST['isUrgent']);

        if ($title === '' || $message === '') {
            throw new \InvalidArgumentException('Title and message are required.');
        }
        if (!in_array($audience, ['all', 'role'], true)
            || ($audience === 'role' && !in_array($targetRole, $roles, true))) {
            throw new \InvalidArgumentException('Select a valid audience and target role.');
        }
        if (!in_array($type, ['info', 'success', 'warning', 'danger'], true)
            || !in_array($status, ['published', 'draft'], true)) {
            throw new \InvalidArgumentException('Select a valid announcement type and status.');
        }

        $announcement = [
            'title' => $title,
            'message' => $message,
            'audience' => $audience,
            'targetRole' => $audience === 'role' ? $targetRole : '',
            'type' => $type,
            'status' => $status,
            'isUrgent' => $isUrgent,
        ];

        if ($action === 'update_announcement') {
            $firebase->updateDocument('announcements', $id, $announcement);
            flash('success', 'Announcement updated.');
        } else {
            $announcement['createdBy'] = current_user_id();
            $announcement['publishedAt'] = $status === 'published' ? date(DATE_ATOM) : null;
            $firebase->addDocument('announcements', $announcement);
            $announcementCreated = true;

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
        }
        redirect($indexUrl);
    } catch (Throwable $e) {
        if ($announcementCreated) {
            flash('warning', 'Announcement saved, but notification delivery could not be confirmed: ' . $e->getMessage());
            redirect($indexUrl);
        } else {
            $errors['general'] = 'Unable to save announcement: ' . $e->getMessage();
            $errors['action'] = $action;
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
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Compose Announcement</h6>
                    </div>
                    <div class="card-body p-4 d-flex flex-column justify-content-center align-items-center text-center">
                        <p class="text-muted">Create a campus-wide announcement or target a specific role. Published announcements can also send an in-app notification.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                            <i class="bi bi-send me-1"></i>Compose Announcement
                        </button>
                    </div>
                </div>
            </div>

            <!-- Announcements Table Column -->
            <div class="col-lg-7">
                <div class="card stat-card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-list-check me-2 text-success"></i>Existing Announcements</h6>
                        <form method="GET" class="m-0" role="search" data-live-search data-live-search-table="#announcementsTable" data-no-page-reload="true">
                            <input type="search" name="search" class="form-control form-control-sm" placeholder="Search announcements..." aria-label="Search announcements" style="width: 240px; max-width: 100%;">
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="announcementsTable" class="table table-hover align-middle mb-0">
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
                                            $searchText = implode(' ', [
                                                (string) ($announcement['title'] ?? ''),
                                                (string) ($announcement['message'] ?? ''),
                                                (string) ($announcement['audience'] ?? 'all'),
                                                (string) ($announcement['targetRole'] ?? ''),
                                                (string) $aType,
                                                (string) $aStatus,
                                            ]);
                                            ?>
                                            <tr data-search-text="<?= e($searchText) ?>">
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
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" title="View" data-bs-toggle="modal" data-bs-target="#announcementViewModal-<?= e($annId) ?>"><i class="bi bi-eye"></i></button>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" title="Edit" data-bs-toggle="modal" data-bs-target="#announcementEditModal-<?= e($annId) ?>"><i class="bi bi-pencil"></i></button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#announcementDeleteModal-<?= e($annId) ?>"><i class="bi bi-trash"></i></button>
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

<div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-hidden="true" data-open-on-load="<?= (!empty($_GET['openCreateAnnouncement']) || (!empty($errors['general']) && ($errors['action'] ?? '') === 'create_announcement')) ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= e($indexUrl) ?>" class="announcement-modal-form">
                <input type="hidden" name="action" value="create_announcement">
                <div class="modal-header"><h5 class="modal-title">Compose Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <?php if (!empty($errors['general'])): ?><div class="alert alert-danger"><?= e($errors['general']) ?></div><?php endif; ?>
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Bulletin title</label><input name="title" class="form-control" maxlength="160" value="<?= e($_POST['title'] ?? '') ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Audience scope</label><select name="audience" id="announcementAudience" class="form-select"><option value="all" <?= ($_POST['audience'] ?? 'all') === 'all' ? 'selected' : '' ?>>All portals &amp; roles</option><option value="role" <?= ($_POST['audience'] ?? '') === 'role' ? 'selected' : '' ?>>Targeted role group</option></select></div>
                        <div class="col-md-6"><label class="form-label">Target role</label><select name="targetRole" id="announcementTargetRole" class="form-select"><option value="">Select role</option><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>" <?= ($_POST['targetRole'] ?? '') === $role ? 'selected' : '' ?>><?= e(ucwords(str_replace(['_', '-'], ' ', $role))) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Notice type</label><select name="type" class="form-select"><?php foreach (['info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'danger' => 'Danger / Critical'] as $type => $label): ?><option value="<?= e($type) ?>" <?= ($_POST['type'] ?? 'info') === $type ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Publish status</label><select name="status" class="form-select"><option value="published" <?= ($_POST['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Publish immediately</option><option value="draft" <?= ($_POST['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Save as draft</option></select></div>
                        <div class="col-12"><label class="form-label">Message content</label><textarea name="message" class="form-control" rows="5" maxlength="5000" required><?= e($_POST['message'] ?? '') ?></textarea></div>
                        <div class="col-12 d-flex flex-column gap-2 bg-light p-3 rounded-3 border">
                            <div class="form-check form-switch"><input type="checkbox" name="sendNotification" class="form-check-input" id="sendAnnouncementNotification" value="1" <?= !isset($_POST['action']) || !empty($_POST['sendNotification']) ? 'checked' : '' ?>><label class="form-check-label fw-semibold small" for="sendAnnouncementNotification">Send in-app notification when published</label></div>
                            <div class="form-check form-switch"><input type="checkbox" name="isUrgent" class="form-check-input" id="announcementUrgent" value="1" <?= !empty($_POST['isUrgent']) ? 'checked' : '' ?>><label class="form-check-label fw-semibold small text-danger" for="announcementUrgent">Mark as urgent</label></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Save announcement</button></div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($announcements as $announcement): ?>
    <?php
    $annId = (string) ($announcement['id'] ?? '');
    $announcementTitle = (string) ($announcement['title'] ?? 'Untitled');
    $announcementMessage = (string) ($announcement['message'] ?? '');
    $announcementAudience = (string) ($announcement['audience'] ?? 'all');
    $announcementTargetRole = (string) ($announcement['targetRole'] ?? '');
    $announcementStatus = (string) ($announcement['status'] ?? 'published');
    ?>
    <div class="modal fade" id="announcementViewModal-<?= e($annId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6><?= e($announcementTitle) ?></h6>
                    <p class="text-muted mb-3"><?= e($announcementAudience === 'role' ? ('Targeted: ' . ucwords(str_replace('_', ' ', $announcementTargetRole))) : 'All users') ?></p>
                    <p class="mb-0"><?= e($announcementMessage) ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="announcementEditModal-<?= e($annId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <form method="POST" action="<?= e($indexUrl) ?>" class="announcement-modal-form">
                    <input type="hidden" name="action" value="update_announcement">
                    <input type="hidden" name="id" value="<?= e($annId) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Announcement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label">Title</label><input name="title" class="form-control" value="<?= e($announcementTitle) ?>" required></div>
                            <div class="col-12"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="5" required><?= e($announcementMessage) ?></textarea></div>
                            <div class="col-md-6"><label class="form-label">Audience scope</label><select name="audience" class="form-select"><option value="all" <?= $announcementAudience === 'all' ? 'selected' : '' ?>>All portals &amp; roles</option><option value="role" <?= $announcementAudience === 'role' ? 'selected' : '' ?>>Targeted role group</option></select></div>
                            <div class="col-md-6"><label class="form-label">Target role</label><select name="targetRole" class="form-select"><option value="">Select role</option><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>" <?= $announcementTargetRole === $role ? 'selected' : '' ?>><?= e(ucwords(str_replace(['_', '-'], ' ', $role))) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label">Notice type</label><select name="type" class="form-select"><?php foreach (['info', 'success', 'warning', 'danger'] as $type): ?><option value="<?= e($type) ?>" <?= ($announcement['type'] ?? 'info') === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="published" <?= $announcementStatus === 'published' ? 'selected' : '' ?>>Published</option><option value="draft" <?= $announcementStatus === 'draft' ? 'selected' : '' ?>>Draft</option></select></div>
                            <div class="col-12 form-check"><input class="form-check-input" type="checkbox" name="isUrgent" value="1" id="announcementUrgent-<?= e($annId) ?>" <?= !empty($announcement['isUrgent']) ? 'checked' : '' ?>><label class="form-check-label text-danger" for="announcementUrgent-<?= e($annId) ?>">Mark as urgent</label></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="announcementDeleteModal-<?= e($annId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger">Delete Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete <strong><?= e($announcementTitle) ?></strong>?</p>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="<?= e($indexUrl) ?>">
                        <input type="hidden" name="action" value="delete_announcement">
                        <input type="hidden" name="id" value="<?= e($annId) ?>">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<script>
document.querySelectorAll('select[name="audience"]').forEach(function (audienceSelect) {
    const form = audienceSelect.closest('form');
    const roleSelect = form ? form.querySelector('select[name="targetRole"]') : null;
    if (!roleSelect) return;
    const updateRoleRequirement = function () {
        roleSelect.required = audienceSelect.value === 'role';
        roleSelect.disabled = audienceSelect.value !== 'role';
    };
    audienceSelect.addEventListener('change', updateRoleRequirement);
    updateRoleRequirement();
});

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('createAnnouncementModal');
    if (modal && modal.dataset.openOnLoad === 'true' && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
});
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
