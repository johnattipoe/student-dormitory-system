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
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$visitors = array_values(array_filter($allVisitors, function ($visitor) use ($search, $statusFilter) {
    return ($search === '' || str_contains(strtolower((string) ($visitor['visitorName'] ?? '')), $search) || str_contains(strtolower((string) ($visitor['studentId'] ?? '')), $search))
        && ($statusFilter === '' || ($visitor['status'] ?? '') === $statusFilter);
}));
$totalVisitorsFiltered = count($visitors);
$totalPages = max(1, (int) ceil($totalVisitorsFiltered / $limit));
$page = min($page, $totalPages);
$visitors = array_slice($visitors, ($page - 1) * $limit, $limit);

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
                <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#securityVisitorImportModal">
                    <i class="bi bi-file-earmark-arrow-up me-1"></i> Upload CSV/Excel
                </button>
                <a href="<?= url('views/security/visitor-history/visitor-history.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-clock-history me-1"></i> Full History
                </a>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#securityVisitorRegisterModal">
                    <i class="bi bi-person-plus me-1"></i> Register Visitor
                </button>
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
                <small class="text-muted">Showing <?= e((string) $totalVisitorsFiltered) ?> records</small>
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
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#securityVisitorView-<?= e($vId) ?>" title="View"><i class="bi bi-eye"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#securityVisitorEdit-<?= e($vId) ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                                            <?php if (in_array($vStatus, ['registered', 'approved'], true)): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#securityVisitorCheckIn-<?= e($vId) ?>" title="Check in"><i class="bi bi-box-arrow-in-right"></i></button>
                                            <?php elseif ($vStatus === 'inside'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#securityVisitorCheckOut-<?= e($vId) ?>" title="Check out"><i class="bi bi-box-arrow-right"></i></button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#securityVisitorDelete-<?= e($vId) ?>" title="Delete"><i class="bi bi-trash"></i></button>
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
                <?php $paginationQuery = []; if ($search !== '') { $paginationQuery[] = 'search=' . urlencode($search); } if ($statusFilter !== '') { $paginationQuery[] = 'status=' . urlencode($statusFilter); } $paginationBaseUrl = url('views/security/visitors/visitors/visitors.php' . (!empty($paginationQuery) ? '?' . implode('&', $paginationQuery) : '')); require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
            </div>
        </div>

    </div>
</div>
<div class="modal fade" id="securityVisitorRegisterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/security/register-visitor/register-visitor.php') ?>" class="security-modal-form">
                <div class="modal-header"><h5 class="modal-title">Register New Visitor</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Visitor Full Name</label><input type="text" name="visitorName" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Host Student</label><select name="studentId" class="form-select" required><option value="">Select student being visited</option><?php foreach ($students as $studentId => $studentName): ?><option value="<?= e($studentId) ?>"><?= e($studentName !== '' ? $studentName : $studentId) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Phone Number</label><input type="text" name="phone" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Identification Type</label><input type="text" name="idType" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">ID / Document Number</label><input type="text" name="idNumber" class="form-control" required></div>
                        <div class="col-12"><label class="form-label">Purpose of Visit</label><textarea name="purpose" class="form-control" rows="3" required></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Register Visitor</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="securityVisitorImportModal" tabindex="-1" aria-hidden="true" data-open-on-load="<?= !empty($_GET['openVisitorImport']) ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" action="<?= url('views/security/visitors/bulk-import/bulk-import.php') ?>" class="security-modal-form">
                <input type="hidden" name="action" value="import_visitors">
                <input type="hidden" name="returnToVisitors" value="1">
                <div class="modal-header"><h5 class="modal-title">Import Visitors</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <p class="text-muted">Accepted columns: VisitorName, StudentId, Phone, Purpose, IdType, IdNumber.</p>
                    <label class="form-label">CSV or XLSX file</label>
                    <input type="file" name="visitors_file" class="form-control" accept=".csv,.xlsx" required>
                    <a class="btn btn-outline-success btn-sm mt-3" href="<?= url('views/security/visitors/bulk-import/bulk-import.php?download_template=1') ?>"><i class="bi bi-download me-1"></i>Download template</a>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Upload and Import</button></div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($visitors as $visitor): ?>
    <?php $modalVisitorId = (string) ($visitor['id'] ?? ''); $modalVisitorStatus = (string) ($visitor['status'] ?? 'registered'); ?>
    <div class="modal fade" id="securityVisitorView-<?= e($modalVisitorId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Visitor Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-5">Name</dt><dd class="col-sm-7"><?= e($visitor['visitorName'] ?? 'Visitor') ?></dd><dt class="col-sm-5">Phone</dt><dd class="col-sm-7"><?= e($visitor['phone'] ?? '—') ?></dd><dt class="col-sm-5">Purpose</dt><dd class="col-sm-7"><?= e($visitor['purpose'] ?? '—') ?></dd><dt class="col-sm-5">Status</dt><dd class="col-sm-7"><?= e(ucwords(str_replace('_', ' ', $modalVisitorStatus))) ?></dd><dt class="col-sm-5">Student / Host</dt><dd class="col-sm-7"><?= e($visitor['studentId'] ?? '—') ?></dd><dt class="col-sm-5">Check-in</dt><dd class="col-sm-7"><?= e($visitor['checkInTime'] ?? '—') ?></dd><dt class="col-sm-5">Check-out</dt><dd class="col-sm-7"><?= e($visitor['checkOutTime'] ?? '—') ?></dd></dl></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
    <div class="modal fade" id="securityVisitorEdit-<?= e($modalVisitorId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form method="POST" action="<?= url('views/security/visitors/edit/edit.php?id=' . urlencode($modalVisitorId)) ?>" class="security-modal-form"><input type="hidden" name="returnToVisitors" value="1"><div class="modal-header"><h5 class="modal-title">Edit Visitor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="id" value="<?= e($modalVisitorId) ?>"><label class="form-label">Visitor Name</label><input name="visitorName" class="form-control mb-3" value="<?= e($visitor['visitorName'] ?? '') ?>" required><label class="form-label">Phone</label><input name="phone" class="form-control mb-3" value="<?= e($visitor['phone'] ?? '') ?>"><label class="form-label">Purpose</label><textarea name="purpose" class="form-control" rows="3"><?= e($visitor['purpose'] ?? '') ?></textarea></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div></form></div></div></div>
    <div class="modal fade" id="securityVisitorDelete-<?= e($modalVisitorId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header border-0"><h5 class="modal-title text-danger">Delete Visitor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">Delete visitor <strong><?= e($visitor['visitorName'] ?? 'this visitor') ?></strong>?</div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/security/visitors/delete/delete.php?id=' . urlencode($modalVisitorId)) ?>"><input type="hidden" name="id" value="<?= e($modalVisitorId) ?>"><button type="submit" class="btn btn-danger">Delete</button></form></div></div></div></div>
    <?php if (in_array($modalVisitorStatus, ['registered', 'approved'], true)): ?>
        <div class="modal fade" id="securityVisitorCheckIn-<?= e($modalVisitorId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="<?= url('views/security/visitor-check-in/visitor-check-in.php') ?>"><input type="hidden" name="returnToVisitors" value="1"><input type="hidden" name="visitorId" value="<?= e($modalVisitorId) ?>"><div class="modal-header"><h5 class="modal-title">Confirm Visitor Check-in</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">Check in <strong><?= e($visitor['visitorName'] ?? 'this visitor') ?></strong>?</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Confirm Check-in</button></div></form></div></div></div>
    <?php elseif ($modalVisitorStatus === 'inside'): ?>
        <div class="modal fade" id="securityVisitorCheckOut-<?= e($modalVisitorId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="<?= url('views/security/visitor-check-out/visitor-check-out.php') ?>"><input type="hidden" name="returnToVisitors" value="1"><input type="hidden" name="visitorId" value="<?= e($modalVisitorId) ?>"><div class="modal-header"><h5 class="modal-title">Confirm Visitor Check-out</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">Check out <strong><?= e($visitor['visitorName'] ?? 'this visitor') ?></strong>?</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning">Confirm Check-out</button></div></form></div></div></div>
    <?php endif; ?>
<?php endforeach; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal[data-open-on-load="true"]').forEach(function (modalElement) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });
});
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
