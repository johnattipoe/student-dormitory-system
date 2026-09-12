<?php
if (!defined('APP_ROOT')) require dirname(__DIR__, 5) . '/public/bootstrap.php';
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$user = current_user() ?? [];
$userId = current_user_id();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile = ['name' => sanitize($_POST['name'] ?? ''), 'phone' => sanitize($_POST['phone'] ?? '')];
    try {
        FirebaseService::getInstance()->updateDocument('users', $userId, $profile);
        $user = array_merge($user, $profile);
        flash('success', 'Account details updated successfully.');
    } catch (Throwable $e) {
        flash('error', 'Failed to update account details: ' . $e->getMessage());
    }
}
$pageTitle = 'Account Overview';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/security/settings/index.php')],
    ['icon' => 'bi-person-vcard', 'label' => 'Account Overview', 'href' => url('views/security/settings/account/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div><h5 class="mb-1"><i class="bi bi-person-vcard me-2 text-primary"></i>Account Overview</h5><p class="text-muted mb-0">Review and update your Security account details.</p></div>
            <a href="<?= url('views/security/settings/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Settings</a>
        </div>
        <div class="card stat-card p-4" style="max-width:800px;">
            <form method="POST" data-ts-profile-form>
                <div class="mb-3"><label class="form-label fw-semibold" for="name">Name</label><input class="form-control" id="name" name="name" value="<?= e($user['name'] ?? $user['fullName'] ?? '') ?>" required></div>
                <div class="mb-3"><label class="form-label fw-semibold" for="phone">Phone</label><input class="form-control" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
                <div class="mb-3"><label class="form-label fw-semibold">Email</label><input class="form-control" value="<?= e($user['email'] ?? 'Not provided') ?>" readonly></div>
                <div class="mb-3"><label class="form-label fw-semibold">Role</label><input class="form-control" value="Security" readonly></div>
                <div class="text-end"><button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save Account</button></div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
