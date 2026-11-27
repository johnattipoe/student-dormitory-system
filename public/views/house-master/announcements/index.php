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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS]; 
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php'; 
 
use App\Services\FirebaseService; 
use App\Services\HouseService;
use App\Services\NotificationService;

$role = current_role(); 
$currentUser = current_user() ?? [];
$currentUserId = (string) current_user_id();
$houseId = (string) ($currentUser['houseId'] ?? $currentUser['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = (string) ($house['name'] ?? ($houseId !== '' ? $houseId : 'your assigned house'));
$indexUrl = url('views/house-master/announcements/index.php');
$firebase = FirebaseService::getInstance();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($houseId === '') {
            throw new \InvalidArgumentException('Your account must have an assigned house to manage announcements.');
        }

        if ($action === 'delete_announcement' || $action === 'update_announcement') {
            $id = trim((string) sanitize($_POST['id'] ?? ''));
            $announcement = $id !== '' ? $firebase->getDocument('announcements', $id) : null;
            if (!$announcement
                || ($announcement['audience'] ?? '') !== 'house'
                || (string) ($announcement['targetHouseId'] ?? '') !== $houseId) {
                throw new \InvalidArgumentException('You can only manage announcements for your assigned house.');
            }

            if ($action === 'delete_announcement') {
                $firebase->deleteDocument('announcements', $id);
                flash('success', 'Announcement deleted successfully.');
                redirect($indexUrl);
            }

            $title = trim((string) sanitize($_POST['title'] ?? ''));
            $message = trim((string) sanitize($_POST['message'] ?? ''));
            $type = (string) sanitize($_POST['type'] ?? 'info');
            $status = (string) sanitize($_POST['status'] ?? 'published');
            if ($title === '' || $message === '') {
                throw new \InvalidArgumentException('Title and message are required.');
            }
            if (!in_array($type, ['info', 'success', 'warning', 'danger'], true)
                || !in_array($status, ['published', 'draft', 'archived'], true)) {
                throw new \InvalidArgumentException('Select a valid notice type and status.');
            }
            $firebase->updateDocument('announcements', $id, [
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'status' => $status,
                'isUrgent' => !empty($_POST['isUrgent']),
                'updatedAt' => date(DATE_ATOM),
            ]);
            flash('success', 'Announcement updated successfully.');
        } elseif ($action === 'create_announcement') {
            $title = trim((string) sanitize($_POST['title'] ?? ''));
            $message = trim((string) sanitize($_POST['message'] ?? ''));
            $type = (string) sanitize($_POST['type'] ?? 'info');
            if ($title === '' || $message === '') {
                throw new \InvalidArgumentException('Title and message are required.');
            }
            if (!in_array($type, ['info', 'success', 'warning', 'danger'], true)) {
                throw new \InvalidArgumentException('Select a valid notice type.');
            }
            $isUrgent = !empty($_POST['isUrgent']);
            $sendNotification = !empty($_POST['sendNotification']);
            $now = date(DATE_ATOM);
            $firebase->addDocument('announcements', [
                'title' => $title,
                'message' => $message,
                'audience' => 'house',
                'targetHouseId' => $houseId,
                'type' => $type,
                'status' => 'published',
                'isUrgent' => $isUrgent,
                'createdBy' => $currentUserId,
                'createdByName' => trim((string) ($currentUser['name'] ?? '')) ?: ucfirst(str_replace('_', ' ', $role)),
                'publishedAt' => $now,
            ]);

            if ($sendNotification) {
                try {
                    $notificationService = new NotificationService();
                    $recipientCount = 0;
                    $recipients = $firebase->getAllCollection('users', [['houseId', '=', $houseId]]);
                    foreach ($recipients as $recipient) {
                        if ((string) ($recipient['houseId'] ?? '') !== $houseId
                            || ($recipient['status'] ?? 'active') !== 'active') {
                            continue;
                        }
                        $recipientId = (string) ($recipient['uid'] ?? $recipient['id'] ?? '');
                        if ($recipientId === '') {
                            continue;
                        }
                        $result = $notificationService->create([
                            'recipientType' => 'user',
                            'userId' => $recipientId,
                            'title' => $title,
                            'message' => $message,
                            'type' => $type,
                            'isUrgent' => $isUrgent,
                        ]);
                        $recipientCount += !empty($result['success']) ? 1 : 0;
                    }
                    flash($recipientCount > 0 ? 'success' : 'warning', 'Announcement published for ' . $houseName . '; notifications confirmed for ' . $recipientCount . ' house user(s).');
                } catch (\Throwable $e) {
                    flash('warning', 'Announcement published for ' . $houseName . ', but in-app notification delivery could not be confirmed: ' . $e->getMessage());
                }
            } else {
                flash('success', 'Announcement published for ' . $houseName . '.');
            }
        } else {
            throw new \InvalidArgumentException('Unknown announcement action.');
        }
        redirect($indexUrl);
    } catch (\Throwable $e) {
        $errors['general'] = 'Unable to save announcement: ' . $e->getMessage();
        $errors['action'] = $action;
    }
}

