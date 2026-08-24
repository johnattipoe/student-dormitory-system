<?php
require __DIR__ . '/_context.php';

use App\Services\BedService;
$id = sanitize($_GET['id'] ?? $_POST['id'] ?? ''); $bed = BedService::find($id); $room = $bed ? ($roomMap[(string)($bed['roomId']??'')] ?? null) : null;
if (!$bed || !house_master_bed_allowed($room, $houseId)) { flash('error', 'Bed not found in your assigned house.'); house_master_bed_redirect(); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { $result = BedService::assign($id, sanitize($_POST['studentId'] ?? '')); flash(!empty($result['success'])?'success':'error', $result['message']??'Unable to assign bed.'); if (!empty($result['success'])) house_master_bed_redirect(); }
$pageTitle='Assign Bed'; $navItems=[['icon'=>'bi-grid-3x3-gap','label'=>'Beds','href'=>url('views/house-master/beds/index.php'),'active'=>true]]; require APP_ROOT.'/app/views/components/header.php'; require APP_ROOT.'/app/views/components/sidebar.php';
?>
<div class="main-content">
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:650px">
            <h5 class="mb-3">Assign Bed <?=e($bed['bedNumber']??'-')?></h5>
            <p class="text-muted">Room: <?=e($room['roomNumber']??'-')?></p>
            <form method="POST">
                <input type="hidden" name="id" value="<?=e($id)?>">
                <label class="form-label">Student</label>
                <select name="studentId" class="form-select" required>
                    <option value="">Select student</option>
                    <?php foreach($students as $student): ?>
                        <option value="<?=e((string)($student['id']??''))?>">
                            <?=e(trim(($student['firstName']??'').' '.($student['lastName']??'')) )?> (<?=e($student['admissionNo']??'')?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="mt-4">
                    <button class="btn btn-primary">Assign bed</button>
                    <a class="btn btn-outline-secondary" href="<?=url('views/house-master/beds/index.php')?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT.'/app/views/components/footer.php'; ?>
