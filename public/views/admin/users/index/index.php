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

use App\Services\FirebaseAdminAuthService;
use App\Services\FirebaseService;
use App\Services\HouseService;
use App\Services\UserService;

$pageTitle = 'Users';
$userService = new UserService();
$houses = HouseService::all();
$roleOptions = ['admin', 'house_master', 'house_mistress', 'senior-houseparent', 'security', 'nurse', 'student'];
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
    // Keep built-in roles available if custom roles cannot be loaded.
}
$showCreateUserModal = ($_GET['createUser'] ?? '') === '1';
$createUserErrors = $showCreateUserModal ? ($_SESSION['_errors'] ?? []) : [];
$createUserOld = $showCreateUserModal ? ($_SESSION['_old'] ?? []) : [];
$createUserTempCreated = $showCreateUserModal ? ($_SESSION['_temp_created'] ?? null) : null;
if ($showCreateUserModal) {
    unset($_SESSION['_errors'], $_SESSION['_old'], $_SESSION['_temp_created']);
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$allUsers = $userService->all();
$users = $allUsers;
$authUsers = [];

if (FirebaseAdminAuthService::credentialsAvailable()) {
    $authUsers = FirebaseAdminAuthService::listAuthUsers(500);
}
$totalUsers = count($users);
$totalPages = max(1, (int) ceil($totalUsers / $limit));
$page = min($page, $totalPages);
$users = array_slice($users, ($page - 1) * $limit, $limit);
$activeUsers = count(array_filter($allUsers, static fn(array $user): bool => ($user['status'] ?? 'active') === 'active'));
$staffUsers = count(array_filter($allUsers, static fn(array $user): bool => ($user['role'] ?? '') !== 'student'));
$roleCount = count(array_unique(array_filter(array_map(static fn(array $user): string => (string) ($user['role'] ?? ''), $allUsers))));
$roleCounts = [];
foreach ($allUsers as $userRecord) {
    $roleKey = strtolower(trim((string) ($userRecord['role'] ?? '')));
    $roleCounts[$roleKey] = ($roleCounts[$roleKey] ?? 0) + 1;
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Users', 'href' => url('views/admin/users/index/index.php'), 'active' => true],
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
                    <i class="bi bi-people-fill text-primary me-2"></i>User &amp; Staff Access Directory
                </h4>
                <p class="text-muted mb-0">Manage system authentication accounts, role permissions, and access levels</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="bi bi-plus-lg me-1"></i> Add User
                </button>
                <a href="<?= url('views/admin/roles/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-shield-check me-1"></i> Roles
                </a>
                <a href="<?= url('views/admin/permissions/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-key me-1"></i> Permissions
                </a>
            </div>
        </div>

        <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="createUserModalLabel">
                            <i class="bi bi-person-plus me-2 text-primary"></i><?= $createUserTempCreated ? 'User Created' : 'Add New User' ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <?php if ($createUserTempCreated): ?>
                        <div class="modal-body">
                            <div class="alert alert-success mb-0">
                                <div class="d-flex align-items-start">
                                    <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                                    <div>
                                        <strong>User created successfully.</strong>
                                        <p class="mb-2"><?= e($createUserTempCreated['message'] ?? 'The new user account is ready.') ?></p>
                                        <?php if (!empty($createUserTempCreated['uid'])): ?>
                                            <div class="small mb-2">UID: <code><?= e($createUserTempCreated['uid']) ?></code></div>
                                        <?php endif; ?>
                                        <?php if (!empty($createUserTempCreated['password'])): ?>
                                            <div class="p-2 bg-white rounded border">
                                                <i class="bi bi-key me-1 text-warning"></i>
                                                Temporary password: <code class="fs-6"><?= e($createUserTempCreated['password']) ?></code>
                                            </div>
                                            <div class="small mt-2">Share this password securely with the user and ask them to change it after signing in.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
                        </div>
                    <?php else: ?>
                    <form method="POST" action="<?= url('views/admin/users/create/create.php') ?>">
                        <input type="hidden" name="returnTo" value="users">
                        <div class="modal-body">
                            <?php if (!empty($createUserErrors)): ?>
                                <div class="alert alert-danger" role="alert">Please fix the highlighted fields and try again.</div>
                            <?php else: ?>
                                <p class="text-muted small mb-3">Create a user account and assign role permissions. A temporary password will be generated automatically.</p>
                            <?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="create-user-name">Full Name <span class="text-danger">*</span></label>
                                    <input id="create-user-name" name="name" class="form-control <?= !empty($createUserErrors['name']) ? 'is-invalid' : '' ?>" value="<?= e($createUserOld['name'] ?? '') ?>" required placeholder="Enter full name">
                                    <?php if (!empty($createUserErrors['name'])): ?><div class="invalid-feedback"><?= e($createUserErrors['name']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="create-user-email">Email Address <span class="text-danger">*</span></label>
                                    <input id="create-user-email" type="email" name="email" class="form-control <?= !empty($createUserErrors['email']) ? 'is-invalid' : '' ?>" value="<?= e($createUserOld['email'] ?? '') ?>" required placeholder="user@example.com">
                                    <?php if (!empty($createUserErrors['email'])): ?><div class="invalid-feedback"><?= e($createUserErrors['email']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="create-user-role-select">Role <span class="text-danger">*</span></label>
                                    <select name="role" class="form-select <?= !empty($createUserErrors['role']) ? 'is-invalid' : '' ?>" id="create-user-role-select" required>
                                        <?php foreach ($roleOptions as $role): ?>
                                            <option value="<?= e($role) ?>" <?= ($createUserOld['role'] ?? 'admin') === $role ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $role)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($createUserErrors['role'])): ?><div class="invalid-feedback"><?= e($createUserErrors['role']) ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="create-user-status">Status</label>
                                    <select name="status" class="form-select" id="create-user-status">
                                        <?php foreach (['active', 'inactive'] as $status): ?>
                                            <option value="<?= e($status) ?>" <?= ($createUserOld['status'] ?? 'active') === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12" id="create-user-house-field" hidden>
                                    <label class="form-label fw-semibold" for="create-user-house-select">Assign to House <span class="text-danger">*</span></label>
                                    <select name="houseId" class="form-select <?= !empty($createUserErrors['houseId']) ? 'is-invalid' : '' ?>" id="create-user-house-select">
                                        <option value="">Select house</option>
                                        <?php foreach ($houses as $house): ?>
                                            <option value="<?= e((string) ($house['id'] ?? '')) ?>" <?= (string) ($createUserOld['houseId'] ?? '') === (string) ($house['id'] ?? '') ? 'selected' : '' ?>><?= e($house['name'] ?? 'House') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($createUserErrors['houseId'])): ?><div class="invalid-feedback"><?= e($createUserErrors['houseId']) ?></div><?php endif; ?>
                                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>Only House Master and House Mistress roles can be assigned to a house. Senior Houseparent is excluded.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save User</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const roleSelect = document.getElementById('create-user-role-select');
            const houseField = document.getElementById('create-user-house-field');
            const houseSelect = document.getElementById('create-user-house-select');

            if (roleSelect && houseField && houseSelect) {
                function updateHouseField() {
                    const isHouseDuty = ['house_master', 'house_mistress'].includes(String(roleSelect.value || '').toLowerCase());
                    houseField.hidden = !isHouseDuty;
                    houseSelect.required = isHouseDuty;
                    houseField.style.display = isHouseDuty ? 'block' : 'none';
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
            }

            <?php if ($showCreateUserModal): ?>
            const createUserModal = document.getElementById('createUserModal');
            if (createUserModal && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(createUserModal).show();
            }
            <?php endif; ?>
        });
        </script>

        <!-- KPI Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Accounts</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalUsers) ?></h3>
                            <span class="small text-muted">All registered profiles</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Active Status</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $activeUsers) ?></h3>
                            <span class="small text-muted"><?= $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 100 ?>% active users</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Staff Accounts</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $staffUsers) ?></h3>
                            <span class="small text-muted">Administrative staff</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-person-badge fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Role Groups</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $roleCount) ?></h3>
                            <span class="small text-muted">Permission tiers</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-shield-check fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role Badges Strip -->
        <div class="row g-2 mb-4">
            <?php $roleCards = [
                ['key' => 'student', 'label' => 'Students', 'icon' => 'bi-mortarboard', 'color' => 'primary'],
                ['key' => 'admin', 'label' => 'Admins', 'icon' => 'bi-shield-lock', 'color' => 'danger'],
                ['key' => 'senior-houseparent', 'label' => 'Senior Houseparents', 'icon' => 'bi-house-heart', 'color' => 'success'],
                ['key' => 'house_master', 'label' => 'House Masters', 'icon' => 'bi-building-check', 'color' => 'info'],
                ['key' => 'security', 'label' => 'Security', 'icon' => 'bi-shield-check', 'color' => 'dark'],
                ['key' => 'nurse', 'label' => 'Nurses', 'icon' => 'bi-heart-pulse', 'color' => 'danger'],
            ]; ?>
            <?php foreach ($roleCards as $roleCard): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card stat-card p-2 border-0 shadow-sm text-center">
                        <small class="text-muted d-block small"><?= e($roleCard['label']) ?></small>
                        <strong class="fs-5 text-<?= $roleCard['color'] ?>"><?= e((string) ($roleCounts[$roleCard['key']] ?? 0)) ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Users Table Card -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i>System User Accounts</h6>
                <div class="d-flex align-items-center gap-3">
                    <form method="GET" class="m-0" role="search" data-live-search data-live-search-table="#systemUsersTable">
                        <input type="search" name="search" class="form-control form-control-sm" placeholder="Search users..." aria-label="Search user accounts" style="width: 240px; max-width: 100%;">
                    </form>
                    <small class="text-muted text-nowrap">Showing <?= e((string) $totalUsers) ?> accounts</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="systemUsersTable" class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>User Name</th>
                            <th>Email Address</th>
                            <th>Role Permission</th>
                            <th>Assigned House</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No user accounts found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <?php
                                $userRole = $user['role'] ?? '';
                                $userHouseId = $user['houseId'] ?? null;
                                $userHouseName = $userHouseId ? (HouseService::find($userHouseId)['name'] ?? 'Assigned') : '-';
                                $uId = (string) ($user['id'] ?? $user['uid'] ?? '');
                                $searchText = implode(' ', [
                                    (string) ($user['name'] ?? 'User'),
                                    (string) ($user['email'] ?? ''),
                                    (string) $userRole,
                                    (string) $userHouseName,
                                    (string) ($user['status'] ?? 'active'),
                                ]);
                                ?>
                                <tr data-search-text="<?= e($searchText) ?>">
                                    <td><strong class="text-dark"><?= e($user['name'] ?? 'User') ?></strong></td>
                                    <td><span class="text-muted font-monospace small"><?= e($user['email'] ?? '—') ?></span></td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= e(str_replace(['_', '-'], ' ', ucfirst($userRole))) ?>
                                        </span>
                                    </td>
                                    <td><?= e(in_array($userRole, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true) ? $userHouseName : '—') ?></td>
                                    <td><span class="badge bg-<?= ($user['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst(e($user['status'] ?? 'active')) ?></span></td>
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" title="View" data-bs-toggle="modal" data-bs-target="#userViewModal-<?= e($uId) ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" title="Edit" data-bs-toggle="modal" data-bs-target="#userEditModal-<?= e($uId) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#userDeleteModal-<?= e($uId) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php $paginationBaseUrl = url('views/admin/users/index/index.php'); require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
            </div>
        </div>

    </div>