$records = $firebase->getCollection('announcements', [], 500);

$userMap = [
    'default-admin' => 'Administrator (Admin)',
    'system' => 'System',
];

$getAuthorName = function (array $announcement) use (&$userMap): string {
    $authorName = (string) ($announcement['createdByName'] ?? '');
    if ($authorName !== '' && $authorName !== 'default-admin' && !str_starts_with($authorName, 'Staff/User')) {
        return $authorName;
    }
    $raw = (string) ($announcement['createdBy'] ?? '');
    if ($raw === '' || $raw === 'system') return 'System';
    if ($raw === 'default-admin') return 'Administrator (Admin)';
    if (isset($userMap[$raw])) return $userMap[$raw];

    try {
        $u = FirebaseService::getInstance()->getDocument('users', $raw);
        if ($u) {
            $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
            if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? $u['email'] ?? '';
            if ($name !== '') {
                $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                $userMap[$raw] = $name . $roleLabel;
                return $userMap[$raw];
            }
        }
        foreach (['uid', 'userId', 'firebaseUid', 'email'] as $field) {
            $users = FirebaseService::getInstance()->getCollection('users', [[$field, '=', $raw]], 1);
            if (isset($users[0])) {
                $u = $users[0];
                $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
                if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? $u['email'] ?? '';
                if ($name !== '') {
                    $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                    $userMap[$raw] = $name . $roleLabel;
                    return $userMap[$raw];
                }
            }
        }
    } catch (\Throwable $e) {}

    $userMap[$raw] = $raw;
    return $raw;
};

$announcements = array_values(array_filter($records, static function (array $item) use ($role, $houseId): bool {
    $audience = (string) ($item['audience'] ?? 'all');
    if ($audience === 'house') {
        return $houseId !== ''
            && (string) ($item['targetHouseId'] ?? '') === $houseId
            && (($item['status'] ?? 'published') === 'published'
                || (string) ($item['createdBy'] ?? '') === current_user_id());
    }
    if (($item['status'] ?? 'published') !== 'published') {
        return false;
    }
    if ($audience === 'all') {
        return true;
    }
    return $audience === 'role' && (string) ($item['targetRole'] ?? '') === $role;
})); 

usort( 
    $announcements, 
    static fn(array $a, array $b): int => strcmp( 
        (string) ($b['publishedAt'] ?? $b['createdAt'] ?? ''), 
        (string) ($a['publishedAt'] ?? $a['createdAt'] ?? '') 
    ) 
); 

$displayDate = static function (?string $value): string { 
    if (!$value) { 
        return 'Not recorded'; 
    } 
    $timestamp = strtotime($value); 
    return $timestamp ? date('M d, Y H:i', $timestamp) : $value; 
}; 

$latestAnnouncement = $announcements[0] ?? null;
$urgentCount = count(array_filter($announcements, static fn(array $announcement): bool => !empty($announcement['isUrgent'])));

