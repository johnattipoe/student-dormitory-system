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

$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\MedicalService;
use PhpOffice\PhpSpreadsheet\IOFactory;

$errors = [];
$success = 0;

if (isset($_GET['download_template'])) {
    header('Content-Type:text/csv');
    header('Content-Disposition:attachment; filename="medical_record_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['StudentId', 'Diagnosis', 'Treatment', 'Notes', 'Severity']);
    fputcsv($out, ['STUDENT_ID', 'Flu', 'Rest and fluids', 'Follow up', 'normal']);
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_records' && !empty($_FILES['records_file'])) {
    $file = $_FILES['records_file'];
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK || !in_array($extension, ['csv', 'xlsx'], true)) {
        $errors[] = 'Upload a valid CSV or XLSX file.';
    } else {
        try {
            $rows = IOFactory::load($file['tmp_name'])->getActiveSheet()->toArray(null, true, true, false);
            $header = array_map(fn($value) => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $value)), array_shift($rows) ?: []);
            $map = [];
            foreach ($header as $index => $name) {
                $map[$name] = $index;
            }

            foreach (array_slice($rows, 0, 1000) as $row) {
                $value = fn($name, $fallback = -1) => $row[$map[strtolower(preg_replace('/[^a-z0-9]/i', '', $name))] ?? $fallback] ?? '';
                $student = sanitize($value('studentid', 0));
                if ($student === '') {
                    continue;
                }

                $result = (new MedicalService())->create([
                    'studentId' => $student,
                    'diagnosis' => sanitize($value('diagnosis', 1)),
                    'treatment' => sanitize($value('treatment', 2)),
                    'notes' => sanitize($value('notes', 3)),
                    'severity' => sanitize($value('severity', 4) ?: 'normal'),
                    'recordedBy' => current_user()['uid'] ?? current_user()['id'] ?? null,
                ]);
                if (!empty($result['success'])) {
                    $success++;
                }
            }

            flash('success', "Imported {$success} medical record(s).");
            redirect(url('views/nurse/medical-records/bulk-import.php'));
        } catch (Throwable $e) {
            $errors[] = 'Import failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Bulk Medical Import';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php'), 'active' => true],
    ['icon' => 'bi-upload', 'label' => 'Import Records', 'href' => url('views/nurse/medical-records/bulk-import.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content nurse-portal">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">
        <section class="nurse-hero mb-4">
            <div class="nurse-hero-icon"><i class="bi bi-upload"></i></div>
            <div>
                <span class="nurse-kicker">Bulk import</span>
                <h1>Import medical records</h1>
                <p>Upload clinic records in CSV or XLSX format. Maximum 1,000 rows per upload.</p>
            </div>
            <a class="btn btn-light" href="<?= url('views/nurse/medical-records/bulk-import.php?download_template=1') ?>">
                <i class="bi bi-download"></i> Download template
            </a>
        </section>

        <div class="row g-4">
            <div class="col-lg-8">
                <section class="nurse-card-panel">
                    <div class="nurse-card-header">
                        <div>
                            <span class="nurse-kicker">Upload file</span>
                            <h2>Medical record spreadsheet</h2>
                            <p>Accepted columns: StudentId, Diagnosis, Treatment, Notes, Severity.</p>
                        </div>
                    </div>

                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endforeach; ?>

                    <form method="POST" enctype="multipart/form-data" class="row g-3 nurse-profile-form">
                        <input type="hidden" name="action" value="import_records">
                        <div class="col-12">
                            <label class="form-label">CSV or XLSX file</label>
                            <input type="file" name="records_file" class="form-control" accept=".csv,.xlsx" required>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-cloud-arrow-up"></i> Upload and import
                            </button>
                            <a class="btn btn-outline-secondary" href="<?= url('views/nurse/medical-records/medical-records.php') ?>">Back to records</a>
                        </div>
                    </form>
                </section>
            </div>

            <div class="col-lg-4">
                <aside class="nurse-side-card">
                    <h2>Import rules</h2>
                    <div class="nurse-info-list">
                        <div><i class="bi bi-file-earmark-spreadsheet"></i><span>File type</span><strong>CSV or XLSX</strong></div>
                        <div><i class="bi bi-list-ol"></i><span>Maximum rows</span><strong>1,000</strong></div>
                        <div><i class="bi bi-person"></i><span>Required field</span><strong>StudentId</strong></div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
