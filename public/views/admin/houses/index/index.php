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
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
if ($search !== '') {
    $houses = array_values(array_filter($houses, fn($house) => str_contains(strtolower((string) ($house['name'] ?? '')), $search) || str_contains(strtolower((string) ($house['location'] ?? '')), $search)));
}
$totalFilteredHouses = count($houses);
$totalPages = max(1, (int) ceil($totalFilteredHouses / $limit));
$page = min($page, $totalPages);
$houses = array_slice($houses, ($page - 1) * $limit, $limit);
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
                    <small class="text-muted">Showing <?= e((string) $totalFilteredHouses) ?> registered dormitory facilities</small>
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
                                        <button type="button" class="btn btn-sm btn-outline-secondary" title="View" data-bs-toggle="modal" data-bs-target="#houseViewModal-<?= e($hId) ?>"><i class="bi bi-eye"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" title="Edit" data-bs-toggle="modal" data-bs-target="#houseEditModal-<?= e($hId) ?>"><i class="bi bi-pencil"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#houseDeleteModal-<?= e($hId) ?>"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php $paginationBaseUrl = url('views/admin/houses/index/index.php' . ($search !== '' ? '?search=' . urlencode($search) : '')); require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
            </div>
        </div>

    </div>
</div>

<?php foreach ($houses as $house): ?>
    <?php
    $hId = (string) ($house['id'] ?? '');
    $hName = (string) ($house['name'] ?? 'House');
    $hGender = (string) ($house['gender'] ?? 'Mixed');
    $hCapacity = (string) ($house['capacity'] ?? '0');
    $hLocation = (string) ($house['location'] ?? 'Main Campus');
    $hStatus = (string) ($house['status'] ?? 'active');
    ?>
    <div class="modal fade" id="houseViewModal-<?= e($hId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">House Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Name</dt><dd class="col-sm-7"><?= e($hName) ?></dd>
                        <dt class="col-sm-5">Gender</dt><dd class="col-sm-7"><?= e(ucfirst($hGender)) ?></dd>
                        <dt class="col-sm-5">Capacity</dt><dd class="col-sm-7"><?= e($hCapacity) ?> beds</dd>
                        <dt class="col-sm-5">Location</dt><dd class="col-sm-7"><?= e($hLocation) ?></dd>
                        <dt class="col-sm-5">Status</dt><dd class="col-sm-7"><?= e(ucfirst($hStatus)) ?></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="houseEditModal-<?= e($hId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST" action="<?= url('views/admin/houses/edit/edit.php?id=' . urlencode($hId)) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit House</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">House Name</label><input name="name" class="form-control" value="<?= e($hName) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="male" <?= strtolower($hGender) === 'male' ? 'selected' : '' ?>>Male</option><option value="female" <?= strtolower($hGender) === 'female' ? 'selected' : '' ?>>Female</option><option value="mixed" <?= strtolower($hGender) === 'mixed' ? 'selected' : '' ?>>Mixed</option></select></div>
                            <div class="col-md-6"><label class="form-label">Capacity</label><input name="capacity" type="number" class="form-control" value="<?= e($hCapacity) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" <?= strtolower($hStatus) === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= strtolower($hStatus) === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
                            <div class="col-md-12"><label class="form-label">Location</label><input name="location" class="form-control" value="<?= e($hLocation) ?>"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="houseDeleteModal-<?= e($hId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger">Delete House</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete <strong><?= e($hName) ?></strong>?</p>
                    <p class="text-muted mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="<?= url('views/admin/houses/delete/delete.php?id=' . urlencode($hId)) ?>">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>