$pageTitle = 'Announcements'; 
$navItems = [ 
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')], 
    ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => url('views/house-master/announcements/index.php'), 'active' => true], 
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-megaphone-fill text-primary me-2"></i>Announcements & Bulletins</h4>
                <p class="text-muted mb-0">Published notices and official communications for your house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="badge bg-primary"><i class="bi bi-megaphone me-1"></i><?= e((string) count($announcements)) ?> notices</span>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createHouseAnnouncementModal" <?= $houseId === '' ? 'disabled title="Assign a house to this account first"' : '' ?>>
                    <i class="bi bi-send me-1"></i>Post Announcement
                </button>
            </div>
        </div>
        <?php if ($houseId === ''): ?>
            <div class="alert alert-warning">This account has no assigned house. Posting and managing house announcements is disabled.</div>
        <?php endif; ?>
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <!-- KPI Row -->
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Latest Notice</span>
                            <h5 class="mt-2 mb-2 fw-bold"><?= e($latestAnnouncement['title'] ?? 'No announcements yet') ?></h5>
                            <?php if ($latestAnnouncement): ?>
                                <p class="text-muted mb-2"><?= e((string) ($latestAnnouncement['message'] ?? '')) ?></p>
                                <div class="small text-secondary">
                                    <span><i class="bi bi-person me-1"></i>Posted by: <strong><?= e($getAuthorName($latestAnnouncement)) ?></strong></span>
                                    <span class="mx-2">•</span>
                                    <span><i class="bi bi-clock me-1"></i><?= e($displayDate($latestAnnouncement['publishedAt'] ?? $latestAnnouncement['createdAt'] ?? null)) ?></span>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">Published notices for your dormitory role will appear here.</p>
                            <?php endif; ?>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-megaphone fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">All Notices</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) count($announcements)) ?></h3>
                            <span class="small text-muted">Published</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-collection fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Urgent</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $urgentCount) ?></h3>
                            <span class="small text-muted">Needs attention</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-triangle fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Announcements Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2"></i>All Announcements</h6>
                <small class="text-muted">Showing <?= e((string) count($announcements)) ?> records</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 data-table w-100"> 
                        <thead class="table-light"> 
                            <tr> 
                                <th>Title & Subject</th> 
                                <th>Category</th>
                                <th>Target Audience</th>
                                <th>Posted By</th> 
                                <th>Date Posted</th> 
                                <th class="text-end">Actions</th>
                            </tr> 
                        </thead> 
                        <tbody> 
                            <?php if (!$announcements): ?> 
                                <tr> 
                                    <td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No announcements available.</td> 
                                </tr> 
                            <?php else: ?> 
                                <?php foreach ($announcements as $announcement): ?> 
                                    <?php 
                                        $type = strtolower((string) ($announcement['type'] ?? 'info'));
                                        $typeBadge = match($type) {
                                            'danger' => 'danger',
                                            'warning' => 'warning',
                                            'success' => 'success',
                                            default => 'info'
                                        };
                                        $audience = (string) ($announcement['audience'] ?? 'all');
                                        $author = $getAuthorName($announcement);
                                        $annId = (string) ($announcement['id'] ?? '');
                                        $canManageAnnouncement = $houseId !== ''
                                            && $audience === 'house'
                                            && (string) ($announcement['targetHouseId'] ?? '') === $houseId;
                                    ?>
                                    <tr> 
                                        <td> 
                                            <strong><?= e($announcement['title'] ?? 'Untitled') ?></strong> 
                                            <?php if (!empty($announcement['isUrgent'])): ?> 
                                                <span class="badge bg-danger ms-2">Urgent</span> 
                                            <?php endif; ?> 
                                            <div class="small text-muted"><?= e(mb_strimwidth((string)($announcement['message'] ?? ''), 0, 70, '...')) ?></div>
                                        </td> 
                                        <td>
                                            <span class="badge bg-<?= e($typeBadge) ?>">
                                                <?= e(ucfirst($type)) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary border">
                                                <?= e($audience === 'house' ? $houseName : ($audience === 'all' ? 'All Dormitory' : ucfirst(str_replace('_', ' ', (string)($announcement['targetRole'] ?? $audience))))) ?>
                                            </span>
                                        </td>
                                        <td class="fw-medium"><?= e($author) ?></td>
                                        <td class="text-nowrap small text-muted"><?= e($displayDate($announcement['publishedAt'] ?? $announcement['createdAt'] ?? null)) ?></td> 
                                        <td class="text-end text-nowrap">
                                            <?php if ($annId !== ''): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#houseAnnouncementViewModal-<?= e($annId) ?>" title="View"><i class="bi bi-eye"></i></button>
                                                <?php if ($canManageAnnouncement): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#houseAnnouncementEditModal-<?= e($annId) ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#houseAnnouncementDeleteModal-<?= e($annId) ?>" title="Delete"><i class="bi bi-trash"></i></button>
                                                <?php endif; ?>
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
<div class="modal fade" id="createHouseAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= e($indexUrl) ?>" class="announcement-modal-form">
                <input type="hidden" name="action" value="create_announcement">
                <div class="modal-header"><h5 class="modal-title">Post Announcement for <?= e($houseName) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <?php if (!empty($errors['general'])): ?><div class="alert alert-danger"><?= e($errors['general']) ?></div><?php endif; ?>
                    <div class="alert alert-info">Only users assigned to <?= e($houseName) ?> will see this announcement. No school-wide audience is available from the House Master/Mistress portal.</div>
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label fw-semibold">Title / subject</label><input name="title" class="form-control" maxlength="160" value="<?= e($_POST['title'] ?? '') ?>" required></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Category</label><select name="type" class="form-select"><?php foreach (['info' => 'General information', 'success' => 'Positive notice', 'warning' => 'Warning / advisory', 'danger' => 'Urgent / critical'] as $type => $label): ?><option value="<?= e($type) ?>" <?= ($_POST['type'] ?? 'info') === $type ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                        <div class="col-12"><label class="form-label fw-semibold">Message</label><textarea name="message" class="form-control" rows="5" maxlength="5000" required><?= e($_POST['message'] ?? '') ?></textarea></div>
                        <div class="col-12 d-flex flex-column gap-2 bg-light p-3 rounded-3 border">
                            <div class="form-check"><input type="checkbox" name="sendNotification" class="form-check-input" id="sendHouseAnnouncementNotification" value="1" <?= !isset($_POST['action']) || !empty($_POST['sendNotification']) ? 'checked' : '' ?>><label class="form-check-label fw-semibold" for="sendHouseAnnouncementNotification">Send in-app notification to active users assigned to this house</label></div>
                            <div class="form-check"><input type="checkbox" name="isUrgent" class="form-check-input" id="houseAnnouncementUrgent" value="1" <?= !empty($_POST['isUrgent']) ? 'checked' : '' ?>><label class="form-check-label fw-semibold text-danger" for="houseAnnouncementUrgent">Flag as urgent</label></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Publish for house</button></div>
            </form>
        </div>
    </div>
