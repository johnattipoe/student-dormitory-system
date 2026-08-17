<?php
require __DIR__ . '/../../../bootstrap.php';
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

                    <div class="col-md-12" id="house-assignment-field" style="display: none;">
                        <label class="form-label">Assign to House</label>
                        <select name="houseId" class="form-select">
                            <option value="">Select house</option>
                            <?php foreach ($houses as $house): ?>
                                <option value="<?= e((string) ($house['id'] ?? '')) ?>" <?= (($old['houseId'] ?? '') === ($house['id'] ?? '')) ? 'selected' : '' ?>><?= e($house['name'] ?? 'House') ?></option>
                            <?php endforeach; ?>
                        </select>
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
(function () {
    const roleSelect = document.getElementById('user-role-select');
    const houseField = document.getElementById('house-assignment-field');
    function updateHouseField() {
        const role = roleSelect.value;
        const isHouseDuty = ['house_master', 'house_mistress'].includes(role);
        houseField.style.display = isHouseDuty ? 'block' : 'none';
        if (!isHouseDuty) {
            const select = houseField.querySelector('select');
            if (select) select.value = '';
        }
    }
    roleSelect.addEventListener('change', updateHouseField);
    updateHouseField();
})();
</script>

<?php require APP_ROOT . '/app/views/components/footer.php'; ?>