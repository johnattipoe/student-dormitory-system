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

$houseId = current_user()['houseId'] ?? null;

// Handle bulk flag operations
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $studentIds = (array) ($_POST['studentIds'] ?? []);
    $allowedStudentIds = array_map(fn($student) => (string) ($student['id'] ?? ''), StudentService::all($houseId));
    $studentIds = array_values(array_intersect(array_map('strval', $studentIds), $allowedStudentIds));
    
    if ($action === 'bulk_flag' && !empty($studentIds)) {
        $flagType = sanitize($_POST['flagType'] ?? '');
        $reason = sanitize($_POST['reason'] ?? '');
        
        if (!$flagType) {
            $errors[] = 'Please select a flag type.';
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
                flash('success', 'Flagged ' . count($studentIds) . ' student(s).');
                redirect(url('views/house-master/students/bulk-flags/bulk-flags.php'));
            } catch (Exception $e) {
                $errors[] = 'Failed to flag students: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'bulk_unflag' && !empty($studentIds)) {
        try {
            foreach ($studentIds as $sId) {
                StudentService::updateFlags($sId, ['flagged' => false]);
            }
            flash('success', 'Removed flags from ' . count($studentIds) . ' student(s).');
            redirect(url('views/house-master/students/bulk-flags/bulk-flags.php'));
        } catch (Exception $e) {
            $errors[] = 'Failed to remove flags: ' . $e->getMessage();
        }
    }
}

$students = StudentService::all($houseId);
$studentIdsInHouse = array_map(fn($student) => (string) ($student['id'] ?? ''), $students);
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
        $haystack = strtolower(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' ' . ($student['admissionNo'] ?? '') . ' ' . ($student['email'] ?? '') . ' ' . ($student['flagType'] ?? '')));
        return str_contains($haystack, $search);
    }));
}
$flagTypeCounts = array_count_values(array_map(fn($student) => (string) ($student['flagType'] ?? 'Unclassified'), $flaggedStudents));

$flagRate = count($students) ? round(count($flaggedStudents) / count($students) * 100) : 0;

