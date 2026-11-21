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
use App\Services\UserService;
use PhpOffice\PhpSpreadsheet\IOFactory;

$pageTitle = 'Houses';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    if (in_array($action, ['create_house', 'import_houses'], true)) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            flash('error', 'The form expired. Please refresh the page and try again.');
            redirect(url('views/admin/houses/index/index.php'));
        }

        if ($action === 'create_house') {
            $name = trim(sanitize($_POST['name'] ?? ''));
            $gender = sanitize($_POST['gender'] ?? 'coed');
            $capacity = (int) ($_POST['capacity'] ?? 1);
            $status = sanitize($_POST['status'] ?? 'active');
            if ($name === '') {
                flash('error', 'House name is required.');
            } elseif (!in_array($gender, ['male', 'female', 'coed'], true) || !in_array($status, ['active', 'inactive'], true) || $capacity < 1) {
                flash('error', 'Enter a valid gender, positive bed capacity, and house status.');
            } else {
                try {
                    HouseService::create([
                        'name' => $name,
                        'gender' => $gender,
                        'capacity' => $capacity,
                        'houseMasterId' => sanitize($_POST['houseMasterId'] ?? ''),
                        'houseMistressId' => sanitize($_POST['houseMistressId'] ?? ''),
                        'location' => sanitize($_POST['location'] ?? ''),
                        'status' => $status,
                    ]);
                    flash('success', 'House created successfully.');
                } catch (Throwable $e) {
                    error_log('Unable to create house: ' . $e->getMessage());
                    flash('error', 'Unable to create house. Please try again.');
                }
            }
            redirect(url('views/admin/houses/index/index.php'));
        }

        $imported = 0;
        $file = $_FILES['houses_file'] ?? [];
        $fileError = validate_uploaded_file($file, ['csv', 'xlsx']);
        if ($fileError !== null) {
            flash('error', $fileError);
            redirect(url('views/admin/houses/index/index.php'));
        }

        try {
            $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
            if ($extension === 'csv') {
                $handle = fopen($file['tmp_name'], 'rb');
                if ($handle === false) {
                    throw new RuntimeException('Unable to open the CSV file.');
                }

                $rows = [];
                try {
                    while (($row = fgetcsv($handle)) !== false) {
                        $rows[] = $row;
                    }
                } finally {
                    fclose($handle);
                }
            } else {
                $ioFactoryClass = 'PhpOffice\\PhpSpreadsheet\\IOFactory';
                if (!class_exists($ioFactoryClass)) {
                    throw new RuntimeException('Excel import support is unavailable. Please upload a CSV file or install PhpSpreadsheet.');
                }
                $rows = call_user_func([$ioFactoryClass, 'load'], $file['tmp_name'])
                    ->getActiveSheet()
                    ->toArray(null, true, true, false);
            }

            $header = array_map(static fn ($value) => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $value)), array_shift($rows) ?: []);
            $columns = [];
            foreach ($header as $index => $column) {
                $columns[$column] = $index;
            }
            if (!array_key_exists('name', $columns)) {
                flash('error', 'The file must include a Name column.');
                redirect(url('views/admin/houses/index/index.php'));
            }

            $value = static fn (array $row, string $column, string $default = ''): string =>
                trim((string) ($row[$columns[$column] ?? -1] ?? $default));
            $maxRecords = 1000;
            $rowsToImport = array_slice($rows, 0, $maxRecords);
            $importErrors = [];
            foreach ($rowsToImport as $index => $row) {
                if (count(array_filter($row, static fn ($cell): bool => trim((string) $cell) !== '')) === 0) {
                    continue;
                }

                $rowNumber = $index + 2;
                $name = $value($row, 'name');
                $gender = strtolower($value($row, 'gender') ?: 'coed');
                $capacityText = $value($row, 'capacity') ?: '1';
                $status = strtolower($value($row, 'status') ?: 'active');
                if ($name === '') {
                    $importErrors[] = "Row {$rowNumber}: house name is required.";
                    continue;
                }
                if (!in_array($gender, ['male', 'female', 'coed'], true)) {
                    $importErrors[] = "Row {$rowNumber}: gender must be male, female, or coed.";
                    continue;
                }
                if (!ctype_digit($capacityText) || (int) $capacityText < 1) {
                    $importErrors[] = "Row {$rowNumber}: capacity must be a positive whole number.";
                    continue;
                }
                if (!in_array($status, ['active', 'inactive'], true)) {
                    $importErrors[] = "Row {$rowNumber}: status must be active or inactive.";
                    continue;
                }

                HouseService::create([
                    'name' => sanitize($name),
                    'gender' => $gender,
                    'capacity' => (int) $capacityText,
                    'location' => sanitize($value($row, 'location')),
                    'status' => $status,
                    'houseMasterId' => sanitize($value($row, 'housemasterid')),
                    'houseMistressId' => sanitize($value($row, 'housemistressid')),
                ]);
                $imported++;
            }

            if ($imported > 0) {
                flash('success', "Imported {$imported} house(s) successfully.");
            }
            if ($importErrors !== []) {
                $shownErrors = array_slice($importErrors, 0, 3);
                $remainingErrors = count($importErrors) - count($shownErrors);
                flash('warning', implode(' ', $shownErrors) . ($remainingErrors > 0 ? " {$remainingErrors} more row(s) could not be imported." : ''));
            } elseif ($imported === 0) {
                flash('error', 'No houses were imported. Check that the file contains valid data rows.');
            }
            if (count($rows) > $maxRecords) {
                flash('info', "Only the first {$maxRecords} data rows were processed.");
            }
        } catch (Throwable $e) {
            error_log('House import failed: ' . $e->getMessage());
            flash(
                $imported > 0 ? 'warning' : 'error',
                $imported > 0
                    ? "Import stopped after {$imported} house(s) were created. Check the house directory before retrying."
                    : 'Import could not be completed. Check the house directory before retrying.'
            );
        }

        redirect(url('views/admin/houses/index/index.php'));
    }
}

