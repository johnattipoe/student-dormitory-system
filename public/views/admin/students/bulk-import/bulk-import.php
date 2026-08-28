<?php
// Ensure bootstrap is loaded
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

$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\UserService;
use App\Services\HouseService;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Handle CSV template download
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_import_template.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['FirstName', 'LastName', 'AdmissionNo', 'Gender', 'Class', 'Form', 'Course', 'NHISNumber', 'GuardianName', 'GuardianPhone', 'GuardianEmail']);
    
    // Add sample row
    fputcsv($output, ['John', 'Doe', 'ADM001', 'Male', 'SHS 1', 'Form 1', 'General Science', 'NHIS12345678', 'Jane Doe', '0241234567', 'guardian@example.com']);
    
    fclose($output);
    exit;
}

// Handle bulk import
$errors = [];
$successCount = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    
    if ($action === 'import_students' && !empty($_FILES['students_file'])) {
        $file = $_FILES['students_file'];
        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($file['error'] === UPLOAD_ERR_OK && in_array($extension, ['csv', 'xlsx'], true)) {
            try {
                $spreadsheet = IOFactory::load($file['tmp_name']);
                $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
                $header = array_map(fn($value) => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $value)), array_shift($rows) ?: []);
                $headerMap = [];
                foreach ($header as $index => $name) $headerMap[$name] = $index;
                $temporaryPassword = trim((string) ($_POST['temporaryPassword'] ?? ''));
                $createAccounts = !empty($_POST['createAccounts']);
                $userCount = 0;
                $processedRows = 0;
                foreach (array_slice($rows, 0, 1000) as $row) {
                    $processedRows++;
                    $firstName = sanitize($row[$headerMap['firstname'] ?? 0] ?? '');
                    $lastName = sanitize($row[$headerMap['lastname'] ?? 1] ?? '');
                    $admissionNo = sanitize($row[$headerMap['admissionno'] ?? 2] ?? '');
                    if ($firstName === '' || $admissionNo === '') {
                        continue;
                    }
                    $formValue = sanitize($row[$headerMap['form'] ?? 5] ?? $row[$headerMap['level'] ?? 5] ?? '');
                    $studentData = [
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'admissionNo' => $admissionNo,
                        'gender' => sanitize($row[$headerMap['gender'] ?? 3] ?? ''),
                        'class' => sanitize($row[$headerMap['class'] ?? 4] ?? ''),
                        'form' => $formValue,
                        'level' => $formValue,
                        'course' => sanitize($row[$headerMap['course'] ?? 6] ?? ''),
                        'nhisNumber' => sanitize($row[$headerMap['nhisnumber'] ?? 7] ?? ''),
                        'houseId' => sanitize($_POST['houseId'] ?? ''),
                        'status' => 'active',
                        'guardianName' => sanitize($row[$headerMap['guardianname'] ?? 8] ?? ''),
                        'guardianPhone' => sanitize($row[$headerMap['guardianphone'] ?? 9] ?? ''),
                        'guardianEmail' => sanitize($row[$headerMap['guardianemail'] ?? 10] ?? ''),
                    ];
                    $studentId = StudentService::create($studentData);
                    $successCount++;
                    if ($createAccounts && $studentId) {
                        try {
                            $userEmail = strtolower(preg_replace('/[^a-z0-9]/i', '', $admissionNo)) . '@student.local';
                            $password = $temporaryPassword !== '' ? $temporaryPassword : ('Student@' . substr($admissionNo, -4));
                            UserService::create([
                                'email' => $userEmail,
                                'password' => $password,
                                'role' => ROLE_STUDENT,
                                'firstName' => $firstName,
                                'lastName' => $lastName,
                                'studentId' => $studentId,
                                'status' => 'active',
                            ]);
                            $userCount++;
                        } catch (Exception $e) {}
                    }
                }
                $accountMessage = $createAccounts ? " Created $userCount login account(s)." : '';
                flash('success', "Imported $successCount student(s) successfully." . $accountMessage);
                redirect(url('views/admin/students/bulk-import/bulk-import.php'));
            } catch (Exception $e) {
                $errors[] = 'Import failed: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'Please upload a valid CSV or XLSX file.';
        }
    }
    
    // Handle bulk activate/deactivate
    elseif ($action === 'bulk_status_change') {
        $studentIds = (array) ($_POST['studentIds'] ?? []);
        $newStatus = sanitize($_POST['newStatus'] ?? 'active');
        
        if (!empty($studentIds)) {
            try {
                foreach ($studentIds as $sId) {
                    StudentService::update($sId, ['status' => $newStatus]);
                }
                flash('success', 'Updated status for ' . count($studentIds) . ' student(s)');
                redirect(url('views/admin/students/bulk-import/bulk-import.php'));
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Get students for status management
$students = StudentService::all();
$houses = HouseService::all();

$pageTitle = 'Bulk Student Management';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/admin/students/index/index.php')],
    ['icon' => 'bi-upload', 'label' => 'Bulk Import', 'href' => url('views/admin/students/bulk-import/bulk-import.php'), 'active' => true],
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
                    <i class="bi bi-file-earmark-arrow-up-fill text-success me-2"></i>Bulk Student Operations
                </h4>
                <p class="text-muted mb-0">Import hundreds of student records via CSV/Excel or manage batch statuses</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('views/admin/students/bulk-import/bulk-import.php?download_template=1') ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-download me-1"></i> Download Template CSV
                </a>
                <a href="<?= url('views/admin/students/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Students
                </a>
            </div>
        </div>

        <!-- Stat Cards -->
        <?php
        $totalStudents = count($students);
        $activeStudents = count(array_filter($students, static fn($s) => ($s['status'] ?? '') === 'active'));
        $inactiveStudents = count(array_filter($students, static fn($s) => in_array($s['status'] ?? '', ['inactive', 'suspended'], true)));
        $houseAssigned = count(array_filter($students, static fn($s) => !empty($s['houseId'])));
        ?>
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted text-uppercase small fw-semibold mb-1">Total Students</p>
                            <h3 class="mb-1"><?= $totalStudents ?></h3>
                            <small class="text-muted">All registered students</small>
                        </div>
                        <span class="text-primary fs-3"><i class="bi bi-people-fill"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted text-uppercase small fw-semibold mb-1">Active Students</p>
                            <h3 class="mb-1 text-success"><?= $activeStudents ?></h3>
                            <small class="text-muted">Eligible for accommodation</small>
                        </div>
                        <span class="text-success fs-3"><i class="bi bi-person-check-fill"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted text-uppercase small fw-semibold mb-1">Inactive / Suspended</p>
                            <h3 class="mb-1 text-warning"><?= $inactiveStudents ?></h3>
                            <small class="text-muted">Need status review</small>
                        </div>
                        <span class="text-warning fs-3"><i class="bi bi-person-dash-fill"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted text-uppercase small fw-semibold mb-1">House Assigned</p>
                            <h3 class="mb-1 text-info"><?= $houseAssigned ?></h3>
                            <small class="text-muted"><?= max(0, $totalStudents - $houseAssigned) ?> awaiting assignment</small>
                        </div>
                        <span class="text-info fs-3"><i class="bi bi-house-check-fill"></i></span>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-pills mb-4 bg-light p-1 rounded-3 d-inline-flex border" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active px-4" id="import-tab" data-bs-toggle="tab" data-bs-target="#import-mode" type="button">
                    <i class="bi bi-upload me-1"></i> Import Students File
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link px-4" id="status-tab" data-bs-toggle="tab" data-bs-target="#status-mode" type="button">
                    <i class="bi bi-toggle-on me-1"></i> Batch Status Manager
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Import Tab -->
            <div class="tab-pane fade show active" id="import-mode">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="card stat-card shadow-sm border-0">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-cloud-arrow-up me-2 text-primary"></i>Upload Student Spreadsheet</h6>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="import_students">
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Assign to House <span class="text-danger">*</span></label>
                                        <select name="houseId" class="form-select select2" required>
                                            <option value="">-- Select a dormitory house --</option>
                                            <?php foreach ($houses as $house): ?>
                                                <option value="<?= e((string) ($house['id'] ?? '')) ?>">
                                                    <?= e($house['name'] ?? '') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">CSV or XLSX Spreadsheet File <span class="text-danger">*</span></label>
                                        <input type="file" name="students_file" class="form-control" accept=".csv,.xlsx" required>
                                        <small class="text-muted d-block mt-2">
                                            Accepted formats: <code>.csv</code>, <code>.xlsx</code> (Max 1,000 rows per upload)
                                        </small>
                                    </div>

                                    <div class="card bg-light border-0 p-3 mb-3">
                                        <div class="row g-3">
                                            <div class="col-md-7">
                                                <label class="form-label small fw-semibold">Custom temporary password (optional)</label>
                                                <input type="text" name="temporaryPassword" class="form-control form-control-sm" placeholder="e.g. Pass@2026">
                                            </div>
                                            <div class="col-md-5 d-flex align-items-end">
                                                <div class="form-check form-switch mb-1">
                                                    <input class="form-check-input" type="checkbox" name="createAccounts" id="createAccounts" value="1">
                                                    <label class="form-check-label small fw-semibold" for="createAccounts">Create student logins</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2 mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-upload me-1"></i> Upload &amp; Import Students
                                        </button>
                                        <a href="<?= url('views/admin/students/bulk-import/bulk-import.php?download_template=1') ?>" class="btn btn-outline-success">
                                            <i class="bi bi-download me-1"></i> Template CSV
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card stat-card shadow-sm border-0">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-info"></i>Spreadsheet Format Guide</h6>
                            </div>
                            <div class="card-body p-4">
                                <p class="small text-muted mb-2">Ensure your spreadsheet header row includes the exact column names below:</p>
                                <div class="bg-light p-2 rounded-3 border small font-monospace mb-3">
                                    FirstName, LastName, AdmissionNo, Gender, Class, Form, Course, NHISNumber, GuardianName, GuardianPhone, GuardianEmail
                                </div>
                                <ul class="small text-muted ps-3 mb-0">
                                    <li class="mb-1"><strong>AdmissionNo</strong> and <strong>FirstName</strong> are mandatory fields.</li>
                                    <li class="mb-1">Leave <strong>GuardianEmail</strong> empty if unavailable.</li>
                                    <li class="mb-1">When account creation is enabled, login email will be generated as <code>{admissionNo}@student.local</code>.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Management Tab -->
            <div class="tab-pane fade" id="status-mode">
                <div class="card stat-card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0 fw-bold"><i class="bi bi-toggle-on me-2 text-warning"></i>Batch Status Modifier</h6>
                            <small class="text-muted">Select multiple students to batch update their enrollment status</small>
                        </div>
                        <div>
                            <span class="badge bg-primary fs-6 me-2" id="selectedCount">0</span> selected
                            <a href="#" id="selectAllLink" class="btn btn-sm btn-outline-primary ms-1">Select All</a>
                            <a href="#" id="clearAllLink" class="btn btn-sm btn-outline-secondary ms-1">Clear</a>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" id="statusForm">
                            <input type="hidden" name="action" value="bulk_status_change">
                            
                            <div class="table-responsive mb-4">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                                            </th>
                                            <th>Student Name</th>
                                            <th>Admission No</th>
                                            <th>Current Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($students)): ?>
                                            <?php foreach ($students as $student): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="form-check-input student-checkbox" 
                                                               data-student-id="<?= e((string) ($student['id'] ?? '')) ?>">
                                                    </td>
                                                    <td><strong><?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?></strong></td>
                                                    <td><span class="font-monospace text-muted"><?= e($student['admissionNo'] ?? '') ?></span></td>
                                                    <td>
                                                        <span class="badge bg-<?= ($student['status'] ?? '') === 'active' ? 'success' : 'danger' ?>">
                                                            <?= e(ucfirst($student['status'] ?? 'active')) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">No students registered in the system.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="row g-3 align-items-center bg-light p-3 rounded-3 border">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold mb-0">Apply New Status:</label>
                                </div>
                                <div class="col-md-4">
                                    <select name="newStatus" class="form-select form-select-sm">
                                        <option value="active">Active (Enrolled)</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="suspended">Suspended</option>
                                        <option value="graduated">Graduated</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-warning btn-sm w-100" id="updateStatusBtn" disabled>
                                        <i class="bi bi-check-circle me-1"></i> Update Selected Status
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    const selectedStudents = new Set();

    document.getElementById('selectAllCheckbox')?.addEventListener('change', function() {
        document.querySelectorAll('.student-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelection();
    });

    document.querySelectorAll('.student-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelection);
    });

    document.getElementById('selectAllLink')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-checkbox').forEach(checkbox => {
            checkbox.checked = true;
        });
        updateSelection();
    });

    document.getElementById('clearAllLink')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelection();
    });

    function updateSelection() {
        selectedStudents.clear();
        const form = document.getElementById('statusForm');
        let html = '';

        document.querySelectorAll('.student-checkbox:checked').forEach(checkbox => {
            selectedStudents.add(checkbox.getAttribute('data-student-id'));
            html += '<input type="hidden" name="studentIds[]" value="' + checkbox.getAttribute('data-student-id') + '">';
        });

        form.querySelectorAll('input[name="studentIds[]"]').forEach(input => input.remove());
        form.insertAdjacentHTML('afterbegin', html);

        const countEl = document.getElementById('selectedCount');
        if (countEl) countEl.textContent = selectedStudents.size;
        const btnEl = document.getElementById('updateStatusBtn');
        if (btnEl) btnEl.disabled = selectedStudents.size === 0;
    }

    updateSelection();
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
