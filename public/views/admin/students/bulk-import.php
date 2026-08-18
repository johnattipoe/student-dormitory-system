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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\UserService;
use App\Services\HouseService;

// Handle bulk import
$errors = [];
$successCount = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    
    if ($action === 'import_students' && !empty($_FILES['students_file'])) {
        $file = $_FILES['students_file'];
        if ($file['error'] === UPLOAD_ERR_OK && $file['type'] === 'text/csv') {
            try {
                $handle = fopen($file['tmp_name'], 'r');
                $header = fgetcsv($handle);
                
                while ($row = fgetcsv($handle)) {
                    if (count($row) < 6) continue; // Skip invalid rows
                    
                    $studentData = [
                        'firstName' => sanitize($row[0] ?? ''),
                        'lastName' => sanitize($row[1] ?? ''),
                        'email' => sanitize($row[2] ?? ''),
                        'phone' => sanitize($row[3] ?? ''),
                        'admissionNo' => sanitize($row[4] ?? ''),
                        'course' => sanitize($row[5] ?? ''),
                        'level' => sanitize($row[6] ?? ''),
                        'houseId' => sanitize($_POST['houseId'] ?? ''),
                        'status' => 'active',
                    ];
                    
                    if (!empty($studentData['firstName']) && !empty($studentData['admissionNo'])) {
                        StudentService::create($studentData);
                        $successCount++;
                    } else {
                        $errors[] = 'Row missing required fields (firstName, admissionNo)';
                    }
                }
                
                fclose($handle);
                flash('success', "Imported $successCount student(s) successfully");
                redirect(url('views/admin/students/bulk-import.php'));
            } catch (Exception $e) {
                $errors[] = 'Import failed: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'Please upload a valid CSV file';
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
                redirect(url('views/admin/students/bulk-import.php'));
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
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/admin/students/index.php')],
    ['icon' => 'bi-upload', 'label' => 'Bulk Import', 'href' => url('views/admin/students/bulk-import.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="import-tab" data-bs-toggle="tab" data-bs-target="#import-mode" type="button">
                    <i class="bi bi-upload"></i> Import Students
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="status-tab" data-bs-toggle="tab" data-bs-target="#status-mode" type="button">
                    <i class="bi bi-toggle-on"></i> Bulk Status Management
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Import Tab -->
            <div class="tab-pane fade show active" id="import-mode">
                <div class="card stat-card p-4" style="max-width: 700px;">
                    <h5 class="mb-3">Import Students from CSV</h5>
                    
                    <form method="POST" enctype="multipart/form-data" class="mb-4">
                        <input type="hidden" name="action" value="import_students">
                        
                        <div class="mb-3">
                            <label class="form-label">Select House</label>
                            <select name="houseId" class="form-select" required>
                                <option value="">-- Select a house --</option>
                                <?php foreach ($houses as $house): ?>
                                    <option value="<?= e((string) ($house['id'] ?? '')) ?>">
                                        <?= e($house['name'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">CSV File</label>
                            <input type="file" name="students_file" class="form-control" accept=".csv" required>
                            <small class="text-muted d-block mt-2">
                                <strong>CSV Format (6 columns):</strong><br>
                                FirstName, LastName, Email, Phone, AdmissionNo, Course, Level
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Import Students
                        </button>
                    </form>

                    <div class="alert alert-info">
                        <strong>Instructions:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Create a CSV file with the columns shown above</li>
                            <li>The first row should contain data (not headers)</li>
                            <li>Admission Number and FirstName are required</li>
                            <li>Maximum 1000 students per import</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Status Management Tab -->
            <div class="tab-pane fade" id="status-mode">
                <div class="card stat-card p-3">
                    <h5 class="mb-3">Bulk Status Management</h5>

                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <strong id="selectedCount">0</strong> student(s) selected
                            <a href="#" id="selectAllLink" class="ms-2 small">Select all</a> | 
                            <a href="#" id="clearAllLink" class="ms-2 small">Clear all</a>
                        </div>
                    </div>

                    <form method="POST" id="statusForm">
                        <input type="hidden" name="action" value="bulk_status_change">
                        
                        <div class="table-responsive mb-3">
                            <table class="table table-hover data-table w-100">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
                                        </th>
                                        <th>Name</th>
                                        <th>Admission No</th>
                                        <th>Status</th>
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
                                                <td><?= e($student['firstName'] ?? '') . ' ' . e($student['lastName'] ?? '') ?></td>
                                                <td><?= e($student['admissionNo'] ?? '') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= ($student['status'] ?? '') === 'active' ? 'success' : 'danger' ?>">
                                                        <?= e(ucfirst($student['status'] ?? 'active')) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No students found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Change Status To</label>
                                <select name="newStatus" class="form-select form-select-sm">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="submit" class="btn btn-warning w-100" id="updateStatusBtn" disabled>
                                    <i class="bi bi-check-circle"></i> Update Status
                                </button>
                            </div>
                        </div>
                    </form>
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

        document.getElementById('selectedCount').textContent = selectedStudents.size;
        document.getElementById('updateStatusBtn').disabled = selectedStudents.size === 0;
    }

    updateSelection();
</script>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
