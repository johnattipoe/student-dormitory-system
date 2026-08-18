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

$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\MedicalService;
use App\Services\StudentService;
use App\Services\FirebaseService;

// Handle bulk operations
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $studentIds = (array) ($_POST['studentIds'] ?? []);
    
    if ($action === 'bulk_create_records' && !empty($studentIds)) {
        $diagnosis = sanitize($_POST['diagnosis'] ?? '');
        $severity = sanitize($_POST['severity'] ?? 'normal');
        $notes = sanitize($_POST['notes'] ?? '');
        
        if (!empty($diagnosis)) {
            try {
                $firebaseService = FirebaseService::getInstance();
                foreach ($studentIds as $sId) {
                    $firebaseService->addDocument(COL_MEDICAL_RECORDS, [
                        'studentId' => $sId,
                        'diagnosis' => $diagnosis,
                        'severity' => $severity,
                        'notes' => $notes,
                        'recordedBy' => current_user()['uid'],
                        'createdAt' => date('Y-m-d H:i:s'),
                    ]);
                }
                flash('success', 'Created medical records for ' . count($studentIds) . ' student(s)');
                redirect(url('views/nurse/medical-records/bulk-management.php'));
            } catch (Exception $e) {
                $errors[] = 'Failed: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'Diagnosis is required';
        }
    }
    
    elseif ($action === 'bulk_mark_critical' && !empty($studentIds)) {
        try {
            $firebaseService = FirebaseService::getInstance();
            $records = $firebaseService->getCollection(COL_MEDICAL_RECORDS, [], 500);
            
            foreach ($records as $record) {
                if (in_array($record['studentId'] ?? '', $studentIds)) {
                    $firebaseService->updateDocument(COL_MEDICAL_RECORDS, (string) ($record['id'] ?? ''), [
                        'severity' => 'critical',
                        'flaggedAt' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
            
            flash('success', 'Marked ' . count($studentIds) . ' record(s) as critical');
            redirect(url('views/nurse/medical-records/bulk-management.php'));
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$students = StudentService::all();
$medicalService = new MedicalService();
$allRecords = $medicalService->all();

$pageTitle = 'Bulk Medical Management';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-plus-circle', 'label' => 'Bulk Management', 'href' => url('views/nurse/medical-records/bulk-management.php'), 'active' => true],
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
                <button class="nav-link active" id="create-tab" data-bs-toggle="tab" data-bs-target="#create-mode" type="button">
                    <i class="bi bi-plus-lg"></i> Create Records
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="mark-critical-tab" data-bs-toggle="tab" data-bs-target="#critical-mode" type="button">
                    <i class="bi bi-exclamation-circle"></i> Mark Critical
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Create Records Tab -->
            <div class="tab-pane fade show active" id="create-mode">
                <div class="card stat-card p-3">
                    <h5 class="mb-3">Create Medical Records for Multiple Students</h5>

                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <strong id="selectedCountCreate">0</strong> student(s) selected
                            <a href="#" id="selectAllCreateLink" class="ms-2 small">Select all</a> | 
                            <a href="#" id="clearAllCreateLink" class="ms-2 small">Clear all</a>
                        </div>
                    </div>

                    <form method="POST" id="createForm">
                        <input type="hidden" name="action" value="bulk_create_records">

                        <div class="table-responsive mb-4">
                            <table class="table table-hover data-table w-100">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAllCheckboxCreate" class="form-check-input">
                                        </th>
                                        <th>Name</th>
                                        <th>Admission No</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($students)): ?>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input student-checkbox-create" 
                                                           data-student-id="<?= e((string) ($student['id'] ?? '')) ?>">
                                                </td>
                                                <td><?= e($student['firstName'] ?? '') . ' ' . e($student['lastName'] ?? '') ?></td>
                                                <td><?= e($student['admissionNo'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No students found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Diagnosis</label>
                                <textarea name="diagnosis" class="form-control" rows="2" placeholder="E.g., Mild fever, Headache..." required></textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Severity</label>
                                <select name="severity" class="form-select">
                                    <option value="normal">Normal</option>
                                    <option value="moderate">Moderate</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100" id="createBtn" disabled>
                                    <i class="bi bi-plus-circle"></i> Create Records
                                </button>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Additional Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Medical observations..."></textarea>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Mark Critical Tab -->
            <div class="tab-pane fade" id="critical-mode">
                <div class="card stat-card p-3">
                    <h5 class="mb-3">Mark Existing Records as Critical</h5>

                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <strong id="selectedCountCritical">0</strong> student(s) selected
                            <a href="#" id="selectAllCriticalLink" class="ms-2 small">Select all</a> | 
                            <a href="#" id="clearAllCriticalLink" class="ms-2 small">Clear all</a>
                        </div>
                    </div>

                    <form method="POST" id="criticalForm">
                        <input type="hidden" name="action" value="bulk_mark_critical">

                        <div class="table-responsive mb-3">
                            <table class="table table-hover data-table w-100">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAllCheckboxCritical" class="form-check-input">
                                        </th>
                                        <th>Name</th>
                                        <th>Current Severity</th>
                                        <th>Last Record</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($allRecords)): ?>
                                        <?php $recordsByStudent = []; ?>
                                        <?php foreach ($allRecords as $record): ?>
                                            <?php $sId = $record['studentId'] ?? ''; ?>
                                            <?php if (!isset($recordsByStudent[$sId])) $recordsByStudent[$sId] = $record; ?>
                                        <?php endforeach; ?>
                                        <?php foreach ($recordsByStudent as $sId => $record): ?>
                                            <?php $student = StudentService::find($sId); ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input student-checkbox-critical" 
                                                           data-student-id="<?= e((string) $sId) ?>">
                                                </td>
                                                <td><?= e($student['firstName'] ?? '') . ' ' . e($student['lastName'] ?? '') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= match(($record['severity'] ?? 'normal')) {
                                                        'critical' => 'danger',
                                                        'moderate' => 'warning',
                                                        'normal' => 'success',
                                                        default => 'secondary'
                                                    } ?>">
                                                        <?= e(ucfirst($record['severity'] ?? 'normal')) ?>
                                                    </span>
                                                </td>
                                                <td><?= e(substr($record['createdAt'] ?? '', 0, 10)) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No medical records found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-danger" id="criticalBtn" disabled>
                            <i class="bi bi-exclamation-circle"></i> Mark as Critical
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Create Records Tab
    const selectedCreate = new Set();
    const selectedCritical = new Set();

    // Setup Create Tab
    document.getElementById('selectAllCheckboxCreate')?.addEventListener('change', function() {
        document.querySelectorAll('.student-checkbox-create').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateCreateSelection();
    });

    document.querySelectorAll('.student-checkbox-create').forEach(checkbox => {
        checkbox.addEventListener('change', updateCreateSelection);
    });

    document.getElementById('selectAllCreateLink')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-checkbox-create').forEach(checkbox => {
            checkbox.checked = true;
        });
        updateCreateSelection();
    });

    document.getElementById('clearAllCreateLink')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-checkbox-create').forEach(checkbox => {
            checkbox.checked = false;
        });
        updateCreateSelection();
    });

    function updateCreateSelection() {
        selectedCreate.clear();
        const form = document.getElementById('createForm');
        let html = '';

        document.querySelectorAll('.student-checkbox-create:checked').forEach(checkbox => {
            selectedCreate.add(checkbox.getAttribute('data-student-id'));
            html += '<input type="hidden" name="studentIds[]" value="' + checkbox.getAttribute('data-student-id') + '">';
        });

        form.querySelectorAll('input[name="studentIds[]"]').forEach(input => input.remove());
        form.insertAdjacentHTML('afterbegin', html);

        document.getElementById('selectedCountCreate').textContent = selectedCreate.size;
        document.getElementById('createBtn').disabled = selectedCreate.size === 0;
    }

    // Setup Critical Tab
    document.getElementById('selectAllCheckboxCritical')?.addEventListener('change', function() {
        document.querySelectorAll('.student-checkbox-critical').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateCriticalSelection();
    });

    document.querySelectorAll('.student-checkbox-critical').forEach(checkbox => {
        checkbox.addEventListener('change', updateCriticalSelection);
    });

    document.getElementById('selectAllCriticalLink')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-checkbox-critical').forEach(checkbox => {
            checkbox.checked = true;
        });
        updateCriticalSelection();
    });

    document.getElementById('clearAllCriticalLink')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.student-checkbox-critical').forEach(checkbox => {
            checkbox.checked = false;
        });
        updateCriticalSelection();
    });

    function updateCriticalSelection() {
        selectedCritical.clear();
        const form = document.getElementById('criticalForm');
        let html = '';

        document.querySelectorAll('.student-checkbox-critical:checked').forEach(checkbox => {
            selectedCritical.add(checkbox.getAttribute('data-student-id'));
            html += '<input type="hidden" name="studentIds[]" value="' + checkbox.getAttribute('data-student-id') + '">';
        });

        form.querySelectorAll('input[name="studentIds[]"]').forEach(input => input.remove());
        form.insertAdjacentHTML('afterbegin', html);

        document.getElementById('selectedCountCritical').textContent = selectedCritical.size;
        document.getElementById('criticalBtn').disabled = selectedCritical.size === 0;
    }

    updateCreateSelection();
    updateCriticalSelection();
</script>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
