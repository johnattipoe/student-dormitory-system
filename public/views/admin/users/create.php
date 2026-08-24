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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\HouseService;
use App\Services\UserService;

$pageTitle = 'Add User';
$userService = new UserService();
$houses = HouseService::all();
$roleOptions = ['admin','house_master','house_mistress','houseparent','security','nurse','student'];
// Custom roles managed via Firestore (roles collection)

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
        redirect(base_url('index.php?route=/views/admin/users/create.php'));
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
            redirect(base_url('index.php?route=/views/admin/users/create.php'));
        }
    }

    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=' . ($result['success'] ? '/views/admin/users/index.php' : '/views/admin/users/create.php')));
}

$errors = $_SESSION['_errors'] ?? [];
unset($_SESSION['_errors']);
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);
$tempCreated = $_SESSION['_temp_created'] ?? null;
if ($tempCreated) { unset($_SESSION['_temp_created']); }

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Users', 'href' => url('views/admin/users/index.php')],
    ['icon' => 'bi-plus-lg', 'label' => 'Add User', 'href' => url('views/admin/users/create.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:720px;">
            <h5 class="mb-3">Create User</h5>
            <?php if (!empty($tempCreated)): ?>
                <div class="alert alert-success">
                    <strong>User created:</strong>
                    <?= e($tempCreated['message']) ?>
                    <?php if (!empty($tempCreated['uid'])): ?>
                        <div class="mt-2 small">UID: <code><?= e($tempCreated['uid']) ?></code></div>
                    <?php endif; ?>
                    <?php if (!empty($tempCreated['password'])): ?>
                        <div class="mt-2">Temporary password: <code><?= e($tempCreated['password']) ?></code></div>
                        <div class="mt-1 small">Please share this password with the user so they can sign in and change it.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="<?= url('views/admin/users/create.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input name="name" class="form-control" value="<?= e($old['name'] ?? '') ?>" required>
                        <?php if (!empty($errors['name'])): ?><div class="text-danger small"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($old['email'] ?? '') ?>" required>
                        <?php if (!empty($errors['email'])): ?><div class="text-danger small"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" id="user-role-select">
                            <?php foreach ($roleOptions as $role): ?>
                                <option value="<?= e($role) ?>" <?= ($old['role'] ?? '') === $role ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $role)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['role'])): ?><div class="text-danger small"><?= e($errors['role']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['active','inactive'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= ($old['status'] ?? 'active') === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-12" id="house-assignment-field" hidden>
                        <label class="form-label">Assign to House</label>
                        <select name="houseId" class="form-select" id="house-assignment-select">
                            <option value="">Select house</option>
                            <?php foreach ($houses as $house): ?>
                                <option value="<?= e((string) ($house['id'] ?? '')) ?>" <?= (($old['houseId'] ?? '') === ($house['id'] ?? '')) ? 'selected' : '' ?>><?= e($house['name'] ?? 'House') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['houseId'])): ?><div class="text-danger small"><?= e($errors['houseId']) ?></div><?php endif; ?>
                        <div class="form-text">Only House Master and House Mistress roles can be assigned to a house. Senior Houseparent is excluded.</div>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">Save User</button>
                    <a href="<?= url('views/admin/users/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
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

<?php require APP_ROOT . '/app/views/components/footer.php'; ?>