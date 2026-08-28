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

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
if ($id === '') {
    flash('error', 'Contact ID is required.');
    redirect(url('views/senior-houseparent/emergency-alerts/index.php'));
}

$firebase = FirebaseService::getInstance();
$contact = $firebase->getDocument('emergency_contacts', $id);
if (!$contact) {
    flash('error', 'Emergency contact not found.');
    redirect(url('views/senior-houseparent/emergency-alerts/index.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'deactivate') {
        $firebase->updateDocument('emergency_contacts', $id, ['status' => 'inactive']);
        flash('success', 'Emergency contact deactivated.');
        redirect(url('views/senior-houseparent/emergency-alerts/index.php'));
    }

    $name = trim((string) sanitize($_POST['name'] ?? ''));
    $roleTitle = trim((string) sanitize($_POST['roleTitle'] ?? ''));
    $phone = trim((string) sanitize($_POST['phone'] ?? ''));
    $altPhone = trim((string) sanitize($_POST['altPhone'] ?? ''));
    $email = trim((string) sanitize($_POST['email'] ?? ''));
    $location = trim((string) sanitize($_POST['location'] ?? ''));
    $priority = trim((string) sanitize($_POST['priority'] ?? 'normal'));
    $status = trim((string) sanitize($_POST['status'] ?? 'active'));

    if ($name === '') $errors['name'] = 'Contact or organization name is required.';
    if ($phone === '') $errors['phone'] = 'Emergency phone number is required.';

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
                'status' => $status,
                'updatedAt' => date(DATE_ATOM),
            ];

            $firebase->updateDocument('emergency_contacts', $id, $data);

            flash('success', 'Emergency contact details updated.');
            redirect(url('views/senior-houseparent/emergency-alerts/index.php'));
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to update contact: ' . $e->getMessage();
        }
    }
}

$nameVal = $_POST['name'] ?? $contact['name'] ?? '';
$roleTitleVal = $_POST['roleTitle'] ?? $contact['roleTitle'] ?? '';
$phoneVal = $_POST['phone'] ?? $contact['phone'] ?? '';
$altPhoneVal = $_POST['altPhone'] ?? $contact['altPhone'] ?? '';
$emailVal = $_POST['email'] ?? $contact['email'] ?? '';
$locationVal = $_POST['location'] ?? $contact['location'] ?? '';
$priorityVal = $_POST['priority'] ?? $contact['priority'] ?? 'normal';
$statusVal = $_POST['status'] ?? $contact['status'] ?? 'active';

$pageTitle = 'Edit Emergency Contact';
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
                <h5 class="mb-1">Edit Emergency Contact</h5>
                <p class="text-muted mb-0">Modify hotline numbers, department details, or priority status.</p>
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
                <input type="hidden" name="id" value="<?= e($id) ?>">

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="name">Organization / Service Name <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($nameVal) ?>" required>
                    <?php if (!empty($errors['name'])): ?>
                        <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="roleTitle">Department / Role</label>
                    <input class="form-control" id="roleTitle" name="roleTitle" value="<?= e($roleTitleVal) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="phone">Emergency Hotline <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['phone']) ? 'is-invalid' : '' ?>" id="phone" name="phone" value="<?= e($phoneVal) ?>" required>
                    <?php if (!empty($errors['phone'])): ?>
                        <div class="invalid-feedback"><?= e($errors['phone']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="altPhone">Alternative Phone</label>
                    <input class="form-control" id="altPhone" name="altPhone" value="<?= e($altPhoneVal) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="email">Emergency Email</label>
                    <input class="form-control" type="email" id="email" name="email" value="<?= e($emailVal) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold" for="priority">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="critical" <?= $priorityVal === 'critical' ? 'selected' : '' ?>>Critical</option>
                        <option value="high" <?= $priorityVal === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="normal" <?= $priorityVal === 'normal' ? 'selected' : '' ?>>Normal</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?= $statusVal === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $statusVal === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="location">Location / Station Address</label>
                    <input class="form-control" id="location" name="location" value="<?= e($locationVal) ?>">
                </div>

                <div class="col-12 d-flex justify-content-between align-items-center mt-4">
                    <button type="submit" name="action" value="deactivate" class="btn btn-outline-danger btn-sm" onclick="return confirm('Deactivate this emergency contact?')">
                        <i class="bi bi-x-circle me-1"></i> Deactivate Contact
                    </button>
                    <div class="d-flex gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/senior-houseparent/emergency-alerts/index.php') ?>">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i> Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

