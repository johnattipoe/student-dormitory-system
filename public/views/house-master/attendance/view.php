<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php'; $allowedRoles=[ROLE_HOUSE_MASTER,ROLE_HOUSE_MISTRESS]; require APP_ROOT.'/app/middleware/RoleMiddleware.php'; use App\Services\AttendanceService; use App\Services\BedService; use App\Services\StudentService; use App\Services\UserService;
$id=sanitize($_GET['id']??'');$houseId=current_user()['houseId']??null;$entry=null;foreach(AttendanceService::byHouse($houseId) as $record){if(($record['id']??'')===$id){$entry=$record;break;}}if(!$entry){flash('error','Attendance record not found.');redirect(url('views/house-master/attendance/history.php'));}$student=StudentService::find((string)($entry['studentId']??''));$bed=null;foreach(BedService::all() as $candidateBed){if((string)($candidateBed['studentId']??'')===(string)($entry['studentId']??'')){$bed=$candidateBed;break;}}$markedBy=(new UserService())->find((string)($entry['markedBy']??''));$markedByName=trim((string)($markedBy['name']??''));if($markedByName===''){$markedByName=trim(($markedBy['firstName']??'').' '.($markedBy['lastName']??''));}$pageTitle='Attendance Details';$navItems=[['icon'=>'bi-calendar-check','label'=>'Attendance','href'=>url('views/house-master/attendance/index.php'),'active'=>true]];require APP_ROOT.'/app/views/components/header.php';require APP_ROOT.'/app/views/components/sidebar.php';?>
<div class="main-content">
    <?php require APP_ROOT.'/app/views/components/navbar.php';?>
    <?php require APP_ROOT.'/app/views/components/alerts.php';?>
    <?php require APP_ROOT.'/app/views/components/navbar.php';?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <div class="d-flex justify-content-between">
                <h5>Attendance Details</h5>
                <a class="btn btn-outline-secondary btn-sm" href="<?=url('views/house-master/attendance/history.php')?>">Back</a>
            </div>
            <dl class="row mt-3">
                <dt class="col-sm-3">Student</dt>
                <dd class="col-sm-9"><?=e(trim(($student['firstName']??'').' '.($student['lastName']??'')))?></dd>
                <dt class="col-sm-3">Bed</dt>
                <dd class="col-sm-9"><?=e($bed['bedNumber']??'—')?></dd>
                <dt class="col-sm-3">Date</dt>
                <dd class="col-sm-9"><?=e($entry['date']??'')?></dd>
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9"><?=e($entry['status']??'')?></dd>
                <dt class="col-sm-3">Marked by</dt>
                <dd class="col-sm-9"><?=e($markedByName!==''?$markedByName:($entry['markedBy']??'—'))?></dd>
            </dl>
            <a class="btn btn-primary" href="<?=url('views/house-master/attendance/edit.php?id='.urlencode($id))?>">Edit record</a>
        </div>
    </div>
</div>
<?php require APP_ROOT.'/app/views/components/footer.php'; ?>