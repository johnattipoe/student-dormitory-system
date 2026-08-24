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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\HouseService;
use App\Services\StudentService;

$pageTitle = 'Students';
$students = StudentService::all(current_role() === ROLE_ADMIN ? null : current_user()['houseId']);
$houses = [];
foreach (HouseService::all() as $house) {
    if (!empty($house['id'])) {
        $houses[(string) $house['id']] = $house['name'] ?? $house['id'];
    }
}
$search = strtolower(sanitize($_GET['search'] ?? ''));
if ($search !== '') {
    $students = array_values(array_filter($students, function ($student) use ($search) {
        $haystack = strtolower(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' ' . ($student['admissionNo'] ?? '') . ' ' . ($student['email'] ?? '')));
        return str_contains($haystack, $search);
    }));
}

if (isset($_GET['created'])) $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Student created.'];
if (isset($_GET['updated'])) $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Student updated.'];
if (isset($_GET['deleted'])) $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Student deleted.'];

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/admin/students/index.php'), 'active' => true],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/admin/rooms/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/admin/attendance/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>

    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Students</h5>
            <?php if (can('students', 'own') || current_role() !== ROLE_STUDENT): ?>
                <div><a href="<?= url('views/admin/students/bulk-import.php') ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-arrow-up"></i> Upload CSV/Excel</a> <a href="<?= url('views/admin/students/create.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Student</a></div>
            <?php endif; ?>
        </div>
        <div class="card stat-card p-3 mb-3"><form method="GET" class="row g-2"><div class="col-md-9"><input name="search" class="form-control form-control-sm" placeholder="Search name, admission number, or email" value="<?= e($search) ?>"></div><div class="col-md-3"><button class="btn btn-primary btn-sm">Filter</button> <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/students/index.php') ?>">Reset</a></div></form></div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Admission No.</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>House</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $s): ?>
                    <tr>
                        <td><?= e($s['admissionNo'] ?? '') ?></td>
                        <td><?= e(trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''))) ?></td>
                        <td><?= e($s['email'] ?? '') ?></td>
                        <td><?= e($s['course'] ?? '') ?></td>
                        <td><?= e($houses[(string) ($s['houseId'] ?? '')] ?? ($s['houseId'] ?? '—')) ?></td>
                        <td><span class="badge bg-<?= ($s['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= e($s['status'] ?? '') ?></span></td>
                        <td>
                            <a href="<?= url('views/admin/students/view.php?id=' . urlencode($s['id'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            <a href="<?= url('views/admin/students/edit.php?id=' . urlencode($s['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <?php if (current_role() === ROLE_ADMIN || current_role() === ROLE_HOUSE_MASTER): ?>
                            <button class="btn btn-sm btn-outline-danger" data-confirm
                                    data-action="<?= url('students/' . urlencode($s['id']) . '/delete') ?>"
                                    data-message="Delete <?= e(trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''))) ?>? This cannot be undone.">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/modal.php'; ?>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
