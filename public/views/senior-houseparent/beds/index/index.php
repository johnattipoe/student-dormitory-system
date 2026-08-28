<?php
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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

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
$pageTitle = 'Senior Houseparent Beds';
$navItems = [
    ['icon'=>'bi-speedometer2','label'=>'Dashboard','href'=>url('views/senior-houseparent/dashboard/index.php')],
    ['icon'=>'bi-mortarboard','label'=>'Students','href'=>url('views/senior-houseparent/students/index/index.php')],
    ['icon'=>'bi-door-open','label'=>'Rooms','href'=>url('views/senior-houseparent/rooms/index/index.php')],
    ['icon'=>'bi-grid-3x3-gap','label'=>'Beds','href'=>url('views/senior-houseparent/beds/index/index.php'),'active'=>true],
];
require APP_ROOT . '/app/views/components/header/header.php'; require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
<?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
<?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

<div class="content-wrapper">
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-1">Beds Overview</h5>
        <p class="text-muted mb-0">Review beds and occupancy in your assigned house.</p>
    </div>
</div>

 <!-- Bed statistics cards -->
  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-4">
        <div class="card stat-card p-3 text-center h-100">
            <div class="text-muted small">Total Beds</div>
            <div class="fs-2 fw-bold"><?= e((string) count($beds)) ?></div>
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
                        <a class="btn btn-outline-secondary btn-sm" href="<?=url('views/senior-houseparent/beds/index/index.php')?>">Reset</a>
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
                                <a class="btn btn-sm btn-outline-primary" href="<?=url('views/senior-houseparent/beds/view/view.php?id='.urlencode($bedId))?>">View</a>
                            </td>
                        </tr>
                        <?php endforeach;endif;?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
