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
use App\Services\HouseService;

$houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

$visitors = (new VisitorService())->byHouse($houseId);
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}

$counts = ['inside' => 0, 'checked_out' => 0, 'approved' => 0, 'pending' => 0];
foreach ($visitors as $visitor) {
    $status = strtolower((string) ($visitor['status'] ?? 'unknown'));
    if (isset($counts[$status])) {
        $counts[$status]++;
    } else {
        $counts[$status] = 1;
    }
}

$pageTitle = 'Visitor Activity Report';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/senior-houseparent/visitors/index/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/senior-houseparent/reports/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Visitor Activity Report (<?= e($houseName) ?>)</h5>
                <p class="text-muted mb-0">Record of all parent, guardian, and guest visits.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-success btn-sm" href="<?= url('views/senior-houseparent/reports/export/export.php?type=visitors') ?>">
                    <i class="bi bi-filetype-csv me-1"></i> Export Visitors CSV
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/reports/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i> Reports Overview
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <?php foreach ([
                'inside' => ['Currently Inside', 'success'],
                'checked_out' => ['Checked Out', 'secondary'],
                'approved' => ['Approved Visits', 'primary'],
                'pending' => ['Pending Requests', 'warning'],
            ] as $vKey => [$vLabel, $vColor]): ?>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card p-3">
                        <small class="text-muted"><?= e($vLabel) ?></small>
                        <strong class="fs-2 text-<?= e($vColor) ?>"><?= e((string) ($counts[$vKey] ?? 0)) ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-bold">Visitor Activity Log</h6>
                <a class="btn btn-sm btn-primary" href="<?= url('views/senior-houseparent/visitors/index/index.php') ?>">
                    <i class="bi bi-people me-1"></i> Open Visitor Log
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover data-table w-100">
                    <thead>
                        <tr>
                            <th>Visitor Name</th>
                            <th>Contact / Phone</th>
                            <th>Student Visited</th>
                            <th>Purpose / Relation</th>
                            <th>Status</th>
                            <th>Visit Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($visitors)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No visitor records found for this house.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($visitors as $visitor): ?>
                                <?php 
                                    $student = $studentMap[(string) ($visitor['studentId'] ?? '')] ?? [];
                                    $sName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($visitor['studentId'] ?? 'Student');
                                    $status = strtolower((string) ($visitor['status'] ?? 'unknown'));
                                    $badgeColor = match($status) {
                                        'inside' => 'success',
                                        'checked_out' => 'secondary',
                                        'approved' => 'primary',
                                        'pending' => 'warning',
                                        'rejected', 'cancelled' => 'danger',
                                        default => 'info'
                                    };
                                    $visitDate = (string) ($visitor['visitDate'] ?? substr((string) ($visitor['createdAt'] ?? '—'), 0, 10));
                                ?>
                                <tr>
                                    <td><strong><?= e($visitor['visitorName'] ?? $visitor['name'] ?? 'Visitor') ?></strong></td>
                                    <td><?= e($visitor['phone'] ?? $visitor['contact'] ?? '—') ?></td>
                                    <td>
                                        <div><strong><?= e($sName) ?></strong></div>
                                        <?php if (!empty($student['admissionNo'])): ?>
                                            <div class="small text-muted">[<?= e($student['admissionNo']) ?>]</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= e($visitor['purpose'] ?? 'General Visit') ?></div>
                                        <?php if (!empty($visitor['relationship'])): ?>
                                            <div class="small text-muted">(<?= e($visitor['relationship']) ?>)</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= e($badgeColor) ?>">
                                            <?= e(ucwords(str_replace('_', ' ', $status))) ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap small text-muted"><?= e($visitDate) ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/senior-houseparent/visitors/view/view.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>">
                                            <i class="bi bi-eye"></i> Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>