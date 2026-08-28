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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT]; 
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

// Use statements for services
use App\Services\BedService; use App\Services\RoomService; 
use App\Services\StudentService;

// Get the current user's house ID
$houseId = current_user()['houseId'] ?? null; $id = sanitize($_GET['id'] ?? '');
$rooms = RoomService::all($houseId);
$roomMap = [];
foreach ($rooms as $availableRoom) $roomMap[(string) ($availableRoom['id'] ?? '')] = $availableRoom;
$bed = $id !== '' ? BedService::find($id) : null;
if (!$bed && $id !== '') {
	foreach (BedService::all() as $availableBed) {
		if ((string) ($availableBed['id'] ?? '') === $id) { $bed = $availableBed; break; }
	}
}

// Get the room associated with the bed
$room = $bed ? ($roomMap[(string) ($bed['roomId'] ?? '')] ?? null) : null;
if (!$bed || !$room) { flash('error', 'Bed not found in your assigned house.'); redirect(url('views/senior-houseparent/beds/index/index.php')); }
$student = !empty($bed['studentId']) ? StudentService::find((string)$bed['studentId']) : null;
$pageTitle='Bed Details'; 
$navItems=[['icon'=>'bi-grid-3x3-gap','label'=>'Beds','href'=>url('views/senior-houseparent/beds/index/index.php'),'active'=>true]]; 

// Include header and sidebar components
require APP_ROOT.'/app/views/components/header/header.php'; 
require APP_ROOT.'/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    // Include navbar and alerts components
    <?php require APP_ROOT.'/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT.'/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:700px">
            <div class="d-flex justify-content-between">
                <h5>Bed <?=e($bed['bedNumber']??'-')?></h5>
                <a class="btn btn-outline-secondary btn-sm" href="<?=url('views/senior-houseparent/beds/index/index.php')?>">Back</a>
            </div>
            <dl class="row mt-3">
                <dt class="col-sm-3">Room</dt>
                <dd class="col-sm-9"><?=e($room['roomNumber']??'-')?></dd>
                <dt class="col-sm-3">Capacity</dt>
                <dd class="col-sm-9"><?=e((string)($bed['capacity']??1))?></dd>
                <dt class="col-sm-3">Student</dt>
                <dd class="col-sm-9"><?=$student?e(trim(($student['firstName']??'').' '.($student['lastName']??''))):'Unassigned'?></dd>
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9"><?=e($bed['status']??'available')?></dd>
            </dl>
        </div>
    </div>
</div>
<?php require APP_ROOT.'/app/views/components/footer/footer.php'; ?>