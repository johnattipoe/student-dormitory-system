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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\HouseService;
use App\Services\StudentService;

$pageTitle = 'Students';
$students = StudentService::all(current_role() === ROLE_ADMIN ? null : current_user()['houseId']);
$studentSummary = [
    'total' => count($students),
    'active' => 0,
    'inactive' => 0,
    'suspended' => 0,
    'assigned' => 0,
];
foreach ($students as $student) {
    $status = strtolower((string) ($student['status'] ?? 'active'));
    if (isset($studentSummary[$status])) {
        $studentSummary[$status]++;
    }
    if (!empty($student['houseId'])) {
        $studentSummary['assigned']++;
    }
}
$houses = [];
foreach (HouseService::all() as $house) {
    if (!empty($house['id'])) {
        $houses[(string) $house['id']] = $house['name'] ?? $house['id'];
    }
}
$search = strtolower(sanitize($_GET['search'] ?? ''));
if ($search !== '') {
    $students = array_values(array_filter($students, function ($student) use ($search) {
        $haystack = strtolower(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' ' . ($student['admissionNo'] ?? '') . ' ' . ($student['class'] ?? '') . ' ' . ($student['course'] ?? '')));
        return str_contains($haystack, $search);
    }));
}

if (isset($_GET['created'])) $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Student created.'];
if (isset($_GET['updated'])) $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Student updated.'];
if (isset($_GET['deleted'])) $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Student deleted.'];

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/admin/students/index/index.php'), 'active' => true],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/admin/rooms/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/admin/attendance/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <!-- Main Content -->
    <div class="content-wrapper">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Students</h5>
            <?php if (can('students', 'own') || current_role() !== ROLE_STUDENT): ?>
                <div><a href="<?= url('views/admin/students/bulk-import/bulk-import.php') ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-arrow-up"></i> Upload CSV/Excel</a> <a href="<?= url('views/admin/students/create/create.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Student</a></div>
            <?php endif; ?>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-0 shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted text-uppercase small fw-semibold mb-1">Total Students</p>
                            <h3 class="mb-1"><?= $studentSummary['total'] ?></h3>
                            <small class="text-muted">All registered students</small>
                        </div>
                        <span class="text-primary fs-3"><i class="bi bi-people-fill"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-0 shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted text-uppercase small fw-semibold mb-1">Active Students</p>
                            <h3 class="mb-1 text-success"><?= $studentSummary['active'] ?></h3>
                            <small class="text-muted">Eligible for accommodation</small>
                        </div>
                        <span class="text-success fs-3"><i class="bi bi-person-check-fill"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-0 shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted text-uppercase small fw-semibold mb-1">Inactive / Suspended</p>
                            <h3 class="mb-1 text-warning"><?= $studentSummary['inactive'] + $studentSummary['suspended'] ?></h3>
                            <small class="text-muted"><?= $studentSummary['inactive'] ?> inactive, <?= $studentSummary['suspended'] ?> suspended</small>
                        </div>
                        <span class="text-warning fs-3"><i class="bi bi-person-dash-fill"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-0 shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted text-uppercase small fw-semibold mb-1">House Assigned</p>
                            <h3 class="mb-1 text-info"><?= $studentSummary['assigned'] ?></h3>
                            <small class="text-muted"><?= max(0, $studentSummary['total'] - $studentSummary['assigned']) ?> awaiting assignment</small>
                        </div>
                        <span class="text-info fs-3"><i class="bi bi-house-check-fill"></i></span>
                    </div>
                </div>
            </div>
        </div>
       
        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-2">
                <div class="col-md-9">
                    <input name="search" class="form-control form-control-sm" placeholder="Search name, admission number, class, or course" value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary btn-sm">Filter</button> 
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/students/index/index.php') ?>">Reset</a>
                </div>
            </form>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Admission No.</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Class Code</th>
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
                        <td><?= e($s['class'] ?? ($s['form'] ?? $s['level'] ?? '—')) ?></td>
                        <td><?= e($s['course'] ?? '') ?></td>
                        <td><?= e($houses[(string) ($s['houseId'] ?? '')] ?? ($s['houseId'] ?? '—')) ?></td>
                        <td><span class="badge bg-<?= ($s['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= e($s['status'] ?? '') ?></span></td>
                        <td>
                            <a href="<?= url('views/admin/students/view/view.php?id=' . urlencode($s['id'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            <a href="<?= url('views/admin/students/edit/edit.php?id=' . urlencode($s['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <?php if (current_role() === ROLE_ADMIN || current_role() === ROLE_HOUSE_MASTER): ?>
                            <button class="btn btn-sm btn-outline-danger" data-confirm
                                    data-action="<?= url('views/admin/students/delete/delete.php?id=' . urlencode($s['id'])) ?>"
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

<?php require APP_ROOT . '/app/views/components/modal/modal.php'; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
