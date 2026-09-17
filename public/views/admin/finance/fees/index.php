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

$feeCategories = [];
try {
    $feeCategories = FirebaseService::getInstance()->getCollection(COL_FINANCE_FEES, [], 200);
} catch (Throwable $e) {
    $feeCategories = [];
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
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0">Fee list</h5>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFeeModal">
                        <i class="bi bi-plus-circle me-1"></i>Add Fee
                    </button>
                </div>

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
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#viewFeeModal<?= e($feeId) ?>" title="View"><i class="bi bi-eye"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editFeeModal<?= e($feeId) ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteFeeModal<?= e($feeId) ?>" title="Delete"><i class="bi bi-trash"></i></button>
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

                                <div class="modal fade" id="editFeeModal<?= e($feeId) ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php'))) ?>">
                                                <input type="hidden" name="action" value="update_fee">
                                                <input type="hidden" name="id" value="<?= e($feeId) ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit fee</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body row g-3">
                                                    <div class="col-12">
                                                        <label class="form-label">Fee Name</label>
                                                        <input type="text" name="name" class="form-control" value="<?= e((string) ($fee['name'] ?? '')) ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Amount</label>
                                                        <input type="number" name="amount" step="0.01" class="form-control" value="<?= e((string) ($fee['amount'] ?? '0')) ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Period</label>
                                                        <select name="period" class="form-select">
                                                            <option value="Monthly" <?= strtolower((string) ($fee['period'] ?? 'Monthly')) === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                                            <option value="Termly" <?= strtolower((string) ($fee['period'] ?? 'Monthly')) === 'termly' ? 'selected' : '' ?>>Termly</option>
                                                            <option value="Yearly" <?= strtolower((string) ($fee['period'] ?? 'Monthly')) === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal fade" id="deleteFeeModal<?= e($feeId) ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-sm modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php'))) ?>">
                                                <input type="hidden" name="action" value="delete_fee">
                                                <input type="hidden" name="id" value="<?= e($feeId) ?>">
                                                <div class="modal-body text-center">
                                                    <i class="bi bi-trash fs-1 text-danger d-block mb-3"></i>
                                                    <h5 class="fw-bold">Delete fee?</h5>
                                                    <p class="mb-0">This will remove <strong><?= e((string) ($fee['name'] ?? 'this fee')) ?></strong> from the live finance collection.</p>
                                                </div>
                                                <div class="modal-footer justify-content-center">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="addFeeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/fees/index.php'))) ?>">
                        <input type="hidden" name="action" value="add_fee">
                        <div class="modal-header">
                            <h5 class="modal-title">Add fee category</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body row g-3">
                            <div class="col-12">
                                <label class="form-label">Fee Name</label>
                                <input type="text" name="name" class="form-control" placeholder="Boarding Fee" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Amount</label>
                                <input type="number" name="amount" step="0.01" class="form-control" placeholder="650" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Period</label>
                                <select name="period" class="form-select">
                                    <option value="Monthly">Monthly</option>
                                    <option value="Termly">Termly</option>
                                    <option value="Yearly">Yearly</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save fee</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
