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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\VisitorService;

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$visitorService = new VisitorService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = sanitize($_POST['studentId'] ?? '');
    $allowedStudentIds = array_fill_keys(array_filter(array_map(
        static fn(array $student): string => (string) ($student['id'] ?? ''),
        $students
    )), true);

    if ((string) $houseId === '' || !isset($allowedStudentIds[$studentId])) {
        flash('error', 'Select a student assigned to your house.');
    } else {
        $result = $visitorService->register([
            'visitorName' => sanitize($_POST['visitorName'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'studentId' => $studentId,
            'purpose' => sanitize($_POST['purpose'] ?? ''),
            'registeredBy' => current_user()['uid'] ?? current_user()['id'] ?? null,
        ]);
        flash($result['success'] ? 'success' : 'error', $result['message']);
    }

    redirect(url('views/house-master/visitors/index/index.php'));
}

$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}
$visitors = $visitorService->byHouse($houseId);
$visitorSearch = strtolower(sanitize($_GET['search'] ?? ''));
$visitorStatus = sanitize($_GET['status'] ?? '');
if ($visitorSearch !== '' || $visitorStatus !== '') {
    $visitors = array_values(array_filter($visitors, function ($visitor) use ($visitorSearch, $visitorStatus, $studentMap) {
        $student = $studentMap[(string) ($visitor['studentId'] ?? '')] ?? [];
        $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
        return ($visitorSearch === '' || str_contains(strtolower((string) ($visitor['visitorName'] ?? '')), $visitorSearch) || str_contains(strtolower($studentName), $visitorSearch))
            && ($visitorStatus === '' || ($visitor['status'] ?? '') === $visitorStatus);
    }));
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$visitorTotal = count($visitors);
$totalPages = max(1, (int) ceil($visitorTotal / $limit));
$page = min($page, $totalPages);
$visitors = array_slice($visitors, ($page - 1) * $limit, $limit);
$visitorInside = count(array_filter($visitors, fn($visitor) => ($visitor['status'] ?? '') === 'inside'));

$pageTitle = 'House Visitors';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index/index.php'), 'active' => true],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-person-walking text-info me-2"></i>Visitors</h4>
                <p class="text-muted mb-0">Review visitor activity for students in your house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-success btn-sm" id="houseVisitorExportButton"
                    data-export-url="<?= e(url('views/house-master/reports/export/export.php') . '&type=visitors') ?>"
                    data-filename="house-master-visitors-<?= e(date('Y-m-d')) ?>.csv">
                    <i class="bi bi-filetype-csv me-1"></i>Export CSV
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#registerHouseVisitorModal">
                    <i class="bi bi-person-plus me-1"></i>Register Visitor
                </button>
            </div>
        </div>
        <div id="houseVisitorExportError" class="alert alert-danger d-none" role="alert"></div>

        <div class="modal fade" id="registerHouseVisitorModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form method="POST" action="<?= url('views/house-master/visitors/index/index.php') ?>" data-disable-loading="true">
                        <input type="hidden" name="action" value="register">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-person-plus me-2 text-primary"></i>Register House Visitor</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Visitor Full Name <span class="text-danger">*</span></label>
                                    <input name="visitorName" class="form-control" placeholder="e.g. Mr. Kofi Acheampong" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Phone / Contact Number</label>
                                    <input name="phone" class="form-control" placeholder="+233 XX XXX XXXX">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Student Being Visited <span class="text-danger">*</span></label>
                                    <select name="studentId" class="form-select" required <?= empty($students) ? 'disabled' : '' ?>>
                                        <option value="">Select resident student</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?= e((string) ($student['id'] ?? '')) ?>">
                                                <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (<?= e($student['admissionNo'] ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($students)): ?>
                                        <div class="form-text text-danger">No students are assigned to your house.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Purpose of Visit</label>
                                    <textarea name="purpose" class="form-control" rows="3" placeholder="Reason for visiting the student..."></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" <?= empty($students) ? 'disabled' : '' ?>>
                                <i class="bi bi-person-check me-1"></i>Register Visitor
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Visitors</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) $visitorTotal) ?></h3>
                            <span class="small text-muted">All records</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Currently Inside</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $visitorInside) ?></h3>
                            <span class="small text-muted">On premises now</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-box-arrow-in-right fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-secondary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Other Statuses</span>
                            <h3 class="fw-bold my-1 text-secondary"><?= e((string) max(0, $visitorTotal - $visitorInside)) ?></h3>
                            <span class="small text-muted">Registered / Checked out</span>
                        </div>
                        <div class="rounded-3 bg-secondary bg-opacity-10 p-2 text-secondary"><i class="bi bi-clock-history fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input name="search" class="form-control form-control-sm" placeholder="Search visitor or student name..." value="<?= e($visitorSearch) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            <option value="registered" <?= $visitorStatus === 'registered' ? 'selected' : '' ?>>Registered</option>
                            <option value="inside" <?= $visitorStatus === 'inside' ? 'selected' : '' ?>>Inside</option>
                            <option value="checked_out" <?= $visitorStatus === 'checked_out' ? 'selected' : '' ?>>Checked out</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/visitors/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Visitors Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-walking me-2"></i>Visitor Registry</h6>
                <small class="text-muted">Showing <?= e((string) count($visitors)) ?> of <?= e((string) $visitorTotal) ?> records</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Student</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($visitors)): ?>
                            <?php foreach ($visitors as $visitor): ?>
                                <?php $visitorStudent = $studentMap[(string) ($visitor['studentId'] ?? '')] ?? null; ?>
                                <tr>
                                    <td class="fw-medium"><?= e($visitor['visitorName'] ?? '') ?></td>
                                    <td><?= e(trim((($visitorStudent['firstName'] ?? '') . ' ' . ($visitorStudent['lastName'] ?? '')))) ?: e($visitor['studentId'] ?? '—') ?></td>
                                    <td><?= e($visitor['purpose'] ?? '—') ?></td>
                                    <td><span class="badge bg-<?= ($visitor['status'] ?? '') === 'inside' ? 'success' : (($visitor['status'] ?? '') === 'checked_out' ? 'dark' : 'secondary') ?>"><?= e($visitor['status'] ?? 'pending') ?></span></td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#houseVisitorViewModal-<?= e((string) ($visitor['id'] ?? '')) ?>"><i class="bi bi-eye me-1"></i>View</button>
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#houseVisitorEditModal-<?= e((string) ($visitor['id'] ?? '')) ?>"><i class="bi bi-pencil me-1"></i>Edit</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#houseVisitorDeleteModal-<?= e((string) ($visitor['id'] ?? '')) ?>"><i class="bi bi-trash me-1"></i>Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No visitors found for your house.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
    </div>
