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
use App\Services\HouseService;

$currentRole = current_role();
$currentHouseId = current_house_id();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    if (!in_array($action, ['create_student', 'update_student'], true)) {
        http_response_code(400);
        exit('Invalid student action.');
    }
    $allowedActionRoles = $action === 'create_student'
        ? [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT]
        : [ROLE_ADMIN, ROLE_HOUSE_MASTER];
    if (!in_array($currentRole, $allowedActionRoles, true)) {
        http_response_code(403);
        exit('You are not authorized to manage students.');
    }
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'The form expired. Please refresh the page and try again.');
        redirect(url('views/students/index/index.php'));
    }

    $firstName = trim(sanitize($_POST['firstName'] ?? ''));
    $lastName = trim(sanitize($_POST['lastName'] ?? ''));
    $email = trim(sanitize($_POST['email'] ?? ''));
    if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Enter a first name, last name, and valid email address.');
        redirect(url('views/students/index/index.php'));
    }

    if ($action === 'create_student') {
        $houseId = $currentRole === ROLE_HOUSE_MASTER
            ? $currentHouseId
            : trim(sanitize($_POST['houseId'] ?? ''));
        if ($currentRole === ROLE_HOUSE_MASTER && !$houseId) {
            flash('error', 'Your account is not assigned to a house.');
            redirect(url('views/students/index/index.php'));
        }

        try {
            StudentService::create([
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'admissionNo' => sanitize($_POST['admissionNo'] ?? ''),
                'course' => sanitize($_POST['course'] ?? ''),
                'houseId' => $houseId ?: null,
                'gender' => sanitize($_POST['gender'] ?? ''),
                'status' => in_array($_POST['status'] ?? '', ['active', 'inactive'], true)
                    ? $_POST['status']
                    : 'active',
            ]);
            flash('success', 'Student created successfully.');
        } catch (Throwable $e) {
            error_log('Unable to create student: ' . $e->getMessage());
            flash('error', 'Unable to create student. Please try again.');
        }
        redirect(url('views/students/index/index.php'));
    }

    if ($action === 'update_student') {
        $studentId = trim(sanitize($_POST['id'] ?? ''));
        try {
            $student = $studentId !== '' ? StudentService::find($studentId) : null;
        } catch (Throwable $e) {
            error_log('Unable to load student for update: ' . $e->getMessage());
            flash('error', 'Unable to load student. Please try again.');
            redirect(url('views/students/index/index.php'));
        }
        if (!$student || ($currentRole === ROLE_HOUSE_MASTER && ($student['houseId'] ?? null) !== $currentHouseId)) {
            flash('error', 'Student not found or you do not have permission to edit this student.');
            redirect(url('views/students/index/index.php'));
        }

        try {
            StudentService::update($studentId, [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'course' => sanitize($_POST['course'] ?? ''),
            ]);
            flash('success', 'Student updated successfully.');
        } catch (Throwable $e) {
            error_log('Unable to update student: ' . $e->getMessage());
            flash('error', 'Unable to update student. Please try again.');
        }
        redirect(url('views/students/index/index.php'));
    }
}

$pageTitle = 'Students';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$allStudents = StudentService::all();
$totalStudents = count($allStudents);
$totalPages = max(1, (int) ceil($totalStudents / $limit));
$students = array_slice($allStudents, ($page - 1) * $limit, $limit);
$houses = HouseService::all();
$currentHouseName = '';
foreach ($houses as $house) {
    if ((string) ($house['id'] ?? '') === (string) $currentHouseId) {
        $currentHouseName = (string) ($house['name'] ?? '');
        break;
    }
}
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
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createStudentModal"><i class="bi bi-plus-lg"></i> Add Student</button>
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
                            <?php if (in_array(current_role(), [ROLE_ADMIN, ROLE_HOUSE_MASTER], true)): ?>
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
<?php if (current_role() !== ROLE_STUDENT): ?>
<div class="modal fade" id="createStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form method="POST" action="<?= url('views/students/index/index.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_student">
            <div class="modal-header"><h5 class="modal-title">Create Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">First Name</label><input name="firstName" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Last Name</label><input name="lastName" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Admission No.</label><input name="admissionNo" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Class Code</label><input name="course" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">House</label><?php if ($currentRole === ROLE_HOUSE_MASTER): ?><input class="form-control" value="<?= e($currentHouseName) ?>" readonly><input type="hidden" name="houseId" value="<?= e($currentHouseId ?? '') ?>"><?php else: ?><select name="houseId" class="form-select"><option value="">Select house</option><?php foreach ($houses as $house): ?><option value="<?= e((string) ($house['id'] ?? '')) ?>"><?= e($house['name'] ?? '') ?></option><?php endforeach; ?></select><?php endif; ?></div>
                    <div class="col-md-6"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="male">Male</option><option value="female">Female</option></select></div>
                    <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Student</button></div>
        </form>
    </div></div>
</div>
<?php endif; ?>
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
                <form method="POST" action="<?= url('views/students/index/index.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_student">
                    <input type="hidden" name="id" value="<?= e($studentId) ?>">
                    <div class="modal-header"><h5 class="modal-title">Edit Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">First Name</label><input name="firstName" class="form-control" value="<?= e($student['firstName'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Last Name</label><input name="lastName" class="form-control" value="<?= e($student['lastName'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($student['email'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Class Code</label><input name="course" class="form-control" value="<?= e($student['course'] ?? '') ?>"></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update Student</button></div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
