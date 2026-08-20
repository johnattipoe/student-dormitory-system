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

$houseId = current_user()['houseId'] ?? null;
$allowedStudentIds = array_map(fn($student) => (string) ($student['id'] ?? ''), StudentService::all($houseId));

// Handle bulk flag operations
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $studentIds = (array) ($_POST['studentIds'] ?? []);
    $studentIds = array_values(array_intersect(array_map('strval', $studentIds), $allowedStudentIds));
    
    if ($action === 'bulk_flag' && !empty($studentIds)) {
        $flagType = sanitize($_POST['flagType'] ?? '');
        $reason = sanitize($_POST['reason'] ?? '');
        
        if (!$flagType) {
            $errors[] = 'Please select a flag type';
        } else {
            try {
                foreach ($studentIds as $sId) {
                    StudentService::updateFlags($sId, [
                        'flagged' => true,
                        'flagType' => $flagType,
                        'flagReason' => $reason,
                        'flaggedAt' => date('Y-m-d H:i:s'),
                        'flaggedBy' => current_user()['uid'],
                    ]);
                }
                flash('success', 'Flagged ' . count($studentIds) . ' student(s)');
                redirect(url('views/houseparent/students/bulk-flags.php'));
            } catch (Exception $e) {
                $errors[] = 'Failed to flag students: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'bulk_unflag' && !empty($studentIds)) {
        try {
            foreach ($studentIds as $sId) {
                StudentService::updateFlags($sId, ['flagged' => false]);
            }
            flash('success', 'Removed flags from ' . count($studentIds) . ' student(s)');
            redirect(url('views/houseparent/students/bulk-flags.php'));
        } catch (Exception $e) {
            $errors[] = 'Failed to remove flags: ' . $e->getMessage();
        }
    }
}

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$flaggedStudents = array_filter($students, fn($s) => ($s['flagged'] ?? false) == true);
$unflaggedStudents = array_filter($students, fn($s) => ($s['flagged'] ?? false) != true);

$filterType = sanitize($_GET['type'] ?? 'all');
$search = strtolower(sanitize($_GET['search'] ?? ''));
if ($filterType === 'flagged') {
    $displayStudents = $flaggedStudents;
} elseif ($filterType === 'unflagged') {
    $displayStudents = $unflaggedStudents;
} else {
    $displayStudents = $students;
}
if ($search !== '') {
    $displayStudents = array_values(array_filter($displayStudents, function ($student) use ($search) {
        return str_contains(strtolower(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' ' . ($student['admissionNo'] ?? '') . ' ' . ($student['email'] ?? '') . ' ' . ($student['flagType'] ?? ''))), $search);
    }));
}

