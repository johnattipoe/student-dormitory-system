<?php
require __DIR__ . '/_context.php';

use App\Services\BedService;

$search = strtolower(trim(sanitize($_GET['search'] ?? '')));
$statusFilter = sanitize($_GET['status'] ?? '');
$visibleBeds = array_values(array_filter($beds, function ($bed) use ($search, $statusFilter, $roomMap) {
    $room = $roomMap[(string) ($bed['roomId'] ?? '')] ?? [];
    return ($search === '' || str_contains(strtolower(($bed['bedNumber'] ?? '') . ' ' . ($room['roomNumber'] ?? ''))))
        && ($statusFilter === '' || ($bed['status'] ?? 'available') === $statusFilter);
}));
$pageTitle = 'House Beds';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index.php')],
    ['icon' => 'bi-grid-3x3-gap', 'label' => 'Beds', 'href' => url('views/house-master/beds/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><div><h5 class="mb-1">Beds</h5><p class="text-muted mb-0"><?= e($house['name'] ?? 'Your assigned house') ?> bed assignments.</p></div><div><a class="btn btn-outline-success btn-sm" href="<?= url('views/house-master/beds/export.php') ?>"><i class="bi bi-download"></i> Export</a> <a class="btn btn-primary btn-sm" href="<?= url('views/house-master/beds/create.php') ?>"><i class="bi bi-plus-lg"></i> Add bed</a></div></div>
        <div class="row g-3 mb-3"><div class="col-md-4"><div class="card stat-card p-3"><small class="text-muted">Beds</small><strong class="fs-3"><?= e((string) count($visibleBeds)) ?></strong></div></div><div class="col-md-4"><div class="card stat-card p-3"><small class="text-muted">Occupied</small><strong class="fs-3 text-warning"><?= e((string) count(array_filter($visibleBeds, static fn ($bed) => ($bed['status'] ?? '') === 'occupied'))) ?></strong></div></div><div class="col-md-4"><div class="card stat-card p-3"><small class="text-muted">Available</small><strong class="fs-3 text-success"><?= e((string) count(array_filter($visibleBeds, static fn ($bed) => ($bed['status'] ?? 'available') === 'available'))) ?></strong></div></div></div>
        <div class="card stat-card p-3 mb-3"><form method="GET" class="row g-2"><div class="col-md-8"><input name="search" class="form-control form-control-sm" placeholder="Search bed or room" value="<?= e($search) ?>"></div><div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option><?php foreach (['available','occupied','maintenance'] as $status): ?><option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-primary btn-sm">Filter</button> <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/beds/index.php') ?>">Reset</a></div></form></div>
        <div class="card stat-card p-3"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Bed</th><th>Capacity</th><th>Room</th><th>Student</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php if (!$visibleBeds): ?><tr><td colspan="6" class="text-center text-muted py-4">No beds found for your assigned house.</td></tr><?php else: foreach ($visibleBeds as $bed): $bedId = (string) ($bed['id'] ?? ''); $room = $roomMap[(string) ($bed['roomId'] ?? '')] ?? []; $student = $studentMap[(string) ($bed['studentId'] ?? '')] ?? []; $status = $bed['status'] ?? 'available'; ?><tr><td><strong><?= e($bed['bedNumber'] ?? '-') ?></strong></td><td><?= e((string)($bed['capacity'] ?? 1)) ?></td><td><?= e($room['roomNumber'] ?? '-') ?></td><td><?= $student ? e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) . '<small class="d-block text-muted">' . e($student['admissionNo'] ?? '') . '</small>' : '<span class="text-muted">Unassigned</span>' ?></td><td><span class="badge bg-<?= $status === 'occupied' ? 'warning' : ($status === 'maintenance' ? 'secondary' : 'success') ?>"><?= e($status) ?></span></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/beds/view.php?id=' . urlencode($bedId)) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/house-master/beds/edit.php?id=' . urlencode($bedId)) ?>">Edit</a><?php if (!$student && $status !== 'maintenance'): ?> <a class="btn btn-sm btn-outline-success" href="<?= url('views/house-master/beds/assign.php?id=' . urlencode($bedId)) ?>">Assign</a><?php elseif ($student): ?> <form method="POST" action="<?= url('views/house-master/beds/unassign.php') ?>" class="d-inline"><input type="hidden" name="id" value="<?= e($bedId) ?>"><button class="btn btn-sm btn-outline-warning">Unassign</button></form><?php endif; ?> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/house-master/beds/delete.php?id=' . urlencode($bedId)) ?>">Delete</a></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
