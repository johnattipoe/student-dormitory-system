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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\NotificationService;

$user = current_user() ?? [];
$userId = current_user_id() ?: 'default-admin';
$userName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: 'Administrator';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) sanitize($_POST['title'] ?? ''));
    $message = trim((string) sanitize($_POST['message'] ?? ''));
    $audience = sanitize($_POST['audience'] ?? 'all');
    $type = sanitize($_POST['type'] ?? 'info');
    $isUrgent = !empty($_POST['isUrgent']);
    $sendNotification = !empty($_POST['sendNotification']);

    if ($title === '') $errors['title'] = 'Title is required.';
    if ($message === '') $errors['message'] = 'Message content is required.';

    if (empty($errors)) {
        try {
            $firebase = FirebaseService::getInstance();

            $targetRole = match($audience) {
                'house_master' => ROLE_HOUSE_MASTER,
                'senior_houseparent' => ROLE_SENIOR_HOUSEPARENT,
                'student' => ROLE_STUDENT,
                'nurse' => ROLE_NURSE,
                'security' => ROLE_SECURITY,
                default => ''
            };

            $docId = $firebase->addDocument('announcements', [
                'title' => $title,
                'message' => $message,
                'audience' => $audience === 'all' ? 'all' : 'role',
                'targetRole' => $targetRole,
                'type' => $type,
                'status' => 'published',
                'isUrgent' => $isUrgent,
                'createdBy' => $userId,
                'createdByName' => $userName . ' (Administrator)',
                'publishedAt' => date(DATE_ATOM),
                'createdAt' => date(DATE_ATOM),
            ]);

            if ($sendNotification) {
                $notifyService = new NotificationService();
                $notifPayload = [
                    'title' => ($isUrgent ? 'URGENT: ' : 'ANNOUNCEMENT: ') . $title,
                    'message' => mb_strimwidth($message, 0, 140, '...'),
                    'type' => $type,
                    'isUrgent' => $isUrgent,
                    'link' => url('views/admin/announcements/index.php'),
                ];

                if ($audience === 'all') {
                    $notifyService->broadcastToAll($notifPayload);
                } elseif ($targetRole !== '') {
                    $notifyService->broadcastToRole($targetRole, $notifPayload);
                }
            }

            flash('success', 'Announcement published successfully.');
            redirect(url('views/admin/announcements/index.php'));
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to publish announcement: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Post Institutional Announcement';
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
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Post Campus Announcement</h5>
                <p class="text-muted mb-0">Broadcast official institutional circulars and dormitory notices.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/announcements/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Announcements
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card stat-card p-4" style="max-width: 850px;">
            <label class="form-label text-muted small text-uppercase fw-bold mb-2">Quick Announcement Presets</label>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAdminAnnPreset('End of Term Dormitory Departure Guidelines', 'Please take note that all students must vacate dormitory rooms by 4:00 PM on Friday. Room keys and inventory checklists must be handed to assigned House Masters.', 'info', 'all', false)">Term Departure</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAdminAnnPreset('Mandatory General Dormitory Inspection', 'All houses will undergo comprehensive health, hygiene, and fire safety inspection this Saturday morning starting at 9:00 AM.', 'warning', 'all', false)">Dorm Inspection</button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="setAdminAnnPreset('URGENT: Gate & Curfew Protocol Enforcement', 'Strict 10:00 PM lights-out and gate lockdown will be enforced. Unauthorized movement on campus grounds after curfew is strictly prohibited.', 'danger', 'student', true)">Curfew Enforcement</button>
            </div>

            <form method="POST" class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-bold" for="title">Title <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['title']) ? 'is-invalid' : '' ?>" id="title" name="title" value="<?= e($_POST['title'] ?? '') ?>" placeholder="e.g. End of Term Dormitory Guidelines" required>
                    <?php if (!empty($errors['title'])): ?>
                        <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold" for="type">Category / Style</label>
                    <select class="form-select" id="type" name="type">
                        <option value="info" <?= ($_POST['type'] ?? '') === 'info' ? 'selected' : '' ?>>Info (Blue)</option>
                        <option value="warning" <?= ($_POST['type'] ?? '') === 'warning' ? 'selected' : '' ?>>Warning (Yellow)</option>
                        <option value="danger" <?= ($_POST['type'] ?? '') === 'danger' ? 'selected' : '' ?>>Urgent / Notice (Red)</option>
                        <option value="success" <?= ($_POST['type'] ?? '') === 'success' ? 'selected' : '' ?>>Success (Green)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="audience">Target Audience</label>
                    <select class="form-select" id="audience" name="audience">
                        <option value="all">Entire Institution (All Staff & Students)</option>
                        <option value="student">Students Only</option>
                        <option value="house_master">House Masters & Mistresses Only</option>
                        <option value="senior_houseparent">Senior Houseparents Only</option>
                        <option value="nurse">Clinic / Nurses Only</option>
                        <option value="security">Security Staff Only</option>
                    </select>
                </div>

                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="isUrgent" name="isUrgent" value="1" <?= !empty($_POST['isUrgent']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold text-danger" for="isUrgent">
                            Mark as High Priority / Urgent
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="message">Announcement Message Body <span class="text-danger">*</span></label>
                    <textarea class="form-control <?= !empty($errors['message']) ? 'is-invalid' : '' ?>" id="message" name="message" rows="6" placeholder="Enter complete announcement text..." required><?= e($_POST['message'] ?? '') ?></textarea>
                    <?php if (!empty($errors['message'])): ?>
                        <div class="invalid-feedback"><?= e($errors['message']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="sendNotification" name="sendNotification" value="1" checked>
                        <label class="form-check-label text-muted" for="sendNotification">
                            Broadcast instant push notification to target audience
                        </label>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/admin/announcements/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i> Publish Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setAdminAnnPreset(title, message, type, audience, isUrgent) {
    document.getElementById('title').value = title;
    document.getElementById('message').value = message;
    document.getElementById('type').value = type;
    document.getElementById('audience').value = audience;
    document.getElementById('isUrgent').checked = isUrgent;
}
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

