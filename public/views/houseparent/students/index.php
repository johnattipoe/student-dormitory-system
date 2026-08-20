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
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\NotificationService;

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
                // Send notification via system
                $notificationService->create([
                    'userId' => $sId,
                    'title' => $subject,
                    'message' => $message,
                    'type' => 'email',
                    'from' => current_user()['uid'],
                    'createdAt' => date('Y-m-d H:i:s'),
                ]);
            }
            flash('success', 'Notifications sent to ' . count($studentIds) . ' student(s).');
            redirect(url('views/houseparent/students/index.php'));
        } catch (Exception $e) {
            $errors[] = 'Failed to send notifications: ' . $e->getMessage();
        }
    }
}

$students = StudentService::all(current_user()['houseId'] ?? null);
$studentSearch = strtolower(sanitize($_GET['search'] ?? ''));
if ($studentSearch !== '') {
    $students = array_values(array_filter($students, function ($student) use ($studentSearch) {
        return str_contains(strtolower(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' ' . ($student['admissionNo'] ?? '') . ' ' . ($student['email'] ?? ''))), $studentSearch);
    }));
}

$pageTitle = 'Houseparent Students';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/houseparent/students/index.php'), 'active' => true],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/houseparent/attendance/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/houseparent/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/houseparent/visitors/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/houseparent/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Houseparent Students</h5>
            <div class="btn-group" role="group">
                <a class="btn btn-outline-success btn-sm" href="<?= url('views/houseparent/students/bulk-import.php') ?>"><i class="bi bi-file-earmark-arrow-up"></i> Upload CSV/Excel</a>
                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#bulkEmailModal">
                    <i class="bi bi-envelope-bulk"></i> Send Bulk Email
                </button>
                <a href="<?= url('views/houseparent/students/bulk-flags.php') ?>" class="btn btn-warning btn-sm">
                    <i class="bi bi-exclamation-triangle"></i> Manage Flags
                </a>
                <a href="<?= url('views/houseparent/students/index.php') ?>" class="btn btn-outline-primary btn-sm">Student search</a>
            </div>
        </div>
        <div class="card stat-card p-3 mb-3"><form method="GET" class="row g-2"><div class="col-md-9"><input name="search" class="form-control form-control-sm" placeholder="Search name, admission number, or email" value="<?= e($studentSearch) ?>"></div><div class="col-md-3"><button class="btn btn-primary btn-sm">Filter</button> <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/houseparent/students/index.php') ?>">Reset</a></div></form></div>
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAllStudentsTable" class="form-check-input">
                        </th>
                        <th>Admission No.</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Room</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($students)): ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input student-row-checkbox" data-student-id="<?= e((string) ($student['id'] ?? '')) ?>" data-email="<?= e($student['email'] ?? '') ?>">
                                </td>
                                <td><?= e($student['admissionNo'] ?? '') ?></td>
                                <td><a href="<?= url('views/houseparent/students/profile.php?studentId=' . urlencode((string) ($student['id'] ?? ''))) ?>" class="text-decoration-none"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></a></td>
                                <td><?= e($student['email'] ?? '') ?></td>
                                <td><?= e($student['course'] ?? '') ?></td>
                                <td><?= e($student['roomId'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= ($student['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= e($student['status'] ?? 'unknown') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No students found for your house.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bulk Email Modal -->
    <div class="modal fade" id="bulkEmailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Bulk Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_email">
                        
                        <div class="alert alert-info">
                            <small>
                                <strong id="selectedStudentsCount">0</strong> student(s) selected.
                                <a href="#" id="selectAllLink" class="ms-2">Select all</a> | 
                                <a href="#" id="clearAllLink" class="ms-2">Clear all</a>
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Recipient Students</label>
                            <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                <div id="selectedStudentsList">
                                    <small class="text-muted">No students selected. Check the table above.</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="Email subject" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="6" placeholder="Email body" required></textarea>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" name="include_guardians" id="includeGuardians" class="form-check-input">
                            <label class="form-check-label" for="includeGuardians">
                                Also send to guardians
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="sendEmailBtn">Send Email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Track selected students
        const selectedStudents = new Set();

        // Select all checkbox in table
        document.getElementById('selectAllStudentsTable')?.addEventListener('change', function() {
            document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
                updateSelectedStudents();
            });
        });

        // Individual row checkboxes
        document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedStudents);
        });

        // Select all link in modal
        document.getElementById('selectAllLink')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectedStudents();
        });

        // Clear all link
        document.getElementById('clearAllLink')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            selectedStudents.clear();
            updateSelectedStudents();
        });

        // Update selected students display
        function updateSelectedStudents() {
            selectedStudents.clear();
            const studentsList = document.getElementById('selectedStudentsList');
            const html = [];

            document.querySelectorAll('.student-row-checkbox:checked').forEach(checkbox => {
                const studentId = checkbox.getAttribute('data-student-id');
                const email = checkbox.getAttribute('data-email');
                selectedStudents.add(studentId);
                html.push('<div><small><input type="hidden" name="studentIds[]" value="' + studentId + '"><i class="bi bi-check-circle text-success"></i> ' + email + '</small></div>');
            });

            document.getElementById('selectedStudentsCount').textContent = selectedStudents.size;
            if (html.length === 0) {
                studentsList.innerHTML = '<small class="text-muted">No students selected. Check the table above.</small>';
            } else {
                studentsList.innerHTML = html.join('');
            }

            document.getElementById('sendEmailBtn').disabled = selectedStudents.size === 0;
        }

        // Initialize on page load
        updateSelectedStudents();
    </script>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
