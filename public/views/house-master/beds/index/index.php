<?php
require __DIR__ . '/../_context/_context.php';

use App\Services\BedService;

$search = strtolower(trim(sanitize($_GET['search'] ?? '')));
$statusFilter = sanitize($_GET['status'] ?? '');
$visibleBeds = array_values(array_filter($beds, function ($bed) use ($search, $statusFilter, $roomMap) {
    $room = $roomMap[(string) ($bed['roomId'] ?? '')] ?? [];
    return ($search === '' || str_contains(strtolower(($bed['bedNumber'] ?? '') . ' ' . ($room['roomNumber'] ?? '')), $search))
        && ($statusFilter === '' || ($bed['status'] ?? 'available') === $statusFilter);
}));

$occupiedCount = count(array_filter($visibleBeds, fn($b) => ($b['status'] ?? '') === 'occupied'));
$availableCount = count(array_filter($visibleBeds, fn($b) => ($b['status'] ?? 'available') === 'available'));
$maintenanceCount = count(array_filter($visibleBeds, fn($b) => ($b['status'] ?? '') === 'maintenance'));

$pageTitle = 'House Beds';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php')],
    ['icon' => 'bi-grid-3x3-gap', 'label' => 'Beds', 'href' => url('views/house-master/beds/index/index.php'), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>Bed Allocation Manager</h4>
                <p class="text-muted mb-0"><?= e($house['name'] ?? 'Your house') ?> — bed assignments and availability</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-success btn-sm" href="<?= url('views/house-master/beds/export/export.php') ?>">
                    <i class="bi bi-download me-1"></i>Export
                </a>
                <a class="btn btn-primary btn-sm" href="<?= url('views/house-master/beds/create/create.php') ?>">
                    <i class="bi bi-plus-lg me-1"></i>Add Bed
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Beds</span>
                            <h3 class="fw-bold my-1 text-primary"><?= count($visibleBeds) ?></h3>
                            <span class="small text-muted">In filter</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-grid-3x3-gap fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Occupied</span>
                            <h3 class="fw-bold my-1 text-warning"><?= $occupiedCount ?></h3>
                            <span class="small text-muted">Assigned beds</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Available</span>
                            <h3 class="fw-bold my-1 text-success"><?= $availableCount ?></h3>
                            <span class="small text-muted">Open beds</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-circle-half fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-secondary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Maintenance</span>
                            <h3 class="fw-bold my-1 text-secondary"><?= $maintenanceCount ?></h3>
                            <span class="small text-muted">Not available</span>
                        </div>
                        <div class="rounded-3 bg-secondary bg-opacity-10 p-2 text-secondary"><i class="bi bi-tools fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Search Beds</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input name="search" class="form-control" placeholder="Search by bed number or room" value="<?= e($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            <?php foreach (['available', 'occupied', 'maintenance'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/beds/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Beds Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap me-2"></i>Bed Listings</h6>
                <small class="text-muted"><?= count($visibleBeds) ?> bed(s)</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 data-table w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Bed No.</th>
                                <th>Capacity</th>
                                <th>Room</th>
                                <th>Assigned Student</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$visibleBeds): ?>
                                <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No beds found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($visibleBeds as $bed): ?>
                                    <?php
                                        $bedId = (string) ($bed['id'] ?? '');
                                        $room = $roomMap[(string) ($bed['roomId'] ?? '')] ?? [];
                                        $student = $studentMap[(string) ($bed['studentId'] ?? '')] ?? [];
                                        $status = $bed['status'] ?? 'available';
                                        $statusBadge = match($status) {
                                            'occupied' => 'bg-warning text-dark',
                                            'maintenance' => 'bg-secondary text-white',
                                            default => 'bg-success text-white'
                                        };
                                    ?>
                                    <tr>
                                        <td><strong class="text-dark"><?= e($bed['bedNumber'] ?? '—') ?></strong></td>
                                        <td class="text-muted"><?= e((string) ($bed['capacity'] ?? 1)) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= e($room['roomNumber'] ?? '—') ?></span></td>
                                        <td>
                                            <?php if ($student): ?>
                                                <strong class="d-block text-dark"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></strong>
                                                <small class="text-muted font-monospace"><?= e($student['admissionNo'] ?? '') ?></small>
                                            <?php else: ?>
                                                <span class="text-muted small">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($status) ?></span></td>
                                        <td class="text-end text-nowrap">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/beds/view/view.php?id=' . urlencode($bedId)) ?>"><i class="bi bi-eye me-1"></i>View</a>
                                            <a class="btn btn-sm btn-outline-secondary ms-1" href="<?= url('views/house-master/beds/edit/edit.php?id=' . urlencode($bedId)) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
                                            <?php if (!$student && $status !== 'maintenance'): ?>
                                                <a class="btn btn-sm btn-outline-success ms-1" href="<?= url('views/house-master/beds/assign/assign.php?id=' . urlencode($bedId)) ?>"><i class="bi bi-person-plus me-1"></i>Assign</a>
                                            <?php elseif ($student): ?>
                                                <form method="POST" action="<?= url('views/house-master/beds/unassign/unassign.php') ?>" class="d-inline ms-1">
                                                    <input type="hidden" name="id" value="<?= e($bedId) ?>">
                                                    <button class="btn btn-sm btn-outline-warning"><i class="bi bi-person-dash me-1"></i>Unassign</button>
                                                </form>
                                            <?php endif; ?>
                                            <a class="btn btn-sm btn-outline-danger ms-1" href="<?= url('views/house-master/beds/delete/delete.php?id=' . urlencode($bedId)) ?>"><i class="bi bi-trash"></i></a>
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
