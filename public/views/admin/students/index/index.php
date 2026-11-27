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
use App\Services\FirebaseService;

$pageTitle = 'Students';
$houseId = current_role() === ROLE_ADMIN ? null : current_user()['houseId'] ?? null;
$createStudentOld = $_SESSION['_admin_student_create_old'] ?? [];
$createStudentErrors = $_SESSION['_admin_student_create_errors'] ?? [];
unset($_SESSION['_admin_student_create_old'], $_SESSION['_admin_student_create_errors']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_student') {
    $formValue = sanitize($_POST['form'] ?? $_POST['level'] ?? '');
    $studentData = [
        'firstName' => sanitize($_POST['firstName'] ?? ''),
        'lastName' => sanitize($_POST['lastName'] ?? ''),
        'admissionNo' => sanitize($_POST['admissionNo'] ?? ''),
        'class' => sanitize($_POST['class'] ?? ''),
        'form' => $formValue,
        'level' => $formValue,
        'gender' => sanitize($_POST['gender'] ?? ''),
        'nhisNumber' => sanitize($_POST['nhisNumber'] ?? ''),
        'course' => sanitize($_POST['course'] ?? ''),
        'houseId' => sanitize($_POST['houseId'] ?? ''),
        'roomId' => sanitize($_POST['roomId'] ?? ''),
        'residenceType' => sanitize($_POST['residenceType'] ?? 'boarding'),
        'status' => sanitize($_POST['status'] ?? 'active'),
        'guardianName' => sanitize($_POST['guardianName'] ?? ''),
        'guardianPhone' => sanitize($_POST['guardianPhone'] ?? ''),
        'guardianEmail' => sanitize($_POST['guardianEmail'] ?? ''),
    ];
    $createStudentErrors = validate_required($studentData, ['firstName', 'lastName']);
    if ($studentData['guardianEmail'] !== '' && !validate_email($studentData['guardianEmail'])) {
        $createStudentErrors['guardianEmail'] = 'Guardian email is invalid.';
    }

    if (empty($createStudentErrors)) {
        try {
            StudentService::create($studentData);
            flash('success', 'Student created successfully.');
            redirect(url('views/admin/students/index/index.php?created=1'));
        } catch (Throwable $e) {
            $createStudentErrors['general'] = 'Unable to create student: ' . $e->getMessage();
        }
    }

    $_SESSION['_admin_student_create_old'] = $studentData;
    $_SESSION['_admin_student_create_errors'] = $createStudentErrors;
    flash('error', 'Please correct the student details and try again.');
    redirect(url('views/admin/students/index/index.php?openAddStudent=1'));
}

$showGraduated = sanitize($_GET['show'] ?? '') === 'graduated';
$search = strtolower(sanitize($_GET['search'] ?? ''));
$firebase = FirebaseService::getInstance();
$houseWheres = $houseId !== null && $houseId !== ''
    ? [['houseId', '=', $houseId]]
    : [];
$allStudentCount = $firebase->count(COL_STUDENTS, $houseWheres);
$graduatedWheres = array_merge($houseWheres, [['status', '=', 'graduated']]);
$graduatedCount = $firebase->count(COL_STUDENTS, $graduatedWheres);
$currentStudentCount = max(0, $allStudentCount - $graduatedCount);
$assignedStudentCount = $houseId !== null && $houseId !== ''
    ? $currentStudentCount
    : max(
        0,
        $firebase->count(COL_STUDENTS, [['houseId', '!=', '']])
            - $firebase->count(COL_STUDENTS, [['status', '=', 'graduated'], ['houseId', '!=', '']])
    );
