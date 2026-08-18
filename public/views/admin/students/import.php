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

use App\Services\FirebaseAdminAuthService;
use App\Services\StudentService;

$pageTitle = 'Import Auth Users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['users'] ?? [];
    $imported = 0;
    foreach ($selected as $uid) {
        // fetch the full list and find the user
        $all = FirebaseAdminAuthService::listAuthUsers(2000);
        $found = null;
        foreach ($all as $u) {
            if (($u['localId'] ?? $u['uid'] ?? '') === $uid) { $found = $u; break; }
        }
        if (!$found) continue;

        // Map to student fields
        $display = $found['displayName'] ?? '';
        $names = preg_split('/\s+/', trim($display));
        $first = $names[0] ?? explode('@', $found['email'] ?? '')[0] ?? '';
        $last = isset($names[1]) ? implode(' ', array_slice($names,1)) : '';

        $studentData = [
            'firstName' => $first,
            'lastName' => $last,
            'email' => $found['email'] ?? '',
            'admissionNo' => '',
            'course' => '',
            'level' => '',
            'phone' => '',
            'gender' => '',
            'houseId' => null,
            'roomId' => null,
            'guardianName' => '',
            'guardianPhone' => '',
            'status' => 'active'
        ];

        StudentService::create($studentData);
        $imported++;
    }

    flash('success', "Imported {$imported} users as students.");
    redirect(base_url('index.php?route=/views/admin/students/index.php'));
}

$authUsers = [];
if (FirebaseAdminAuthService::credentialsAvailable()) {
    $authUsers = FirebaseAdminAuthService::listAuthUsers(1000);
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/admin/students/index.php')],
    ['icon' => 'bi-cloud-arrow-down', 'label' => 'Import', 'href' => url('views/admin/students/import.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <h5 class="mb-3">Import Firebase Auth Users</h5>
            <?php if (empty($authUsers)): ?>
                <div class="alert alert-info">No service account available or no Auth users found.</div>
            <?php else: ?>
                <form method="POST" action="<?= url('views/admin/students/import.php') ?>">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover w-100">
                            <thead>
                                <tr><th></th><th>Email</th><th>Name</th><th>UID</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($authUsers as $u): ?>
                                <?php $uid = e($u['localId'] ?? $u['uid'] ?? ''); ?>
                                <tr>
                                    <td><input type="checkbox" name="users[]" value="<?= $uid ?>"></td>
                                    <td><?= e($u['email'] ?? '') ?></td>
                                    <td><?= e($u['displayName'] ?? '') ?></td>
                                    <td><code><?= $uid ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-primary">Import Selected</button>
                        <a href="<?= url('views/admin/students/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
// TODO: CSV bulk-import — parse uploaded file, map columns, batch-create via StudentService::create().
$pageTitle = 'Import Students';
require APP_ROOT . '/app/views/components/header.php';
echo '<div class="p-4">CSV import form goes here — same layout pattern as create.php.</div>';
require APP_ROOT . '/app/views/components/footer.php';
