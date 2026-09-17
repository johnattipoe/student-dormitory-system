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
    $name = trim((string) ($_POST['name'] ?? ''));
    $amount = (float) ($_POST['amount'] ?? 0);
    $period = trim((string) ($_POST['period'] ?? 'Monthly'));

    if ($name !== '' && $amount > 0) {
        try {
            $payload = [
                'name' => $name,
                'amount' => $amount,
                'period' => $period,
                'status' => 'active',
            ];

            $recordExists = false;
            try {
                $recordExists = FirebaseService::getInstance()->getDocument(COL_FINANCE_FEES, $id) !== null;
            } catch (Throwable $e) {
                $recordExists = false;
            }

            if ($recordExists) {
                FirebaseService::getInstance()->updateDocument(COL_FINANCE_FEES, $id, $payload);
            } else {
                FirebaseService::getInstance()->addDocument(COL_FINANCE_FEES, $payload, $id);
            }

            flash('success', 'Fee category updated.');
            redirect(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php')));
        } catch (Throwable $e) {
            flash('error', 'Unable to update fee category: ' . $e->getMessage());
        }
    } else {
        flash('error', 'Fee name and amount are required.');
    }
}

$pageTitle = 'Edit Fee Category';
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
                <h4 class="mb-1 fw-bold">Edit Fee Category</h4>
                <p class="text-muted mb-0">Update the fee name, amount, or billing period.</p>
            </div>
            <a href="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php'))) ?>" class="btn btn-outline-secondary">Back</a>
        </div>

        <div class="card stat-card p-4" style="max-width: 720px;">
            <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/fees/edit/edit.php') . '&id=' . urlencode($id))) ?>" class="row g-3 align-items-end">
                <input type="hidden" name="id" value="<?= e($id) ?>">

                <div class="col-md-5">
                    <label class="form-label fw-bold">Fee Name</label>
                    <input type="text" name="name" class="form-control" value="<?= e((string) ($fee['name'] ?? '')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Amount</label>
                    <input type="number" name="amount" step="0.01" class="form-control" value="<?= e((string) ($fee['amount'] ?? '0')) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Period</label>
                    <select name="period" class="form-select">
                        <?php $selectedPeriod = (string) ($fee['period'] ?? 'Monthly'); ?>
                        <option value="Monthly" <?= $selectedPeriod === 'Monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="Termly" <?= $selectedPeriod === 'Termly' ? 'selected' : '' ?>>Termly</option>
                        <option value="Yearly" <?= $selectedPeriod === 'Yearly' ? 'selected' : '' ?>>Yearly</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
