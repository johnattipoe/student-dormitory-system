<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\RoomService;
use App\Services\StudentService;
$id = sanitize($_GET['id'] ?? '');
$room = $id ? RoomService::find($id) : null;
$houseId = current_user()['houseId'] ?? null;
if (!$room || ($room['houseId'] ?? null) !== $houseId) { flash('error', 'Room not found in your assigned house.'); redirect(url('views/house-master/rooms/index.php')); }
$students = array_values(array_filter(StudentService::all($houseId), fn($student) => ($student['roomId'] ?? '') === $id));
$pageTitle = 'Room Details';
$navItems = [['icon'=>'bi-door-closed','label'=>'Rooms','href'=>url('views/house-master/rooms/index.php'),'active'=>true]];
require APP_ROOT . '/app/views/components/header.php'; require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content"><?php require APP_ROOT . '/app/views/components/navbar.php'; ?><div class="content-wrapper"><div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-1">Room <?= e($room['roomNumber'] ?? '') ?></h5><p class="text-muted mb-0">Room details and current occupants.</p></div><div><a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/rooms/index.php') ?>">Back</a> <a class="btn btn-primary btn-sm" href="<?= url('views/house-master/rooms/edit.php?id=' . urlencode($id)) ?>">Edit room</a></div></div><div class="row g-3"><div class="col-md-4"><div class="card stat-card p-3"><small class="text-muted">Capacity</small><strong class="fs-2"><?= e((string) ($room['capacity'] ?? 0)) ?></strong></div></div><div class="col-md-4"><div class="card stat-card p-3"><small class="text-muted">Occupied</small><strong class="fs-2"><?= e((string) ($room['occupied'] ?? $room['occupancy'] ?? 0)) ?></strong></div></div><div class="col-md-4"><div class="card stat-card p-3"><small class="text-muted">Status</small><strong class="fs-2"><?= e($room['status'] ?? 'unknown') ?></strong></div></div></div><div class="card stat-card p-3 mt-3"><h6>Occupants</h6><div class="list-group list-group-flush"><?php foreach ($students as $student): ?><a class="list-group-item list-group-item-action px-0" href="<?= url('views/house-master/students/profile.php?studentId=' . urlencode((string) ($student['id'] ?? ''))) ?>"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> <span class="text-muted">(<?= e($student['admissionNo'] ?? '') ?>)</span></a><?php endforeach; ?><?php if (!$students): ?><div class="text-muted">No assigned occupants.</div><?php endif; ?></div></div></div></div><?php require APP_ROOT . '/app/views/components/footer.php'; ?>