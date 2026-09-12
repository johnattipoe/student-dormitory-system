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
$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\UserService;

$user = current_user() ?? [];
$userId = current_user_id();
$userService = new UserService();
$profile = $userId ? $userService->find($userId) : null;
if (is_array($profile)) {
    $user = array_merge($user, $profile);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) sanitize($_POST['name'] ?? ''));
    $phone = trim((string) sanitize($_POST['phone'] ?? ''));
    $altPhone = trim((string) sanitize($_POST['altPhone'] ?? ''));
    $officeRoom = trim((string) sanitize($_POST['officeRoom'] ?? ''));
    $bio = trim((string) sanitize($_POST['bio'] ?? ''));

    if ($name === '') {
        $errors['name'] = 'Full name is required.';
    }

    if (empty($errors)) {
        $updateData = [
            'name' => $name,
            'phone' => $phone,
            'altPhone' => $altPhone,
            'officeRoom' => $officeRoom,
            'bio' => $bio,
            'updatedAt' => date(DATE_ATOM),
        ];

        $result = $userService->update($userId, $updateData);
        if ($result['success']) {
            $updatedSessionUser = array_merge($user, $updateData);
            session_put(AUTH_USER_SESSION, $updatedSessionUser);
            flash('success', 'Profile updated successfully.');
            redirect(url('views/nurse/profile/index.php'));
        } else {
            $errors['general'] = $result['message'] ?? 'Failed to update profile.';
        }
    }
}

$nameVal = $_POST['name'] ?? $user['name'] ?? $user['fullName'] ?? '';
$phoneVal = $_POST['phone'] ?? $user['phone'] ?? '';
$altPhoneVal = $_POST['altPhone'] ?? $user['altPhone'] ?? '';
$officeRoomVal = $_POST['officeRoom'] ?? $user['officeRoom'] ?? 'Main Campus Clinic Desk';
$bioVal = $_POST['bio'] ?? $user['bio'] ?? '';

$pageTitle = 'Edit Nurse Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/nurse/profile/index.php'), 'active' => true],
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
                <h5 class="mb-1">Edit Clinical Staff Profile</h5>
                <p class="text-muted mb-0">Update contact details and clinic station information.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/nurse/profile/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Profile
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card stat-card p-4" style="max-width: 750px;">
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="name">Full Name <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($nameVal) ?>" required>
                    <?php if (!empty($errors['name'])): ?>
                        <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="email">Email Address</label>
                    <input class="form-control bg-light" id="email" value="<?= e($user['email'] ?? '—') ?>" readonly disabled>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="phone">Primary Phone</label>
                    <input class="form-control" id="phone" name="phone" value="<?= e($phoneVal) ?>" placeholder="e.g. +233 24 000 0000">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="altPhone">Emergency Hotline</label>
                    <input class="form-control" id="altPhone" name="altPhone" value="<?= e($altPhoneVal) ?>" placeholder="e.g. +233 20 000 0000">
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-bold" for="officeRoom">Infirmary Desk / Clinic Station</label>
                    <input class="form-control" id="officeRoom" name="officeRoom" value="<?= e($officeRoomVal) ?>" placeholder="e.g. Main Clinic, Bed Bay 1">
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="bio">Bio & Scope</label>
                    <textarea class="form-control" id="bio" name="bio" rows="3"><?= e($bioVal) ?></textarea>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/nurse/profile/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

