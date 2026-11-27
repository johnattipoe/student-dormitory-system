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

use App\Services\BedService;
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;
use PhpOffice\PhpSpreadsheet\IOFactory;

$rooms = RoomService::all();
$houses = HouseService::all();
$allBeds = BedService::all();
$beds = $allBeds;
$houseMap = [];
$roomMap = [];
foreach ($houses as $house) $houseMap[(string) ($house['id'] ?? '')] = (string) ($house['name'] ?? $house['id'] ?? '');
foreach ($rooms as $room) $roomMap[(string) ($room['id'] ?? '')] = $room;
$studentMap = [];
foreach ($allBeds as $bed) {
    $studentId = trim((string) ($bed['studentId'] ?? ''));
    if ($studentId === '' || array_key_exists($studentId, $studentMap)) {
        continue;
    }

    $student = StudentService::find($studentId);
    if ($student !== null) {
        $studentMap[$studentId] = $student;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    if ($action === 'import') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            flash('error', 'The form expired. Please refresh the page and try again.');
            redirect(url('views/admin/beds/index/index.php'));
        }

        $file = $_FILES['beds_file'] ?? [];
        $fileError = validate_uploaded_file($file, ['csv', 'xlsx']);
        if ($fileError !== null) {
            flash('error', $fileError);
            redirect(url('views/admin/beds/index/index.php'));
        }

        try {
            $rows = IOFactory::load($file['tmp_name'])->getActiveSheet()->toArray(null, true, true, false);
            $header = array_map(static fn ($value) => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $value)), array_shift($rows) ?: []);
            $map = [];
            foreach ($header as $index => $name) {
                $map[$name] = $index;
            }

            $defaultRoomId = sanitize($_POST['defaultRoomId'] ?? '');
            $successCount = 0;
            $importErrors = [];
            $maxRecords = (int) (app_config()['import_max_records'] ?? 1000);
            foreach (array_slice($rows, 0, $maxRecords) as $row) {
                $value = static fn ($name, $fallback = -1) => $row[$map[strtolower(preg_replace('/[^a-z0-9]/i', '', $name))] ?? $fallback] ?? '';
                $result = BedService::create([
                    'bedNumber' => sanitize($value('bednumber', 0)),
                    'roomId' => sanitize($value('roomid', 1) ?: $defaultRoomId),
                    'status' => sanitize($value('status', 2) ?: 'available'),
                ]);
                if (!empty($result['success'])) {
                    $successCount++;
                } elseif (($result['message'] ?? '') !== '') {
                    $importErrors[] = $result['message'];
                }
            }

            if ($successCount > 0) {
                flash('success', "Imported {$successCount} bed(s) successfully.");
            }
            if ($importErrors !== []) {
                $shownErrors = array_slice($importErrors, 0, 3);
                $remainingErrors = count($importErrors) - count($shownErrors);
                flash('warning', implode(' ', $shownErrors) . ($remainingErrors > 0 ? " {$remainingErrors} more row(s) could not be imported." : ''));
            } elseif ($successCount === 0) {
                flash('error', 'No beds were imported. Check that the file has BedNumber, RoomId, and Status columns and contains valid rows.');
            }
        } catch (Throwable $e) {
            error_log('Bed import failed: ' . $e->getMessage());
            flash('error', 'Import failed. Check the file format and try again.');
        }

        redirect(url('views/admin/beds/index/index.php'));
    }

    $bedId = sanitize($_POST['bedId'] ?? '');
    if ($action === 'create') {
        $result = BedService::create(['bedNumber' => sanitize($_POST['bedNumber'] ?? ''), 'roomId' => sanitize($_POST['roomId'] ?? ''), 'status' => sanitize($_POST['status'] ?? 'available')]);
    } elseif ($action === 'update') {
        $result = BedService::update($bedId, ['bedNumber' => sanitize($_POST['bedNumber'] ?? ''), 'roomId' => sanitize($_POST['roomId'] ?? ''), 'status' => sanitize($_POST['status'] ?? 'available')]);
    } elseif ($action === 'assign') {
        $result = BedService::assign($bedId, sanitize($_POST['studentId'] ?? ''));
    } elseif ($action === 'unassign') {
        $result = BedService::unassign($bedId);
    } elseif ($action === 'delete') {
        $result = BedService::delete($bedId);
    } else {
        $result = ['success' => false, 'message' => 'Unknown bed action.'];
    }
    flash(!empty($result['success']) ? 'success' : 'error', $result['message'] ?? 'Bed action failed.');
    redirect(url('views/admin/beds/index/index.php'));
}