</div>

<?php foreach ($users as $user): ?>
    <?php
    $uId = (string) ($user['id'] ?? $user['uid'] ?? '');
    $userRole = (string) ($user['role'] ?? '');
    $userName = (string) ($user['name'] ?? 'User');
    $userEmail = (string) ($user['email'] ?? '');
    $userStatus = (string) ($user['status'] ?? 'active');
    $userHouseId = $user['houseId'] ?? null;
    $userHouseName = $userHouseId ? (HouseService::find($userHouseId)['name'] ?? 'Assigned') : '-';
    ?>
    <div class="modal fade" id="userViewModal-<?= e($uId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                            <i class="bi bi-person-fill fs-3"></i>
                        </div>
                    </div>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?= e($userName) ?></dd>
                        <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($userEmail) ?></dd>
                        <dt class="col-sm-4">Role</dt><dd class="col-sm-8"><?= e(str_replace(['_', '-'], ' ', ucfirst($userRole))) ?></dd>
                        <dt class="col-sm-4">House</dt><dd class="col-sm-8"><?= e($userHouseName) ?></dd>
                        <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><?= e(ucfirst($userStatus)) ?></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="userEditModal-<?= e($uId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST" action="<?= url('views/admin/users/edit/edit.php?id=' . urlencode($uId)) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?= e($uId) ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input name="name" class="form-control" value="<?= e($userName) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?= e($userEmail) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-select">
                                    <?php foreach (['admin','house_master','house_mistress','senior-houseparent','security','nurse','student'] as $roleOption): ?>
                                        <option value="<?= e($roleOption) ?>" <?= $userRole === $roleOption ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $roleOption))) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?= $userStatus === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $userStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">House</label>
                                <select name="houseId" class="form-select">
                                    <option value="">Select house</option>
                                    <?php foreach (HouseService::all() as $house): ?>
                                        <option value="<?= e((string) ($house['id'] ?? '')) ?>" <?= (($userHouseId ?? '') === ($house['id'] ?? '')) ? 'selected' : '' ?>><?= e($house['name'] ?? 'House') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="userDeleteModal-<?= e($uId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete <strong><?= e($userName) ?></strong>?</p>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="<?= url('views/admin/users/delete/delete.php?id=' . urlencode($uId)) ?>">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
