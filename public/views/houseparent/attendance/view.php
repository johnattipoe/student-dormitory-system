<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_HOUSEPARENT]; require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\AttendanceService; use App\Services\BedService; use App\Services\StudentService;
$id = sanitize($_GET['id'] ?? ''); $houseId = current_user()['houseId'] ?? null; $entry = null;
foreach (AttendanceService::byHouse($houseId) as $record) if (($record['id'] ?? '') === $id) { $entry = $record; break; }
if (!$entry) { flash('error', 'Attendance record not found.'); redirect(url('views/houseparent/attendance/index.php')); }
$student = StudentService::find((string) ($entry['studentId'] ?? '')); $bed = null;
foreach (BedService::all() as $candidate) if ((string) ($candidate['studentId'] ?? '') === (string) ($entry['studentId'] ?? '')) { $bed = $candidate; break; }
$pageTitle = 'Attendance Details'; $navItems = [['icon'=>'bi-calendar-check','label'=>'Attendance','href'=>url('views/houseparent/attendance/index.php'),'active'=>true]];
require APP_ROOT . '/app/views/components/header.php'; require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content"><?php require APP_ROOT . '/app/views/components/navbar.php'; ?><?php require APP_ROOT . '/app/views/components/alerts.php'; ?><div class="content-wrapper"><div class="card stat-card p-4"><div class="d-flex justify-content-between"><h5>Attendance Details</h5><a class="btn btn-outline-secondary btn-sm" href="<?= url('views/houseparent/attendance/index.php') ?>">Back</a></div><dl class="row mt-3"><dt class="col-sm-3">Student</dt><dd class="col-sm-9"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></dd><dt class="col-sm-3">Bed</dt><dd class="col-sm-9"><?= e($bed['bedNumber'] ?? '—') ?></dd><dt class="col-sm-3">Date</dt><dd class="col-sm-9"><?= e($entry['date'] ?? '') ?></dd><dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= e($entry['status'] ?? '') ?></dd></dl><a class="btn btn-primary" href="<?= url('views/houseparent/attendance/edit.php?id=' . urlencode($id)) ?>">Edit record</a></div></div></div><?php require APP_ROOT . '/app/views/components/footer.php'; ?>