$pageTitle = 'Bulk Flag Management';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/houseparent/students/index.php')],
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
            <div><h5 class="mb-1">Bulk Flag Management</h5><p class="text-muted mb-0">Review and manage student concerns.</p></div>
            <span class="badge bg-warning text-dark"><?= e((string) count($flaggedStudents)) ?> flagged</span>
        </div>

        <div class="card stat-card p-3 mb-3"><form method="GET" class="row g-2"><input type="hidden" name="type" value="<?= e($filterType) ?>"><div class="col-md-9"><input name="search" class="form-control form-control-sm" placeholder="Search student, admission number, or flag type" value="<?= e($search) ?>"></div><div class="col-md-3"><button class="btn btn-primary btn-sm">Search</button> <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/houseparent/students/bulk-flags.php?type=' . urlencode($filterType)) ?>">Reset</a></div></form></div>

        <!-- Filter Tabs -->
        <div class="mb-3">
            <div class="btn-group" role="group">
                <a href="<?= url('views/houseparent/students/bulk-flags.php') ?>" class="btn btn-outline-primary btn-sm <?= $filterType === 'all' ? 'active' : '' ?>">
                    All (<?= count($students) ?>)
                </a>
                <a href="<?= url('views/houseparent/students/bulk-flags.php?type=flagged') ?>" class="btn btn-outline-warning btn-sm <?= $filterType === 'flagged' ? 'active' : '' ?>">
                    Flagged (<?= count($flaggedStudents) ?>)
                </a>
                <a href="<?= url('views/houseparent/students/bulk-flags.php?type=unflagged') ?>" class="btn btn-outline-success btn-sm <?= $filterType === 'unflagged' ? 'active' : '' ?>">
                    Unflagged (<?= count($unflaggedStudents) ?>)
                </a>
            </div>
        </div>

        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between mb-3">
                <div>
                    <strong id="selectedCount">0</strong> student(s) selected
                    <a href="#" id="selectAllBtn" class="ms-2 small">Select all</a>
                    <a href="#" id="clearAllBtn" class="ms-2 small">Clear all</a>
                </div>
                <div class="btn-group" role="group">
                    <button class="btn btn-warning btn-sm" id="flagSelectedBtn" data-bs-toggle="modal" data-bs-target="#flagModal" disabled>
                        <i class="bi bi-exclamation-triangle"></i> Flag Selected
                    </button>
                    <button class="btn btn-success btn-sm" id="unflagSelectedBtn" data-bs-toggle="modal" data-bs-target="#unflagModal" disabled>
                        <i class="bi bi-check-circle"></i> Unflag Selected
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                            </th>
                            <th>Name</th>
                            <th>Admission No.</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Flag Type</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($displayStudents)): ?>
                            <?php foreach ($displayStudents as $student): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input student-row-checkbox" data-student-id="<?= e((string) ($student['id'] ?? '')) ?>">
                                    </td>
                                    <td><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></td>
                                    <td><?= e($student['admissionNo'] ?? '') ?></td>
                                    <td><?= e($student['email'] ?? '') ?></td>
                                    <td>
                                        <?php if ($student['flagged'] ?? false): ?>
                                            <span class="badge bg-warning">Flagged</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($student['flagType'] ?? '—') ?></td>
                                    <td><?= e(substr($student['flagReason'] ?? '', 0, 30)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No students found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Flag Modal -->
<div class="modal fade" id="flagModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Flag Students</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="flagForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_flag">
                    <div id="flagStudentsList"></div>

                    <div class="mb-3">
                        <label class="form-label">Flag Type</label>
                        <select name="flagType" class="form-select" required>
                            <option value="">-- Select type --</option>
                            <option value="attendance_concern">Attendance Concern</option>
                            <option value="academic_concern">Academic Concern</option>
                            <option value="behavioral_concern">Behavioral Concern</option>
                            <option value="health_concern">Health Concern</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="4" placeholder="Detailed reason for flagging" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Flag Students</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Unflag Modal -->
<div class="modal fade" id="unflagModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Unflag Students</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_unflag">
                    <div id="unflagStudentsList"></div>
                    <p class="text-muted">Are you sure you want to remove flags from the selected students?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Remove Flags</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const selectedStudents = new Set();

    // Select all checkbox
    document.getElementById('selectAllCheckbox')?.addEventListener('change', function() {
        document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelection();
    });

    // Individual checkboxes
    document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelection);
    });

    // Select all button
    document.getElementById('selectAllBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
            checkbox.checked = true;
        });
        updateSelection();
    });

    // Clear all button
    document.getElementById('clearAllBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelection();
    });

    // Update selection display
    function updateSelection() {
        selectedStudents.clear();
        const flagForm = document.getElementById('flagForm');
        let html = '';

        document.querySelectorAll('.student-row-checkbox:checked').forEach(checkbox => {
            selectedStudents.add(checkbox.getAttribute('data-student-id'));
            html += '<input type="hidden" name="studentIds[]" value="' + checkbox.getAttribute('data-student-id') + '">';
        });

        // Clear old inputs and add new ones
        flagForm.querySelectorAll('input[name="studentIds[]"]').forEach(input => input.remove());
        flagForm.insertAdjacentHTML('afterbegin', html);

        document.getElementById('selectedCount').textContent = selectedStudents.size;
        document.getElementById('flagSelectedBtn').disabled = selectedStudents.size === 0;
        document.getElementById('unflagSelectedBtn').disabled = selectedStudents.size === 0;

        // Update modal lists
        const studentsList = Array.from(selectedStudents).join(', ');
        document.getElementById('flagStudentsList').innerHTML = '<p class="mb-3"><strong>Selected:</strong> ' + (selectedStudents.size > 0 ? selectedStudents.size + ' student(s)' : 'None') + '</p>';
        document.getElementById('unflagStudentsList').innerHTML = '<p class="mb-3"><strong>Selected:</strong> ' + (selectedStudents.size > 0 ? selectedStudents.size + ' student(s)' : 'None') + '</p>';
    }

    // Initialize
    updateSelection();
</script>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