$pageTitle = 'Bulk Flag Management';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger mb-3"><i class="bi bi-exclamation-triangle me-2"></i><?= e($err) ?></div>
        <?php endforeach; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-flag-fill text-warning me-2"></i>Bulk Student Flag Management</h4>
                <p class="text-muted mb-0">Monitor behavioral, academic, or health concern alerts for resident students</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-success btn-sm" href="<?= url('views/house-master/reports/export/export.php?type=flags') ?>">
                    <i class="bi bi-filetype-csv me-1"></i>Export CSV
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/students/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back to Directory
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Residents</span>
                            <h3 class="fw-bold my-1 text-primary"><?= count($students) ?></h3>
                            <span class="small text-muted">House roster</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Flagged Students</span>
                            <h3 class="fw-bold my-1 text-warning"><?= count($flaggedStudents) ?></h3>
                            <span class="small text-muted">Requires follow-up</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-flag fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">House Flag Rate</span>
                            <h3 class="fw-bold my-1 text-info"><?= $flagRate ?>%</h3>
                            <span class="small text-muted"><?= count($unflaggedStudents) ?> in good standing</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-pie-chart fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <div class="btn-group btn-group-sm" role="group">
                        <a href="<?= url('views/house-master/students/bulk-flags/bulk-flags.php') ?>" class="btn btn-outline-primary <?= $filterType === 'all' ? 'active' : '' ?>">
                            All Residents (<?= count($students) ?>)
                        </a>
                        <a href="<?= url('views/house-master/students/bulk-flags/bulk-flags.php?type=flagged') ?>" class="btn btn-outline-warning <?= $filterType === 'flagged' ? 'active' : '' ?>">
                            Flagged Only (<?= count($flaggedStudents) ?>)
                        </a>
                        <a href="<?= url('views/house-master/students/bulk-flags/bulk-flags.php?type=unflagged') ?>" class="btn btn-outline-success <?= $filterType === 'unflagged' ? 'active' : '' ?>">
                            Good Standing (<?= count($unflaggedStudents) ?>)
                        </a>
                    </div>
                </div>

                <form method="GET" class="row g-2 align-items-end">
                    <input type="hidden" name="type" value="<?= e($filterType) ?>">
                    <div class="col-md-10">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input name="search" class="form-control" placeholder="Search by student name, admission number, email, or flag category..." value="<?= e($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill" type="submit">Search</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/students/bulk-flags/bulk-flags.php?type=' . urlencode($filterType)) ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Batch Action Card & Table -->
        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <span class="fw-semibold text-dark"><span id="selectedCount" class="badge bg-primary me-1">0</span> selected</span>
                    <a href="#" id="selectAllBtn" class="small text-decoration-none">Select all</a>
                    <a href="#" id="clearAllBtn" class="small text-muted text-decoration-none">Clear</a>
                </div>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-warning" id="flagSelectedBtn" data-bs-toggle="modal" data-bs-target="#flagModal" disabled>
                        <i class="bi bi-flag me-1"></i>Flag Selected
                    </button>
                    <button class="btn btn-success" id="unflagSelectedBtn" data-bs-toggle="modal" data-bs-target="#unflagModal" disabled>
                        <i class="bi bi-check-circle me-1"></i>Remove Flags
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 data-table w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 45px;" class="text-center">
                                    <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                                </th>
                                <th>Student Name</th>
                                <th>Admission No.</th>
                                <th>Class</th>
                                <th>Status</th>
                                <th>Flag Type</th>
                                <th>Reason / Notes</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($displayStudents)): ?>
                                <?php foreach ($displayStudents as $student): ?>
                                    <?php 
                                        $isFlagged = $student['flagged'] ?? false; 
                                        $flagTypeLabel = !empty($student['flagType']) ? ucwords(str_replace('_', ' ', (string)$student['flagType'])) : '—';
                                    ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input student-row-checkbox" data-student-id="<?= e((string) ($student['id'] ?? '')) ?>">
                                        </td>
                                        <td>
                                            <strong class="text-dark d-block"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></strong>
                                        </td>
                                        <td class="font-monospace small text-muted"><?= e($student['admissionNo'] ?? '—') ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= e($student['class'] ?? '—') ?></span></td>
                                        <td>
                                            <?php if ($isFlagged): ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-flag-fill me-1"></i>Flagged</span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border"><i class="bi bi-check-circle me-1"></i>Normal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small fw-semibold"><?= e($flagTypeLabel) ?></td>
                                        <td class="small text-muted"><?= e(mb_strimwidth((string)($student['flagReason'] ?? '—'), 0, 35, '...')) ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/students/profile/profile.php?studentId=' . urlencode((string) ($student['id'] ?? ''))) ?>">
                                                <i class="bi bi-eye me-1"></i>Profile
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5"><i class="bi bi-person-x fs-3 d-block mb-2"></i>No matching students found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Flag Modal -->
<div class="modal fade" id="flagModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-flag-fill text-warning me-2"></i>Flag Selected Students</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="flagForm" action="<?= url('views/house-master/students/bulk-flags/bulk-flags.php') ?>">
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_flag">
                    <div id="flagStudentsList"></div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Flag Category <span class="text-danger">*</span></label>
                        <select name="flagType" class="form-select" required>
                            <option value="">-- Select Category --</option>
                            <option value="attendance_concern">Attendance Concern / Truancy</option>
                            <option value="academic_concern">Academic Concern</option>
                            <option value="behavioral_concern">Behavioral Concern / Disciplinary</option>
                            <option value="health_concern">Health / Medical Watchlist</option>
                            <option value="other">Other Concern</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason &amp; Follow-up Instructions <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="4" placeholder="Detailed reason for placing this flag..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Apply Flag</button>
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
                <h5 class="modal-title"><i class="bi bi-check-circle-fill text-success me-2"></i>Clear Student Flags</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="unflagForm" action="<?= url('views/house-master/students/bulk-flags/bulk-flags.php') ?>">
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_unflag">
                    <div id="unflagStudentsList"></div>
                    <p class="text-muted">Are you sure you want to remove the active flag from all selected students?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Remove</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const selectedStudents = new Set();

    document.getElementById('selectAllCheckbox')?.addEventListener('change', function() {
        document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelection();
    });

    document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelection);
    });

    document.getElementById('selectAllBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
            checkbox.checked = true;
        });
        updateSelection();
    });

    document.getElementById('clearAllBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-row-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelection();
    });

    function updateSelection() {
        selectedStudents.clear();
        const flagForm = document.getElementById('flagForm');
        const unflagForm = document.getElementById('unflagForm');
        let html = '';

        document.querySelectorAll('.student-row-checkbox:checked').forEach(checkbox => {
            selectedStudents.add(checkbox.getAttribute('data-student-id'));
            html += '<input type="hidden" name="studentIds[]" value="' + checkbox.getAttribute('data-student-id') + '">';
        });

        flagForm.querySelectorAll('input[name="studentIds[]"]').forEach(input => input.remove());
        flagForm.insertAdjacentHTML('afterbegin', html);

        unflagForm.querySelectorAll('input[name="studentIds[]"]').forEach(input => input.remove());
        unflagForm.insertAdjacentHTML('afterbegin', html);

        document.getElementById('selectedCount').textContent = selectedStudents.size;
        document.getElementById('flagSelectedBtn').disabled = selectedStudents.size === 0;
        document.getElementById('unflagSelectedBtn').disabled = selectedStudents.size === 0;

        document.getElementById('flagStudentsList').innerHTML = '<p class="mb-3"><strong>Selected:</strong> ' + (selectedStudents.size > 0 ? selectedStudents.size + ' student(s)' : 'None') + '</p>';
        document.getElementById('unflagStudentsList').innerHTML = '<p class="mb-3"><strong>Selected:</strong> ' + (selectedStudents.size > 0 ? selectedStudents.size + ' student(s)' : 'None') + '</p>';
    }

    updateSelection();
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
