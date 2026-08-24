<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';

$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\VisitorService;
use PhpOffice\PhpSpreadsheet\IOFactory;

$errors = [];
$success = 0;

if (isset($_GET['download_template'])) {
    header('Content-Type:text/csv');
    header('Content-Disposition:attachment; filename="visitor_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['VisitorName', 'StudentId', 'Phone', 'Purpose', 'IdType', 'IdNumber']);
    fputcsv($out, ['John Doe', 'STUDENT_ID', '0800000000', 'Visit', 'National ID', 'ID001']);
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_visitors' && !empty($_FILES['visitors_file'])) {
    $file = $_FILES['visitors_file'];
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
                $name = sanitize($value('visitorname', 0));
                $student = sanitize($value('studentid', 1));
                if ($name === '' || $student === '') {
                    continue;
                }

                $result = (new VisitorService())->register([
                    'visitorName' => $name,
                    'studentId' => $student,
                    'phone' => sanitize($value('phone', 2)),
                    'purpose' => sanitize($value('purpose', 3)),
                    'idType' => sanitize($value('idtype', 4)),
                    'idNumber' => sanitize($value('idnumber', 5)),
                    'registeredBy' => current_user()['uid'] ?? current_user()['id'] ?? null,
                ]);
                if (!empty($result['success'])) {
                    $success++;
                }
            }

            flash('success', "Imported {$success} visitor(s).");
            redirect(url('views/security/visitors/bulk-import.php'));
        } catch (Throwable $e) {
            $errors[] = 'Import failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Bulk Visitor Import';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-upload', 'label' => 'Import Visitors', 'href' => url('views/security/visitors/bulk-import.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content security-portal">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>

    <div class="content-wrapper">
        <section class="security-hero mb-4">
            <div>
                <span class="security-eyebrow"><i class="bi bi-upload"></i> Bulk import</span>
                <h1>Import visitors</h1>
                <p>Upload visitor records in CSV or XLSX format. Maximum 1,000 rows per upload.</p>
            </div>
            <a class="btn btn-light" href="<?= url('views/security/visitors/bulk-import.php?download_template=1') ?>">
                <i class="bi bi-download"></i> Download template
            </a>
        </section>

        <div class="row g-4">
            <div class="col-lg-8">
                <section class="security-card">
                    <div class="security-card-header">
                        <div>
                            <h2>Upload file</h2>
                            <p>Accepted columns: VisitorName, StudentId, Phone, Purpose, IdType, IdNumber.</p>
                        </div>
                    </div>

                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endforeach; ?>

                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="action" value="import_visitors">
                        <div class="col-12">
                            <label class="form-label">CSV or XLSX file</label>
                            <input type="file" name="visitors_file" class="form-control" accept=".csv,.xlsx" required>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-cloud-arrow-up"></i> Upload and import
                            </button>
                            <a class="btn btn-outline-secondary" href="<?= url('views/security/visitors/visitors.php') ?>">Back to visitors</a>
                        </div>
                    </form>
                </section>
            </div>

            <div class="col-lg-4">
                <aside class="security-side-card">
                    <h3>Import rules</h3>
                    <ul class="security-info-list">
                        <li><span>File type</span><strong>CSV or XLSX</strong></li>
                        <li><span>Maximum rows</span><strong>1,000</strong></li>
                        <li><span>Required fields</span><strong>VisitorName, StudentId</strong></li>
                    </ul>
                </aside>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
