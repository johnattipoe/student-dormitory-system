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
use App\Services\FirebaseService;
use App\Services\UserService;

$pageTitle = 'Add User';
$userService = new UserService();
$houses = HouseService::all();
$roleOptions = ['admin','house_master','house_mistress','senior-houseparent','security','nurse','student'];
try {
    $customRoles = FirebaseService::getInstance()->getCollection(COL_ROLES, [], 200);
    foreach ($customRoles as $customRole) {
        $roleKey = trim((string) ($customRole['key'] ?? ''));
        if ($roleKey !== '') {
            $roleOptions[] = $roleKey;
        }
    }
    $roleOptions = array_values(array_unique($roleOptions));
} catch (Throwable $e) {
    // Built-in roles remain available when Firestore is unavailable.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = sanitize($_POST['role'] ?? '');
    $houseId = in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true) ? sanitize($_POST['houseId'] ?? '') : null;
    $data = [
        'name' => sanitize($_POST['name'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'role' => $role,
        'houseId' => $houseId,
        'status' => sanitize($_POST['status'] ?? 'active'),
    ];

    $errors = validate_required($data, ['name', 'email', 'role']);

    if (in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true) && $houseId === '') {
        $errors['houseId'] = 'A house is required for this role.';
    }

    if (!empty($data['email']) && !validate_email($data['email'])) {
        $errors['email'] = 'Email is invalid.';
    }

    if (!empty($errors)) {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = $data;
        flash('error', 'Please fix the highlighted fields.');
        redirect(base_url('index.php?route=/views/admin/users/create/create.php'));
    }

    $result = $userService->create($data);

    if ($result['success'] && $houseId && in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true)) {
        $house = HouseService::find($houseId);
        if ($house) {
            $houseData = [
                'name' => $house['name'] ?? '',
                'gender' => $house['gender'] ?? '',
                'capacity' => $house['capacity'] ?? 0,
                'houseMasterId' => $role === ROLE_HOUSE_MASTER ? ($result['uid'] ?? $result['id'] ?? '') : ($house['houseMasterId'] ?? null),
                'houseMistressId' => $role === ROLE_HOUSE_MISTRESS ? ($result['uid'] ?? $result['id'] ?? '') : ($house['houseMistressId'] ?? null),
                'location' => $house['location'] ?? '',
                'status' => $house['status'] ?? 'active',
            ];
            HouseService::update($houseId, $houseData);
        }
    }

    if ($result['success']) {
        // store generated temp password in session to show to admin on redirect
        if (!empty($result['temp_password'])) {
            $_SESSION['_temp_created'] = [
                'uid' => $result['uid'] ?? null,
                'password' => $result['temp_password'],
                'message' => $result['message'] ?? 'User created.'
            ];
            flash('success', $result['message']);
            redirect(base_url('index.php?route=/views/admin/users/create/create.php'));
        }
    }

    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=' . ($result['success'] ? '/views/admin/users/index/index.php' : '/views/admin/users/create/create.php')));
}

