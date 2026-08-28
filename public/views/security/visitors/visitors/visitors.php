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
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\VisitorService;
use App\Services\StudentService;

$allVisitors = (new VisitorService())->all();
$totalVisitors = count($allVisitors);
$insideVisitors = count(array_filter($allVisitors, fn($v) => ($v['status'] ?? '') === 'inside'));
$checkedOutVisitors = count(array_filter($allVisitors, fn($v) => ($v['status'] ?? '') === 'checked_out'));

$students = [];
foreach (StudentService::all() as $student) {
    $sId = (string)($student['id'] ?? '');
    if ($sId !== '') {
        $students[$sId] = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
    }
}

$search = strtolower(sanitize($_GET['search'] ?? ''));
$statusFilter = sanitize($_GET['status'] ?? '');
$visitors = array_values(array_filter($allVisitors, function ($visitor) use ($search, $statusFilter) {
    return ($search === '' || str_contains(strtolower((string) ($visitor['visitorName'] ?? '')), $search) || str_contains(strtolower((string) ($visitor['studentId'] ?? '')), $search))
        && ($statusFilter === '' || ($visitor['status'] ?? '') === $statusFilter);
}));

$pageTitle = 'Security Visitors';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors/visitors.php'), 'active' => true],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents/incidents.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php')],
    ['icon' => 'bi-person-plus', 'label' => 'Register Visitor', 'href' => url('views/security/register-visitor/register-visitor.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-shield-lock-fill text-dark me-2"></i>Campus Gate &amp; Visitor Log
                </h4>
                <p class="text-muted mb-0">Monitor perimeter access, gate entries, check-outs, and guest verification</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/security/visitors/bulk-import/bulk-import.php') ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-arrow-up me-1"></i> Upload CSV/Excel
                </a>
                <a href="<?= url('views/security/visitor-history/visitor-history.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-clock-history me-1"></i> Full History
                </a>
                <a href="<?= url('views/security/register-visitor/register-visitor.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus me-1"></i> Register Visitor
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Logged</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalVisitors) ?></h3>
                            <span class="small text-muted">Gate entries</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-person-badge fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Currently on Campus</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $insideVisitors) ?></h3>
                            <span class="small text-muted">Active guest passes</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-door-open fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-secondary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Departed / Checked Out</span>
                            <h3 class="fw-bold my-1 text-secondary"><?= e((string) $checkedOutVisitors) ?></h3>
                            <span class="small text-muted">Completed visits</span>
                        </div>
                        <div class="rounded-3 bg-secondary bg-opacity-10 p-2 text-secondary"><i class="bi bi-box-arrow-right fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Search Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-7">
                        <input name="search" class="form-control form-control-sm" placeholder="Search by visitor name or student..." value="<?= e($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="inside" <?= $statusFilter === 'inside' ? 'selected' : '' ?>>Currently Inside Campus</option>
                            <option value="checked_out" <?= $statusFilter === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
                            <option value="registered" <?= $statusFilter === 'registered' ? 'selected' : '' ?>>Registered (Pending)</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/security/visitors/visitors/visitors.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Visitors Table Card -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-shield-check me-2 text-dark"></i>Gate Access Records</h6>
                <small class="text-muted">Showing <?= count($visitors) ?> records</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 data-table w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Visitor Name</th>
                                <th>Resident Student</th>
                                <th>Visit Purpose</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($visitors)): ?>
                                <?php foreach ($visitors as $visitor): ?>
                                    <?php
                                    $stId = (string)($visitor['studentId'] ?? '');
                                    $studentDisplay = $students[$stId] ?? ($stId !== '' ? $stId : '—');
                                    $vStatus = strtolower((string)($visitor['status'] ?? 'registered'));
                                    $vBadge = match($vStatus) {
                                        'inside' => 'bg-success',
                                        'checked_out' => 'bg-secondary',
                                        default => 'bg-warning text-dark',
                                    };
                                    $vId = (string)($visitor['id'] ?? '');
                                    ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark d-block"><?= e($visitor['visitorName'] ?? 'Visitor') ?></strong>
                                            <small class="text-muted"><?= e($visitor['phone'] ?? '') ?></small>
                                        </td>
                                        <td><?= e($studentDisplay) ?></td>
                                        <td><small class="text-muted"><?= e($visitor['purpose'] ?? '—') ?></small></td>
                                        <td><span class="badge <?= $vBadge ?>"><?= ucfirst(str_replace('_', ' ', e($vStatus))) ?></span></td>
                                        <td class="text-end text-nowrap">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/security/visitors/view/view.php?id=' . urlencode($vId)) ?>" title="View"><i class="bi bi-eye"></i></a> 
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/security/visitors/edit/edit.php?id=' . urlencode($vId)) ?>" title="Edit"><i class="bi bi-pencil"></i></a> 
                                            <a class="btn btn-sm btn-outline-danger" href="<?= url('views/security/visitors/delete/delete.php?id=' . urlencode($vId)) ?>" title="Delete"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No gate visitor records found.</td>
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
