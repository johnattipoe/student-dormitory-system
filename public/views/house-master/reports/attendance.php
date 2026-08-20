<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\AttendanceService;
use App\Services\StudentService;
$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) $studentMap[(string) ($student['id'] ?? '')] = $student;
$records = AttendanceService::forDate($date, $houseId);
$summary = AttendanceService::summary($date, $houseId);
$csvUrl = url('views/house-master/reports/export.php?type=attendance&date=' . urlencode($date));
$pageTitle = 'Attendance Report';
$navItems = [['icon'=>'bi-file-earmark-text','label'=>'Reports','href'=>url('views/house-master/reports/index.php'),'active'=>true]];
require APP_ROOT . '/app/views/components/header.php'; require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content"><?php require APP_ROOT . '/app/views/components/navbar.php'; ?><div class="content-wrapper"><div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><div><h5 class="mb-1">Attendance Report</h5><p class="text-muted mb-0">Daily attendance breakdown for your assigned house.</p></div><form method="GET" class="d-flex gap-2"><input type="date" name="date" class="form-control" value="<?= e($date) ?>"><button class="btn btn-primary">View</button></form></div><div class="row g-3 mb-3"><?php foreach(['total'=>['Total','primary'],'present'=>['Present','success'],'absent'=>['Absent','danger'],'late'=>['Late','warning']] as $key=>[$label,$color]): ?><div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted"><?=e($label)?></small><strong class="fs-2 text-<?=e($color)?>"><?=e((string)($summary[$key]??0))?></strong></div></div><?php endforeach; ?></div><div class="card stat-card p-3"><div class="d-flex justify-content-between mb-3"><h6 class="mb-0">Daily records</h6><a class="btn btn-sm btn-outline-primary" href="<?=url('views/house-master/attendance/export.php')?>">Export CSV</a></div><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Student</th><th>Admission No.</th><th>Status</th><th>Marked By</th><th>Action</th></tr></thead><tbody><?php foreach($records as $record): $student=$studentMap[(string)($record['studentId']??'')]??[]; ?><tr><td><?=e(trim(($student['firstName']??'').' '.($student['lastName']??''))?:($record['studentId']??'-'))?></td><td><?=e($student['admissionNo']??'-')?></td><td><?=e($record['status']??'-')?></td><td><?=e($record['markedBy']??'-')?></td><td><a class="btn btn-sm btn-outline-primary" href="<?=url('views/house-master/attendance/view.php?id='.urlencode((string)($record['id']??'')))?>">View</a></td></tr><?php endforeach; ?><?php if(!$records): ?><tr><td colspan="5" class="text-center text-muted">No attendance records for this date.</td></tr><?php endif; ?></tbody></table></div></div></div></div><?php require APP_ROOT . '/app/views/components/footer.php'; ?>