$studentSummary = [
    'total' => $currentStudentCount,
    'active' => $firebase->count(COL_STUDENTS, array_merge($houseWheres, [['status', '=', 'active']])),
    'inactive' => $firebase->count(COL_STUDENTS, array_merge($houseWheres, [['status', '=', 'inactive']])),
    'suspended' => $firebase->count(COL_STUDENTS, array_merge($houseWheres, [['status', '=', 'suspended']])),
    'assigned' => $assignedStudentCount,
    'graduated' => $graduatedCount,
];
$pageSize = 50;
$pageNumber = max(1, (int) ($_GET['page'] ?? 1));
if ($search !== '') {
    $allStudents = StudentService::allRecords($houseId);
    $students = array_values(array_filter($allStudents, static function (array $student) use ($showGraduated, $search): bool {
        if ((strtolower((string) ($student['status'] ?? 'active')) === 'graduated') !== $showGraduated) {
            return false;
        }

        $haystack = strtolower(trim(
            ($student['firstName'] ?? '') . ' ' .
            ($student['lastName'] ?? '') . ' ' .
            ($student['admissionNo'] ?? '') . ' ' .
            ($student['class'] ?? '') . ' ' .
            ($student['course'] ?? '')
        ));

        return str_contains($haystack, $search);
    }));
    $pageCount = max(1, (int) ceil(count($students) / $pageSize));
    $pageNumber = min($pageNumber, $pageCount);
    $students = array_slice($students, ($pageNumber - 1) * $pageSize, $pageSize);
} else {
    $pageRecordCount = $showGraduated ? $graduatedCount : $currentStudentCount;
    $pageCount = max(1, (int) ceil($pageRecordCount / $pageSize));
    $pageNumber = min($pageNumber, $pageCount);
    $cursorSessionKey = '_student_list_cursors_' . hash('sha256', serialize([
        $houseId,
        $showGraduated,
        current_role(),
    ]));
    $pageCursors = $_SESSION[$cursorSessionKey] ?? [1 => []];
    if (!is_array($pageCursors) || ($pageNumber > 1 && !isset($pageCursors[$pageNumber]))) {
        $pageNumber = 1;
        $pageCursors = [1 => []];
    }

    $studentFilters = array_merge(
        $houseWheres,
        [$showGraduated ? ['status', '=', 'graduated'] : ['status', '!=', 'graduated']]
    );
    $studentOrder = $showGraduated
        ? [['__name__', 'ASC']]
        : [['status', 'ASC'], ['__name__', 'ASC']];
    $pageResult = $firebase->getCollectionPage(
        COL_STUDENTS,
        $studentFilters,
        $pageSize,
        $studentOrder,
        $pageCursors[$pageNumber] ?? []
    );
    $students = $pageResult['items'];
    if ($pageResult['nextCursor'] !== null && $pageNumber < $pageCount) {
        $pageCursors[$pageNumber + 1] = $pageResult['nextCursor'];
    }
    $_SESSION[$cursorSessionKey] = $pageCursors;
}

