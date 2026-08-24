<?php
require __DIR__ . '/_context.php';

use App\Services\BedService;
$id = sanitize($_GET['id'] ?? '');
$bed = BedService::find($id);
$room = $bed ? ($roomMap[(string)($bed['roomId']??'')] ?? null) : null;
if (!$bed || !house_master_bed_allowed($room, $houseId)) { flash('error', 'Bed not found in your assigned house.'); house_master_bed_redirect(); }
$student = $studentMap[(string)($bed['studentId']??'')] ?? null;
$pageTitle = 'Bed Details'; $navItems = [['icon'=>'bi-grid-3x3-gap','label'=>'Beds','href'=>url('views/house-master/beds/index.php'),'active'=>true]];
require APP_ROOT . '/app/views/components/header.php'; require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:700px">
            <div class="d-flex justify-content-between">
                <h5>Bed <?= e($bed['bedNumber']??'-') ?></h5>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/beds/index.php') ?>">Back</a>
            </div>
            <dl class="row mt-3">
                <dt class="col-sm-3">Room</dt>
                <dd class="col-sm-9"><?= e($room['roomNumber']??'-') ?></dd>
                <dt class="col-sm-3">Capacity</dt>
                <dd class="col-sm-9"><?= e((string)($bed['capacity'] ?? 1)) ?></dd>
                <dt class="col-sm-3">House</dt>
                <dd class="col-sm-9"><?= e($house['name']??'-') ?></dd>
                <dt class="col-sm-3">Student</dt>
                <dd class="col-sm-9"><?= $student ? e(trim(($student['firstName']??'').' '.($student['lastName']??''))) : 'Unassigned' ?></dd>
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9"><?= e($bed['status']??'available') ?></dd>
            </dl>
            <a class="btn btn-primary" href="<?= url('views/house-master/beds/edit.php?id='.urlencode($id)) ?>">Edit bed</a>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