$errors = $_SESSION['_errors'] ?? [];
unset($_SESSION['_errors']);
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);
$tempCreated = $_SESSION['_temp_created'] ?? null;
if ($tempCreated) { unset($_SESSION['_temp_created']); }

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Users', 'href' => url('views/admin/users/index/index.php')],
    ['icon' => 'bi-plus-lg', 'label' => 'Add User', 'href' => url('views/admin/users/create/create.php'), 'active' => true],
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
                    <i class="bi bi-person-plus-fill text-primary me-2"></i>Add New User
                </h4>
                <p class="text-muted mb-0">Create a new user account and assign role permissions</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/admin/users/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Users
                </a>
            </div>
        </div>

        <!-- Temp Password Display -->
        <?php if (!empty($tempCreated)): ?>
            <div class="alert alert-success border-start border-4 border-success shadow-sm">
                <div class="d-flex align-items-start">
                    <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                    <div>
                        <strong>User created successfully!</strong>
                        <p class="mb-1 mt-1"><?= e($tempCreated['message']) ?></p>
                        <?php if (!empty($tempCreated['uid'])): ?>
                            <div class="small">UID: <code><?= e($tempCreated['uid']) ?></code></div>
                        <?php endif; ?>
                        <?php if (!empty($tempCreated['password'])): ?>
                            <div class="mt-2 p-2 bg-white rounded border">
                                <i class="bi bi-key me-1 text-warning"></i>Temporary password: <code class="fs-6"><?= e($tempCreated['password']) ?></code>
                            </div>
                            <div class="mt-1 small text-muted"><i class="bi bi-info-circle me-1"></i>Please share this password with the user so they can sign in and change it.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Create Form Card -->
        <div class="card stat-card shadow-sm border-0" style="max-width:800px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-vcard me-2"></i>User Registration Form</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/admin/users/create/create.php') ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="bi bi-person me-1 text-muted"></i>Full Name</label>
                            <input name="name" class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($old['name'] ?? '') ?>" required placeholder="Enter full name">
                            <?php if (!empty($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="bi bi-envelope me-1 text-muted"></i>Email Address</label>
                            <input type="email" name="email" class="form-control <?= !empty($errors['email']) ? 'is-invalid' : '' ?>" value="<?= e($old['email'] ?? '') ?>" required placeholder="user@example.com">
                            <?php if (!empty($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="bi bi-shield-lock me-1 text-muted"></i>Role</label>
                            <select name="role" class="form-select" id="user-role-select">
                                <?php foreach ($roleOptions as $role): ?>
                                    <option value="<?= e($role) ?>" <?= ($old['role'] ?? '') === $role ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $role)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['role'])): ?><div class="text-danger small mt-1"><?= e($errors['role']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><i class="bi bi-toggle-on me-1 text-muted"></i>Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['active','inactive'] as $status): ?>
                                    <option value="<?= e($status) ?>" <?= ($old['status'] ?? 'active') === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12" id="house-assignment-field" hidden>
                            <label class="form-label fw-semibold"><i class="bi bi-building me-1 text-muted"></i>Assign to House</label>
                            <select name="houseId" class="form-select" id="house-assignment-select">
                                <option value="">Select house</option>
                                <?php foreach ($houses as $house): ?>
                                    <option value="<?= e((string) ($house['id'] ?? '')) ?>" <?= (($old['houseId'] ?? '') === ($house['id'] ?? '')) ? 'selected' : '' ?>><?= e($house['name'] ?? 'House') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['houseId'])): ?><div class="text-danger small mt-1"><?= e($errors['houseId']) ?></div><?php endif; ?>
                            <div class="form-text"><i class="bi bi-info-circle me-1"></i>Only House Master and House Mistress roles can be assigned to a house. Senior Houseparent is excluded.</div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save User</button>
                        <a href="<?= url('views/admin/users/index/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('user-role-select');
    const houseField = document.getElementById('house-assignment-field');
    const houseSelect = document.getElementById('house-assignment-select');
    if (!roleSelect || !houseField || !houseSelect) return;

    function updateHouseField() {
        const role = String(roleSelect.value || '').toLowerCase();
        const isHouseDuty = ['house_master', 'house_mistress'].includes(role);
        houseField.hidden = !isHouseDuty;
        houseField.style.display = isHouseDuty ? 'block' : 'none';
        houseSelect.required = isHouseDuty;
        if (!isHouseDuty) {
            houseSelect.value = '';
            if (window.jQuery && jQuery.fn.select2) {
                jQuery(houseSelect).trigger('change');
            }
        }
    }

    roleSelect.addEventListener('change', updateHouseField);
    roleSelect.addEventListener('input', updateHouseField);
    if (window.jQuery && jQuery.fn.select2) {
        jQuery(roleSelect).on('select2:select change', updateHouseField);
    }
    updateHouseField();
    window.setTimeout(updateHouseField, 0);
});
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>