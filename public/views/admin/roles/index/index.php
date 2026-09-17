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

use App\Services\FirebaseService;

$pageTitle = 'Roles';
// Custom roles managed via Firestore (roles collection)

$displayRoles = [
    ['key' => ROLE_ADMIN, 'name' => 'Admin', 'dashboard' => 'Admin Dashboard', 'house_access' => 'All houses', 'description' => 'Full control over the dormitory system.'],
    ['key' => ROLE_HOUSE_MASTER, 'name' => 'House Master', 'dashboard' => 'House Master Dashboard', 'house_access' => 'Assigned house', 'description' => 'Manages attendance, rooms, students, incidents and reports for their house.'],
    ['key' => ROLE_HOUSE_MISTRESS, 'name' => 'House Mistress', 'dashboard' => 'House Master Dashboard', 'house_access' => 'Assigned house', 'description' => 'Same house management duties as the House Master.'],
    ['key' => ROLE_SENIOR_HOUSEPARENT, 'name' => 'Senior Houseparent', 'dashboard' => 'Senior Houseparent Dashboard', 'house_access' => 'Supervision only', 'description' => 'Monitors house operations and related records without being assigned to a house.'],
    ['key' => ROLE_NURSE, 'name' => 'Nurse', 'dashboard' => 'Nurse Dashboard', 'house_access' => 'None', 'description' => 'Handles medical records and student wellness tracking.'],
    ['key' => ROLE_SECURITY, 'name' => 'Security', 'dashboard' => 'Security Dashboard', 'house_access' => 'None', 'description' => 'Manages visitors and security-related incidents.'],
    ['key' => ROLE_STUDENT, 'name' => 'Student', 'dashboard' => 'Student Dashboard', 'house_access' => 'Own profile', 'description' => 'Accesses personal attendance, room, and notification data.'],
];

