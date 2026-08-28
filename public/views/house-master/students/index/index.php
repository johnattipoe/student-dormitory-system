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

// Handle batch email/notification
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = sanitize($_POST['action']);
    
    if ($action === 'bulk_email' && !empty($_POST['studentIds']) && !empty($_POST['subject']) && !empty($_POST['message'])) {
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

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$rooms = RoomService::all($houseId);
$roomMap = [];
foreach ($rooms as $room) {
    $roomMap[(string) ($room['id'] ?? '')] = (string) ($room['roomNumber'] ?? $room['id'] ?? '');
}
$studentSearch = strtolower(sanitize($_GET['search'] ?? ''));
$studentStatus = sanitize($_GET['status'] ?? '');
if ($studentSearch !== '' || $studentStatus !== '') {
    $students = array_values(array_filter($students, function ($student) use ($studentSearch, $studentStatus) {
        $haystack = strtolower(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' ' . ($student['admissionNo'] ?? '') . ' ' . ($student['class'] ?? '') . ' ' . ($student['course'] ?? '')));
        return ($studentSearch === '' || str_contains($haystack, $studentSearch))
            && ($studentStatus === '' || ($student['status'] ?? '') === $studentStatus);
    }));
}
$activeStudentCount = count(array_filter($students, fn($student) => ($student['status'] ?? '') === 'active'));
$inactiveStudentCount = count(array_filter($students, fn($student) => ($student['status'] ?? '') === 'inactive'));
$suspendedStudentCount = count(array_filter($students, fn($student) => ($student['status'] ?? '') === 'suspended'));
$assignedRoomCount = count(array_filter($students, fn($student) => !empty($student['roomId'])));
$unassignedRoomCount = max(0, count($students) - $assignedRoomCount);

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
                <a href="<?= url('views/house-master/students/create/create.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Add Student
                </a>
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

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-7">
                        <input name="search" class="form-control form-control-sm" placeholder="Search by name, admission number, class, or course..." value="<?= e($studentSearch) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $studentStatus === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $studentStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="suspended" <?= $studentStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1">Filter</button> 
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/students/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Students Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-success"></i>Assigned House Students</h6>
                <small class="text-muted">Showing <?= count($students) ?> residents</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
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
                                        <td><span class="badge <?= $stBadge ?>"><?= ucfirst(e($st)) ?></span></td>
                                        <td class="text-end text-nowrap">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/students/profile/profile.php?studentId=' . urlencode((string) ($student['id'] ?? ''))) ?>" title="View"><i class="bi bi-eye"></i></a> 
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/house-master/students/edit/edit.php?studentId=' . urlencode((string) ($student['id'] ?? ''))) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <a class="btn btn-sm btn-outline-danger" href="<?= url('views/house-master/students/delete/delete.php?id=' . urlencode((string) ($student['id'] ?? ''))) ?>" title="Delete"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No students found registered under your house.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

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
