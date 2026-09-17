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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;

$pageTitle = 'Students';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$allStudents = StudentService::all();
$totalStudents = count($allStudents);
$totalPages = max(1, (int) ceil($totalStudents / $limit));
$students = array_slice($allStudents, ($page - 1) * $limit, $limit);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/students/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Students</h5>
            <?php if (current_role() !== ROLE_STUDENT): ?>
                <a href="<?= url('views/admin/students/create/create.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Student</a>
            <?php endif; ?>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Admission No.</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Class Code</th>
                    <th>House</th>
                    <th>Residence</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= e($student['admissionNo'] ?? '') ?></td>
                        <td><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></td>
                        <td><?= e($student['email'] ?? '') ?></td>
                        <td><?= e($student['course'] ?? '-') ?></td>
                        <td><?= e($student['houseId'] ?? '-') ?></td>
                        <td><span class="badge bg-warning text-dark"><?= e(strtolower((string) ($student['residenceType'] ?? 'boarding')) === 'day' ? 'Day' : 'Boarding') ?></span></td>
                        <td><span class="badge bg-<?= ($student['status'] ?? 'active') === 'active' ? 'success' : 'secondary' ?>"><?= e($student['status'] ?? 'active') ?></span></td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#studentViewModal-<?= e((string) ($student['id'] ?? '')) ?>"><i class="bi bi-eye"></i></button>
                            <?php if (current_role() !== ROLE_STUDENT): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#studentEditModal-<?= e((string) ($student['id'] ?? '')) ?>"><i class="bi bi-pencil"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php $paginationBaseUrl = url('views/students/index/index.php'); require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
        </div>
    </div>
</div>
<?php foreach ($students as $student): ?>
    <?php
        $studentId = (string) ($student['id'] ?? '');
        if ($studentId === '') continue;
        $studentName = trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')));
    ?>
    <div class="modal fade" id="studentViewModal-<?= e($studentId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Student Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Name</dt>
                        <dd class="col-sm-8"><?= e($studentName ?: '—') ?></dd>
                        <dt class="col-sm-4">Admission No.</dt>
                        <dd class="col-sm-8"><?= e($student['admissionNo'] ?? '—') ?></dd>
                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8"><?= e($student['email'] ?? '—') ?></dd>
                        <dt class="col-sm-4">Class Code</dt>
                        <dd class="col-sm-8"><?= e($student['course'] ?? '-') ?></dd>
                        <dt class="col-sm-4">House</dt>
                        <dd class="col-sm-8"><?= e($student['houseId'] ?? '-') ?></dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><span class="badge bg-<?= ($student['status'] ?? 'active') === 'active' ? 'success' : 'secondary' ?>"><?= e($student['status'] ?? 'active') ?></span></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="studentEditModal-<?= e($studentId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Open the full edit form to update <strong><?= e($studentName ?: 'this student') ?></strong>.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
