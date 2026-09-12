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

use App\Services\VisitorService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$houseId = current_user()['houseId'] ?? null;
$service = new VisitorService();
$visitor = null;
foreach ($service->byHouse($houseId) as $record) {
    if (($record['id'] ?? '') === $id) {
        $visitor = $record;
        break;
    }
}

if (!$visitor) {
    flash('error', 'Visitor not found.');
    redirect(url('views/house-master/visitors/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service->update($id, [
        'visitorName' => sanitize($_POST['visitorName'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'purpose' => sanitize($_POST['purpose'] ?? ''),
        'status' => sanitize($_POST['status'] ?? 'registered'),
    ]);
    flash('success', 'Visitor updated successfully.');
    redirect(url('views/house-master/visitors/view/view.php?id=' . urlencode($id)));
}

$pageTitle = 'Edit Visitor';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index/index.php'), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Visitor Record</h4>
                <p class="text-muted mb-0">Update visit details and check-in status</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/visitors/view/view.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-eye me-1"></i>View
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/visitors/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0" style="max-width: 700px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Visitor Information</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/house-master/visitors/edit/edit.php?id=' . urlencode($id)) ?>">
                    <input type="hidden" name="id" value="<?= e($id) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Visitor Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input name="visitorName" class="form-control" value="<?= e($visitor['visitorName'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-telephone"></i></span>
                                <input name="phone" class="form-control" value="<?= e($visitor['phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Visit Status</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-check2-circle"></i></span>
                                <select name="status" class="form-select">
                                    <option value="registered" <?= ($visitor['status'] ?? '') === 'registered' ? 'selected' : '' ?>>Registered (Awaiting Check-in)</option>
                                    <option value="inside" <?= ($visitor['status'] ?? '') === 'inside' ? 'selected' : '' ?>>Inside (Currently Visiting)</option>
                                    <option value="checked_out" <?= ($visitor['status'] ?? '') === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Purpose of Visit</label>
                            <textarea name="purpose" class="form-control" rows="3"><?= e($visitor['purpose'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/visitors/view/view.php?id=' . urlencode($id)) ?>">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>