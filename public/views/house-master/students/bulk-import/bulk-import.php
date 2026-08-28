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
use PhpOffice\PhpSpreadsheet\IOFactory;

$houseId = current_user()['houseId'] ?? null;
$errors = [];
$success = 0;

if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="house_student_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['FirstName','LastName','AdmissionNo','Gender','Class','Form','Course','NHISNumber','GuardianName','GuardianPhone','GuardianEmail']);
    fputcsv($out, ['Kofi','Mensah','ADM-2026-001','Male','Science 1','Form 1','General Science','NHIS-12345678','Kwame Mensah','+233201234567','guardian@example.com']);
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_students' && !empty($_FILES['students_file'])) {
    $file = $_FILES['students_file'];
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK || !in_array($extension, ['csv', 'xlsx'], true)) {
        $errors[] = 'Please upload a valid CSV or XLSX file.';
    } else {
        try {
            $rows = IOFactory::load($file['tmp_name'])->getActiveSheet()->toArray(null, true, true, false);
            $header = array_map(fn($v) => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $v)), array_shift($rows) ?: []);
            $map = [];
            foreach ($header as $i => $name) {
                $map[$name] = $i;
            }

            foreach (array_slice($rows, 0, 1000) as $row) {
                $value = fn($name, $fallback = -1) => $row[$map[strtolower(preg_replace('/[^a-z0-9]/i', '', $name))] ?? $fallback] ?? '';
                
                $first = sanitize($value('firstname', 0));
                $last = sanitize($value('lastname', 1));
                $admission = sanitize($value('admissionno', 2));

                if ($first === '' || $admission === '') continue;

                $formVal = sanitize($value('form', 5)) ?: sanitize($value('level', 5)) ?: 'Form 1';

                StudentService::create([
                    'firstName' => $first,
                    'lastName' => $last,
                    'admissionNo' => $admission,
                    'gender' => sanitize($value('gender', 3)) ?: 'Male',
                    'class' => sanitize($value('class', 4)),
                    'form' => $formVal,
                    'level' => $formVal,
                    'course' => sanitize($value('course', 6)),
                    'nhisNumber' => sanitize($value('nhisnumber', 7)),
                    'guardianName' => sanitize($value('guardianname', 8)),
                    'guardianPhone' => sanitize($value('guardianphone', 9)),
                    'guardianEmail' => sanitize($value('guardianemail', 10)),
                    'houseId' => $houseId,
                    'status' => 'active',
                ]);
                $success++;
            }

            flash('success', "Imported {$success} student(s) into your house.");
            redirect(url('views/house-master/students/index/index.php'));
        } catch (Throwable $e) {
            $errors[] = 'Import failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Import House Students';
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

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-upload text-primary me-2"></i>Bulk Import Students</h4>
                <p class="text-muted mb-0">Upload a CSV or Excel file to batch-enrol resident students into your house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-success btn-sm" href="<?= url('views/house-master/students/bulk-import/bulk-import.php?download_template=1') ?>">
                    <i class="bi bi-download me-1"></i>Download Template
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/students/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back to Students
                </a>
            </div>
        </div>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger mb-3"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
        <?php endforeach; ?>

        <div class="row g-4" style="max-width: 860px;">
            <!-- Upload Form Card -->
            <div class="col-12">
                <div class="card stat-card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-arrow-up me-2 text-primary"></i>Upload Student File</h6>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data" action="<?= url('views/house-master/students/bulk-import/bulk-import.php') ?>">
                            <input type="hidden" name="action" value="import_students">

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Select CSV or XLSX File <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-file-spreadsheet"></i></span>
                                    <input type="file" name="students_file" class="form-control" accept=".csv,.xlsx" required>
                                </div>
                                <div class="form-text">Max file size: 5MB. Accepted formats: .csv, .xlsx</div>
                            </div>

                            <div class="alert alert-info mb-4">
                                <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1"></i>Required File Format</h6>
                                <p class="mb-1 small">Your file must include the following columns in order:</p>
                                <code class="d-block small bg-white p-2 rounded mt-2">
                                    FirstName, LastName, AdmissionNo, Gender, Class, Form, Course, NHISNumber, GuardianName, GuardianPhone, GuardianEmail
                                </code>
                                <p class="small mt-2 mb-0 text-muted">All imported students will be automatically enrolled into <strong>your assigned house</strong>.</p>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a class="btn btn-outline-secondary" href="<?= url('views/house-master/students/index/index.php') ?>">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload me-1"></i>Upload &amp; Import Students
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>