<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\HouseService;
use App\Services\UserService;

$pageTitle = 'Add House';
$userService = new UserService();
$users = $userService->all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => sanitize($_POST['name'] ?? ''),
        'gender' => sanitize($_POST['gender'] ?? ''),
        'capacity' => (int) ($_POST['capacity'] ?? 1),
        'houseMasterId' => sanitize($_POST['houseMasterId'] ?? ''),
        'houseMistressId' => sanitize($_POST['houseMistressId'] ?? ''),
        'location' => sanitize($_POST['location'] ?? ''),
        'status' => sanitize($_POST['status'] ?? 'active'),
    ];

    $errors = validate_required($data, ['name']);

    if (!empty($errors)) {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = $data;
        flash('error', 'Please fix the highlighted fields.');
        redirect(base_url('index.php?route=/views/admin/houses/create.php'));
    }

    HouseService::create($data);

    flash('success', 'House created successfully.');
    redirect(base_url('index.php?route=/views/admin/houses/index.php'));
}

$errors = $_SESSION['_errors'] ?? [];
unset($_SESSION['_errors']);
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-building', 'label' => 'Houses', 'href' => url('views/admin/houses/index.php')],
    ['icon' => 'bi-plus-lg', 'label' => 'Add House', 'href' => url('views/admin/houses/create.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:720px;">
            <h5 class="mb-3">Create House</h5>
            <form method="POST" action="<?= url('views/admin/houses/create.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input name="name" class="form-control" value="<?= e($old['name'] ?? '') ?>" required>
                        <?php if (!empty($errors['name'])): ?><div class="text-danger small"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <?php foreach (['male', 'female', 'coed'] as $gender): ?>
                                <option value="<?= e($gender) ?>" <?= ($old['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= ucfirst($gender) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Capacity</label>
                        <input type="number" name="capacity" class="form-control" min="1" value="<?= e($old['capacity'] ?? 1) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input name="location" class="form-control" value="<?= e($old['location'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">House Master</label>
                        <select name="houseMasterId" class="form-select">
                            <option value="">Not assigned</option>
                            <?php foreach ($users as $staff): ?>
                                <?php if (($staff['role'] ?? '') === ROLE_HOUSE_MASTER): ?>
                                    <option value="<?= e((string) ($staff['id'] ?? $staff['uid'] ?? '')) ?>" <?= (($old['houseMasterId'] ?? '') === ((string) ($staff['id'] ?? $staff['uid'] ?? ''))) ? 'selected' : '' ?>><?= e($staff['name'] ?? '') ?> (<?= e($staff['email'] ?? '') ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">House Mistress</label>
                        <select name="houseMistressId" class="form-select">
                            <option value="">Not assigned</option>
                            <?php foreach ($users as $staff): ?>
                                <?php if (($staff['role'] ?? '') === ROLE_HOUSE_MISTRESS): ?>
                                    <option value="<?= e((string) ($staff['id'] ?? $staff['uid'] ?? '')) ?>" <?= (($old['houseMistressId'] ?? '') === ((string) ($staff['id'] ?? $staff['uid'] ?? ''))) ? 'selected' : '' ?>><?= e($staff['name'] ?? '') ?> (<?= e($staff['email'] ?? '') ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['active', 'inactive'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= ($old['status'] ?? '') === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">Save House</button>
                    <a href="<?= url('views/admin/houses/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>