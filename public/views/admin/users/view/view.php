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
use App\Services\HouseService;
use App\Services\UserService;

$pageTitle = 'View User';
$id = sanitize($_GET['id'] ?? '');
$userService = new UserService();
$selectedUser = $id !== '' ? $userService->find($id) : null;
$editHouses = $selectedUser ? HouseService::all() : [];
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Users', 'href' => url('views/admin/users/index/index.php')],
    ['icon' => 'bi-eye', 'label' => 'View User', 'href' => url('views/admin/users/view/view.php?id=' . urlencode($id)), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-person-badge text-primary me-2"></i>
                    <?= $selectedUser ? e($selectedUser['name'] ?? 'User Profile') : 'User Not Found' ?>
                </h4>
                <p class="text-muted mb-0">View user account details and role assignment</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/admin/users/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Users
                </a>
                <?php if ($selectedUser): ?>
                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#adminEditUserModal">
                    <i class="bi bi-pencil-square me-1"></i>Edit
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#adminDeleteUserModal">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$selectedUser): ?>
            <div class="alert alert-warning d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> User not found.
            </div>
        <?php else: ?>
            <?php
                $role = $selectedUser['role'] ?? 'unknown';
                $roleBadge = match(strtolower($role)) {
                    'admin' => 'bg-danger',
                    'house_master', 'house_mistress' => 'bg-success',
                    'senior-houseparent' => 'bg-info',
                    'security' => 'bg-dark',
                    'nurse' => 'bg-warning text-dark',
                    'student' => 'bg-primary',
                    default => 'bg-secondary',
                };
                $statusColor = strtolower($selectedUser['status'] ?? '') === 'active' ? 'success' : 'secondary';
            ?>
            <div class="row g-4">
                <!-- User Details Card -->
                <div class="col-lg-8">
                    <div class="card stat-card shadow-sm border-0">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Account Details</h6>
                            <span class="badge <?= $roleBadge ?>"><?= ucfirst(str_replace('_', ' ', e($role))) ?></span>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between py-3">
                                    <span class="text-muted fw-semibold"><i class="bi bi-person me-2"></i>Full Name</span>
                                    <span class="fw-medium"><?= e($selectedUser['name'] ?? '-') ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-3">
                                    <span class="text-muted fw-semibold"><i class="bi bi-envelope me-2"></i>Email</span>
                                    <span class="fw-medium"><?= e($selectedUser['email'] ?? '-') ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-3">
                                    <span class="text-muted fw-semibold"><i class="bi bi-shield-lock me-2"></i>Role</span>
                                    <span class="badge <?= $roleBadge ?> px-3 py-2"><?= ucfirst(str_replace('_', ' ', e($role))) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-3">
                                    <span class="text-muted fw-semibold"><i class="bi bi-circle-fill me-2 text-<?= $statusColor ?>" style="font-size:0.5rem;vertical-align:middle;"></i>Status</span>
                                    <span class="badge bg-<?= $statusColor ?> px-3 py-2"><?= ucfirst(e($selectedUser['status'] ?? '-')) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-3">
                                    <span class="text-muted fw-semibold"><i class="bi bi-building me-2"></i>House</span>
                                    <span class="fw-medium"><?= e($selectedUser['houseId'] ?? '-') ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Sidebar -->
                <div class="col-lg-4">
                    <div class="card stat-card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="button" data-bs-toggle="modal" data-bs-target="#adminEditUserModal" class="btn btn-outline-warning btn-sm text-start">
                                    <i class="bi bi-pencil-square me-2"></i>Edit Account
                                </button>
                                <button type="button" data-bs-toggle="modal" data-bs-target="#adminDeleteUserModal" class="btn btn-outline-danger btn-sm text-start">
                                    <i class="bi bi-trash me-2"></i>Delete Account
                                </button>
                                <a href="<?= url('views/admin/users/index/index.php') ?>" class="btn btn-outline-secondary btn-sm text-start">
                                    <i class="bi bi-people me-2"></i>All Users
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card stat-card shadow-sm border-0 mt-3 p-3 border-start border-4 border-info">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold">User ID</span>
                                <p class="fw-bold my-1 text-info small font-monospace"><?= e($selectedUser['id'] ?? $selectedUser['uid'] ?? '-') ?></p>
                            </div>
                            <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info">
                                <i class="bi bi-fingerprint fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php if ($selectedUser): ?>
<div class="modal fade" id="adminEditUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form method="POST" action="<?= url('views/admin/users/edit/edit.php?id=' . urlencode($id)) ?>">
            <input type="hidden" name="id" value="<?= e($id) ?>">
            <div class="modal-header"><h5 class="modal-title">Edit User Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Full Name</label><input name="name" class="form-control" value="<?= e($selectedUser['name'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" value="<?= e($selectedUser['email'] ?? '') ?>" disabled></div>
                    <div class="col-md-6"><label class="form-label">Role</label><select name="role" class="form-select"><?php foreach (['admin', 'house_master', 'house_mistress', 'senior-houseparent', 'security', 'nurse', 'student'] as $roleOption): ?><option value="<?= e($roleOption) ?>" <?= ($selectedUser['role'] ?? '') === $roleOption ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $roleOption))) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" <?= ($selectedUser['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($selectedUser['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
                    <div class="col-12"><label class="form-label">House</label><select name="houseId" class="form-select"><option value="">Select house</option><?php foreach ($editHouses as $house): ?><option value="<?= e((string) ($house['id'] ?? '')) ?>" <?= (($selectedUser['houseId'] ?? '') === ($house['id'] ?? '')) ? 'selected' : '' ?>><?= e($house['name'] ?? 'House') ?></option><?php endforeach; ?></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
        </form>
    </div></div>
</div>
<div class="modal fade" id="adminDeleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title text-danger">Delete User</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">Delete <strong><?= e($selectedUser['name'] ?? 'this user') ?></strong>? This action cannot be undone.</div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/admin/users/delete/delete.php?id=' . urlencode($id)) ?>"><input type="hidden" name="id" value="<?= e($id) ?>"><button type="submit" class="btn btn-danger">Delete</button></form></div>
    </div></div>
</div>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>