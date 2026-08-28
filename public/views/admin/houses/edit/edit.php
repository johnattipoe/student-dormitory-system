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

$pageTitle = 'Edit House';
$id = $_GET['id'] ?? null;
$house = $id ? HouseService::find($id) : null;
$userService = new UserService();
$staffUsers = $userService->all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId = sanitize($_POST['id'] ?? '');
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

    if (!$postId) {
        flash('error', 'House ID is required.');
        redirect(base_url('index.php?route=/views/admin/houses/index/index.php'));
    }

    if (!empty($errors)) {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = $data;
        flash('error', 'Please fix the highlighted fields.');
        redirect(base_url('index.php?route=/views/admin/houses/edit/edit.php?id=' . urlencode($id)));
    }

    try {
        HouseService::update($postId, $data);
        flash('success', 'House updated successfully.');
    } catch (\Throwable $e) {
        flash('error', 'Unable to update house: ' . $e->getMessage());
    }

    redirect(base_url('index.php?route=/views/admin/houses/index/index.php'));
}

$errors = $_SESSION['_errors'] ?? [];
unset($_SESSION['_errors']);
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-building', 'label' => 'Houses', 'href' => url('views/admin/houses/index/index.php')],
    ['icon' => 'bi-pencil', 'label' => 'Edit House', 'href' => url('views/admin/houses/edit/edit.php?id=' . urlencode((string)$id)), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-pencil-square text-warning me-2"></i>Edit House: <?= e($house['name'] ?? 'House') ?>
                </h4>
                <p class="text-muted mb-0">Update dormitory specifications, capacities, and assigned house masters</p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($house): ?>
                    <a href="<?= url('views/admin/houses/view/view.php?id=' . urlencode((string)$id)) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-eye me-1"></i> View House
                    </a>
                <?php endif; ?>
                <a href="<?= url('views/admin/houses/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Houses
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0" style="max-width: 760px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>House Modification Form</h6>
            </div>
            <div class="card-body p-4">
                <?php if (!$house): ?>
                    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>House not found.</div>
                <?php else: ?>
                    <form method="POST" action="<?= url('views/admin/houses/edit/edit.php?id=' . urlencode((string)$id)) ?>">
                        <input type="hidden" name="id" value="<?= e($house['id'] ?? '') ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">House Name <span class="text-danger">*</span></label>
                                <input name="name" class="form-control" value="<?= e($old['name'] ?? $house['name'] ?? '') ?>" required>
                                <?php if (!empty($errors['name'])): ?><div class="text-danger small mt-1"><?= e($errors['name']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Gender Allocation</label>
                                <select name="gender" class="form-select select2">
                                    <?php foreach (['male','female','coed'] as $gender): ?>
                                        <option value="<?= e($gender) ?>" <?= (($old['gender'] ?? $house['gender'] ?? '') === $gender) ? 'selected' : '' ?>><?= ucfirst($gender) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Bed Capacity</label>
                                <input type="number" name="capacity" class="form-control" min="1" value="<?= e($old['capacity'] ?? $house['capacity'] ?? 0) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Location / Zone</label>
                                <input name="location" class="form-control" value="<?= e($old['location'] ?? $house['location'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Assigned House Master</label>
                                <select name="houseMasterId" class="form-select select2">
                                    <option value="">— Not assigned —</option>
                                    <?php foreach ($staffUsers as $staff): ?>
                                        <?php if (($staff['role'] ?? '') === ROLE_HOUSE_MASTER): ?>
                                            <option value="<?= e((string) ($staff['id'] ?? $staff['uid'] ?? '')) ?>" <?= (($old['houseMasterId'] ?? $house['houseMasterId'] ?? '') === ((string) ($staff['id'] ?? $staff['uid'] ?? ''))) ? 'selected' : '' ?>><?= e($staff['name'] ?? '') ?> (<?= e($staff['email'] ?? '') ?>)</option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Assigned House Mistress</label>
                                <select name="houseMistressId" class="form-select select2">
                                    <option value="">— Not assigned —</option>
                                    <?php foreach ($staffUsers as $staff): ?>
                                        <?php if (($staff['role'] ?? '') === ROLE_HOUSE_MISTRESS): ?>
                                            <option value="<?= e((string) ($staff['id'] ?? $staff['uid'] ?? '')) ?>" <?= (($old['houseMistressId'] ?? $house['houseMistressId'] ?? '') === ((string) ($staff['id'] ?? $staff['uid'] ?? ''))) ? 'selected' : '' ?>><?= e($staff['name'] ?? '') ?> (<?= e($staff['email'] ?? '') ?>)</option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <?php foreach (['active','inactive'] as $status): ?>
                                        <option value="<?= e($status) ?>" <?= (($old['status'] ?? $house['status'] ?? '') === $status) ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Update House</button>
                            <a href="<?= url('views/admin/houses/index/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>