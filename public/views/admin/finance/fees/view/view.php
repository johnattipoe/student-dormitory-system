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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$defaultFees = [
    ['id' => 'boarding-fee', 'name' => 'Boarding Fee', 'amount' => 650.00, 'period' => 'Monthly', 'status' => 'active'],
    ['id' => 'utility-fee', 'name' => 'Utility Fee', 'amount' => 120.00, 'period' => 'Monthly', 'status' => 'active'],
];

$deletedFallbackFeeIds = $_SESSION['deleted_fallback_fee_ids'] ?? [];
if (!is_array($deletedFallbackFeeIds)) {
    $deletedFallbackFeeIds = [];
}
$deletedFallbackFeeIds = array_values(array_unique(array_map('strval', $deletedFallbackFeeIds)));
$defaultFees = array_values(array_filter($defaultFees, static fn (array $fee): bool => !in_array((string) ($fee['id'] ?? ''), $deletedFallbackFeeIds, true)));

$id = sanitize($_GET['id'] ?? '');
if ($id === '') {
    flash('error', 'Fee ID is required.');
    redirect(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php')));
}

$fee = null;
try {
    $fee = FirebaseService::getInstance()->getDocument(COL_FINANCE_FEES, $id);
} catch (Throwable $e) {
    $fee = null;
}

if (!$fee) {
    foreach ($defaultFees as $defaultFee) {
        if ((string) ($defaultFee['id'] ?? '') === $id) {
            $fee = $defaultFee;
            break;
        }
    }
}

if (!$fee) {
    flash('error', 'Fee category not found.');
    redirect(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php')));
}

$pageTitle = 'View Fee Category';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('index.php?route=' . urlencode('/views/admin/dashboard.php'))],
    ['icon' => 'bi-cash-stack', 'label' => 'Finance', 'href' => url('index.php?route=' . urlencode('/views/admin/finance/index/index.php'))],
    ['icon' => 'bi-tag', 'label' => 'Fee Categories', 'href' => url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php')), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold">Fee Category Details</h4>
                <p class="text-muted mb-0">Review the selected dormitory fee configuration.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php'))) ?>" class="btn btn-outline-secondary">Back</a>
                <a href="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/fees/edit/edit.php') . '&id=' . urlencode($id))) ?>" class="btn btn-primary">Edit</a>
            </div>
        </div>

        <div class="card stat-card p-4" style="max-width: 720px;">
            <div class="mb-4">
                <div class="text-muted small text-uppercase">Fee Name</div>
                <h3 class="mb-0"><?= e((string) ($fee['name'] ?? 'Unnamed Fee')) ?></h3>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="text-muted small text-uppercase">Amount</div>
                    <div class="fs-5 fw-semibold">GHS <?= number_format((float) ($fee['amount'] ?? 0), 2) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small text-uppercase">Period</div>
                    <div class="fs-5 fw-semibold"><?= e((string) ($fee['period'] ?? 'Monthly')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small text-uppercase">Status</div>
                    <?php if (strtolower((string) ($fee['status'] ?? 'active')) === 'active'): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Draft</span>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small text-uppercase">Document ID</div>
                    <div class="small text-break"><?= e((string) ($fee['id'] ?? $id)) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