</div>
<?php foreach ($announcements as $announcement): ?>
    <?php
    $modalAnnouncementId = (string) ($announcement['id'] ?? '');
    $modalAnnouncementType = (string) ($announcement['type'] ?? 'info');
    $modalCanManageAnnouncement = $houseId !== ''
        && ($announcement['audience'] ?? '') === 'house'
        && (string) ($announcement['targetHouseId'] ?? '') === $houseId;
    ?>
    <div class="modal fade" id="houseAnnouncementViewModal-<?= e($modalAnnouncementId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Announcement Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><h5><?= e($announcement['title'] ?? 'Untitled') ?></h5><span class="badge bg-<?= e($modalAnnouncementType === 'danger' ? 'danger' : ($modalAnnouncementType === 'warning' ? 'warning text-dark' : ($modalAnnouncementType === 'success' ? 'success' : 'info'))) ?> mb-3"><?= e(ucfirst($modalAnnouncementType)) ?></span><p class="mb-0" style="white-space: pre-line;"><?= e($announcement['message'] ?? '') ?></p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
    <?php if ($modalCanManageAnnouncement): ?>
        <div class="modal fade" id="houseAnnouncementEditModal-<?= e($modalAnnouncementId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content"><form method="POST" action="<?= e($indexUrl) ?>" class="announcement-modal-form"><input type="hidden" name="action" value="update_announcement"><input type="hidden" name="id" value="<?= e($modalAnnouncementId) ?>"><div class="modal-header"><h5 class="modal-title">Edit Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><label class="form-label">Title</label><input name="title" class="form-control mb-3" value="<?= e($announcement['title'] ?? '') ?>" required><label class="form-label">Type</label><select name="type" class="form-select mb-3"><option value="info" <?= $modalAnnouncementType === 'info' ? 'selected' : '' ?>>Info</option><option value="success" <?= $modalAnnouncementType === 'success' ? 'selected' : '' ?>>Success</option><option value="warning" <?= $modalAnnouncementType === 'warning' ? 'selected' : '' ?>>Warning</option><option value="danger" <?= $modalAnnouncementType === 'danger' ? 'selected' : '' ?>>Urgent</option></select><label class="form-label">Message</label><textarea name="message" class="form-control" rows="5" required><?= e($announcement['message'] ?? '') ?></textarea><input type="hidden" name="status" value="<?= e($announcement['status'] ?? 'published') ?>"><input type="hidden" name="isUrgent" value="<?= !empty($announcement['isUrgent']) ? '1' : '' ?>"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div></form></div></div></div>
        <div class="modal fade" id="houseAnnouncementDeleteModal-<?= e($modalAnnouncementId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header border-0"><h5 class="modal-title text-danger">Delete Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="mb-0">Delete <strong><?= e($announcement['title'] ?? 'this announcement') ?></strong>?</p></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= e($indexUrl) ?>"><input type="hidden" name="action" value="delete_announcement"><input type="hidden" name="id" value="<?= e($modalAnnouncementId) ?>"><button type="submit" class="btn btn-danger">Delete</button></form></div></div></div></div>
    <?php endif; ?>
<?php endforeach; ?>
<?php if (!empty($errors['general']) && ($errors['action'] ?? '') === 'create_announcement'): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('createHouseAnnouncementModal');
        if (modal && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modal).show();
    });
    </script>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
