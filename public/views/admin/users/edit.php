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

$pageTitle = 'Edit User';
$id = sanitize($_GET['id'] ?? '');
$userService = new UserService();
$houses = HouseService::all();
$roleOptions = ['admin','house_master','house_mistress','houseparent','security','nurse','student'];
// Custom roles managed via Firestore (roles collection)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId = sanitize($_POST['id'] ?? '');
    $role = sanitize($_POST['role'] ?? '');
    $houseId = in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true) ? sanitize($_POST['houseId'] ?? '') : null;
    $data = [
        'name' => sanitize($_POST['name'] ?? ''),
        'role' => $role,
        'houseId' => $houseId,
        'status' => sanitize($_POST['status'] ?? 'active'),
    ];

    $errors = validate_required($data, ['name', 'role']);

    if (!$postId) {
        flash('error', 'User ID is required.');
        redirect(base_url('index.php?route=/views/admin/users/index.php'));
    }

    if (!empty($errors)) {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = $data;
        flash('error', 'Please fix the highlighted fields.');
        redirect(base_url('index.php?route=/views/admin/users/edit.php?id=' . urlencode($id)));
    }

    $result = $userService->update($postId, $data);

    if ($result['success'] && $houseId && in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true)) {
        $house = HouseService::find($houseId);
        if ($house) {
            $houseData = [
                'name' => $house['name'] ?? '',
                'gender' => $house['gender'] ?? '',
                'capacity' => $house['capacity'] ?? 0,
                'houseMasterId' => $role === ROLE_HOUSE_MASTER ? $postId : ($house['houseMasterId'] ?? null),
                'houseMistressId' => $role === ROLE_HOUSE_MISTRESS ? $postId : ($house['houseMistressId'] ?? null),
                'location' => $house['location'] ?? '',
                'status' => $house['status'] ?? 'active',
            ];
            HouseService::update($houseId, $houseData);
        }
    }

    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/admin/users/index.php'));
}

$errors = $_SESSION['_errors'] ?? [];
unset($_SESSION['_errors']);
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);

$selectedUser = $id !== '' ? $userService->find($id) : null;
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Users', 'href' => url('views/admin/users/index.php')],
    ['icon' => 'bi-pencil', 'label' => 'Edit User', 'href' => url('views/admin/users/edit.php?id=' . urlencode($id)), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:720px;">
            <h5 class="mb-3">Edit User</h5>
            <?php if (!$selectedUser): ?>
                <div class="alert alert-warning">User not found.</div>
            <?php else: ?>
                <form method="POST" action="<?= url('views/admin/users/edit.php?id=' . urlencode($id)) ?>">
                    <input type="hidden" name="id" value="<?= e($selectedUser['id'] ?? $selectedUser['uid'] ?? '') ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input name="name" class="form-control" value="<?= e($old['name'] ?? $selectedUser['name'] ?? '') ?>" required>
                            <?php if (!empty($errors['name'])): ?><div class="text-danger small"><?= e($errors['name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= e($selectedUser['email'] ?? '') ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" id="user-role-select">
                                <?php foreach ($roleOptions as $role): ?>
                                    <option value="<?= e($role) ?>" <?= (($old['role'] ?? $selectedUser['role'] ?? '') === $role) ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $role)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['role'])): ?><div class="text-danger small"><?= e($errors['role']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['active','inactive'] as $status): ?>
                                    <option value="<?= e($status) ?>" <?= (($old['status'] ?? $selectedUser['status'] ?? 'active') === $status) ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12" id="house-assignment-field" style="display: none;">
                            <label class="form-label">Assign to House</label>
                            <select name="houseId" class="form-select">
                                <option value="">Select house</option>
                                <?php foreach ($houses as $house): ?>
                                    <option value="<?= e((string) ($house['id'] ?? '')) ?>" <?= (($old['houseId'] ?? $selectedUser['houseId'] ?? '') === ($house['id'] ?? '')) ? 'selected' : '' ?>><?= e($house['name'] ?? 'House') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Only House Master and House Mistress roles can be assigned to a house. Senior Houseparent is excluded.</div>
                        </div>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                        <button class="btn btn-primary">Update User</button>
                        <a href="<?= url('views/admin/users/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
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