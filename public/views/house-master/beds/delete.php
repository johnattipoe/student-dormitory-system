<?php
require __DIR__ . '/_context.php';

use App\Services\BedService;
$id = sanitize($_GET['id'] ?? $_POST['id'] ?? ''); $bed = BedService::find($id); $room = $bed ? ($roomMap[(string)($bed['roomId']??'')] ?? null) : null;
if (!$bed || !house_master_bed_allowed($room, $houseId)) { flash('error', 'Bed not found in your assigned house.'); house_master_bed_redirect(); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { $result = BedService::delete($id); flash(!empty($result['success'])?'success':'error', $result['message']??'Unable to delete bed.'); house_master_bed_redirect(); }
$pageTitle='Delete Bed'; $navItems=[['icon'=>'bi-grid-3x3-gap','label'=>'Beds','href'=>url('views/house-master/beds/index.php'),'active'=>true]]; require APP_ROOT.'/app/views/components/header.php'; require APP_ROOT.'/app/views/components/sidebar.php';
?>
<div class="main-content"><div class="content-wrapper"><div class="card stat-card p-4" style="max-width:600px"><h5>Delete bed <?=e($bed['bedNumber']??'-')?>?</h5><p class="text-muted">Only unassigned beds can be deleted.</p><form method="POST"><input type="hidden" name="id" value="<?=e($id)?>"><button class="btn btn-danger">Confirm delete</button> <a class="btn btn-outline-secondary" href="<?=url('views/house-master/beds/index.php')?>">Cancel</a></form></div></div></div><?php require APP_ROOT.'/app/views/components/footer.php'; ?>