$houses = HouseService::all();
$staffUsers = (new UserService())->all();
$houseMasters = array_values(array_filter($staffUsers, static fn (array $staff): bool => ($staff['role'] ?? '') === ROLE_HOUSE_MASTER));
$houseMistresses = array_values(array_filter($staffUsers, static fn (array $staff): bool => ($staff['role'] ?? '') === ROLE_HOUSE_MISTRESS));
$totalHouses = count($houses);
$activeHouses = count(array_filter($houses, fn($h) => ($h['status'] ?? 'active') === 'active'));
$totalCapacity = array_sum(array_map(fn($h) => (int) ($h['capacity'] ?? 0), $houses));

$searchQuery = trim(sanitize($_GET['search'] ?? ''));
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

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h4 class="mb-0 fw-bold text-dark">Houses</h4>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#importHousesModal">
                    <i class="bi bi-file-earmark-arrow-up me-1"></i> Upload CSV/Excel
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createHouseModal">
                    <i class="bi bi-plus-lg me-1"></i> Add House
                </button>
            </div>
        </div>
        <br>

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

        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-0">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h6 class="mb-0 fw-bold"><i class="bi bi-building me-2 text-success"></i>House Directory</h6>
                        <small class="text-muted"><?= e((string) $totalHouses) ?> registered dormitory facilities</small>
                    </div>
                    <form method="GET" action="<?= url('index.php') ?>" class="house-directory-search" role="search" data-live-search data-live-search-table="#houseDirectoryTable" data-no-page-reload="true">
                        <div class="input-group input-group-sm">
                            <input type="search" name="search" class="form-control" placeholder="Search houses..." value="<?= e($searchQuery) ?>" aria-label="Search houses by name or location">
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="houseDirectoryTable" class="table table-hover align-middle mb-0" data-external-table-search="true">
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
                    <input type="hidden" name="id" value="<?= e($hId) ?>">
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
<div class="modal fade" id="createHouseModal" tabindex="-1" aria-labelledby="createHouseModalLabel" aria-hidden="true" data-open-on-load="<?= !empty($_GET['openCreateHouse']) ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/admin/houses/index/index.php') ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="createHouseModalLabel">Add dormitory house</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_house">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="newHouseName">House name</label>
                            <input id="newHouseName" name="name" class="form-control" placeholder="e.g. Mandela House" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="newHouseGender">Gender allocation</label>
                            <select id="newHouseGender" name="gender" class="form-select" required>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="coed" selected>Co-ed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="newHouseCapacity">Bed capacity</label>
                            <input id="newHouseCapacity" type="number" name="capacity" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="newHouseLocation">Location / zone</label>
                            <input id="newHouseLocation" name="location" class="form-control" placeholder="e.g. East Campus, Block B">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="newHouseMaster">Assigned house master</label>
                            <select id="newHouseMaster" name="houseMasterId" class="form-select">
                                <option value="">Not assigned</option>
                                <?php foreach ($houseMasters as $staff): ?>
                                    <?php $staffId = (string) ($staff['id'] ?? $staff['uid'] ?? ''); ?>
                                    <option value="<?= e($staffId) ?>"><?= e($staff['name'] ?? '') ?> (<?= e($staff['email'] ?? '') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="newHouseMistress">Assigned house mistress</label>
                            <select id="newHouseMistress" name="houseMistressId" class="form-select">
                                <option value="">Not assigned</option>
                                <?php foreach ($houseMistresses as $staff): ?>
                                    <?php $staffId = (string) ($staff['id'] ?? $staff['uid'] ?? ''); ?>
                                    <option value="<?= e($staffId) ?>"><?= e($staff['name'] ?? '') ?> (<?= e($staff['email'] ?? '') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="newHouseStatus">Status</label>
                            <select id="newHouseStatus" name="status" class="form-select">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save house</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="importHousesModal" tabindex="-1" aria-labelledby="importHousesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/admin/houses/index/index.php') ?>" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="importHousesModalLabel">Import houses</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="import_houses">
                    <label class="form-label" for="housesImportFile">CSV or XLSX file</label>
                    <input id="housesImportFile" type="file" name="houses_file" class="form-control mb-3" accept=".csv,.xlsx" required>
                    <p class="small text-muted mb-0">Required header: Name. Optional headers: Gender, Capacity, Location, Status, HouseMasterId, HouseMistressId. Maximum 1,000 rows.</p>
                </div>
                <div class="modal-footer justify-content-between">
                    <a class="btn btn-outline-secondary" href="data:text/csv;charset=utf-8,Name%2CGender%2CCapacity%2CLocation%2CStatus%2CHouseMasterId%2CHouseMistressId%0AHouse%20A%2Cmale%2C100%2CNorth%20Block%2Cactive%2C%2C%0A" download="house-import-template.csv"><i class="bi bi-file-earmark-arrow-down me-1"></i>Download template</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload and import</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('#createHouseModal, #importHousesModal').forEach(function (modal) {
    modal.dataset.disableLoading = 'true';
    document.body.appendChild(modal);
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.bootstrap) return;
    document.querySelectorAll('.modal[data-open-on-load="true"]').forEach(function (modalElement) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });
});
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>