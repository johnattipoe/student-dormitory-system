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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incidentType = sanitize($_POST['incidentType'] ?? '');
    $studentName = sanitize($_POST['studentName'] ?? '');
    $uploadError = null;
    $savedFile = null;

    if (isset($_FILES['signedBond']) && !empty($_FILES['signedBond']['name'])) {
        $file = $_FILES['signedBond'];
        $allowedExt = ['jpg', 'jpeg', 'pdf'];
        $allowedMime = ['image/jpeg', 'application/pdf'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeType = strtolower((string) ($file['type'] ?? ''));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadError = 'The uploaded incident file could not be processed. Please try again.';
        } elseif (!in_array($extension, $allowedExt, true) || !in_array($mimeType, $allowedMime, true)) {
            $uploadError = 'Only JPEG and PDF files are allowed for the uploaded incident document.';
        } else {
            $uploadDir = APP_ROOT . '/public/uploads/external-incidents';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($file['name']));
            $filename = 'incident-' . time() . '-' . $safeName;
            $target = $uploadDir . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $target)) {
                $uploadError = 'The file upload failed. Please try again.';
            } else {
                $savedFile = $filename;
                $metadata = [
                    'id' => 'EXT-' . date('YmdHis'),
                    'studentName' => $studentName,
                    'incidentType' => $incidentType,
                    'fileName' => $filename,
                    'originalName' => $file['name'],
                    'uploadedAt' => date('Y-m-d H:i:s'),
                    'status' => 'submitted',
                    'mimeType' => $file['type'] ?? 'application/octet-stream',
                    'size' => $file['size'] ?? 0,
                ];
                file_put_contents($target . '.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }
    }

    if ($uploadError) {
        flash('error', $uploadError);
    } elseif ($incidentType !== '' && $studentName !== '' && $savedFile !== null) {
        flash('success', 'External incident form submitted successfully.');
        redirect(url('views/house-master/incidents/index/index.php'));
    } else {
        flash('error', 'Please complete the required external incident details before submitting.');
    }
}

$pageTitle = 'Upload External Incident';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-upload text-info me-2"></i>Upload External Incident</h4>
                <p class="text-muted mb-0">Complete the official external incident form for discipline review and follow-up.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/incidents/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back to Incidents
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data" action="<?= url('views/house-master/incidents/upload/upload.php') ?>">
                    <div class="alert alert-light border mb-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                           
                            <a class="btn btn-outline-primary btn-sm" href="<?= url('templates/external-incident-template.docx') ?>" target="_blank" rel="noopener">
                                <i class="bi bi-file-earmark-word me-1"></i>Download Word Template
                                <div class="form-text small mb-0">Fill out the official external incident form in Word format, then upload the completed scan or PDF below.</div>
                            </a>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Upload External Incident</label>
                            <input type="file" name="signedBond" class="form-control" accept=".jpeg,.jpg,image/jpeg,.pdf,application/pdf" required>
                            <div class="form-text">Accepted formats: JPEG and PDF only. Fill the Word template first, then upload the completed scan or PDF.</div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/incidents/index/index.php') ?>">Cancel</a>
                        <button type="submit" class="btn btn-info">
                            <i class="bi bi-cloud-upload me-1"></i>Upload External Incident
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
