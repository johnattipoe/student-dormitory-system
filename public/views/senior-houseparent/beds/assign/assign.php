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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT]; require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';
use App\Services\BedService; use App\Services\RoomService; use App\Services\StudentService;
$houseId = current_user()['houseId'] ?? null; flash('error', 'Senior Houseparents have overview-only access to beds.'); redirect(url('views/senior-houseparent/beds/index/index.php')); $id = sanitize($_GET['id'] ?? $_POST['id'] ?? ''); $bed = BedService::find($id); $room = $bed ? RoomService::find((string)($bed['roomId'] ?? '')) : null;
if (!$bed || !$room || (string)($room['houseId'] ?? '') !== (string)$houseId) { flash('error', 'Bed not found in your assigned house.'); redirect(url('views/senior-houseparent/beds/index/index.php')); }
$students = StudentService::all($houseId);
if ($_SERVER['REQUEST_METHOD'] === 'POST') { $result = BedService::assign($id, sanitize($_POST['studentId'] ?? '')); flash(!empty($result['success'])?'success':'error', $result['message']??'Unable to assign bed.'); if (!empty($result['success'])) redirect(url('views/senior-houseparent/beds/index/index.php')); }
$pageTitle='Assign Bed'; $navItems=[['icon'=>'bi-grid-3x3-gap','label'=>'Beds','href'=>url('views/senior-houseparent/beds/index/index.php'),'active'=>true]]; require APP_ROOT.'/app/views/components/header/header.php'; require APP_ROOT.'/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT.'/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT.'/app/views/components/alerts/alerts.php'; ?>
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
                        <option value="<?=e((string)($student['id']??''))?>"><?=e(trim(($student['firstName']??'').' '.($student['lastName']??'')))?> (<?=e($student['admissionNo']??'')?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="mt-4">
                    <button class="btn btn-primary">Assign bed</button>
                    <a class="btn btn-outline-secondary" href="<?=url('views/senior-houseparent/beds/index/index.php')?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT.'/app/views/components/footer/footer.php'; ?>