</div>
<?php foreach ($visitors as $visitor): ?>
    <?php $modalVisitorId = (string) ($visitor['id'] ?? ''); $modalVisitorStatus = (string) ($visitor['status'] ?? 'registered'); $modalStudent = $studentMap[(string) ($visitor['studentId'] ?? '')] ?? []; $modalStudentName = trim(($modalStudent['firstName'] ?? '') . ' ' . ($modalStudent['lastName'] ?? '')) ?: ($visitor['studentId'] ?? '—'); ?>
    <div class="modal fade" id="houseVisitorViewModal-<?= e($modalVisitorId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Visitor Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-5">Name</dt><dd class="col-sm-7"><?= e($visitor['visitorName'] ?? 'Visitor') ?></dd><dt class="col-sm-5">Student</dt><dd class="col-sm-7"><?= e($modalStudentName) ?></dd><dt class="col-sm-5">Phone</dt><dd class="col-sm-7"><?= e($visitor['phone'] ?? '—') ?></dd><dt class="col-sm-5">Purpose</dt><dd class="col-sm-7"><?= e($visitor['purpose'] ?? '—') ?></dd><dt class="col-sm-5">Status</dt><dd class="col-sm-7"><?= e(ucwords(str_replace('_', ' ', $modalVisitorStatus))) ?></dd></dl></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
    <div class="modal fade" id="houseVisitorEditModal-<?= e($modalVisitorId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="<?= url('views/house-master/visitors/edit/edit.php?id=' . urlencode($modalVisitorId)) ?>"><div class="modal-header"><h5 class="modal-title">Edit Visitor</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><input type="hidden" name="id" value="<?= e($modalVisitorId) ?>"><label class="form-label">Visitor Name</label><input name="visitorName" class="form-control mb-3" value="<?= e($visitor['visitorName'] ?? '') ?>" required><label class="form-label">Phone</label><input name="phone" class="form-control mb-3" value="<?= e($visitor['phone'] ?? '') ?>"><label class="form-label">Status</label><select name="status" class="form-select mb-3"><option value="registered" <?= $modalVisitorStatus === 'registered' ? 'selected' : '' ?>>Registered</option><option value="inside" <?= $modalVisitorStatus === 'inside' ? 'selected' : '' ?>>Inside</option><option value="checked_out" <?= $modalVisitorStatus === 'checked_out' ? 'selected' : '' ?>>Checked Out</option></select><label class="form-label">Purpose</label><textarea name="purpose" class="form-control" rows="3"><?= e($visitor['purpose'] ?? '') ?></textarea></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div></form></div></div></div>
    <div class="modal fade" id="houseVisitorDeleteModal-<?= e($modalVisitorId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header border-0"><h5 class="modal-title text-danger">Delete Visitor</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="mb-0">Delete visitor <strong><?= e($visitor['visitorName'] ?? 'this visitor') ?></strong>?</p></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/house-master/visitors/delete/delete.php?id=' . urlencode($modalVisitorId)) ?>"><input type="hidden" name="id" value="<?= e($modalVisitorId) ?>"><button type="submit" class="btn btn-danger">Delete</button></form></div></div></div></div>
<?php endforeach; ?>
<script>
document.querySelectorAll('.modal').forEach(function (modal) {
    document.body.appendChild(modal);
});

document.getElementById('houseVisitorExportButton').addEventListener('click', async function () {
    const button = this;
    const errorMessage = document.getElementById('houseVisitorExportError');
    SDS.showButtonLoading(button);
    errorMessage.classList.add('d-none');
    errorMessage.textContent = '';
    try {
        const response = await fetch(button.dataset.exportUrl, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok || !(response.headers.get('content-type') || '').includes('text/csv')) {
            throw new Error('The visitor export could not be downloaded. Please try again.');
        }
        const downloadUrl = URL.createObjectURL(await response.blob());
        const downloadLink = document.createElement('a');
        downloadLink.href = downloadUrl;
        downloadLink.download = button.dataset.filename;
        document.body.appendChild(downloadLink);
        downloadLink.click();
        downloadLink.remove();
        window.setTimeout(function () { URL.revokeObjectURL(downloadUrl); }, 1000);
    } catch (error) {
        errorMessage.textContent = error instanceof Error ? error.message : 'The visitor export could not be downloaded. Please try again.';
        errorMessage.classList.remove('d-none');
    } finally {
        SDS.hideButtonLoading(button);
    }
});
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
