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

use App\Services\UserService;
use App\Services\HouseService;

$user = current_user() ?? [];
$userId = current_user_id();
$userService = new UserService();
$profile = $userId ? $userService->find($userId) : null;
if (is_array($profile)) {
    $user = array_merge($user, $profile);
}

$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) sanitize($_POST['name'] ?? ''));
    $phone = trim((string) sanitize($_POST['phone'] ?? ''));
    $altPhone = trim((string) sanitize($_POST['altPhone'] ?? ''));
    $officeRoom = trim((string) sanitize($_POST['officeRoom'] ?? ''));
    $officeHours = trim((string) sanitize($_POST['officeHours'] ?? ''));
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
            'officeHours' => $officeHours,
            'bio' => $bio,
            'updatedAt' => date(DATE_ATOM),
        ];

        $result = $userService->update($userId, $updateData);
        if ($result['success']) {
            $updatedSessionUser = array_merge($user, $updateData);
            session_put(AUTH_USER_SESSION, $updatedSessionUser);
            flash('success', 'Profile updated successfully.');
            redirect(url('views/senior-houseparent/profile/index.php'));
        } else {
            $errors['general'] = 'Failed to update profile: ' . ($result['message'] ?? 'Unknown error');
        }
    }
}

$pageTitle = 'Edit Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/senior-houseparent/profile/index.php'), 'active' => true],
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
                <h5 class="mb-1">Edit Senior Houseparent Profile</h5>
                <p class="text-muted mb-0">Update your contact details, office location, and availability.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/profile/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Profile
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card stat-card p-4" style="max-width: 850px;">
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="name">Full Name <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($_POST['name'] ?? $user['name'] ?? '') ?>" required>
                    <?php if (!empty($errors['name'])): ?>
                        <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="email">Staff Email (System Login)</label>
                    <input class="form-control bg-light" id="email" value="<?= e($user['email'] ?? 'Not set') ?>" disabled readonly>
                    <small class="text-muted">Email address cannot be changed directly.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="phone">Primary Phone Number</label>
                    <input class="form-control" id="phone" name="phone" value="<?= e($_POST['phone'] ?? $user['phone'] ?? '') ?>" placeholder="e.g. +233 24 000 0000">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="altPhone">Alternative / Emergency Phone</label>
                    <input class="form-control" id="altPhone" name="altPhone" value="<?= e($_POST['altPhone'] ?? $user['altPhone'] ?? $user['emergencyPhone'] ?? '') ?>" placeholder="e.g. +233 20 000 0000">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="officeRoom">Office Location / Room</label>
                    <input class="form-control" id="officeRoom" name="officeRoom" value="<?= e($_POST['officeRoom'] ?? $user['officeRoom'] ?? 'Senior Houseparent Office') ?>" placeholder="e.g. Block A, Office 102">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="officeHours">Available Office Hours</label>
                    <input class="form-control" id="officeHours" name="officeHours" value="<?= e($_POST['officeHours'] ?? $user['officeHours'] ?? 'Monday – Friday (8:00 AM – 5:00 PM)') ?>" placeholder="e.g. Mon-Fri 8am-5pm">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="house">Assigned Dormitory House</label>
                    <input class="form-control bg-light" id="house" value="<?= e($houseName) ?>" disabled readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="role">Assigned System Role</label>
                    <input class="form-control bg-light" id="role" value="Senior Houseparent" disabled readonly>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="bio">Bio & Operational Notes</label>
                    <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Brief notes about your role or house supervision duties..."><?= e($_POST['bio'] ?? $user['bio'] ?? '') ?></textarea>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/senior-houseparent/profile/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

