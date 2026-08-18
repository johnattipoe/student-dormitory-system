<?php
// Ensure bootstrap is loaded
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

$allowedRoles = [ROLE_NURSE, ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\AuditService;
use App\Services\MedicalService;
use App\Services\StudentService;

// Get filter parameters
$filterAction = sanitize($_GET['filterAction'] ?? '');
$filterStudent = sanitize($_GET['filterStudent'] ?? '');
$dateFrom = sanitize($_GET['dateFrom'] ?? date('Y-m-d', strtotime('-30 days')));
$dateTo = sanitize($_GET['dateTo'] ?? date('Y-m-d'));

// Fetch audits
$auditService = new AuditService();
$audits = $auditService->all($filterAction ?: null, 500);

// Filter by date range
$dateStart = strtotime($dateFrom);
$dateEnd = strtotime($dateTo) + 86400;

$audits = array_filter($audits, function($a) use ($dateStart, $dateEnd, $filterStudent) {
    $time = strtotime($a['timestamp'] ?? '');
    $inDateRange = $time >= $dateStart && $time <= $dateEnd;
    
    if ($filterStudent) {
        $inStudentRange = ($a['studentId'] ?? '') === $filterStudent;
        return $inDateRange && $inStudentRange;
    }
    
    return $inDateRange;
});

// Sort by timestamp descending
usort($audits, function($a, $b) {
    $timeA = strtotime($a['timestamp'] ?? '');
    $timeB = strtotime($b['timestamp'] ?? '');
    return $timeB - $timeA;
});

$students = StudentService::all();

$pageTitle = 'Medical Record Audit Trail';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Audit Trail', 'href' => url('views/nurse/medical-records/audit-trail.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Medical Record Audit Trail</h5>

        <!-- Filters -->
        <div class="card stat-card p-3 mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Date From</label>
                    <input type="date" name="dateFrom" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Date To</label>
                    <input type="date" name="dateTo" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Action</label>
                    <select name="filterAction" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        <option value="created" <?= $filterAction === 'created' ? 'selected' : '' ?>>Created</option>
                        <option value="updated" <?= $filterAction === 'updated' ? 'selected' : '' ?>>Updated</option>
                        <option value="severity_changed" <?= $filterAction === 'severity_changed' ? 'selected' : '' ?>>Severity Changed</option>
                        <option value="flagged" <?= $filterAction === 'flagged' ? 'selected' : '' ?>>Flagged</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Student</label>
                    <select name="filterStudent" class="form-select form-select-sm">
                        <option value="">All Students</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= e((string) ($student['id'] ?? '')) ?>" <?= $filterStudent === ($student['id'] ?? '') ? 'selected' : '' ?>>
                                <?= e($student['firstName'] ?? '') . ' ' . e($student['lastName'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="<?= url('views/nurse/medical-records/audit-trail.php') ?>" class="btn btn-outline-secondary btn-sm">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Audit Trail Table -->
        <div class="card stat-card p-3">
            <div class="mb-3">
                <strong><?= count($audits) ?></strong> audit record(s) found
            </div>

            <div class="table-responsive">
                <table class="table table-hover data-table w-100 small">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Action</th>
                            <th>Student</th>
                            <th>Changed By</th>
                            <th>Changes</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($audits)): ?>
                            <?php foreach ($audits as $audit): ?>
                                <?php $student = StudentService::find($audit['studentId'] ?? ''); ?>
                                <tr>
                                    <td>
                                        <small><?= e(substr($audit['timestamp'] ?? '', 0, 16)) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= match(($audit['action'] ?? '')) {
                                            'created' => 'success',
                                            'severity_changed' => 'danger',
                                            'flagged' => 'warning',
                                            'updated' => 'info',
                                            default => 'secondary'
                                        } ?>">
                                            <?= e(ucfirst(str_replace('_', ' ', $audit['action'] ?? ''))) ?>
                                        </span>
                                    </td>
                                    <td><?= e($student['firstName'] ?? '') . ' ' . e($student['lastName'] ?? '') ?></td>
                                    <td><?= e($audit['changedBy'] ?? '—') ?></td>
                                    <td>
                                        <small>
                                            <?php
                                            $changes = $audit['changes'] ?? [];
                                            echo AuditService::formatChanges($changes);
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small><?= e(substr($audit['reason'] ?? '—', 0, 50)) ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No audit records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="row g-3 mt-4">
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Total Changes</div>
                    <div class="fs-4 fw-bold"><?= count($audits) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Severity Changes</div>
                    <div class="fs-4 fw-bold">
                        <?= count(array_filter($audits, fn($a) => ($a['action'] ?? '') === 'severity_changed')) ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Flagged Records</div>
                    <div class="fs-4 fw-bold">
                        <?= count(array_filter($audits, fn($a) => ($a['action'] ?? '') === 'flagged')) ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Unique Users</div>
                    <div class="fs-4 fw-bold">
                        <?= count(array_unique(array_map(fn($a) => $a['changedBy'] ?? '', $audits))) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