$studentListUrl = base_url(
    'index.php?route=' . urlencode('/views/admin/students/index/index.php')
    . ($showGraduated ? '' : '&show=graduated')
);
$studentPageUrl = static function (int $page) use ($showGraduated, $search): string {
    $query = 'index.php?route=' . urlencode('/views/admin/students/index/index.php')
        . ($showGraduated ? '&show=graduated' : '')
        . ($search !== '' ? '&search=' . urlencode($search) : '')
        . '&page=' . $page;

    return base_url($query);
};
$roomNumbers = [];
foreach (RoomService::all($houseId, 150) as $room) {
    $roomId = trim((string) ($room['id'] ?? ''));
    if ($roomId !== '') {
        $roomNumbers[$roomId] = (string) ($room['roomNumber'] ?? '');
    }
}
$houses = [];
foreach (HouseService::all() as $house) {
    if (!empty($house['id'])) {
        $houses[trim((string) $house['id'])] = (string) ($house['name'] ?? $house['id']);
    }
}
$classRecords = FirebaseService::getInstance()->getCollection('classes', [], 500);
$studentClassOptions = array_values(array_unique(array_filter(array_map(
    static fn(array $class): string => (string) ($class['className'] ?? ''),
    array_filter($classRecords, static fn(array $class): bool => ($class['status'] ?? 'active') === 'active')
))));
if (empty($studentClassOptions)) {
    $studentClassOptions = ['SHS 1', 'SHS 2', 'SHS 3'];
}
$studentCourseOptions = array_values(array_unique(array_filter(array_map(
    static fn(array $class): string => trim((string) ($class['classCode'] ?? '')),
    $classRecords
))));
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
            <h5 class="mb-0"><?= $showGraduated ? 'Graduated Students / Alumni' : 'Students' ?></h5>
            <?php if (can('students', 'own') || current_role() !== ROLE_STUDENT): ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (current_role() === ROLE_ADMIN): ?>
                        <a href="<?= url('views/admin/students/promotion/index.php') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-up-circle me-1"></i> Promote Students</a>
                        <a href="<?= e($studentListUrl) ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-archive me-1"></i><?= $showGraduated ? 'Current Students' : 'Graduated / Alumni (' . e((string) $studentSummary['graduated']) . ')' ?>
                        </a>
                    <?php endif; ?>
                    <?php if (current_role() === ROLE_ADMIN): ?>
                        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#studentImportModal"><i class="bi bi-file-earmark-arrow-up"></i> Upload CSV/Excel</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#studentCreateModal"><i class="bi bi-plus-lg"></i> Add Student</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$showGraduated): ?>
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-0 shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted text-uppercase small fw-semibold mb-1">Current Students</p>
                            <h3 class="mb-1"><?= $studentSummary['total'] ?></h3>
                            <small class="text-muted">Excludes graduated alumni</small>
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
        <?php endif; ?>
       
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100" data-no-copy-export="true" data-export-exclude-last-column="true">
                <thead>
                <tr>
                    <th>Admission No.</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Form / Level</th>
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
                        <td><?= e($s['form'] ?? ($s['level'] ?? '—')) ?></td>
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
        <?php if ($pageCount > 1): ?>
            <div class="d-flex justify-content-between align-items-center gap-3 mt-3">
                <small class="text-muted">Page <?= $pageNumber ?> of <?= $pageCount ?></small>
                <nav aria-label="Student list pages">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $pageNumber <= 1 ? 'disabled' : '' ?>">
                            <?php if ($pageNumber > 1): ?>
                                <a class="page-link" href="<?= e($studentPageUrl($pageNumber - 1)) ?>">Previous</a>
                            <?php else: ?>
                                <span class="page-link">Previous</span>
                            <?php endif; ?>
                        </li>
                        <li class="page-item <?= $pageNumber >= $pageCount ? 'disabled' : '' ?>">
                            <?php if ($pageNumber < $pageCount): ?>
                                <a class="page-link" href="<?= e($studentPageUrl($pageNumber + 1)) ?>">Next</a>
                            <?php else: ?>
                                <span class="page-link">Next</span>
                            <?php endif; ?>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
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
                <form method="POST" action="<?= url('views/admin/students/edit/edit.php?id=' . urlencode($studentId)) ?>" class="student-modal-form">
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

