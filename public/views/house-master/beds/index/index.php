<?php
require __DIR__ . '/../_context/_context.php';

use App\Services\BedService;
use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    if (in_array($action, ['create', 'import'], true)) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            flash('error', 'The form expired. Please refresh the page and try again.');
            house_master_bed_redirect();
        }

        if ($action === 'create') {
            $roomId = sanitize($_POST['roomId'] ?? '');
            if (!house_master_bed_allowed($roomMap[$roomId] ?? null, $houseId)) {
                flash('error', 'Select a room in your assigned house.');
            } else {
                $result = BedService::create([
                    'bedNumber' => sanitize($_POST['bedNumber'] ?? ''),
                    'roomId' => $roomId,
                    'capacity' => max(1, (int) ($_POST['capacity'] ?? 1)),
                    'status' => sanitize($_POST['status'] ?? 'available'),
                ]);
                flash(!empty($result['success']) ? 'success' : 'error', $result['message'] ?? 'Unable to create bed.');
            }
            house_master_bed_redirect();
        }

        $file = $_FILES['beds_file'] ?? [];
        $fileError = validate_uploaded_file($file, ['csv', 'xlsx']);
        if ($fileError !== null) {
            flash('error', $fileError);
            house_master_bed_redirect();
        }

        try {
            $rows = IOFactory::load($file['tmp_name'])->getActiveSheet()->toArray(null, true, true, false);
            $header = array_map(static fn ($value) => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $value)), array_shift($rows) ?: []);
            $columns = [];
            foreach ($header as $index => $name) {
                $columns[$name] = $index;
            }
            if (!array_key_exists('bednumber', $columns)) {
                flash('error', 'The file must include a BedNumber column.');
                house_master_bed_redirect();
            }

            $defaultRoomId = sanitize($_POST['defaultRoomId'] ?? '');
            if (!array_key_exists('roomid', $columns) && $defaultRoomId === '') {
                flash('error', 'Include a RoomId column or choose a default room.');
                house_master_bed_redirect();
            }

            $value = static fn (array $row, string $column, string $default = ''): string =>
                trim((string) ($row[$columns[$column] ?? -1] ?? $default));
            $maxRecords = max(1, (int) (app_config()['import_max_records'] ?? 1000));
            $rowsToImport = array_slice($rows, 0, $maxRecords);
            $imported = 0;
            $importErrors = [];
            foreach ($rowsToImport as $index => $row) {
                if (count(array_filter($row, static fn ($cell): bool => trim((string) $cell) !== '')) === 0) {
                    continue;
                }

                $rowNumber = $index + 2;
                $bedNumber = $value($row, 'bednumber');
                $roomId = $value($row, 'roomid') ?: $defaultRoomId;
                $status = strtolower($value($row, 'status') ?: 'available');
                $capacityText = $value($row, 'capacity') ?: '1';
                if ($bedNumber === '') {
                    $importErrors[] = "Row {$rowNumber}: bed number is required.";
                    continue;
                }
                if (!house_master_bed_allowed($roomMap[$roomId] ?? null, $houseId)) {
                    $importErrors[] = "Row {$rowNumber}: select a room in your assigned house.";
                    continue;
                }
                if ($capacityText === '' || !ctype_digit($capacityText) || (int) $capacityText < 1) {
                    $importErrors[] = "Row {$rowNumber}: capacity must be a positive whole number.";
                    continue;
                }
                if (!in_array($status, ['available', 'maintenance'], true)) {
                    $importErrors[] = "Row {$rowNumber}: status must be available or maintenance.";
                    continue;
                }

                $result = BedService::create([
                    'bedNumber' => sanitize($bedNumber),
                    'roomId' => $roomId,
                    'capacity' => (int) $capacityText,
                    'status' => $status,
                ]);
                if (!empty($result['success'])) {
                    $imported++;
                } else {
                    $importErrors[] = "Row {$rowNumber}: " . (string) ($result['message'] ?? 'Unable to create bed.');
                }
            }

            if ($imported > 0) {
                flash('success', "Imported {$imported} bed(s) into your house.");
            }
            if ($importErrors !== []) {
                $shownErrors = array_slice($importErrors, 0, 3);
                $remainingErrors = count($importErrors) - count($shownErrors);
                flash('warning', implode(' ', $shownErrors) . ($remainingErrors > 0 ? " {$remainingErrors} more row(s) could not be imported." : ''));
            } elseif ($imported === 0) {
                flash('error', 'No beds were imported. Check that the file contains valid data rows.');
            }
            if (count($rows) > $maxRecords) {
                flash('info', "Only the first {$maxRecords} data rows were processed.");
            }
        } catch (Throwable $e) {
            error_log('House bed import failed: ' . $e->getMessage());
            flash('error', 'Import failed. Check the file format and try again.');
        }

        house_master_bed_redirect();
    }
}

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
                <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#houseExportBedsModal">
                    <i class="bi bi-download me-1"></i>Export
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#houseImportBedsModal">
                    <i class="bi bi-upload me-1"></i>Import
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#houseCreateBedModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Bed
                </button>
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
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#houseBedViewModal-<?= e($bedId) ?>"><i class="bi bi-eye me-1"></i>View</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary ms-1" data-bs-toggle="modal" data-bs-target="#houseBedEditModal-<?= e($bedId) ?>"><i class="bi bi-pencil me-1"></i>Edit</button>
                                            <?php if (!$student && $status !== 'maintenance'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success ms-1" data-bs-toggle="modal" data-bs-target="#houseBedAssignModal-<?= e($bedId) ?>"><i class="bi bi-person-plus me-1"></i>Assign</button>
                                            <?php elseif ($student): ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning ms-1" data-bs-toggle="modal" data-bs-target="#houseBedUnassignModal-<?= e($bedId) ?>"><i class="bi bi-person-dash me-1"></i>Unassign</button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-1" data-bs-toggle="modal" data-bs-target="#houseBedDeleteModal-<?= e($bedId) ?>"><i class="bi bi-trash"></i></button>
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
<?php foreach ($visibleBeds as $bed): ?>
    <?php $modalBedId = (string) ($bed['id'] ?? ''); $modalBedStatus = (string) ($bed['status'] ?? 'available'); $modalRoom = $roomMap[(string) ($bed['roomId'] ?? '')] ?? []; $modalStudent = $studentMap[(string) ($bed['studentId'] ?? '')] ?? []; ?>
    <div class="modal fade" id="houseBedViewModal-<?= e($modalBedId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Bed Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-5">Bed Number</dt><dd class="col-sm-7"><?= e($bed['bedNumber'] ?? '—') ?></dd><dt class="col-sm-5">Room</dt><dd class="col-sm-7"><?= e($modalRoom['roomNumber'] ?? '—') ?></dd><dt class="col-sm-5">Capacity</dt><dd class="col-sm-7"><?= e((string) ($bed['capacity'] ?? 1)) ?></dd><dt class="col-sm-5">Student</dt><dd class="col-sm-7"><?= e(trim(($modalStudent['firstName'] ?? '') . ' ' . ($modalStudent['lastName'] ?? '')) ?: 'Unassigned') ?></dd><dt class="col-sm-5">Status</dt><dd class="col-sm-7"><?= e(ucfirst($modalBedStatus)) ?></dd></dl></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
    <div class="modal fade" id="houseBedEditModal-<?= e($modalBedId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="<?= url('views/house-master/beds/edit/edit.php?id=' . urlencode($modalBedId)) ?>"><div class="modal-header"><h5 class="modal-title">Edit Bed</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><input type="hidden" name="id" value="<?= e($modalBedId) ?>"><label class="form-label">Bed Number</label><input name="bedNumber" class="form-control mb-3" value="<?= e($bed['bedNumber'] ?? '') ?>" required><label class="form-label">Room</label><select name="roomId" class="form-select mb-3"><?php foreach ($rooms as $roomOption): ?><option value="<?= e((string) ($roomOption['id'] ?? '')) ?>" <?= ((string) ($bed['roomId'] ?? '')) === (string) ($roomOption['id'] ?? '') ? 'selected' : '' ?>><?= e($roomOption['roomNumber'] ?? '—') ?></option><?php endforeach; ?></select><label class="form-label">Capacity</label><input type="number" min="1" name="capacity" class="form-control mb-3" value="<?= e((string) ($bed['capacity'] ?? 1)) ?>" required><label class="form-label">Status</label><select name="status" class="form-select"><option value="available" <?= $modalBedStatus === 'available' ? 'selected' : '' ?>>Available</option><option value="occupied" <?= $modalBedStatus === 'occupied' ? 'selected' : '' ?>>Occupied</option><option value="maintenance" <?= $modalBedStatus === 'maintenance' ? 'selected' : '' ?>>Maintenance</option></select></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div></form></div></div></div>
    <?php if (!$modalStudent && $modalBedStatus !== 'maintenance'): ?><div class="modal fade" id="houseBedAssignModal-<?= e($modalBedId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="<?= url('views/house-master/beds/assign/assign.php?id=' . urlencode($modalBedId)) ?>"><div class="modal-header"><h5 class="modal-title">Assign Bed</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><input type="hidden" name="id" value="<?= e($modalBedId) ?>"><label class="form-label">Student</label><select name="studentId" class="form-select" required><option value="">Choose a student</option><?php foreach ($students as $studentOption): ?><option value="<?= e((string) ($studentOption['id'] ?? '')) ?>"><?= e(trim(($studentOption['firstName'] ?? '') . ' ' . ($studentOption['lastName'] ?? ''))) ?> (<?= e($studentOption['admissionNo'] ?? '') ?>)</option><?php endforeach; ?></select></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Assign student</button></div></form></div></div></div><?php endif; ?>
    <?php if ($modalStudent): ?><div class="modal fade" id="houseBedUnassignModal-<?= e($modalBedId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Unassign Bed</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">Remove <strong><?= e(trim(($modalStudent['firstName'] ?? '') . ' ' . ($modalStudent['lastName'] ?? ''))) ?></strong> from this bed?</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/house-master/beds/unassign/unassign.php') ?>"><input type="hidden" name="id" value="<?= e($modalBedId) ?>"><button type="submit" class="btn btn-warning">Unassign</button></form></div></div></div></div><?php endif; ?>
    <div class="modal fade" id="houseBedDeleteModal-<?= e($modalBedId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header border-0"><h5 class="modal-title text-danger">Delete Bed</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="mb-0">Delete bed <strong><?= e($bed['bedNumber'] ?? 'this bed') ?></strong>?</p></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/house-master/beds/delete/delete.php?id=' . urlencode($modalBedId)) ?>"><input type="hidden" name="id" value="<?= e($modalBedId) ?>"><button type="submit" class="btn btn-danger">Delete</button></form></div></div></div></div>
<?php endforeach; ?>
<div class="modal fade" id="houseExportBedsModal" tabindex="-1" aria-labelledby="houseExportBedsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="houseExportBedsModalLabel">Export house beds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Download a CSV of beds in <?= e($house['name'] ?? 'your house') ?>, including room and student assignment details.
                <div id="houseExportBedsError" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="houseExportBedsDownload" class="btn btn-success" data-export-url="<?= e(url('views/house-master/beds/export/export.php')) ?>" data-filename="house-beds-<?= e(date('Y-m-d')) ?>.csv">
                    <i class="bi bi-download me-1"></i>Download CSV
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="houseCreateBedModal" tabindex="-1" aria-labelledby="houseCreateBedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/house-master/beds/index/index.php') ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="houseCreateBedModalLabel">Add bed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <label class="form-label" for="houseBedNumber">Bed number</label>
                    <input id="houseBedNumber" name="bedNumber" class="form-control mb-3" placeholder="e.g. Bed A1 or Bunk 2" required>
                    <label class="form-label" for="houseBedCapacity">Bed capacity</label>
                    <input id="houseBedCapacity" type="number" name="capacity" class="form-control mb-3" min="1" value="1" required>
                    <label class="form-label" for="houseBedRoom">Room</label>
                    <select id="houseBedRoom" name="roomId" class="form-select mb-3" required>
                        <option value="">Select room</option>
                        <?php foreach ($rooms as $room): ?>
                            <?php if (!house_master_bed_allowed($room, $houseId)) continue; ?>
                            <option value="<?= e((string) ($room['id'] ?? '')) ?>"><?= e($room['roomNumber'] ?? '—') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label" for="houseBedStatus">Initial status</label>
                    <select id="houseBedStatus" name="status" class="form-select">
                        <option value="available">Available</option>
                        <option value="maintenance">Under maintenance</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Create bed</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="houseImportBedsModal" tabindex="-1" aria-labelledby="houseImportBedsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/house-master/beds/index/index.php') ?>" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="houseImportBedsModalLabel">Import house beds</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="import">
                    <label class="form-label" for="houseBedsImportFile">CSV or XLSX file</label>
                    <input id="houseBedsImportFile" type="file" name="beds_file" class="form-control mb-3" accept=".csv,.xlsx" required>
                    <label class="form-label" for="houseBedsDefaultRoom">Default room</label>
                    <select id="houseBedsDefaultRoom" name="defaultRoomId" class="form-select mb-3">
                        <option value="">Use the RoomId column</option>
                        <?php foreach ($rooms as $room): ?>
                            <?php if (!house_master_bed_allowed($room, $houseId)) continue; ?>
                            <option value="<?= e((string) ($room['id'] ?? '')) ?>"><?= e($room['roomNumber'] ?? '—') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="small text-muted mb-0">Required header: BedNumber. Optional headers: RoomId, Capacity, Status. Any RoomId must belong to your assigned house. Maximum <?= e((string) (app_config()['import_max_records'] ?? 1000)) ?> rows.</p>
                </div>
                <div class="modal-footer justify-content-between">
                    <a class="btn btn-outline-secondary" href="data:text/csv;charset=utf-8,BedNumber%2CRoomId%2CCapacity%2CStatus%0ABed%201%2C%2C1%2Cavailable%0A" download="house-bed-import-template.csv"><i class="bi bi-file-earmark-arrow-down me-1"></i>Download template</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload and import</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.modal').forEach(function (modal) {
    modal.dataset.disableLoading = 'true';
    document.body.appendChild(modal);
});

document.getElementById('houseExportBedsDownload').addEventListener('click', async function () {
    const button = this;
    const errorMessage = document.getElementById('houseExportBedsError');
    SDS.showButtonLoading(button);
    errorMessage.classList.add('d-none');
    errorMessage.textContent = '';

    try {
        const response = await fetch(button.dataset.exportUrl, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok || !(response.headers.get('content-type') || '').includes('text/csv')) {
            throw new Error('The bed export could not be downloaded. Please try again.');
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
        errorMessage.textContent = error instanceof Error ? error.message : 'The bed export could not be downloaded. Please try again.';
        errorMessage.classList.remove('d-none');
    } finally {
        SDS.hideButtonLoading(button);
    }
});
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
