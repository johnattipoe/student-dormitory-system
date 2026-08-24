<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_HOUSEPARENT]; require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\BedService; use App\Services\RoomService; use App\Services\StudentService;
$houseId = current_user()['houseId'] ?? null; $id = sanitize($_GET['id'] ?? ''); $bed = BedService::find($id); $room = $bed ? RoomService::find((string)($bed['roomId'] ?? '')) : null;
if (!$bed || !$room || (string)($room['houseId'] ?? '') !== (string)$houseId) { flash('error', 'Bed not found in your assigned house.'); redirect(url('views/houseparent/beds/index.php')); }
$student = !empty($bed['studentId']) ? StudentService::find((string)$bed['studentId']) : null;
$pageTitle='Bed Details'; $navItems=[['icon'=>'bi-grid-3x3-gap','label'=>'Beds','href'=>url('views/houseparent/beds/index.php'),'active'=>true]]; require APP_ROOT.'/app/views/components/header.php'; require APP_ROOT.'/app/views/components/sidebar.php';
?>
<div class="main-content"><?php require APP_ROOT.'/app/views/components/navbar.php'; ?><?php require APP_ROOT.'/app/views/components/alerts.php'; ?><div class="content-wrapper"><div class="card stat-card p-4" style="max-width:700px"><div class="d-flex justify-content-between"><h5>Bed <?=e($bed['bedNumber']??'-')?></h5><a class="btn btn-outline-secondary btn-sm" href="<?=url('views/houseparent/beds/index.php')?>">Back</a></div><dl class="row mt-3"><dt class="col-sm-3">Room</dt><dd class="col-sm-9"><?=e($room['roomNumber']??'-')?></dd><dt class="col-sm-3">Capacity</dt><dd class="col-sm-9"><?=e((string)($bed['capacity']??1))?></dd><dt class="col-sm-3">Student</dt><dd class="col-sm-9"><?=$student?e(trim(($student['firstName']??'').' '.($student['lastName']??''))):'Unassigned'?></dd><dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?=e($bed['status']??'available')?></dd></dl></div></div></div><?php require APP_ROOT.'/app/views/components/footer.php'; ?>
