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
use App\Services\RoomService;
use App\Services\StudentService;

$pageTitle = 'Students';
$houseId = current_role() === ROLE_ADMIN ? null : current_user()['houseId'] ?? null;
$students = StudentService::allRecords($houseId);
$roomNumbers = [];
foreach (RoomService::all($houseId, 150) as $room) {
    $roomId = trim((string) ($room['id'] ?? ''));
    if ($roomId !== '') {
        $roomNumbers[$roomId] = (string) ($room['roomNumber'] ?? '');
    }
}
$totalStudents = count($students);
$studentSummary = [
    'total' => $totalStudents,
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
        $houses[trim((string) $house['id'])] = (string) ($house['name'] ?? $house['id']);
    }
}
$resolveHouseName = static function ($houseId) use (&$houses): string {
    $normalizedId = trim((string) $houseId);
    if ($normalizedId === '') {
        return '—';
    }
    if (isset($houses[$normalizedId])) {
        return $houses[$normalizedId];
    }
    $house = HouseService::find($normalizedId);
    if (is_array($house) && !empty($house['name'])) {
        $houses[$normalizedId] = (string) $house['name'];
        return $houses[$normalizedId];
    }
    return $normalizedId;
};
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
       
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100" data-no-copy-export="true" data-export-exclude-last-column="true">
                <thead>
                <tr>
                    <th>Admission No.</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Class Code</th>
                    <th>House</th>
                    <th>Residence Type</th>
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
                        <td><?= e($resolveHouseName($s['houseId'] ?? '')) ?></td>
                        <td><span class="badge bg-warning text-dark"><?= e(strtolower((string) ($s['residenceType'] ?? 'boarding')) === 'day' ? 'Day' : 'Boarding') ?></span></td>
                        <td><span class="badge bg-<?= ($s['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= e($s['status'] ?? '') ?></span></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Student actions">
                            <button type="button" class="btn btn-outline-secondary" title="View student" data-bs-toggle="modal" data-bs-target="#studentViewModal-<?= e((string) ($s['id'] ?? '')) ?>"><i class="bi bi-eye"></i><span class="visually-hidden">View</span></button>
                            <button type="button" class="btn btn-outline-primary" title="Edit student" data-bs-toggle="modal" data-bs-target="#studentEditModal-<?= e((string) ($s['id'] ?? '')) ?>"><i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span></button>
                            <?php if (current_role() === ROLE_ADMIN || current_role() === ROLE_HOUSE_MASTER): ?>
                            <button type="button" class="btn btn-outline-danger" title="Delete student" data-bs-toggle="modal" data-bs-target="#studentDeleteModal-<?= e((string) ($s['id'] ?? '')) ?>"><i class="bi bi-trash"></i><span class="visually-hidden">Delete</span></button>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($students as $s): ?>
    <?php
    $studentId = (string) ($s['id'] ?? '');
    $studentName = trim((string) (($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? '')));
    $studentHouse = $resolveHouseName($s['houseId'] ?? '');
    $studentRoomId = trim((string) ($s['roomId'] ?? ''));
    $studentRoomNumber = $studentRoomId !== '' ? ($roomNumbers[$studentRoomId] ?? '') : '';
    $displayStudentValue = static function ($value): string {
        $value = trim((string) ($value ?? ''));
        return e($value !== '' ? $value : '—');
    };
    $studentStatus = ucfirst(strtolower((string) ($s['status'] ?? 'active')));
    ?>
    <div class="modal fade" id="studentViewModal-<?= e($studentId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content student-profile-modal">
                <div class="modal-header student-modal-header">
                    <span class="student-modal-icon"><i class="bi bi-person-vcard"></i></span>
                    <div class="student-modal-heading">
                        <span class="student-modal-kicker">Student record</span>
                        <h5 class="modal-title"><?= e($studentName ?: 'Student Profile') ?></h5>
                        <p>Admission No. <?= $displayStudentValue($s['admissionNo'] ?? '') ?></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body student-profile-body">
                    <section class="student-profile-section">
                        <h6><i class="bi bi-person-lines-fill"></i> Personal and academic</h6>
                        <dl class="student-detail-grid">
                            <div><dt>First Name</dt><dd><?= $displayStudentValue($s['firstName'] ?? '') ?></dd></div>
                            <div><dt>Last Name</dt><dd><?= $displayStudentValue($s['lastName'] ?? '') ?></dd></div>
                            <div><dt>Gender</dt><dd><?= $displayStudentValue(ucfirst((string) ($s['gender'] ?? ''))) ?></dd></div>
                            <div><dt>Class</dt><dd><?= $displayStudentValue($s['class'] ?? '') ?></dd></div>
                            <div><dt>Form / Level</dt><dd><?= $displayStudentValue($s['form'] ?? $s['level'] ?? '') ?></dd></div>
                            <div><dt>Class Code</dt><dd><?= $displayStudentValue($s['course'] ?? '') ?></dd></div>
                            <div><dt>Email</dt><dd><?= $displayStudentValue($s['email'] ?? '') ?></dd></div>
                            <div><dt>Phone</dt><dd><?= $displayStudentValue($s['phone'] ?? '') ?></dd></div>
                            <div><dt>NHIS Number</dt><dd><?= $displayStudentValue($s['nhisNumber'] ?? '') ?></dd></div>
                        </dl>
                    </section>
                    <section class="student-profile-section">
                        <h6><i class="bi bi-house-door"></i> Housing and status</h6>
                        <dl class="student-detail-grid">
                            <div><dt>House</dt><dd><?= $displayStudentValue($studentHouse) ?></dd></div>
                            <div><dt>Room Number</dt><dd><?= $displayStudentValue($studentRoomNumber) ?></dd></div>
                            <div><dt>Residence Type</dt><dd><?= strtolower((string) ($s['residenceType'] ?? 'boarding')) === 'day' ? 'Day' : 'Boarding' ?></dd></div>
                            <div><dt>Status</dt><dd><span class="student-status-value"><?= e($studentStatus) ?></span></dd></div>
                        </dl>
                    </section>
                    <section class="student-profile-section">
                        <h6><i class="bi bi-person-heart"></i> Parent / guardian</h6>
                        <dl class="student-detail-grid">
                            <div><dt>Guardian Name</dt><dd><?= $displayStudentValue($s['guardianName'] ?? '') ?></dd></div>
                            <div><dt>Guardian Phone</dt><dd><?= $displayStudentValue($s['guardianPhone'] ?? '') ?></dd></div>
                            <div><dt>Guardian Email</dt><dd><?= $displayStudentValue($s['guardianEmail'] ?? '') ?></dd></div>
                        </dl>
                    </section>
                </div>
                <div class="modal-footer student-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="studentEditModal-<?= e($studentId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content student-edit-modal">
                <form method="POST" action="<?= url('views/admin/students/edit/edit.php?id=' . urlencode($studentId)) ?>">
                    <div class="modal-header student-modal-header">
                        <span class="student-modal-icon student-edit-icon"><i class="bi bi-pencil-square"></i></span>
                        <div class="student-modal-heading">
                            <span class="student-modal-kicker">Student record</span>
                            <h5 class="modal-title">Edit <?= e($studentName ?: 'Student') ?></h5>
                            <p>Update personal, academic, residence, and guardian details</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body student-edit-body">
                        <section class="student-edit-section">
                        <h6 class="student-edit-section-heading"><i class="bi bi-person-lines-fill"></i> Personal and academic</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6"><label class="form-label">First Name</label><input name="firstName" class="form-control" value="<?= e($s['firstName'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Last Name</label><input name="lastName" class="form-control" value="<?= e($s['lastName'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Admission No.</label><input name="admissionNo" class="form-control" value="<?= e($s['admissionNo'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">Select Gender</option><option value="male" <?= strtolower((string) ($s['gender'] ?? '')) === 'male' ? 'selected' : '' ?>>Male</option><option value="female" <?= strtolower((string) ($s['gender'] ?? '')) === 'female' ? 'selected' : '' ?>>Female</option><option value="other" <?= strtolower((string) ($s['gender'] ?? '')) === 'other' ? 'selected' : '' ?>>Other</option></select></div>
                            <div class="col-md-6"><label class="form-label">Class</label><input name="class" class="form-control" value="<?= e($s['class'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Form / Level</label><input name="form" class="form-control" value="<?= e($s['form'] ?? $s['level'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Class Code</label><input name="course" class="form-control" value="<?= e($s['course'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">NHIS Number</label><input name="nhisNumber" class="form-control" value="<?= e($s['nhisNumber'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($s['email'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control" value="<?= e($s['phone'] ?? '') ?>"></div>
                        </div>
                        </section>
                        <section class="student-edit-section">
                        <h6 class="student-edit-section-heading"><i class="bi bi-house-door"></i> Housing and status</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6"><label class="form-label">House</label><select name="houseId" class="form-select"><option value="">— Unassigned —</option><?php foreach ($houses as $houseId => $houseName): ?><option value="<?= e($houseId) ?>" <?= ($s['houseId'] ?? '') === $houseId ? 'selected' : '' ?>><?= e($houseName) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label">Residence Type</label><select name="residenceType" class="form-select"><option value="boarding" <?= strtolower((string) ($s['residenceType'] ?? 'boarding')) === 'boarding' ? 'selected' : '' ?>>Boarding</option><option value="day" <?= strtolower((string) ($s['residenceType'] ?? 'boarding')) === 'day' ? 'selected' : '' ?>>Day</option></select></div>
                            <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" <?= ($s['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($s['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option><option value="suspended" <?= ($s['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option><option value="graduated" <?= ($s['status'] ?? '') === 'graduated' ? 'selected' : '' ?>>Graduated</option></select></div>
                        </div>
                        </section>
                        <section class="student-edit-section">
                        <h6 class="student-edit-section-heading"><i class="bi bi-person-heart"></i> Parent / guardian</h6>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Guardian Name</label><input name="guardianName" class="form-control" value="<?= e($s['guardianName'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Guardian Phone</label><input type="tel" name="guardianPhone" class="form-control" value="<?= e($s['guardianPhone'] ?? '') ?>"></div>
                            <div class="col-md-12"><label class="form-label">Guardian Email</label><input type="email" name="guardianEmail" class="form-control" value="<?= e($s['guardianEmail'] ?? '') ?>"></div>
                        </div>
                        </section>
                    </div>
                    <div class="modal-footer student-modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="studentDeleteModal-<?= e($studentId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger">Delete Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete <strong><?= e($studentName ?: 'this student') ?></strong>?</p>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="<?= url('views/admin/students/delete/delete.php?id=' . urlencode($studentId)) ?>">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require APP_ROOT . '/app/views/components/modal/modal.php'; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
