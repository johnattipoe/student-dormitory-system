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

use App\Services\VisitorService;
use App\Services\StudentService;

$id = sanitize($_GET['id'] ?? '');
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
    flash('error', 'Visitor record not found.');
    redirect(url('views/senior-houseparent/visitors/index/index.php'));
}

$student = StudentService::find((string) ($visitor['studentId'] ?? ''));
$studentName = $student ? trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) : ($visitor['studentId'] ?? '—');
if ($student && !empty($student['admissionNo'])) {
    $studentName .= ' [' . $student['admissionNo'] . ']';
}

$pageTitle = 'Visitor Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/senior-houseparent/visitors/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:700px">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><?= e($visitor['visitorName'] ?? 'Visitor') ?></h5>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/visitors/index/index.php') ?>">Back</a>
            </div>
            <dl class="row mt-3">
                <dt class="col-sm-3">Student</dt>
                <dd class="col-sm-9"><?= e($studentName) ?></dd>

                <dt class="col-sm-3">Purpose</dt>
                <dd class="col-sm-9"><?= e($visitor['purpose'] ?? '—') ?></dd>

                <dt class="col-sm-3">Phone</dt>
                <dd class="col-sm-9"><?= e($visitor['phone'] ?? '—') ?></dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    <span class="badge bg-<?= ($visitor['status'] ?? '') === 'inside' ? 'success' : (($visitor['status'] ?? '') === 'checked_out' ? 'secondary' : 'primary') ?>">
                        <?= e(ucwords(str_replace('_', ' ', (string) ($visitor['status'] ?? 'registered')))) ?>
                    </span>
                </dd>
            </dl>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>