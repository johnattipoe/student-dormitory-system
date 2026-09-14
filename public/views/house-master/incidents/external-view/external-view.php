<?php
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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

$fileName = sanitize($_GET['file'] ?? '');
$baseDir = APP_ROOT . '/public/uploads/external-incidents';
$filePath = $fileName !== '' ? $baseDir . '/' . basename($fileName) : '';
$metadataPath = $filePath !== '' ? $filePath . '.json' : '';
$metadata = [];

if ($filePath === '' || !file_exists($filePath)) {
    flash('error', 'External incident file not found.');
    redirect(url('views/house-master/incidents/index/index.php'));
}

if (file_exists($metadataPath)) {
    $decoded = json_decode((string) file_get_contents($metadataPath), true);
    if (is_array($decoded)) {
        $metadata = $decoded;
    }
}

$pageTitle = 'External Incident Details';
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-text text-info me-2"></i>External Incident Details</h4>
                <p class="text-muted mb-0">Uploaded incident record and document summary</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/incidents/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back to Records
                </a>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Print / Save as PDF
                </button>
                <a class="btn btn-success btn-sm" href="<?= url('uploads/external-incidents/' . basename($fileName)) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-download me-1"></i>Download File
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Student Name</span>
                        <strong><?= e((string) ($metadata['studentName'] ?? 'Unknown Student')) ?></strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Incident Type</span>
                        <strong><?= e((string) ($metadata['incidentType'] ?? 'External Incident')) ?></strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Incident Date</span>
                        <strong><?= e((string) ($metadata['incidentDate'] ?? '—')) ?></strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Submitted</span>
                        <strong><?= e((string) ($metadata['uploadedAt'] ?? '—')) ?></strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Status</span>
                        <span class="badge bg-success"><?= e((string) ($metadata['status'] ?? 'Submitted')) ?></span>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">File</span>
                        <strong><?= e((string) ($metadata['originalName'] ?? basename($fileName))) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-richtext me-2"></i>Uploaded Document</h6>
            </div>
            <div class="card-body p-0">
                <?php
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if ($extension === 'pdf') {
                    echo '<iframe src="' . e(url('uploads/external-incidents/' . basename($fileName))) . '" style="width:100%; height:800px; border:0;" title="External Incident PDF"></iframe>';
                } else {
                    echo '<div class="p-4 text-center"><img src="' . e(url('uploads/external-incidents/' . basename($fileName))) . '" class="img-fluid rounded border shadow-sm" alt="External Incident Document" /></div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
