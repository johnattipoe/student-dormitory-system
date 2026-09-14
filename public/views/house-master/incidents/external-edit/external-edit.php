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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

$fileName = sanitize($_GET['file'] ?? '');
$metadataPath = $fileName !== '' ? APP_ROOT . '/public/uploads/external-incidents/' . basename($fileName) . '.json' : '';
$metadata = [];

if ($fileName === '' || !file_exists($metadataPath)) {
    flash('error', 'External incident record not found.');
    redirect(url('views/house-master/incidents/index/index.php'));
}

$decoded = json_decode((string) file_get_contents($metadataPath), true);
if (is_array($decoded)) {
    $metadata = $decoded;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metadata['studentName'] = sanitize($_POST['studentName'] ?? $metadata['studentName'] ?? '');
    $metadata['incidentType'] = sanitize($_POST['incidentType'] ?? $metadata['incidentType'] ?? '');
    $metadata['incidentDate'] = sanitize($_POST['incidentDate'] ?? $metadata['incidentDate'] ?? '');
    $metadata['status'] = sanitize($_POST['status'] ?? $metadata['status'] ?? 'submitted');
    file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    flash('success', 'External incident record updated successfully.');
    redirect(url('views/house-master/incidents/index/index.php'));
}

$pageTitle = 'Edit External Incident';
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit External Incident</h4>
                <p class="text-muted mb-0">Update the record details for this uploaded incident</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/incidents/index/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <div class="card stat-card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Student Name</label>
                            <input type="text" name="studentName" class="form-control" value="<?= e((string) ($metadata['studentName'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Incident Type</label>
                            <input type="text" name="incidentType" class="form-control" value="<?= e((string) ($metadata['incidentType'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Incident Date</label>
                            <input type="date" name="incidentDate" class="form-control" value="<?= e((string) ($metadata['incidentDate'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="submitted" <?= (($metadata['status'] ?? 'submitted') === 'submitted') ? 'selected' : '' ?>>Submitted</option>
                                <option value="reviewed" <?= (($metadata['status'] ?? 'submitted') === 'reviewed') ? 'selected' : '' ?>>Reviewed</option>
                                <option value="resolved" <?= (($metadata['status'] ?? 'submitted') === 'resolved') ? 'selected' : '' ?>>Resolved</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/incidents/index/index.php') ?>">Cancel</a>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
