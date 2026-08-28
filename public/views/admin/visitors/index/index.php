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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\VisitorService;

$visitorService = new VisitorService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'register';

    if ($action === 'check_in') {
        $result = $visitorService->checkIn(sanitize($_POST['id'] ?? ''), current_user()['uid'] ?? 'admin');
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('index.php?route=/views/admin/visitors/index/index.php'));
    }

    if ($action === 'check_out') {
        $result = $visitorService->checkOut(sanitize($_POST['id'] ?? ''), current_user()['uid'] ?? 'admin');
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('index.php?route=/views/admin/visitors/index/index.php'));
    }

    $data = [
        'visitorName' => sanitize($_POST['visitorName'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'studentId' => sanitize($_POST['studentId'] ?? ''),
        'purpose' => sanitize($_POST['purpose'] ?? ''),
        'idType' => sanitize($_POST['idType'] ?? ''),
        'idNumber' => sanitize($_POST['idNumber'] ?? ''),
        'registeredBy' => current_user()['uid'] ?? 'admin',
    ];

    $result = $visitorService->register($data);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/admin/visitors/index/index.php'));
}

$pageTitle = 'Visitors';
$allVisitors = $visitorService->all();
$totalVisitors = count($allVisitors);
$insideVisitors = count(array_filter($allVisitors, fn($v) => ($v['status'] ?? '') === 'inside'));
$todayVisitors = count(array_filter($allVisitors, fn($v) => str_starts_with((string)($v['checkInTime'] ?? $v['visitDate'] ?? $v['createdAt'] ?? ''), date('Y-m-d'))));

$search = strtolower(sanitize($_GET['search'] ?? ''));
$statusFilter = sanitize($_GET['status'] ?? '');
$visitors = array_values(array_filter($allVisitors, function ($visitor) use ($search, $statusFilter) {
    return ($search === '' || str_contains(strtolower((string) ($visitor['visitorName'] ?? '')), $search) || str_contains(strtolower((string) ($visitor['studentId'] ?? '')), $search))
        && ($statusFilter === '' || ($visitor['status'] ?? '') === $statusFilter);
}));
$students = StudentService::all();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-person-badge', 'label' => 'Visitors', 'href' => url('views/admin/visitors/index/index.php'), 'active' => true],
    ['icon' => 'bi-bar-chart', 'label' => 'Reports', 'href' => url('views/admin/visitors/reports/reports.php')],
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
                    <i class="bi bi-person-badge-fill text-info me-2"></i>Campus Visitor Management
                </h4>
                <p class="text-muted mb-0">Track campus guest registrations, monitor check-in movements, and gate passes</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('views/admin/visitors/reports/reports.php') ?>" class="btn btn-outline-info btn-sm">
                    <i class="bi bi-bar-chart me-1"></i> Visitor Reports
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
                            <span class="small text-muted">All-time registrations</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Currently on Campus</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $insideVisitors) ?></h3>
                            <span class="small text-muted">Active visitors</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-door-open fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Today's Visits</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $todayVisitors) ?></h3>
                            <span class="small text-muted"><?= e(date('M d, Y')) ?></span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-calendar-check fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Register Visitor Form -->
        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2 text-primary"></i>Register New Visitor</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/admin/visitors/index/index.php') ?>" class="row g-3">
                    <input type="hidden" name="action" value="register">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Visitor Name <span class="text-danger">*</span></label>
                        <input name="visitorName" class="form-control" placeholder="Full name" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input name="phone" class="form-control" placeholder="e.g. +233 24 000 0000">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Student to Visit <span class="text-danger">*</span></label>
                        <select name="studentId" class="form-select select2" required>
                            <option value="">-- Choose student --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= e((string) ($student['id'] ?? '')) ?>"><?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' (' . ($student['admissionNo'] ?? '') . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Purpose of Visit</label>
                        <input name="purpose" class="form-control" placeholder="e.g. Guardian visit, supplies delivery">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">ID Document Type</label>
                        <input name="idType" class="form-control" placeholder="e.g. Ghana Card, Driver License">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">ID Document Number</label>
                        <input name="idNumber" class="form-control" placeholder="e.g. GHA-123456789-0">
                    </div>
                    <div class="col-12 mt-3">
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Register Visitor</button>
                    </div>
                </form>
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
                            <option value="inside" <?= $statusFilter === 'inside' ? 'selected' : '' ?>>Currently Inside</option>
                            <option value="checked_out" <?= $statusFilter === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
                            <option value="registered" <?= $statusFilter === 'registered' ? 'selected' : '' ?>>Registered (Pending Entry)</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/visitors/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Visitors Table Card -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-info"></i>Visitor Log Registry</h6>
                <small class="text-muted">Showing <?= count($visitors) ?> records</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Visitor Name</th>
                            <th>Student Visited</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($visitors)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-person-x fs-3 d-block text-secondary mb-1"></i>
                                    No visitor records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($visitors as $visitor): ?>
                                <?php
                                $studentName = '-';
                                foreach ($students as $student) {
                                    if (($student['id'] ?? '') === ($visitor['studentId'] ?? '')) {
                                        $studentName = (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($visitor['studentId'] ?? '-');
                                        break;
                                    }
                                }
                                $vStatus = strtolower((string) ($visitor['status'] ?? 'registered'));
                                $vBadge = match($vStatus) {
                                    'inside' => 'bg-success',
                                    'checked_out' => 'bg-secondary',
                                    default => 'bg-warning text-dark',
                                };
                                $vId = (string) ($visitor['id'] ?? '');
                                ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark d-block"><?= e($visitor['visitorName'] ?? 'Visitor') ?></strong>
                                        <small class="text-muted"><?= e($visitor['phone'] ?? '') ?></small>
                                    </td>
                                    <td><?= e($studentName) ?></td>
                                    <td><small class="text-muted"><?= e($visitor['purpose'] ?? '—') ?></small></td>
                                    <td><span class="badge <?= $vBadge ?>"><?= ucfirst(str_replace('_', ' ', e($vStatus))) ?></span></td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/admin/visitors/view/view.php?id=' . urlencode($vId)) ?>" title="View"><i class="bi bi-eye"></i></a>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/admin/visitors/edit/edit.php?id=' . urlencode($vId)) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a class="btn btn-sm btn-outline-danger" href="<?= url('views/admin/visitors/delete/delete.php?id=' . urlencode($vId)) ?>" title="Delete"><i class="bi bi-trash"></i></a>
                                        <?php if ($vStatus === 'inside'): ?>
                                            <form method="POST" action="<?= url('views/admin/visitors/index/index.php') ?>" class="d-inline">
                                                <input type="hidden" name="action" value="check_out">
                                                <input type="hidden" name="id" value="<?= e($vId) ?>">
                                                <button class="btn btn-sm btn-outline-secondary ms-1">Check out</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?= url('views/admin/visitors/index/index.php') ?>" class="d-inline">
                                                <input type="hidden" name="action" value="check_in">
                                                <input type="hidden" name="id" value="<?= e($vId) ?>">
                                                <button class="btn btn-sm btn-success ms-1">Check in</button>
                                            </form>
                                        <?php endif; ?>
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
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>