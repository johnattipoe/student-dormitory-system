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

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $recordExists = false;
        try {
            $recordExists = FirebaseService::getInstance()->getDocument(COL_FINANCE_FEES, $id) !== null;
        } catch (Throwable $e) {
            $recordExists = false;
        }

        if ($recordExists) {
            FirebaseService::getInstance()->deleteDocument(COL_FINANCE_FEES, $id);
        } else {
            $deletedFallbackFeeIds[] = $id;
            $deletedFallbackFeeIds = array_values(array_unique(array_map('strval', $deletedFallbackFeeIds)));
            $_SESSION['deleted_fallback_fee_ids'] = $deletedFallbackFeeIds;
        }

        flash('success', 'Fee category deleted.');
        redirect(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php')));
    } catch (Throwable $e) {
        flash('error', 'Unable to delete fee category: ' . $e->getMessage());
        redirect(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php')));
    }
}

$pageTitle = 'Delete Fee Category';
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
        <div class="card stat-card p-4 mx-auto" style="max-width: 620px;">
            <div class="text-center mb-3">
                <i class="bi bi-exclamation-triangle text-warning fs-1"></i>
                <h4 class="mt-3 fw-bold">Delete Fee Category?</h4>
                <p class="text-muted mb-4">This will permanently remove <strong><?= e((string) ($fee['name'] ?? 'this fee')) ?></strong> from the finance list.</p>
            </div>
            <form method="POST" class="d-flex justify-content-center gap-2">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <a href="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php'))) ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-danger">Yes, Delete</button>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