$search = strtolower(trim(sanitize($_GET['search'] ?? '')));
$statusFilter = sanitize($_GET['status'] ?? '');
$roomFilter = sanitize($_GET['roomId'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$beds = array_values(array_filter($beds, function ($bed) use ($search, $statusFilter, $roomFilter, $roomMap, $houseMap) {
    $room = $roomMap[(string) ($bed['roomId'] ?? '')] ?? [];
    $roomName = (string) ($room['roomNumber'] ?? $bed['roomId'] ?? '');
    $houseName = $houseMap[(string) ($room['houseId'] ?? '')] ?? '';
    return ($search === '' || str_contains(strtolower(($bed['bedNumber'] ?? '') . ' ' . $roomName . ' ' . $houseName), $search))
        && ($statusFilter === '' || ($bed['status'] ?? 'available') === $statusFilter)
        && ($roomFilter === '' || (string) ($bed['roomId'] ?? '') === $roomFilter);
}));
    $totalBeds = count($beds);
    $totalPages = max(1, (int) ceil($totalBeds / $limit));
    $page = min($page, $totalPages);
    $beds = array_slice($beds, ($page - 1) * $limit, $limit);

$pageTitle = 'Bed Management';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/admin/rooms/index/index.php')],
    ['icon' => 'bi-grid-3x3-gap', 'label' => 'Beds', 'href' => url('views/admin/beds/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content" data-disable-loading>
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div><h5 class="mb-1">Bed Management</h5><p class="text-muted mb-0">Create beds, assign students, and manage availability.</p></div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#exportBedsModal"><i class="bi bi-download"></i> Export</button>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importBedsModal"><i class="bi bi-upload"></i> Import</button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createBedModal"><i class="bi bi-plus-lg"></i> Add bed</button>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Beds shown</small><strong class="fs-3"><?= e((string) $totalBeds) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Occupied</small><strong class="fs-3 text-warning"><?= e((string) count(array_filter($beds, static fn ($bed) => ($bed['status'] ?? '') === 'occupied'))) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Available</small><strong class="fs-3 text-success"><?= e((string) count(array_filter($beds, static fn ($bed) => ($bed['status'] ?? 'available') === 'available'))) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Maintenance</small><strong class="fs-3 text-secondary"><?= e((string) count(array_filter($beds, static fn ($bed) => ($bed['status'] ?? '') === 'maintenance'))) ?></strong></div></div>
        </div>
        <div class="card stat-card p-4 mb-3">
            <h6 class="mb-3">Assign student to bed</h6>
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="assign">
                <div class="col-md-5">
                    <label class="form-label">Student</label>
                    <select name="studentId" class="form-select" required>
                        <option value="">Select student</option>
                        <?php foreach ($students as $student): ?>
                            <?php if (in_array(strtolower((string) ($student['status'] ?? 'active')), ['inactive', 'suspended'], true)) continue; ?>
                            <option value="<?= e((string) ($student['id'] ?? '')) ?>"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (<?= e($student['admissionNo'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Available bed</label>
                    <select name="bedId" class="form-select" required>
                        <option value="">Select bed</option>
                        <?php foreach ($allBeds as $bed): ?>
                            <?php if (($bed['status'] ?? 'available') !== 'available' || !empty($bed['studentId'])) continue; $bedRoom = $roomMap[(string) ($bed['roomId'] ?? '')] ?? []; ?>
                            <option value="<?= e((string) ($bed['id'] ?? '')) ?>"><?= e($bed['bedNumber'] ?? '-') ?> - <?= e($bedRoom['roomNumber'] ?? '-') ?> (<?= e($houseMap[(string) ($bedRoom['houseId'] ?? '')] ?? '-') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">Assign</button>
                </div>
            </form>
        </div>
        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-2">
                <div class="col-md-5">
                    <input name="search" class="form-control form-control-sm" placeholder="Search bed, room, or house" value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="roomId" class="form-select form-select-sm">
                        <option value="">All rooms</option><?php foreach ($rooms as $room): ?>
                            <option value="<?= e((string) ($room['id'] ?? '')) ?>" <?= $roomFilter === ($room['id'] ?? '') ? 'selected' : '' ?>><?= e($room['roomNumber'] ?? '-') ?> - <?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? '-') ?>
                        </option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option><?php foreach (['available','occupied','maintenance'] as $status): ?>
                            <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?>
                        </option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm">Filter</button>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/beds/index/index.php') ?>">Reset</a>
                </div>
            </form>
        </div>
        <div class="card stat-card p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Bed</th>
                            <th>Room</th>
                            <th>House</th>
                            <th>Student</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead><tbody>
        <?php if (!$beds): ?>
            <tr>
                <td colspan="6" class="text-center text-muted py-4">No beds found. Add the first bed to begin.</td>
            </tr>
            <?php else: foreach ($beds as $bed): $bedId = (string) ($bed['id'] ?? ''); $room = $roomMap[(string) ($bed['roomId'] ?? '')] ?? []; $student = $studentMap[(string) ($bed['studentId'] ?? '')] ?? []; $status = $bed['status'] ?? 'available'; ?>
            <tr>
                <td>
                    <strong><?= e($bed['bedNumber'] ?? '-') ?></strong>
                </td>
                <td><?= e($room['roomNumber'] ?? ($bed['roomId'] ?? '-')) ?></td>
                <td><?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? '-') ?></td>
                <td><?= $student ? e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) . '<small class="d-block text-muted">' . e($student['admissionNo'] ?? '') . '</small>' : '<span class="text-muted">Unassigned</span>' ?></td>
                <td>
                    <span class="badge bg-<?= $status === 'occupied' ? 'warning' : ($status === 'maintenance' ? 'secondary' : 'success') ?>"><?= e($status) ?></span>
                </td>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewBed<?= e($bedId) ?>">
                        <i class="bi bi-eye"></i> View</button> 
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editBed<?= e($bedId) ?>">
                            <i class="bi bi-pencil"></i> Edit</button> 
                            <?php if (!$student && $status !== 'maintenance'): ?>
                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#assignBed<?= e($bedId) ?>">
                                    <i class="bi bi-person-plus"></i> Assign</button>
                                    <?php elseif ($student): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="unassign">
                                            <input type="hidden" name="bedId" value="<?= e($bedId) ?>">
                                            <button class="btn btn-sm btn-outline-warning"><i class="bi bi-person-dash"></i> Unassign</button>
                                        </form><?php endif; ?> 
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="bedId" value="<?= e($bedId) ?>">
                                            <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this bed?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
            <div class="modal fade" id="viewBed<?= e($bedId) ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Bed <?= e($bed['bedNumber'] ?? '-') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p><strong>Room:</strong> <?= e($room['roomNumber'] ?? '-') ?></p><p><strong>House:</strong> <?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? '-') ?></p><p><strong>Student:</strong> <?= $student ? e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) : 'Unassigned' ?></p><p><strong>Status:</strong> <?= e($status) ?></p></div></div></div></div>
            <div class="modal fade" id="editBed<?= e($bedId) ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Edit bed</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="update"><input type="hidden" name="bedId" value="<?= e($bedId) ?>"><label class="form-label">Bed number</label><input name="bedNumber" class="form-control mb-3" value="<?= e($bed['bedNumber'] ?? '') ?>" required><label class="form-label">Room</label><select name="roomId" class="form-select mb-3" required><?php foreach ($rooms as $optionRoom): ?><option value="<?= e((string) ($optionRoom['id'] ?? '')) ?>" <?= (string) ($bed['roomId'] ?? '') === (string) ($optionRoom['id'] ?? '') ? 'selected' : '' ?>><?= e($optionRoom['roomNumber'] ?? '-') ?> - <?= e($houseMap[(string) ($optionRoom['houseId'] ?? '')] ?? '-') ?></option><?php endforeach; ?></select><label class="form-label">Status</label><select name="status" class="form-select"><option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option><option value="maintenance" <?= $status === 'maintenance' ? 'selected' : '' ?>>Maintenance</option></select></div><div class="modal-footer"><button class="btn btn-primary">Save changes</button></div></form></div></div></div>
            <?php if (!$student && $status !== 'maintenance'): ?><div class="modal fade" id="assignBed<?= e($bedId) ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Assign bed <?= e($bed['bedNumber'] ?? '-') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="assign"><input type="hidden" name="bedId" value="<?= e($bedId) ?>"><label class="form-label">Student</label><select name="studentId" class="form-select" data-admin-student-options="<?= e(url('views/admin/emergency-contacts/student-options.php')) ?>" required><option value="">Select student</option></select></div><div class="modal-footer"><button class="btn btn-primary">Assign bed</button></div></form></div></div></div><?php endif; ?>
        <?php endforeach; endif; ?></tbody></table></div>
            <?php
            $paginationQuery = [];
            foreach (['search' => $search, 'roomId' => $roomFilter, 'status' => $statusFilter] as $key => $value) {
                if ($value !== '') $paginationQuery[] = $key . '=' . urlencode($value);
            }
            $paginationBaseUrl = url('views/admin/beds/index/index.php' . (!empty($paginationQuery) ? '?' . implode('&', $paginationQuery) : ''));
            require APP_ROOT . '/app/views/components/pagination/pagination.php';
            ?>
        </div></div>
    </div>
</div>
<div class="modal fade" id="exportBedsModal" tabindex="-1" aria-labelledby="exportBedsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportBedsModalLabel">Export beds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Download a CSV containing all beds, including room, house, student, admission number, and status.
                <div id="exportBedsError" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="exportBedsDownload" class="btn btn-success" data-export-url="<?= e(url('views/admin/beds/export/export.php')) ?>" data-filename="beds-<?= e(date('Y-m-d')) ?>.csv"><i class="bi bi-download me-1"></i> Download CSV</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="importBedsModal" tabindex="-1" aria-labelledby="importBedsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="importBedsModalLabel">Import beds</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="import">
                    <label class="form-label" for="bedsImportFile">CSV or XLSX file</label>
                    <input id="bedsImportFile" type="file" name="beds_file" class="form-control mb-3" accept=".csv,.xlsx" required>
                    <label class="form-label" for="bedsDefaultRoom">Default room</label>
                    <select id="bedsDefaultRoom" name="defaultRoomId" class="form-select mb-3">
                        <option value="">Use RoomId column</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= e((string) ($room['id'] ?? '')) ?>"><?= e($room['roomNumber'] ?? '-') ?> - <?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? '-') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="small text-muted mb-0">Required headers: BedNumber, RoomId, Status. Maximum <?= e((string) (app_config()['import_max_records'] ?? 1000)) ?> rows.</p>
                </div>
                <div class="modal-footer justify-content-between">
                    <a class="btn btn-outline-secondary" href="data:text/csv;charset=utf-8,BedNumber%2CRoomId%2CStatus%0ABed%201%2CROOM_ID%2Cavailable%0A" download="bed-import-template.csv"><i class="bi bi-file-earmark-arrow-down me-1"></i> Download template</a>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-upload me-1"></i> Upload and import</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="createBedModal" tabindex="-1" aria-labelledby="createBedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="createBedModalLabel">Add bed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <label class="form-label">Bed number</label>
                    <input name="bedNumber" class="form-control mb-3" placeholder="e.g. Bed 1" required>
                    <label class="form-label">Room</label>
                    <select name="roomId" class="form-select mb-3" required>
                        <option value="">Select room</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= e((string) ($room['id'] ?? '')) ?>"><?= e($room['roomNumber'] ?? '-') ?> - <?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? '-') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="available">Available</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Create bed</button>
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

document.getElementById('exportBedsDownload').addEventListener('click', async function () {
    const button = this;
    const errorMessage = document.getElementById('exportBedsError');
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
