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

$pageTitle = 'Fee Categories';
$defaultFees = [
    ['id' => 'boarding-fee', 'name' => 'Boarding Fee', 'amount' => 650.00, 'period' => 'Monthly', 'status' => 'active'],
    ['id' => 'utility-fee', 'name' => 'Utility Fee', 'amount' => 120.00, 'period' => 'Monthly', 'status' => 'active'],
];

$editFeeId = trim((string) ($_GET['edit'] ?? ''));
$editingFee = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'add_fee'));

    if ($action === 'delete_fee') {
        $feeId = trim((string) ($_POST['id'] ?? ''));
        if ($feeId !== '') {
            try {
                FirebaseService::getInstance()->deleteDocument(COL_FINANCE_FEES, $feeId);
                flash('success', 'Fee category deleted.');
            } catch (Throwable $e) {
                flash('error', 'Unable to delete fee category: ' . $e->getMessage());
            }
        }
        redirect(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php')));
    }

    if ($action === 'update_fee') {
        $feeId = trim((string) ($_POST['id'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $amount = (float) ($_POST['amount'] ?? 0);
        $period = trim((string) ($_POST['period'] ?? 'Monthly'));
        if ($feeId !== '' && $name !== '' && $amount > 0) {
            $payload = ['name' => $name, 'amount' => $amount, 'period' => $period, 'status' => 'active'];
            try {
                FirebaseService::getInstance()->updateDocument(COL_FINANCE_FEES, $feeId, $payload);
                flash('success', 'Fee category updated.');
            } catch (Throwable $e) {
                flash('error', 'Unable to update fee category: ' . $e->getMessage());
            }
        }
        redirect(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php')));
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $amount = (float) ($_POST['amount'] ?? 0);
    $period = trim((string) ($_POST['period'] ?? 'Monthly'));
    if ($name !== '' && $amount > 0) {
        $payload = ['name' => $name, 'amount' => $amount, 'period' => $period, 'status' => 'active'];
        try {
            FirebaseService::getInstance()->addDocument(COL_FINANCE_FEES, $payload, strtolower(str_replace(' ', '-', $name)) . '-' . time());
            flash('success', 'Fee category saved.');
        } catch (Throwable $e) {
            flash('error', 'Unable to save fee category: ' . $e->getMessage());
        }
        redirect(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php')));
    }
}

$feeCategories = $defaultFees;
try {
    $feeCategories = FirebaseService::getInstance()->getCollection(COL_FINANCE_FEES, [], 200);
    if (empty($feeCategories)) {
        $feeCategories = $defaultFees;
    }
} catch (Throwable $e) {
    $feeCategories = $defaultFees;
}

if ($editFeeId !== '') {
    foreach ($feeCategories as $fee) {
        if ((string) ($fee['id'] ?? '') === $editFeeId) {
            $editingFee = $fee;
            break;
        }
    }
}

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold">Fee Categories</h4>
                <p class="text-muted mb-0">Live fee set from the dormitory finance collection, with a default fallback when no records exist.</p>
            </div>
            <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/index/index.php')) ?>" class="btn btn-outline-secondary">Back to Finance</a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($editingFee !== null): ?>
                    <div class="alert alert-info d-flex justify-content-between align-items-center mb-3">
                        <div>Editing fee: <strong><?= e((string) ($editingFee['name'] ?? '')) ?></strong></div>
                        <a href="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php'))) ?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php'))) ?>" class="row g-3 align-items-end">
                    <?php if ($editingFee !== null): ?>
                        <input type="hidden" name="action" value="update_fee">
                        <input type="hidden" name="id" value="<?= e((string) ($editingFee['id'] ?? '')) ?>">
                    <?php else: ?>
                        <input type="hidden" name="action" value="add_fee">
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label">Fee Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Boarding Fee" value="<?= e((string) ($editingFee['name'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" step="0.01" class="form-control" placeholder="650" value="<?= e((string) ($editingFee['amount'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Period</label>
                        <select name="period" class="form-select">
                            <?php $selectedPeriod = (string) ($editingFee['period'] ?? 'Monthly'); ?>
                            <option value="Monthly" <?= $selectedPeriod === 'Monthly' ? 'selected' : '' ?>>Monthly</option>
                            <option value="Termly" <?= $selectedPeriod === 'Termly' ? 'selected' : '' ?>>Termly</option>
                            <option value="Yearly" <?= $selectedPeriod === 'Yearly' ? 'selected' : '' ?>>Yearly</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit"><?= $editingFee !== null ? 'Save' : 'Add' ?></button>
                    </div>
                </form>

                <div class="table-responsive mt-4">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Fee Type</th>
                                <th>Amount</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th class="text-center" style="width: 190px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeCategories as $fee): ?>
                                <?php $feeId = (string) ($fee['id'] ?? ''); ?>
                                <tr>
                                    <td><?= e((string) ($fee['name'] ?? 'Unnamed Fee')) ?></td>
                                    <td>GHS <?= number_format((float) ($fee['amount'] ?? 0), 2) ?></td>
                                    <td><?= e((string) ($fee['period'] ?? 'Monthly')) ?></td>
                                    <td>
                                        <?php if (strtolower((string) ($fee['status'] ?? 'active')) === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        <a href="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/fees/view/view.php') . '&id=' . urlencode($feeId))) ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/fees/edit/edit.php') . '&id=' . urlencode($feeId))) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a href="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/fees/delete/delete.php') . '&id=' . urlencode($feeId))) ?>" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>

                                <div class="modal fade" id="viewFeeModal<?= e($feeId) ?>" tabindex="-1" aria-labelledby="viewFeeModalLabel<?= e($feeId) ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="viewFeeModalLabel<?= e($feeId) ?>"><?= e((string) ($fee['name'] ?? 'Unnamed Fee')) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong>Amount:</strong> GHS <?= number_format((float) ($fee['amount'] ?? 0), 2) ?></p>
                                                <p><strong>Period:</strong> <?= e((string) ($fee['period'] ?? 'Monthly')) ?></p>
                                                <p><strong>Status:</strong> <?= e((string) ($fee['status'] ?? 'active')) ?></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
