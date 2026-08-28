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

$id = sanitize($_GET['id'] ?? '');
$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}

$request = FirebaseService::getInstance()->getDocument(COL_VISITOR_REQUESTS, $id);
$student = $request ? ($studentMap[(string) ($request['studentId'] ?? '')] ?? null) : null;

if (!$request || (!$student && ($request['houseId'] ?? null) !== $houseId)) {
    flash('error', 'Visitor request not found for your house.');
    redirect(url('views/senior-houseparent/visitors/requests/requests.php'));
}

$studentName = $student ? trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) : ($request['studentId'] ?? '—');
if ($student && !empty($student['admissionNo'])) {
    $studentName .= ' [' . $student['admissionNo'] . ']';
}

$pageTitle = 'Visitor Request Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitor Requests', 'href' => url('views/senior-houseparent/visitors/requests/requests.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1">Visitor Request</h5>
                    <p class="text-muted mb-0"><?= e($request['visitorName'] ?? 'Visitor') ?></p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/visitors/requests/requests.php') ?>">Back</a>
            </div>
            <dl class="row mt-4">
                <dt class="col-sm-4">Student</dt>
                <dd class="col-sm-8"><?= e($studentName) ?></dd>

                <dt class="col-sm-4">Visit Date</dt>
                <dd class="col-sm-8"><?= e($request['requestedDate'] ?? $request['visitDate'] ?? '—') ?></dd>

                <dt class="col-sm-4">Relationship</dt>
                <dd class="col-sm-8"><?= e($request['relationship'] ?? '—') ?></dd>

                <dt class="col-sm-4">Purpose</dt>
                <dd class="col-sm-8"><?= e($request['purpose'] ?? '—') ?></dd>

                <dt class="col-sm-4">Status</dt>
                <dd class="col-sm-8">
                    <span class="badge bg-<?= ($request['status'] ?? '') === 'approved' ? 'success' : (($request['status'] ?? '') === 'rejected' ? 'danger' : 'warning') ?>">
                        <?= e(ucfirst($request['status'] ?? 'pending')) ?>
                    </span>
                </dd>
            </dl>
            <?php if (($request['status'] ?? 'pending') === 'pending'): ?>
                <div class="d-flex gap-2 mt-4">
                    <form method="POST" action="<?= url('views/senior-houseparent/visitors/requests/requests.php') ?>">
                        <input type="hidden" name="action" value="approve_request">
                        <input type="hidden" name="requestId" value="<?= e($id) ?>">
                        <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Approve Request</button>
                    </form>
                    <a class="btn btn-outline-danger" href="<?= url('views/senior-houseparent/visitors/requests/requests.php') ?>">Reject from Request List</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>