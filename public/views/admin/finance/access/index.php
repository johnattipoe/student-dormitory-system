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


$pageTitle = 'Finance Access Control';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $userId = trim((string) ($_POST['userId'] ?? ''));
        $houseId = trim((string) ($_POST['houseId'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $action = strtolower(trim((string) ($_POST['action'] ?? '')));

        if ($userId !== '' && $houseId !== '' && $role !== '') {
            $match = FirebaseService::getInstance()->getCollection(COL_FINANCE_ACCESS, [
                ['userId', '=', $userId],
                ['houseId', '=', $houseId],
            ], 20);

            $recordId = null;
            foreach ($match as $item) {
                if ((string) ($item['userId'] ?? '') === $userId && (string) ($item['houseId'] ?? '') === $houseId) {
                    $recordId = (string) ($item['id'] ?? '');
                    break;
                }
            }

            $payload = [
                'userId' => $userId,
                'role' => $role,
                'houseId' => $houseId,
                'status' => $action === 'approve' ? 'approved' : 'revoked',
                'level' => 'view',
                'approvedBy' => current_user_id() ?: 'admin',
                'approvedAt' => date('Y-m-d H:i:s'),
            ];

            if ($recordId !== null && $recordId !== '') {
                FirebaseService::getInstance()->updateDocument(COL_FINANCE_ACCESS, $recordId, $payload);
            } else {
                FirebaseService::getInstance()->addDocument(COL_FINANCE_ACCESS, $payload, $userId . '_' . $houseId);
            }

            flash('success', $action === 'approve' ? 'Finance access approved.' : 'Finance access revoked.');
        }
    }
} catch (Throwable $e) {
    flash('error', 'Unable to update finance access: ' . $e->getMessage());
}

$staff = [];
$allHouseStaff = [];
$houses = [];

try {
    $houses = FirebaseService::getInstance()->getCollection(COL_HOUSES, [], 200);
    $users = FirebaseService::getInstance()->getCollection(COL_USERS, [], 500);
    foreach ($users as $user) {
        $role = strtolower((string) ($user['role'] ?? ''));
        if (!in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true)) {
            continue;
        }

        $userId = (string) ($user['uid'] ?? $user['id'] ?? '');
        $houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
        if ($userId === '') {
            continue;
        }

        $accessRecord = null;
        $accessMatches = FirebaseService::getInstance()->getCollection(COL_FINANCE_ACCESS, [['userId', '=', $userId]], 20);
        foreach ($accessMatches as $match) {
            if ((string) ($match['houseId'] ?? '') === $houseId) {
                $accessRecord = $match;
                break;
            }
        }

        $status = strtolower((string) ($accessRecord['status'] ?? 'pending'));
        $allHouseStaff[] = [
            'id' => $userId,
            'name' => (string) ($user['name'] ?? $user['fullName'] ?? 'House Staff'),
            'role' => $role,
            'houseId' => $houseId,
            'status' => $status,
            'houseName' => '',
        ];
    }

    foreach ($allHouseStaff as &$member) {
        $member['houseName'] = 'Unassigned';
        foreach ($houses as $house) {
            if ((string) ($house['id'] ?? '') === $member['houseId']) {
                $member['houseName'] = (string) ($house['name'] ?? $house['houseName'] ?? 'House');
                break;
            }
        }
    }
    unset($member);
} catch (Throwable $e) {
    $allHouseStaff = [];
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-currency-dollar', 'label' => 'Finance', 'href' => url('views/admin/finance/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold">Finance Access Control</h4>
                <p class="text-muted mb-0">Approve or revoke finance access for house staff assigned to each house.</p>
            </div>
            <a href="<?= url('index.php?route=' . urlencode('/views/admin/finance/index/index.php')) ?>" class="btn btn-outline-secondary">Back to Finance</a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Staff</th>
                                <th>Role</th>
                                <th>House</th>
                                <th>Access Level</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allHouseStaff as $member): ?>
                                <tr>
                                    <td><?= e($member['name']) ?></td>
                                    <td><?= e(ucwords(str_replace('_', ' ', $member['role']))) ?></td>
                                    <td><?= e($member['houseName']) ?></td>
                                    <td>View Finance</td>
                                    <td>
                                        <?php if (strtolower((string) $member['status']) === 'approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (strtolower((string) $member['status']) === 'approved'): ?>
                                            <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/access/index.php'))) ?>" class="d-inline">
                                                <input type="hidden" name="userId" value="<?= e($member['id']) ?>">
                                                <input type="hidden" name="houseId" value="<?= e($member['houseId']) ?>">
                                                <input type="hidden" name="role" value="<?= e($member['role']) ?>">
                                                <input type="hidden" name="action" value="revoke">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Revoke</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/admin/finance/access/index.php'))) ?>" class="d-inline">
                                                <input type="hidden" name="userId" value="<?= e($member['id']) ?>">
                                                <input type="hidden" name="houseId" value="<?= e($member['houseId']) ?>">
                                                <input type="hidden" name="role" value="<?= e($member['role']) ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button class="btn btn-sm btn-success" type="submit">Approve</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($allHouseStaff)): ?>
                                <tr>
                                    <td colspan="6" class="text-muted text-center">No house staff found to approve.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