<div class="modal fade" id="studentCreateModal" tabindex="-1" aria-hidden="true" data-open-on-load="<?= !empty($_GET['openAddStudent']) ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/admin/students/index/index.php') ?>" class="student-modal-form">
                <input type="hidden" name="action" value="create_student">
                <div class="modal-header">
                    <h5 class="modal-title">Add Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($createStudentErrors['general'])): ?><div class="alert alert-danger"><?= e($createStudentErrors['general']) ?></div><?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">First Name <span class="text-danger">*</span></label><input name="firstName" class="form-control" value="<?= e($createStudentOld['firstName'] ?? '') ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Last Name <span class="text-danger">*</span></label><input name="lastName" class="form-control" value="<?= e($createStudentOld['lastName'] ?? '') ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Admission No.</label><input name="admissionNo" class="form-control" value="<?= e($createStudentOld['admissionNo'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">Select Gender</option><?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label): ?><option value="<?= e($value) ?>" <?= strtolower((string) ($createStudentOld['gender'] ?? '')) === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Class</label><select name="class" class="form-select"><option value="">Select Class</option><?php foreach ($studentClassOptions as $classOption): ?><option value="<?= e($classOption) ?>" <?= ($createStudentOld['class'] ?? '') === $classOption ? 'selected' : '' ?>><?= e($classOption) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Form / Level</label><select name="form" class="form-select"><option value="">Select Form</option><?php foreach (['Form 1', 'Form 2', 'Form 3', 'Form 4'] as $formOption): ?><option value="<?= e($formOption) ?>" <?= ($createStudentOld['form'] ?? $createStudentOld['level'] ?? '') === $formOption ? 'selected' : '' ?>><?= e($formOption) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Class Code</label><select name="course" class="form-select"><option value="">Select Class Code</option><?php foreach ($studentCourseOptions as $courseOption): ?><option value="<?= e($courseOption) ?>" <?= ($createStudentOld['course'] ?? '') === $courseOption ? 'selected' : '' ?>><?= e($courseOption) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">NHIS Number</label><input name="nhisNumber" class="form-control" value="<?= e($createStudentOld['nhisNumber'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">House</label><select name="houseId" class="form-select"><option value="">— Unassigned —</option><?php foreach ($houses as $houseOptionId => $houseOptionName): ?><option value="<?= e($houseOptionId) ?>" <?= ($createStudentOld['houseId'] ?? '') === $houseOptionId ? 'selected' : '' ?>><?= e($houseOptionName) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Residence Type</label><select name="residenceType" class="form-select"><option value="boarding" <?= ($createStudentOld['residenceType'] ?? 'boarding') === 'boarding' ? 'selected' : '' ?>>Boarding</option><option value="day" <?= ($createStudentOld['residenceType'] ?? '') === 'day' ? 'selected' : '' ?>>Day</option></select></div>
                        <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended', 'graduated' => 'Graduated'] as $value => $label): ?><option value="<?= e($value) ?>" <?= ($createStudentOld['status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Guardian Name</label><input name="guardianName" class="form-control" value="<?= e($createStudentOld['guardianName'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Guardian Phone</label><input name="guardianPhone" class="form-control" value="<?= e($createStudentOld['guardianPhone'] ?? '') ?>"></div>
                        <div class="col-12"><label class="form-label">Guardian Email</label><input type="email" name="guardianEmail" class="form-control" value="<?= e($createStudentOld['guardianEmail'] ?? '') ?>"><?php if (!empty($createStudentErrors['guardianEmail'])): ?><div class="text-danger small mt-1"><?= e($createStudentErrors['guardianEmail']) ?></div><?php endif; ?></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Add Student</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="studentImportModal" tabindex="-1" aria-hidden="true" data-open-on-load="<?= !empty($_GET['openImport']) ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" action="<?= url('views/admin/students/bulk-import/bulk-import.php') ?>" class="student-modal-form">
                <input type="hidden" name="action" value="import_students">
                <input type="hidden" name="returnToStudents" value="1">
                <div class="modal-header"><h5 class="modal-title">Import Students</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <p class="text-muted">Upload a CSV or Excel file using the student import template.</p>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">House <span class="text-danger">*</span></label><select name="houseId" class="form-select" required><option value="">Select House</option><?php foreach ($houses as $houseOptionId => $houseOptionName): ?><option value="<?= e($houseOptionId) ?>"><?= e($houseOptionName) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Residence Type</label><select name="residenceType" class="form-select"><option value="boarding">Boarding</option><option value="day">Day</option></select></div>
                        <div class="col-12"><label class="form-label">Student file</label><input type="file" name="students_file" class="form-control" accept=".csv,.xlsx" required></div>
                        <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="createAccounts" id="studentImportCreateAccounts" value="1"><label class="form-check-label" for="studentImportCreateAccounts">Create login accounts for imported students</label></div>
                        <div class="col-12"><label class="form-label">Temporary password (optional)</label><input type="text" name="temporaryPassword" class="form-control" autocomplete="off"></div>
                    </div>
                    <a href="<?= url('views/admin/students/bulk-import/bulk-import.php?download_template=1') ?>" class="btn btn-outline-success btn-sm mt-3"><i class="bi bi-download me-1"></i>Download template CSV</a>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success"><i class="bi bi-upload me-1"></i>Import Students</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal[data-open-on-load="true"]').forEach(function (modalElement) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });
});
</script>

<?php require APP_ROOT . '/app/views/components/modal/modal.php'; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
