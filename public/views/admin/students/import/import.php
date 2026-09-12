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
use App\Services\StudentService;

$pageTitle = 'Import Auth Users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['users'] ?? [];
    $imported = 0;
    foreach ($selected as $uid) {
        $all = FirebaseAdminAuthService::listAuthUsers(2000);
        $found = null;
        foreach ($all as $u) {
            if (($u['localId'] ?? $u['uid'] ?? '') === $uid) { $found = $u; break; }
        }
        if (!$found) continue;

        $display = $found['displayName'] ?? '';
        $names = preg_split('/\s+/', trim($display));
        $first = $names[0] ?? explode('@', $found['email'] ?? '')[0] ?? '';
        $last = isset($names[1]) ? implode(' ', array_slice($names, 1)) : '';

        StudentService::create([
            'firstName'    => $first,
            'lastName'     => $last,
            'email'        => $found['email'] ?? '',
            'admissionNo'  => '',
            'course'       => '',
            'level'        => '',
            'phone'        => '',
            'gender'       => '',
            'houseId'      => null,
            'roomId'       => null,
            'guardianName' => '',
            'guardianPhone'=> '',
            'status'       => 'active',
        ]);
        $imported++;
    }

    flash('success', "Imported {$imported} users as students.");
    redirect(url('views/admin/students/index/index.php'));
}

$authUsers = [];
if (FirebaseAdminAuthService::credentialsAvailable()) {
    $authUsers = FirebaseAdminAuthService::listAuthUsers(1000);
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-mortarboard',  'label' => 'Students',  'href' => url('views/admin/students/index/index.php')],
    ['icon' => 'bi-cloud-arrow-down', 'label' => 'Import', 'href' => url('views/admin/students/import/import.php'), 'active' => true],
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
                    <i class="bi bi-cloud-arrow-down-fill text-primary me-2"></i>Import Firebase Auth Users
                </h4>
                <p class="text-muted mb-0">Select Firebase Authentication users to register as student records</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('views/admin/students/bulk-import/bulk-import.php') ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-arrow-up me-1"></i> Bulk CSV Import
                </a>
                <a href="<?= url('views/admin/students/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Students
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-check me-2 text-primary"></i>Firebase Auth Users</h6>
                <?php if (!empty($authUsers)): ?>
                    <span class="badge bg-primary"><?= count($authUsers) ?> user(s) found</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <?php if (empty($authUsers)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-cloud-slash fs-1 text-muted mb-3 d-block"></i>
                        <p class="text-muted mb-0">No service account available or no Firebase Auth users found.</p>
                        <small class="text-muted">Ensure your Firebase service account credentials are configured.</small>
                    </div>
                <?php else: ?>
                    <form method="POST" action="<?= url('views/admin/students/import/import.php') ?>">
                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>Email</th>
                                        <th>Display Name</th>
                                        <th>UID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($authUsers as $u): ?>
                                        <?php $uid = e($u['localId'] ?? $u['uid'] ?? ''); ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input user-checkbox" name="users[]" value="<?= $uid ?>">
                                            </td>
                                            <td><?= e($u['email'] ?? '—') ?></td>
                                            <td><?= e($u['displayName'] ?? '—') ?></td>
                                            <td><code class="small text-muted"><?= $uid ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus me-1"></i> Import Selected
                            </button>
                            <a href="<?= url('views/admin/students/index/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    document.getElementById('selectAll')?.addEventListener('change', function () {
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
    });
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

