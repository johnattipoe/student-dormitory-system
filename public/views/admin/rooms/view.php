<?php
require_once dirname(__DIR__,3).'/bootstrap.php';$allowedRoles=[ROLE_ADMIN];
require APP_ROOT.'/app/middleware/RoleMiddleware.php';
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;

$id=sanitize($_GET['id']??'');
$room=$id?RoomService::find($id):null;if(!$room){flash('error','Room not found.');redirect(url('views/admin/rooms/allocation.php'));}
$houses = HouseService::all();
$houseMap = [];
foreach ($houses as $house) {
    $houseMap[(string) ($house['id'] ?? '')] = (string) ($house['name'] ?? $house['id'] ?? '');
}
$students=array_values(array_filter(StudentService::all(),fn($student)=>($student['roomId']??'')===$id));
$pageTitle='Room Details';
$navItems=[['icon'=>'bi-door-closed','label'=>'Rooms','href'=>url('views/admin/rooms/index.php'),'active'=>true]];require APP_ROOT.'/app/views/components/header.php';require APP_ROOT.'/app/views/components/sidebar.php';?>
<div class="main-content">
    <?php require APP_ROOT.'/app/views/components/navbar.php';?>
    <div class="content-wrapper"><div class="card stat-card p-4">
        <div class="d-flex justify-content-between">
            <h5>Room <?=e($room['roomNumber']??'')?></h5>
            <a class="btn btn-outline-secondary btn-sm" href="<?=url('views/admin/rooms/allocation.php')?>">Back</a>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-4">
                <strong>House:</strong>
                <?=e($houseMap[(string) ($room['houseId'] ?? '')] ?? ($room['houseId'] ?? '—'))?>
            </div><div class="col-md-4">
                <strong>Capacity:</strong> 
                <?=e($room['capacity']??0)?>
            </div><div class="col-md-4">
                <strong>Occupied:</strong> 
                <?=e($room['occupied']??0)?></div>
            </div>
            <hr>
            <h6>Occupants</h6>
            <?php foreach($students as $student):?>
                <a class="d-block py-2 border-bottom" href="<?=url('views/admin/students/view.php?id='.urlencode((string)($student['id']??'')))?>"><?=e(trim(($student['firstName']??'').' '.($student['lastName']??'')))?>
            </a><?php endforeach;?>
            <div class="mt-4">
                <a class="btn btn-primary" href="<?=url('views/admin/rooms/edit.php?id='.urlencode($id))?>">Edit room</a>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT.'/app/views/components/footer.php';?>