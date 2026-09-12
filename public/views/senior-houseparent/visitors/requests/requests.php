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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\StudentService;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_request') {
    $requestId = sanitize($_POST['requestId'] ?? '');
    if ($requestId) {
        try {
            FirebaseService::getInstance()->updateDocument(\COL_VISITOR_REQUESTS, $requestId, ['status' => 'approved', 'approvedBy' => current_user()['uid'], 'approvedAt' => date('Y-m-d H:i:s')]);
            flash('success', 'Visitor request approved');
        } catch (Exception $e) {
            flash('error', 'Failed to approve request: ' . $e->getMessage());
        }
        redirect(base_url('index.php?route=/views/senior-houseparent/visitors/requests/requests.php'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_request') {
    $requestId = sanitize($_POST['requestId'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');
    if ($requestId) {
        try {
            FirebaseService::getInstance()->updateDocument(\COL_VISITOR_REQUESTS, $requestId, ['status' => 'rejected', 'rejectedBy' => current_user()['uid'], 'rejectionReason' => $reason, 'rejectedAt' => date('Y-m-d H:i:s')]);
            flash('success', 'Visitor request rejected');
        } catch (Exception $e) {
            flash('error', 'Failed to reject request: ' . $e->getMessage());
        }
        redirect(base_url('index.php?route=/views/senior-houseparent/visitors/requests/requests.php'));
    }
}

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}

$statusFilter = sanitize($_GET['status'] ?? '');
$requestSearch = strtolower(sanitize($_GET['search'] ?? ''));
$visitorRequests = FirebaseService::getInstance()->getCollection(\COL_VISITOR_REQUESTS, [], 500);
$filteredRequests = [];
foreach ($visitorRequests as $request) {
    $studentId = (string) ($request['studentId'] ?? '');
    if ($houseId && !empty($studentMap[$studentId]) && ($studentMap[$studentId]['houseId'] ?? null) !== $houseId) {
        continue;
    }
    if ($statusFilter && ($request['status'] ?? 'pending') !== $statusFilter) {
        continue;
    }
    if ($requestSearch !== '' && !str_contains(strtolower((string) ($request['visitorName'] ?? '')), $requestSearch) && !str_contains(strtolower(trim(($studentMap[$studentId]['firstName'] ?? '') . ' ' . ($studentMap[$studentId]['lastName'] ?? ''))), $requestSearch)) {
        continue;
    }
    if (!$houseId || (($request['houseId'] ?? null) === $houseId) || !empty($studentMap[$studentId])) {
        $filteredRequests[] = $request;
    }
}

$pendingCount = count(array_filter($filteredRequests, fn($r) => ($r['status'] ?? 'pending') === 'pending'));
$approvedCount = count(array_filter($filteredRequests, fn($r) => ($r['status'] ?? '') === 'approved'));
$rejectedCount = count(array_filter($filteredRequests, fn($r) => ($r['status'] ?? '') === 'rejected'));

$pageTitle = 'Visitor Requests';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/senior-houseparent/attendance/index/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/senior-houseparent/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/senior-houseparent/visitors/index/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/senior-houseparent/incidents/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/senior-houseparent/notifications/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Visitor Requests</h5>
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="route" value="/views/senior-houseparent/visitors/requests/requests.php">
                <input name="search" class="form-control form-control-sm" placeholder="Search visitor or student" value="<?= e($requestSearch) ?>">
                <select name="status" class="form-select form-select-sm" style="max-width: 150px;" onchange="this.form.submit()">
                    <option value="">All Requests</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </form>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Pending</div>
                    <div class="fs-2 fw-bold"><?= e((string) $pendingCount) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Approved</div>
                    <div class="fs-2 fw-bold"><?= e((string) $approvedCount) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Rejected</div>
                    <div class="fs-2 fw-bold"><?= e((string) $rejectedCount) ?></div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Visitor</th>
                        <th>Student</th>
                        <th>Visit Date</th>
                        <th>Relationship</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredRequests)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No visitor requests found for your house.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredRequests as $request): ?>
                            <?php $requestStudent = $studentMap[(string) ($request['studentId'] ?? '')] ?? null; ?>
                            <tr>
                                <td><?= e($request['visitorName'] ?? '—') ?></td>
                                <td><?= e(trim((($requestStudent['firstName'] ?? '') . ' ' . ($requestStudent['lastName'] ?? '')))) ?: e($request['studentId'] ?? '—') ?></td>
                                <td><?= e($request['requestedDate'] ?? ($request['visitDate'] ?? '—')) ?></td>
                                <td><?= e($request['relationship'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= ($request['status'] ?? '') === 'approved' ? 'success' : (($request['status'] ?? '') === 'rejected' ? 'danger' : 'warning') ?>"><?= e($request['status'] ?? 'pending') ?></span></td>
                                <td>
                                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/visitors/request-view/request-view.php?id=' . urlencode((string) ($request['id'] ?? ''))) ?>">View</a>
                                    <?php if (($request['status'] ?? 'pending') === 'pending'): ?>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="approve_request">
                                                <input type="hidden" name="requestId" value="<?= e((string) ($request['id'] ?? '')) ?>">
                                                <button class="btn btn-success btn-sm">Approve</button>
                                            </form>
                                            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal_<?= md5((string) ($request['id'] ?? '')) ?>">Reject</button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php foreach ($filteredRequests as $request): ?>
            <?php if (($request['status'] ?? 'pending') === 'pending'): ?>
                <div class="modal fade" id="rejectModal_<?= md5((string) ($request['id'] ?? '')) ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Reject Request</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="reject_request">
                                    <input type="hidden" name="requestId" value="<?= e((string) ($request['id'] ?? '')) ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Reason for Rejection</label>
                                        <textarea name="reason" class="form-control" placeholder="Why are you rejecting this request?" required></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Reject Request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
