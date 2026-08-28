<?php
// Ensure bootstrap is loaded (safe at any view nesting depth)
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) { require $dir . '/bootstrap.php'; break; }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$user = current_user() ?? [];
$userId = current_user_id();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) sanitize($_POST['name'] ?? ''));
    $roleTitle = trim((string) sanitize($_POST['roleTitle'] ?? ''));
    $phone = trim((string) sanitize($_POST['phone'] ?? ''));
    $altPhone = trim((string) sanitize($_POST['altPhone'] ?? ''));
    $email = trim((string) sanitize($_POST['email'] ?? ''));
    $location = trim((string) sanitize($_POST['location'] ?? ''));
    $priority = trim((string) sanitize($_POST['priority'] ?? 'normal'));

    if ($name === '') $errors['name'] = 'Contact or organization name is required.';
    if ($phone === '') $errors['phone'] = 'Emergency phone number is required.';
    if (!in_array($priority, ['critical', 'high', 'normal'], true)) $priority = 'normal';

    if (empty($errors)) {
        try {
            $data = [
                'name' => $name,
                'roleTitle' => $roleTitle,
                'phone' => $phone,
                'altPhone' => $altPhone,
                'email' => $email,
                'location' => $location,
                'priority' => $priority,
                'status' => 'active',
                'addedBy' => $userId,
                'createdAt' => date(DATE_ATOM),
            ];

            FirebaseService::getInstance()->addDocument('emergency_contacts', array_filter($data, fn($v) => $v !== null && $v !== ''));

            flash('success', 'Emergency contact added to directory.');
            redirect(url('views/senior-houseparent/emergency-alerts/index.php'));
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to add emergency contact: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Add Emergency Contact';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-telephone-inbound', 'label' => 'Emergency Alerts', 'href' => url('views/senior-houseparent/emergency-alerts/index.php'), 'active' => true],
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
                <h5 class="mb-1">Add Emergency Contact / Responder</h5>
                <p class="text-muted mb-0">Add an emergency service, local authority, or medical responder to the quick-dial directory.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/emergency-alerts/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Directory
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card stat-card p-4" style="max-width: 800px;">
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="name">Organization / Service Name <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. Police Dispatch, Red Cross Desk" required>
                    <?php if (!empty($errors['name'])): ?>
                        <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="roleTitle">Department / Role Title</label>
                    <input class="form-control" id="roleTitle" name="roleTitle" value="<?= e($_POST['roleTitle'] ?? '') ?>" placeholder="e.g. Emergency Triage, Gate Security">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="phone">Emergency Hotline / Phone <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['phone']) ? 'is-invalid' : '' ?>" id="phone" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="e.g. +233 24 000 0000 or 191" required>
                    <?php if (!empty($errors['phone'])): ?>
                        <div class="invalid-feedback"><?= e($errors['phone']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="altPhone">Alternative Phone</label>
                    <input class="form-control" id="altPhone" name="altPhone" value="<?= e($_POST['altPhone'] ?? '') ?>" placeholder="e.g. +233 20 000 0000">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="email">Emergency Email</label>
                    <input class="form-control" type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="e.g. emergency@domain.com">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="priority">Priority Classification</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="critical">Critical (Immediate First Responder)</option>
                        <option value="high">High Priority</option>
                        <option value="normal" selected>Normal Support Service</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="location">Location / Station Address</label>
                    <input class="form-control" id="location" name="location" value="<?= e($_POST['location'] ?? '') ?>" placeholder="e.g. Building C, Room 101 or Off-Campus District Command">
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/senior-houseparent/emergency-alerts/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Save Emergency Contact
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