try {
    $customRoles = FirebaseService::getInstance()->getCollection(COL_ROLES, [], 200);
    foreach ($customRoles as $customRole) {
        if (!empty($customRole['key'])) {
            $displayRoles[] = [
                'key' => $customRole['key'],
                'name' => $customRole['name'] ?? $customRole['key'],
                'dashboard' => $customRole['dashboard'] ?? 'Custom Dashboard',
                'house_access' => $customRole['house_access'] ?? 'Custom',
                'description' => $customRole['description'] ?? '',
            ];
        }
    }
} catch (Throwable $e) {
    // Built-in roles remain available when Firestore is unavailable.
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-shield-check', 'label' => 'Roles', 'href' => url('views/admin/roles/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Roles</h5>
            <div class="d-flex gap-2">
                <a href="<?= url('views/admin/users/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back to Users</a>
                <a href="<?= url('views/admin/roles/create/create.php') ?>" class="btn btn-success btn-sm">Add Role</a>
                <a href="<?= url('views/admin/permissions/index/index.php') ?>" class="btn btn-primary btn-sm">Permissions</a>
            </div>
        </div>

        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h6 class="mb-1">System Roles</h6>
                    <p class="text-muted mb-0">Click a role to view its detailed access and configuration.</p>
                </div>
                <span class="badge bg-primary bg-opacity-10 text-primary"><?= count($displayRoles) ?> roles</span>
            </div>

            <style>
                .role-detail-accordion {
                    display: grid;
                    gap: 0.8rem;
                }
                .role-detail-item {
                    border: 1px solid rgba(15, 76, 150, 0.12);
                    border-radius: 12px;
                    overflow: hidden;
                    background: #ffffff;
                    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
                }
                .role-detail-header {
                    width: 100%;
                    border: none;
                    background: linear-gradient(90deg, #eef5ff 0%, #f9fbff 100%);
                    border-left: 5px solid #1d7ef2;
                    padding: 0.9rem 1.1rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    text-align: left;
                    color: #0f172a;
                    font-weight: 700;
                    letter-spacing: 0.01em;
                    border-radius: 0;
                }
                .role-detail-header small {
                    font-weight: 600;
                    color: #475569;
                }
                .role-detail-header:focus,
                .role-detail-header:active {
                    box-shadow: none;
                }
                .role-detail-body {
                    padding: 1rem;
                    background: #ffffff;
                }
                .role-detail-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                    gap: 0.75rem;
                }
                .role-detail-card {
                    border: 1px solid #edf2f7;
                    border-radius: 10px;
                    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    padding: 0.75rem 0.8rem;
                }
                .role-detail-card .label {
                    display: block;
                    font-size: 0.7rem;
                    color: #64748b;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    margin-bottom: 0.38rem;
                }
                .role-detail-card .value {
                    font-size: 0.98rem;
                    color: #0f172a;
                    font-weight: 600;
                    line-height: 1.5;
                }
                .role-detail-card .value-muted {
                    color: #475569;
                    font-weight: 500;
                }
            </style>

            <div class="role-detail-accordion accordion" id="roleDetailAccordion">
                <?php foreach ($displayRoles as $role): ?>
                    <?php
                    $badgeClass = 'bg-secondary';
                    $roleName = strtolower((string) ($role['name'] ?? 'Custom'));
                    if (strpos($roleName, 'admin') !== false) $badgeClass = 'bg-primary';
                    elseif (strpos($roleName, 'master') !== false || strpos($roleName, 'mistress') !== false) $badgeClass = 'bg-info text-dark';
                    elseif (strpos($roleName, 'parent') !== false) $badgeClass = 'bg-secondary';
                    elseif (strpos($roleName, 'nurse') !== false) $badgeClass = 'bg-success';
                    elseif (strpos($roleName, 'security') !== false) $badgeClass = 'bg-warning text-dark';
                    elseif (strpos($roleName, 'student') !== false) $badgeClass = 'bg-dark';
                    ?>
                    <div class="role-detail-item accordion-item">
                        <h2 class="accordion-header" id="heading-role-<?= e($role['key']) ?>">
                            <button class="role-detail-header accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-role-<?= e($role['key']) ?>" aria-expanded="false" aria-controls="collapse-role-<?= e($role['key']) ?>">
                                <span>
                                    <span class="badge <?= $badgeClass ?> me-2"><?= e($role['name']) ?></span>
                                </span>
                                <small><?= e($role['house_access']) ?></small>
                            </button>
                        </h2>
                        <div id="collapse-role-<?= e($role['key']) ?>" class="accordion-collapse collapse" aria-labelledby="heading-role-<?= e($role['key']) ?>" data-bs-parent="#roleDetailAccordion">
                            <div class="role-detail-body">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                    <strong><?= e($role['name']) ?> overview</strong>
                                    <?php if (!in_array($role['key'], ALL_ROLES, true)): ?>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#roleEditModal-<?= e($role['key']) ?>">Edit</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#roleDeleteModal-<?= e($role['key']) ?>">Delete</button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">Built-in role</span>
                                    <?php endif; ?>
                                </div>

                                <div class="role-detail-grid">
                                    <div class="role-detail-card">
                                        <span class="label">Dashboard</span>
                                        <div class="value"><?= e($role['dashboard']) ?></div>
                                    </div>
                                    <div class="role-detail-card">
                                        <span class="label">House Access</span>
                                        <div class="value value-muted"><?= e($role['house_access']) ?></div>
                                    </div>
                                    <div class="role-detail-card" style="grid-column: 1 / -1;">
                                        <span class="label">Description</span>
                                        <div class="value value-muted"><?= e($role['description']) ?: 'No description available.' ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php foreach ($displayRoles as $role): ?>
    <?php if (!in_array($role['key'], ALL_ROLES, true)): ?>
        <div class="modal fade" id="roleEditModal-<?= e($role['key']) ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="<?= url('views/admin/roles/edit/edit.php?id=' . urlencode($role['key'])) ?>"><div class="modal-header"><h5 class="modal-title">Edit <?= e($role['name']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><input type="hidden" name="id" value="<?= e($role['key']) ?>"><label class="form-label">Role name</label><input name="name" class="form-control mb-3" value="<?= e($role['name']) ?>" required><label class="form-label">Dashboard</label><input name="dashboard" class="form-control mb-3" value="<?= e($role['dashboard']) ?>"><label class="form-label">House access</label><input name="house_access" class="form-control mb-3" value="<?= e($role['house_access']) ?>"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4"><?= e($role['description']) ?></textarea></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save changes</button></div></form></div></div>
        </div>
        <div class="modal fade" id="roleDeleteModal-<?= e($role['key']) ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header border-0"><h5 class="modal-title text-danger">Delete Role</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="mb-0">Delete custom role <strong><?= e($role['name']) ?></strong>?</p></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/admin/roles/delete/delete.php?id=' . urlencode($role['key'])) ?>"><input type="hidden" name="id" value="<?= e($role['key']) ?>"><button class="btn btn-danger" type="submit">Delete</button></form></div></div></div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
