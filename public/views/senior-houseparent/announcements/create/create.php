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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\NotificationService;

$errors = [];
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) sanitize($_POST['title'] ?? ''));
    $message = trim((string) sanitize($_POST['message'] ?? ''));
    $audience = sanitize($_POST['audience'] ?? 'all');
    $type = sanitize($_POST['type'] ?? 'info');
    $isUrgent = !empty($_POST['isUrgent']);
    $sendNotification = !empty($_POST['sendNotification']);
    $old = compact('title', 'message', 'audience', 'type', 'isUrgent', 'sendNotification');

    if ($title === '') $errors['title'] = 'Announcement title is required.';
    if ($message === '') $errors['message'] = 'Announcement message content is required.';
    if (!in_array($audience, ['all', 'house_master', 'student'], true)) $audience = 'all';
    if (!in_array($type, ['info', 'success', 'warning', 'danger'], true)) $type = 'info';

    if (empty($errors)) {
        try {
            $firebase = FirebaseService::getInstance();
            $targetRole = match($audience) {
                'house_master' => ROLE_HOUSE_MASTER,
                'student' => ROLE_STUDENT,
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
                'createdBy' => current_user_id(),
                'createdByName' => current_user()['name'] ?? 'Senior Houseparent',
                'publishedAt' => date(DATE_ATOM),
                'createdAt' => date(DATE_ATOM),
            ]);

            if ($sendNotification) {
                $notification = [
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'isUrgent' => $isUrgent,
                    'link' => url('views/announcements/view.php?id=' . urlencode($docId)),
                ];

                if ($audience === 'house_master') {
                    (new NotificationService())->broadcastToRole(ROLE_HOUSE_MASTER, $notification);
                    (new NotificationService())->broadcastToRole(ROLE_HOUSE_MISTRESS, $notification);
                } elseif ($audience === 'student') {
                    (new NotificationService())->broadcastToRole(ROLE_STUDENT, $notification);
                } else {
                    (new NotificationService())->broadcastToAll($notification);
                }
            }

            flash('success', 'Announcement published successfully.');
            redirect(url('views/senior-houseparent/announcements/index.php'));
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to publish announcement: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Create Announcement';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => url('views/senior-houseparent/announcements/index.php'), 'active' => true],
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
                <h5 class="mb-1">Create House Announcement</h5>
                <p class="text-muted mb-0">Publish an official bulletin or dispatch urgent alerts to house staff and students.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/announcements/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Announcements
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card stat-card p-4 mb-4">
            <label class="form-label text-muted small text-uppercase fw-bold">Quick Templates</label>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="applyTemplate('Dormitory Inspection Schedule', 'Please be advised that general dormitory inspections will take place this Saturday morning at 9:00 AM. All rooms must be swept, beds neatly laid, and personal belongings organized.\n\nThank you for your cooperation.', 'info', 'all', false)">Inspection Notice</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="applyTemplate('Lights Out & Curfew Advisory', 'This is a reminder that dormitory lights out strictly commences at 10:00 PM on weekdays. All students are required to be in their assigned rooms and quiet hours observed.', 'warning', 'all', false)">Curfew Notice</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="applyTemplate('Weekend Exeat Protocols', 'All students requesting weekend exeat must submit their digital requests before Thursday at 5:00 PM. No verbal or late submissions will be processed.', 'info', 'student', false)">Exeat Guidelines</button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="applyTemplate('URGENT: Emergency Safety Advisory', 'Attention all dormitory residents and staff: Please observe all fire safety protocols and keep hallways clear of luggage or obstruction at all times.', 'danger', 'all', true)">Emergency Advisory</button>
            </div>

            <form method="POST" class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-bold" for="title">Announcement Title <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['title']) ? 'is-invalid' : '' ?>" id="title" name="title" value="<?= e($old['title'] ?? '') ?>" placeholder="e.g. General Dormitory Inspection" required>
                    <?php if (!empty($errors['title'])): ?>
                        <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold" for="type">Category / Priority</label>
                    <select class="form-select" id="type" name="type">
                        <option value="info" <?= ($old['type'] ?? '') === 'info' ? 'selected' : '' ?>>General Information (Blue)</option>
                        <option value="warning" <?= ($old['type'] ?? '') === 'warning' ? 'selected' : '' ?>>Warning / Inspection (Yellow)</option>
                        <option value="danger" <?= ($old['type'] ?? '') === 'danger' ? 'selected' : '' ?>>Urgent / Emergency (Red)</option>
                        <option value="success" <?= ($old['type'] ?? '') === 'success' ? 'selected' : '' ?>>Commendation / Update (Green)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="audience">Target Audience</label>
                    <select class="form-select" id="audience" name="audience">
                        <option value="all" <?= ($old['audience'] ?? '') === 'all' ? 'selected' : '' ?>>All Dormitory (Staff & Students)</option>
                        <option value="house_master" <?= ($old['audience'] ?? '') === 'house_master' ? 'selected' : '' ?>>House Masters & Mistresses Only</option>
                        <option value="student" <?= ($old['audience'] ?? '') === 'student' ? 'selected' : '' ?>>Dormitory Students Only</option>
                    </select>
                </div>

                <div class="col-md-6 d-flex align-items-center">
                    <div class="mt-4 pt-1">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="isUrgent" name="isUrgent" value="1" <?= !empty($old['isUrgent']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold text-danger" for="isUrgent">Mark as Urgent Bulletin</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="sendNotification" name="sendNotification" value="1" <?= !isset($old['sendNotification']) || !empty($old['sendNotification']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="sendNotification">Send Instant System Notification to Target Users</label>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="message">Message Content <span class="text-danger">*</span></label>
                    <textarea class="form-control <?= !empty($errors['message']) ? 'is-invalid' : '' ?>" id="message" name="message" rows="6" placeholder="Enter the complete announcement details here..." required><?= e($old['message'] ?? '') ?></textarea>
                    <?php if (!empty($errors['message'])): ?>
                        <div class="invalid-feedback"><?= e($errors['message']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/senior-houseparent/announcements/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-megaphone me-1"></i> Publish Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function applyTemplate(title, message, type, audience, urgent) {
    document.getElementById('title').value = title;
    document.getElementById('message').value = message;
    document.getElementById('type').value = type;
    document.getElementById('audience').value = audience;
    document.getElementById('isUrgent').checked = urgent;
}
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

