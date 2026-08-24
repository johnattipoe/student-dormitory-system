<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\BedService;
use App\Services\RoomService;
use App\Services\StudentService;

$houseId = current_user()['houseId'] ?? null;
$rooms = RoomService::all($houseId);
$roomMap = [];
foreach ($rooms as $room) $roomMap[(string) ($room['id'] ?? '')] = $room;
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) $studentMap[(string) ($student['id'] ?? '')] = $student;
$beds = array_values(array_filter(BedService::all(), static fn ($bed) => isset($roomMap[(string) ($bed['roomId'] ?? '')])));

$search = strtolower(trim(sanitize($_GET['search'] ?? '')));
$statusFilter = sanitize($_GET['status'] ?? '');
$beds = array_values(array_filter($beds, function ($bed) use ($search, $statusFilter, $roomMap) {
    $room = $roomMap[(string) ($bed['roomId'] ?? '')] ?? [];
    return ($search === '' || str_contains(strtolower(($bed['bedNumber'] ?? '') . ' ' . ($room['roomNumber'] ?? '')), $search))
        && ($statusFilter === '' || ($bed['status'] ?? 'available') === $statusFilter);
}));
$pageTitle = 'Houseparent Beds';
$navItems = [
    ['icon'=>'bi-speedometer2','label'=>'Dashboard','href'=>url('views/houseparent/dashboard/index.php')],
    ['icon'=>'bi-mortarboard','label'=>'Students','href'=>url('views/houseparent/students/index.php')],
    ['icon'=>'bi-door-open','label'=>'Rooms','href'=>url('views/houseparent/rooms/index.php')],
    ['icon'=>'bi-grid-3x3-gap','label'=>'Beds','href'=>url('views/houseparent/beds/index.php'),'active'=>true],
];
require APP_ROOT . '/app/views/components/header.php'; require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
<?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
<?php require APP_ROOT . '/app/views/components/alerts.php'; ?>

<div class="content-wrapper">
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-1">Beds Overview</h5>
        <p class="text-muted mb-0">Review beds and occupancy in your assigned house.</p>
    </div>
</div>
<div class="card stat-card p-3 mb-3">
    <form method="GET" class="row g-2">
        <div class="col-md-8">
            <input name="search" class="form-control form-control-sm" placeholder="Search bed or room" value="<?= e($search) ?>"></div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option><?php foreach(['available','occupied','maintenance'] as $status):?>
                        <option value="<?=e($status)?>" <?= $statusFilter===$status?'selected':'' ?>><?=ucfirst($status)?></option>
                        <?php endforeach;?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm">Filter</button>
                    <a class="btn btn-outline-secondary btn-sm" href="<?=url('views/houseparent/beds/index.php')?>">Reset</a>
                </div>
            </form>
        </div>
<div class="card stat-card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Bed</th>
                    <th>Capacity</th>
                    <th>Room</th>
                    <th>Student</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!$beds):?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No beds found.</td>
                    </tr><?php else:foreach($beds as $bed):$bedId=(string)($bed['id']??'');$room=$roomMap[(string)($bed['roomId']??'')]??[];$student=$studentMap[(string)($bed['studentId']??'')]??[];$status=$bed['status']??'available';?>
                    <tr>
                        <td>
                            <strong><?=e($bed['bedNumber']??'-')?></strong>
                        </td>
                        <td><?=e((string)($bed['capacity']??1))?></td>
                        <td><?=e($room['roomNumber']??'-')?></td>
                        <td><?=$student?e(trim(($student['firstName']??'').' '.($student['lastName']??''))):'<span class="text-muted">Unassigned</span>'?></td>
                        <td><span class="badge bg-<?=$status==='occupied'?'warning':($status==='maintenance'?'secondary':'success')?>"><?=e($status)?></span></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?=url('views/houseparent/beds/view.php?id='.urlencode($bedId))?>">View</a>
                        </td>
                    </tr>
                    <?php endforeach;endif;?>
                </tbody>
            </table>
        </div>
    </div>
</div></div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
