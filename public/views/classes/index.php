<?php
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

$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\HouseService;

$firebase = FirebaseService::getInstance();
$role = current_role();
$user = current_user() ?? [];
$userHouseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$errors = [];

$houses = [];
foreach (HouseService::all() as $house) {
    $id = (string) ($house['id'] ?? $house['houseId'] ?? '');
    if ($id !== '') {
        $houses[$id] = $house['name'] ?? $id;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $className = sanitize($_POST['className'] ?? '');
    $classCode = sanitize($_POST['classCode'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $status = sanitize($_POST['status'] ?? 'active');
    $houseId = $role === ROLE_ADMIN
        ? sanitize($_POST['houseId'] ?? '')
        : $userHouseId;

    if ($className === '') {
        $errors['className'] = 'Class name is required.';
    }

    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }

    if ($role === ROLE_HOUSE_MASTER && $houseId === '') {
        $errors['houseId'] = 'Your account is not assigned to a house.';
    }

    if (empty($errors)) {
        try {
            $existingClasses = $firebase->getCollection('classes', [], 500);
            foreach ($existingClasses as $existingClass) {
                $sameName = strtolower((string) ($existingClass['className'] ?? '')) === strtolower($className);
                $sameScope = (string) ($existingClass['houseId'] ?? '') === $houseId;
                if ($sameName && $sameScope) {
                    $errors['className'] = 'This class already exists for the selected scope.';
                    break;
                }
            }

            if (empty($errors)) {
                $firebase->addDocument('classes', [
                    'className' => $className,
                    'classCode' => $classCode,
                    'description' => $description,
                    'houseId' => $houseId !== '' ? $houseId : null,
                    'scope' => $houseId !== '' ? 'house' : 'global',
                    'status' => $status,
                    'createdBy' => $user['uid'] ?? $user['id'] ?? null,
                ]);

                flash('success', 'Class added successfully.');
                redirect(url('views/classes/index.php'));
            }
        } catch (Throwable $e) {
            $errors['general'] = 'Unable to add class: ' . $e->getMessage();
        }
    }
}

$classes = $firebase->getCollection('classes', [], 500);
if ($role === ROLE_HOUSE_MASTER) {
    $classes = array_values(array_filter($classes, static fn(array $class): bool => (string) ($class['houseId'] ?? '') === $userHouseId));
}

usort($classes, static fn(array $first, array $second): int => strcasecmp(
    (string) ($first['className'] ?? ''),
    (string) ($second['className'] ?? '')
));

$pageTitle = 'Classes';
$dashboardRoute = $role === ROLE_ADMIN
    ? 'views/admin/dashboard.php'
    : 'views/house-master/dashboard/index.php';
$studentsRoute = $role === ROLE_ADMIN
    ? 'views/admin/students/index/index.php'
    : 'views/house-master/students/index.php';

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url($dashboardRoute)],
    ['icon' => 'bi-layers', 'label' => 'Classes', 'href' => url('views/classes/index.php'), 'active' => true],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url($studentsRoute)],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Add Classes</h5>
                <p class="text-muted mb-0">Create class options used when registering students.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url($studentsRoute) ?>">
                <i class="bi bi-mortarboard"></i> Students
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card stat-card p-4">
                    <h6 class="mb-3">New Class</h6>
                    <form method="POST" action="<?= url('views/classes/index.php') ?>" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Class Name</label>
                            <input name="className" class="form-control" placeholder="Example: SHS 1" required>
                            <?php if (!empty($errors['className'])): ?><div class="text-danger small"><?= e($errors['className']) ?></div><?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Class Code</label>
                            <input name="classCode" class="form-control" placeholder="Example: SHS1">
                        </div>

                        <?php if ($role === ROLE_ADMIN): ?>
                            <div class="col-12">
                                <label class="form-label">House Scope</label>
                                <select name="houseId" class="form-select select2">
                                    <option value="">Global class</option>
                                    <?php foreach ($houses as $houseId => $houseName): ?>
                                        <option value="<?= e($houseId) ?>"><?= e($houseName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Choose Global if all houses can use this class.</small>
                            </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Optional notes about this class"></textarea>
                        </div>

                        <div class="col-12">
                            <button class="btn btn-primary w-100" type="submit">
                                <i class="bi bi-plus-lg"></i> Add Class
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card stat-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Existing Classes</h6>
                        <span class="badge bg-primary"><?= e((string) count($classes)) ?> total</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover data-table w-100">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Code</th>
                                    <th>Scope</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($classes)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No classes added yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($classes as $class): ?>
                                        <?php $classHouseId = (string) ($class['houseId'] ?? ''); ?>
                                        <tr>
                                            <td><strong><?= e($class['className'] ?? 'Class') ?></strong></td>
                                            <td><?= e($class['classCode'] ?? '—') ?></td>
                                            <td><?= e($classHouseId !== '' ? ($houses[$classHouseId] ?? $classHouseId) : 'Global') ?></td>
                                            <td>
                                                <span class="badge bg-<?= ($class['status'] ?? 'active') === 'active' ? 'success' : 'secondary' ?>">
                                                    <?= e($class['status'] ?? 'active') ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
