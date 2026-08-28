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
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;

$pageTitle = 'Houses';
$houses = HouseService::all();
$totalHouses = count($houses);
$activeHouses = count(array_filter($houses, fn($h) => ($h['status'] ?? 'active') === 'active'));
$totalCapacity = array_sum(array_map(fn($h) => (int) ($h['capacity'] ?? 0), $houses));

$search = strtolower(sanitize($_GET['search'] ?? ''));
if ($search !== '') {
    $houses = array_values(array_filter($houses, fn($house) => str_contains(strtolower((string) ($house['name'] ?? '')), $search) || str_contains(strtolower((string) ($house['location'] ?? '')), $search)));
}
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-building', 'label' => 'Houses', 'href' => url('views/admin/houses/index/index.php'), 'active' => true],
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
                    <i class="bi bi-building-fill text-success me-2"></i>Dormitory Houses
                </h4>
                <p class="text-muted mb-0">Manage student residential dormitories, capacities, and house allocations</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/admin/houses/bulk-import/bulk-import.php') ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-arrow-up me-1"></i> Bulk Import
                </a>
                <a href="<?= url('views/admin/houses/create/create.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Add New House
                </a>
            </div>
        </div>

        <!-- KPI Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Houses</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $totalHouses) ?></h3>
                            <span class="small text-muted"><?= $activeHouses ?> active houses</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-building fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Bed Capacity</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalCapacity) ?></h3>
                            <span class="small text-muted">Across all campus houses</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-door-open fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Active Status</span>
                            <h3 class="fw-bold my-1 text-info"><?= $totalHouses > 0 ? round(($activeHouses / $totalHouses) * 100) : 100 ?>%</h3>
                            <span class="small text-muted">Operational rate</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-check-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Search Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-9">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input name="search" class="form-control form-control-sm border-start-0" placeholder="Search by house name or campus location..." value="<?= e($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-filter me-1"></i> Filter</button> 
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/houses/index/index.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Houses Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="bi bi-building me-2 text-success"></i>House Directory</h6>
                    <small class="text-muted">Showing <?= count($houses) ?> registered dormitory facilities</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>House Name</th>
                            <th>Gender Allocation</th>
                            <th>Bed Capacity</th>
                            <th>Location / Zone</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($houses)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-building-x fs-3 d-block text-secondary mb-1"></i>
                                    No dormitory houses found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($houses as $house): ?>
                                <?php
                                $hStatus = strtolower((string) ($house['status'] ?? 'active'));
                                $hBadge = $hStatus === 'active' ? 'bg-success' : 'bg-secondary';
                                $hId = (string) ($house['id'] ?? '');
                                ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark"><?= e($house['name'] ?? '-') ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-gender-ambiguous me-1"></i><?= e(ucfirst($house['gender'] ?? 'Mixed')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-primary"><?= e((string) ($house['capacity'] ?? '-')) ?> beds</span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($house['location'] ?? 'Main Campus') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $hBadge ?>"><?= ucfirst(e($hStatus)) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= url('views/admin/houses/view/view.php?id=' . urlencode($hId)) ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="<?= url('views/admin/houses/edit/edit.php?id=' . urlencode($hId)) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a href="<?= url('views/admin/houses/delete/delete.php?id=' . urlencode($hId)) ?>" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></a>
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