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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\NotificationService;
use App\Services\RoomService;
use App\Services\HouseService;
use App\Services\FirebaseService;

// Handle batch email/notification
$errors = [];
$createStudentOld = $_SESSION['_house_student_create_old'] ?? [];
$createStudentErrors = $_SESSION['_house_student_create_errors'] ?? [];
$editStudentOld = $_SESSION['_house_student_edit_old'] ?? [];
$editStudentOldId = (string) ($_SESSION['_house_student_edit_old_id'] ?? '');
unset(
    $_SESSION['_house_student_create_old'],
    $_SESSION['_house_student_create_errors'],
    $_SESSION['_house_student_edit_old'],
    $_SESSION['_house_student_edit_old_id']
);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = sanitize($_POST['action']);
    
    if ($action === 'create_student') {
        $houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
        $house = $houseId !== '' ? HouseService::find($houseId) : null;
        $formValue = sanitize($_POST['form'] ?? $_POST['level'] ?? 'Form 1');
        $studentData = [
            'firstName' => sanitize($_POST['firstName'] ?? ''),
            'lastName' => sanitize($_POST['lastName'] ?? ''),
            'class' => sanitize($_POST['class'] ?? ''),
            'form' => $formValue,
            'level' => $formValue,
            'gender' => sanitize($_POST['gender'] ?? 'Male'),
            'nhisNumber' => sanitize($_POST['nhisNumber'] ?? ''),
            'admissionNo' => sanitize($_POST['admissionNo'] ?? ''),
            'course' => sanitize($_POST['course'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'houseId' => $houseId,
            'residenceType' => sanitize($_POST['residenceType'] ?? 'boarding'),
            'guardianName' => sanitize($_POST['guardianName'] ?? ''),
            'guardianPhone' => sanitize($_POST['guardianPhone'] ?? ''),
            'guardianEmail' => sanitize($_POST['guardianEmail'] ?? ''),
            'status' => sanitize($_POST['status'] ?? 'active'),
        ];
        $createStudentErrors = validate_required($studentData, ['firstName', 'lastName', 'admissionNo']);
        if ($studentData['guardianEmail'] !== '' && !validate_email($studentData['guardianEmail'])) {
            $createStudentErrors['guardianEmail'] = 'Guardian email is invalid.';
        }

        if (!$house) {
            $createStudentErrors['general'] = 'Your account must have an assigned house to add students.';
        } elseif (empty($createStudentErrors)) {
            try {
                StudentService::create($studentData);
                flash('success', 'Student added to ' . ($house['name'] ?? 'your house') . '.');
                redirect(url('views/house-master/students/index/index.php'));
            } catch (Throwable $e) {
                $createStudentErrors['general'] = 'Unable to create student: ' . $e->getMessage();
            }
        }

        $_SESSION['_house_student_create_old'] = $studentData;
        $_SESSION['_house_student_create_errors'] = $createStudentErrors;
        flash('error', 'Please correct the student details and try again.');
        redirect(url('views/house-master/students/index/index.php?openAddStudent=1'));
    } elseif ($action === 'update_student') {
        $studentId = trim((string) sanitize($_POST['studentId'] ?? ''));
        $houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
        $studentRecord = $studentId !== '' ? StudentService::find($studentId) : null;
        if (!$studentRecord || (string) ($studentRecord['houseId'] ?? '') !== $houseId) {
            flash('error', 'Student not found in your assigned house.');
            redirect(url('views/house-master/students/index/index.php'));
        }

        $formValue = sanitize($_POST['form'] ?? $_POST['level'] ?? ($studentRecord['form'] ?? $studentRecord['level'] ?? 'Form 1'));
        $studentData = [
            'firstName' => sanitize($_POST['firstName'] ?? ''),
            'lastName' => sanitize($_POST['lastName'] ?? ''),
            'class' => sanitize($_POST['class'] ?? ''),
            'form' => $formValue,
            'level' => $formValue,
            'nhisNumber' => sanitize($_POST['nhisNumber'] ?? ''),
            'admissionNo' => sanitize($_POST['admissionNo'] ?? ''),
            'course' => sanitize($_POST['course'] ?? ''),
            'gender' => sanitize($_POST['gender'] ?? 'Male'),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'residenceType' => sanitize($_POST['residenceType'] ?? 'boarding'),
            'status' => sanitize($_POST['status'] ?? 'active'),
            'guardianName' => sanitize($_POST['guardianName'] ?? ''),
            'guardianPhone' => sanitize($_POST['guardianPhone'] ?? ''),
            'guardianEmail' => sanitize($_POST['guardianEmail'] ?? ''),
        ];
        $studentErrors = validate_required($studentData, ['firstName', 'lastName', 'admissionNo']);
        if ($studentData['guardianEmail'] !== '' && !validate_email($studentData['guardianEmail'])) {
            $studentErrors['guardianEmail'] = 'Guardian email is invalid.';
        }

        if (empty($studentErrors)) {
            try {
                StudentService::update($studentId, $studentData);
                flash('success', 'Student profile updated successfully.');
                redirect(url('views/house-master/students/index/index.php'));
            } catch (Throwable $e) {
                $studentErrors['general'] = 'Unable to update student: ' . $e->getMessage();
            }
        }
        if (empty($studentErrors['general'])) {
            $studentErrors['general'] = 'Please correct the student details and try again.';
        }
        $studentData['general'] = $studentErrors['general'];
        if (!empty($studentErrors['guardianEmail'])) {
            $studentData['guardianEmailError'] = $studentErrors['guardianEmail'];
        }
        $_SESSION['_house_student_edit_old'] = $studentData;
        $_SESSION['_house_student_edit_old_id'] = $studentId;
        flash('error', 'Please correct the student details and try again.');
        redirect(url('views/house-master/students/index/index.php?editStudent=' . urlencode($studentId)));
    } elseif ($action === 'delete_student') {
        $studentId = trim((string) sanitize($_POST['studentId'] ?? ''));
        $houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
        $studentRecord = $studentId !== '' ? StudentService::find($studentId) : null;
        if (!$studentRecord || (string) ($studentRecord['houseId'] ?? '') !== $houseId) {
            flash('error', 'Student not found in your assigned house.');
            redirect(url('views/house-master/students/index/index.php'));
        }
        StudentService::delete($studentId);
        flash('success', 'Student deleted successfully.');
        redirect(url('views/house-master/students/index/index.php'));
    } elseif ($action === 'bulk_email' && !empty($_POST['studentIds']) && !empty($_POST['subject']) && !empty($_POST['message'])) {
        $studentIds = (array) $_POST['studentIds'];
        $subject = sanitize($_POST['subject']);
        $message = sanitize($_POST['message']);
        $notificationService = new NotificationService();
        
        try {
            foreach ($studentIds as $sId) {
                $notificationService->create([
                    'userId' => $sId,
                    'title' => $subject,
                    'message' => $message,
                    'type' => 'info',
                    'from' => current_user()['uid'] ?? current_user()['id'] ?? null,
                    'createdAt' => date('Y-m-d H:i:s'),
                ]);
            }
            flash('success', 'Notifications sent to ' . count($studentIds) . ' student(s).');
            redirect(url('views/house-master/students/index/index.php'));
        } catch (Exception $e) {
            $errors[] = 'Failed to send notifications: ' . $e->getMessage();
        }
    }
}

$houseId = trim((string) (current_user()['houseId'] ?? current_user()['house_id'] ?? ''));
$house = $houseId ? HouseService::find((string) $houseId) : null;
$rooms = $houseId !== '' ? RoomService::all($houseId) : [];
$classRecords = $houseId !== '' ? FirebaseService::getInstance()->getCollection('classes', [], 500) : [];
$studentClassOptions = array_values(array_unique(array_filter(array_map(
    static function (array $class): string {
        $code = trim((string) ($class['classCode'] ?? ''));
        return $code !== '' ? $code : trim((string) ($class['className'] ?? ''));
    },
    array_filter($classRecords, static fn(array $class): bool => ($class['status'] ?? 'active') === 'active')
))));
if (empty($studentClassOptions)) {
    $studentClassOptions = ['SHS 1A', 'SHS 1B', 'SHS 2A', 'SHS 2B', 'SHS 3A', 'SHS 3B'];
}
$studentCourseOptions = [
    'General Science',
    'General Arts',
    'Business',
    'Home Economics',
    'Visual Arts',
    'Agricultural Science',
    'Technical',
];
$roomMap = [];
foreach ($rooms as $room) {
    $roomMap[(string) ($room['id'] ?? '')] = (string) ($room['roomNumber'] ?? $room['id'] ?? '');
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$studentStats = $houseId !== ''
    ? StudentService::stats($houseId)
    : ['total' => 0, 'active' => 0, 'inactive' => 0, 'suspended' => 0, 'assignedRooms' => 0];
$activeStudentCount = (int) ($studentStats['active'] ?? 0);
$inactiveStudentCount = (int) ($studentStats['inactive'] ?? 0);
$suspendedStudentCount = (int) ($studentStats['suspended'] ?? 0);
$assignedRoomCount = (int) ($studentStats['assignedRooms'] ?? 0);
$totalStudents = (int) ($studentStats['total'] ?? 0);
$unassignedRoomCount = max(0, $totalStudents - $assignedRoomCount);
$totalPages = max(1, (int) ceil($totalStudents / $limit));
$page = min($page, $totalPages);
$students = $houseId !== '' ? StudentService::page($houseId, $limit, $page) : [];

$pageTitle = 'Resident Students';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php'), 'active' => true],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>
    
    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-mortarboard-fill text-success me-2"></i>House Residents Directory
                </h4>
                <p class="text-muted mb-0">Manage enrolled residents in your assigned dormitory house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkEmailModal">
                    <i class="bi bi-envelope me-1"></i> Broadcast Notice
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#houseStudentCreateModal" <?= !$house ? 'disabled title="Assign a house to this account first"' : '' ?>>
                    <i class="bi bi-plus-lg me-1"></i> Add Student
                </button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">House Residents</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) count($students)) ?></h3>
                            <span class="small text-muted">Total enrolled</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Active Students</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $activeStudentCount) ?></h3>
                            <span class="small text-muted">In good standing</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Room Assigned</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $assignedRoomCount) ?></h3>
                            <span class="small text-muted"><?= $unassignedRoomCount ?> awaiting room</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-door-open fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Suspended</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $suspendedStudentCount) ?></h3>
                            <span class="small text-muted">Disciplinary flags</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-person-dash fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Students Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-success"></i>Assigned House Students</h6>
                <div class="d-flex align-items-center gap-3">
                    <form method="GET" class="m-0" role="search" data-live-search data-live-search-table="#houseStudentsTable">
                        <input type="search" name="search" class="form-control form-control-sm" placeholder="Search students..." aria-label="Search assigned house students" style="width: 240px; max-width: 100%;">
                    </form>
                    <small class="text-muted text-nowrap">Showing <?= e((string) $totalStudents) ?> residents</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="houseStudentsTable" class="table table-hover align-middle mb-0" data-no-copy-export="true" data-external-table-search="true">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAllStudentsTable" class="form-check-input">
                                </th>
                                <th>Admission No.</th>
                                <th>Full Name</th>
                                <th>Class</th>
                                <th>Form</th>
                                <th>Class Code</th>
                                <th>Room</th>
                                <th>Residence Type</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($students)): ?>
                                <?php foreach ($students as $student): ?>
                                    <?php 
                                    $st = strtolower((string)($student['status'] ?? 'active'));
                                    $stBadge = match($st) { 'active' => 'bg-success', 'suspended' => 'bg-danger', default => 'bg-secondary' };
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input student-row-checkbox" data-student-id="<?= e((string) ($student['id'] ?? '')) ?>" data-name="<?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?>">
                                        </td>
                                        <td><span class="font-monospace text-muted"><?= e($student['admissionNo'] ?? '') ?></span></td>
                                        <td><strong class="text-dark"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></strong></td>
                                        <td><?= e($student['class'] ?? '—') ?></td>
                                        <td><?= e($student['form'] ?? $student['level'] ?? '—') ?></td>
                                        <td><small class="text-muted"><?= e($student['course'] ?? '—') ?></small></td>
                                        <td><span class="badge bg-light text-dark border">Room <?= e($roomMap[(string) ($student['roomId'] ?? '')] ?? ($student['roomId'] ?? '—')) ?></span></td>
                                        <td><span class="badge bg-warning text-dark"><?= e(strtolower((string) ($student['residenceType'] ?? 'boarding')) === 'day' ? 'Day' : 'Boarding') ?></span></td>
                                        <td><span class="badge <?= $stBadge ?>"><?= ucfirst(e($st)) ?></span></td>
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#houseStudentViewModal-<?= e((string) ($student['id'] ?? '')) ?>" title="View"><i class="bi bi-eye"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#houseStudentEditModal-<?= e((string) ($student['id'] ?? '')) ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#houseStudentDeleteModal-<?= e((string) ($student['id'] ?? '')) ?>" title="Delete"><i class="bi bi-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">No students found registered under your house.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php $paginationBaseUrl = url('views/house-master/students/index/index.php'); require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="houseStudentCreateModal" tabindex="-1" aria-hidden="true" data-open-on-load="<?= !empty($_GET['openAddStudent']) ? 'true' : 'false' ?>">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="<?= url('views/house-master/students/index/index.php') ?>" class="student-modal-form">
                    <input type="hidden" name="action" value="create_student">
                    <div class="modal-header"><h5 class="modal-title">Add Student to <?= e($house['name'] ?? 'Your House') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <?php if (!empty($createStudentErrors['general'])): ?><div class="alert alert-danger"><?= e($createStudentErrors['general']) ?></div><?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">First Name <span class="text-danger">*</span></label><input name="firstName" class="form-control" value="<?= e($createStudentOld['firstName'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Last Name <span class="text-danger">*</span></label><input name="lastName" class="form-control" value="<?= e($createStudentOld['lastName'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Admission No. <span class="text-danger">*</span></label><input name="admissionNo" class="form-control" value="<?= e($createStudentOld['admissionNo'] ?? '') ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="Male" <?= ($createStudentOld['gender'] ?? 'Male') === 'Male' ? 'selected' : '' ?>>Male</option><option value="Female" <?= ($createStudentOld['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option></select></div>
                            <div class="col-md-6"><label class="form-label">Form / Level</label><select name="form" class="form-select"><?php foreach (['Form 1', 'Form 2', 'Form 3', 'Form 4'] as $formOption): ?><option value="<?= e($formOption) ?>" <?= ($createStudentOld['form'] ?? 'Form 1') === $formOption ? 'selected' : '' ?>><?= e($formOption) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label">Class / Section</label><select name="class" class="form-select"><option value="">Select Class / Section</option><?php foreach ($studentClassOptions as $classOption): ?><option value="<?= e($classOption) ?>" <?= ($createStudentOld['class'] ?? '') === $classOption ? 'selected' : '' ?>><?= e($classOption) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label">Course / Programme</label><select name="course" class="form-select"><option value="">Select Programme</option><?php foreach ($studentCourseOptions as $courseOption): ?><option value="<?= e($courseOption) ?>" <?= ($createStudentOld['course'] ?? '') === $courseOption ? 'selected' : '' ?>><?= e($courseOption) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label">NHIS Number</label><input name="nhisNumber" class="form-control" value="<?= e($createStudentOld['nhisNumber'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Student Phone</label><input name="phone" class="form-control" value="<?= e($createStudentOld['phone'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">Residence Type</label><select name="residenceType" class="form-select"><option value="boarding" <?= ($createStudentOld['residenceType'] ?? 'boarding') === 'boarding' ? 'selected' : '' ?>>Boarding</option><option value="day" <?= ($createStudentOld['residenceType'] ?? '') === 'day' ? 'selected' : '' ?>>Day</option></select></div>
                            <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $value => $label): ?><option value="<?= e($value) ?>" <?= ($createStudentOld['status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
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

    <?php foreach ($students as $student): ?>
        <?php
        $modalStudentId = (string) ($student['id'] ?? '');
        $modalStudentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
        $modalStudentValues = $editStudentOldId === $modalStudentId ? array_merge($student, $editStudentOld) : $student;
        $modalClassOptions = $studentClassOptions;
        $modalStudentClass = (string) ($modalStudentValues['class'] ?? '');
        if ($modalStudentClass !== '' && !in_array($modalStudentClass, $modalClassOptions, true)) {
            $modalClassOptions[] = $modalStudentClass;
        }
        $modalCourseOptions = $studentCourseOptions;
        $modalStudentCourse = (string) ($modalStudentValues['course'] ?? '');
        if ($modalStudentCourse !== '' && !in_array($modalStudentCourse, $modalCourseOptions, true)) {
            $modalCourseOptions[] = $modalStudentCourse;
        }
        $modalFormOptions = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];
        $modalStudentForm = (string) ($modalStudentValues['form'] ?? $modalStudentValues['level'] ?? '');
        if ($modalStudentForm !== '' && !in_array($modalStudentForm, $modalFormOptions, true)) {
            $modalFormOptions[] = $modalStudentForm;
        }
        ?>
        <div class="modal fade" id="houseStudentViewModal-<?= e($modalStudentId) ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Student Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Name</dt><dd class="col-sm-7"><?= e($modalStudentName ?: 'Student') ?></dd>
                            <dt class="col-sm-5">Admission No.</dt><dd class="col-sm-7"><?= e($student['admissionNo'] ?? '—') ?></dd>
                            <dt class="col-sm-5">Gender</dt><dd class="col-sm-7"><?= e($student['gender'] ?? '—') ?></dd>
                            <dt class="col-sm-5">Class</dt><dd class="col-sm-7"><?= e($student['class'] ?? '—') ?></dd>
                            <dt class="col-sm-5">Form</dt><dd class="col-sm-7"><?= e($student['form'] ?? $student['level'] ?? '—') ?></dd>
                            <dt class="col-sm-5">Course</dt><dd class="col-sm-7"><?= e($student['course'] ?? '—') ?></dd>
                            <dt class="col-sm-5">NHIS Number</dt><dd class="col-sm-7"><?= e($student['nhisNumber'] ?? '—') ?></dd>
                            <dt class="col-sm-5">Phone</dt><dd class="col-sm-7"><?= e($student['phone'] ?? '—') ?></dd>
                            <dt class="col-sm-5">Room</dt><dd class="col-sm-7"><?= e($roomMap[(string) ($student['roomId'] ?? '')] ?? ($student['roomId'] ?? '—')) ?></dd>
                            <dt class="col-sm-5">Residence Type</dt><dd class="col-sm-7"><?= e(strtolower((string) ($student['residenceType'] ?? 'boarding')) === 'day' ? 'Day' : 'Boarding') ?></dd>
                            <dt class="col-sm-5">Status</dt><dd class="col-sm-7"><?= e(ucfirst((string) ($student['status'] ?? 'active'))) ?></dd>
                            <dt class="col-sm-5">Guardian</dt><dd class="col-sm-7"><?= e($student['guardianName'] ?? '—') ?></dd>
                            <dt class="col-sm-5">Guardian Phone</dt><dd class="col-sm-7"><?= e($student['guardianPhone'] ?? '—') ?></dd>
                            <dt class="col-sm-5">Guardian Email</dt><dd class="col-sm-7"><?= e($student['guardianEmail'] ?? '—') ?></dd>
                        </dl>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="houseStudentEditModal-<?= e($modalStudentId) ?>" tabindex="-1" aria-hidden="true" data-open-on-load="<?= !empty($_GET['editStudent']) && (string) $_GET['editStudent'] === $modalStudentId ? 'true' : 'false' ?>">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST" action="<?= url('views/house-master/students/index/index.php') ?>" class="student-modal-form">
                        <input type="hidden" name="action" value="update_student">
                        <input type="hidden" name="studentId" value="<?= e($modalStudentId) ?>">
                        <div class="modal-header"><h5 class="modal-title">Edit <?= e($modalStudentName ?: 'Student') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body">
                            <?php if ($editStudentOldId === $modalStudentId && !empty($editStudentOld['general'])): ?><div class="alert alert-danger"><?= e($editStudentOld['general']) ?></div><?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">First Name <span class="text-danger">*</span></label><input name="firstName" class="form-control" value="<?= e($modalStudentValues['firstName'] ?? '') ?>" required></div>
                                <div class="col-md-6"><label class="form-label">Last Name <span class="text-danger">*</span></label><input name="lastName" class="form-control" value="<?= e($modalStudentValues['lastName'] ?? '') ?>" required></div>
                                <div class="col-md-6"><label class="form-label">Admission No. <span class="text-danger">*</span></label><input name="admissionNo" class="form-control" value="<?= e($modalStudentValues['admissionNo'] ?? '') ?>" required></div>
                                <div class="col-md-6"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="Male" <?= strtolower((string) ($modalStudentValues['gender'] ?? 'Male')) === 'male' ? 'selected' : '' ?>>Male</option><option value="Female" <?= strtolower((string) ($modalStudentValues['gender'] ?? '')) === 'female' ? 'selected' : '' ?>>Female</option></select></div>
                                <div class="col-md-6"><label class="form-label">Form / Level</label><select name="form" class="form-select"><?php foreach ($modalFormOptions as $formOption): ?><option value="<?= e($formOption) ?>" <?= ($modalStudentForm ?: 'Form 1') === $formOption ? 'selected' : '' ?>><?= e($formOption) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-6"><label class="form-label">Class / Section</label><select name="class" class="form-select"><option value="">Select Class / Section</option><?php foreach ($modalClassOptions as $classOption): ?><option value="<?= e($classOption) ?>" <?= $modalStudentClass === $classOption ? 'selected' : '' ?>><?= e($classOption) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-6"><label class="form-label">Course / Programme</label><select name="course" class="form-select"><option value="">Select Programme</option><?php foreach ($modalCourseOptions as $courseOption): ?><option value="<?= e($courseOption) ?>" <?= $modalStudentCourse === $courseOption ? 'selected' : '' ?>><?= e($courseOption) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-6"><label class="form-label">NHIS Number</label><input name="nhisNumber" class="form-control" value="<?= e($modalStudentValues['nhisNumber'] ?? '') ?>"></div>
                                <div class="col-md-6"><label class="form-label">Student Phone</label><input name="phone" class="form-control" value="<?= e($modalStudentValues['phone'] ?? '') ?>"></div>
                                <div class="col-md-6"><label class="form-label">Residence Type</label><select name="residenceType" class="form-select"><option value="boarding" <?= strtolower((string) ($modalStudentValues['residenceType'] ?? 'boarding')) === 'boarding' ? 'selected' : '' ?>>Boarding</option><option value="day" <?= strtolower((string) ($modalStudentValues['residenceType'] ?? '')) === 'day' ? 'selected' : '' ?>>Day</option></select></div>
                                <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $value => $label): ?><option value="<?= e($value) ?>" <?= ($modalStudentValues['status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-6"><label class="form-label">Guardian Name</label><input name="guardianName" class="form-control" value="<?= e($modalStudentValues['guardianName'] ?? '') ?>"></div>
                                <div class="col-md-6"><label class="form-label">Guardian Phone</label><input name="guardianPhone" class="form-control" value="<?= e($modalStudentValues['guardianPhone'] ?? '') ?>"></div>
                                <div class="col-12"><label class="form-label">Guardian Email</label><input type="email" name="guardianEmail" class="form-control" value="<?= e($modalStudentValues['guardianEmail'] ?? '') ?>"><?php if ($editStudentOldId === $modalStudentId && !empty($editStudentOld['guardianEmailError'])): ?><div class="text-danger small mt-1"><?= e($editStudentOld['guardianEmailError']) ?></div><?php endif; ?></div>
                            </div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal fade" id="houseStudentDeleteModal-<?= e($modalStudentId) ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header border-0"><h5 class="modal-title text-danger">Delete Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body"><p class="mb-0">Delete <strong><?= e($modalStudentName ?: 'this student') ?></strong>?</p></div>
                <div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/house-master/students/index/index.php') ?>"><input type="hidden" name="action" value="delete_student"><input type="hidden" name="studentId" value="<?= e($modalStudentId) ?>"><button type="submit" class="btn btn-danger">Delete</button></form></div>
            </div></div>
        </div>
    <?php endforeach; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.modal[data-open-on-load="true"]').forEach(function (modalElement) {
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        });
    });
    </script>

    <!-- Bulk Message Modal -->
    <div class="modal fade" id="bulkEmailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Broadcast Notice to House Residents</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_email">
                        
                        <div class="alert alert-info py-2">
                            <small>
                                <strong id="selectedStudentsCount">0</strong> resident(s) selected.
                                <a href="#" id="selectAllLink" class="ms-2">Select all</a> | 
                                <a href="#" id="clearAllLink" class="ms-2">Clear all</a>
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Subject / Title</label>
                            <input type="text" name="subject" class="form-control" placeholder="e.g. Mandatory Dormitory Inspection Tonight" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Notice Message</label>
                            <textarea name="message" class="form-control" rows="5" placeholder="Enter instructions for house residents..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="sendBulkEmailBtn" disabled>
                            <i class="bi bi-send me-1"></i> Dispatch Notice
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const selectedStudents = new Set();

    document.getElementById('selectAllStudentsTable')?.addEventListener('change', function() {
        document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelection();
    });

    document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelection);
    });

    document.getElementById('selectAllLink')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
            checkbox.checked = true;
        });
        updateSelection();
    });

    document.getElementById('clearAllLink')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelection();
    });

    function updateSelection() {
        selectedStudents.clear();
        const form = document.querySelector('#bulkEmailModal form');
        
        document.querySelectorAll('.student-row-checkbox:checked').forEach(checkbox => {
            selectedStudents.add(checkbox.getAttribute('data-student-id'));
        });

        form.querySelectorAll('input[name="studentIds[]"]').forEach(input => input.remove());
        selectedStudents.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'studentIds[]';
            input.value = id;
            form.appendChild(input);
        });

        const countEl = document.getElementById('selectedStudentsCount');
        if (countEl) countEl.textContent = selectedStudents.size;
        const btn = document.getElementById('sendBulkEmailBtn');
        if (btn) btn.disabled = selectedStudents.size === 0;
    }

    updateSelection();
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
