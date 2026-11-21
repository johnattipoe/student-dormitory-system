<?php
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) { require $dir . '/bootstrap.php'; break; }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_NURSE, ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;
use App\Services\FirebaseService;

$id = $_GET['id'] ?? '';
$student = $id ? StudentService::find($id) : null;
if (!$student) { http_response_code(404); echo 'Student not found.'; exit; }
$isAdmin = current_role() === ROLE_ADMIN;
$editHouses = $isAdmin ? FirebaseService::getInstance()->getCollection(COL_HOUSES, [], 100) : [];
$house    = !empty($student['houseId']) ? HouseService::find((string) $student['houseId']) : null;
$houseName = $house['name'] ?? ($student['houseId'] ?? '—');
$room     = !empty($student['roomId']) ? RoomService::find((string) $student['roomId']) : null;
$roomName = $room['roomNumber'] ?? ($student['roomId'] ?? '—');
$fullName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: 'Student';
$st = strtolower((string)($student['status'] ?? 'active'));
$statusBadge = match($st) {
    'active'    => 'bg-success',
    'inactive'  => 'bg-secondary',
    'suspended' => 'bg-danger',
    'graduated' => 'bg-info',
    default     => 'bg-primary',
};

$pageTitle = 'Student Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-mortarboard',  'label' => 'Students',  'href' => url('views/admin/students/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-person-badge-fill text-primary me-2"></i><?= e($fullName) ?>
                </h4>
                <p class="text-muted mb-0">
                    Student Profile &bull; Adm. No: <strong><?= e($student['admissionNo'] ?? '—') ?></strong>
                    &bull; <span class="badge <?= $statusBadge ?>"><?= ucfirst(e($st)) ?></span>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($isAdmin): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adminEditStudentModal"><i class="bi bi-pencil me-1"></i>Edit Student</button>
                <?php elseif (in_array(current_role(), [ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT], true)): ?>
                    <a href="<?= url('views/admin/students/edit/edit.php?id=' . urlencode((string)($student['id'] ?? $id))) ?>"
                       class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit Student</a>
                <?php endif; ?>
                <a href="<?= url('views/gallery/index.php?houseId=' . urlencode((string) ($student['houseId'] ?? '')) . '&studentId=' . urlencode((string)($student['id'] ?? $id))) ?>"
                   class="btn btn-outline-info btn-sm"><i class="bi bi-images me-1"></i>Gallery</a>
                <a href="<?= url('views/admin/students/index/index.php') ?>"
                   class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to List</a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Personal Info -->
            <div class="col-lg-8">
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-person me-2 text-primary"></i>Personal Information</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Full Name</span>
                                <span class="fw-bold"><?= e($fullName) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Admission No.</span>
                                <span class="fw-bold font-monospace"><?= e($student['admissionNo'] ?? '—') ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Gender</span>
                                <span><?= e(ucfirst((string)($student['gender'] ?? '—'))) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Class</span>
                                <span><?= e($student['class'] ?? '—') ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Form / Level</span>
                                <span><?= e($student['form'] ?? $student['level'] ?? '—') ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Class Code</span>
                                <span><?= e($student['course'] ?? '—') ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">NHIS Number</span>
                                <span class="font-monospace"><?= e($student['nhisNumber'] ?? '—') ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Status</span>
                                <span class="badge <?= $statusBadge ?>"><?= ucfirst(e($st)) ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Guardian Info -->
                <div class="card stat-card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-person-heart me-2 text-success"></i>Guardian / Parent Information</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Guardian Name</span>
                                <span><?= e($student['guardianName'] ?? '—') ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Guardian Phone</span>
                                <span><?= e($student['guardianPhone'] ?? '—') ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted fw-semibold small">Guardian Email</span>
                                <span><?= e($student['guardianEmail'] ?? '—') ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Sidebar: Residence + Quick Actions -->
            <div class="col-lg-4">
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-house-door me-2 text-info"></i>Residence Assignment</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><span class="text-muted small d-block">Residence Type</span><strong><?= e(strtolower((string) ($student['residenceType'] ?? 'boarding')) === 'day' ? 'Day' : 'Boarding') ?></strong></p>
                        <div class="d-flex align-items-center gap-3 mb-3 p-3 bg-primary bg-opacity-5 rounded-3">
                            <div class="rounded-3 bg-primary bg-opacity-15 p-2 text-primary"><i class="bi bi-building fs-4"></i></div>
                            <div>
                                <div class="fw-bold"><?= e($houseName) ?></div>
                                <small class="text-muted">Assigned House</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3 p-3 bg-info bg-opacity-5 rounded-3">
                            <div class="rounded-3 bg-info bg-opacity-15 p-2 text-info"><i class="bi bi-door-open fs-4"></i></div>
                            <div>
                                <div class="fw-bold">Room <?= e($roomName) ?></div>
                                <small class="text-muted">Assigned Room</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card stat-card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body p-3 d-grid gap-2">
                        <?php if ($isAdmin): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adminEditStudentModal"><i class="bi bi-pencil me-1"></i>Edit Student</button>
                        <?php elseif (in_array(current_role(), [ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT], true)): ?>
                            <a href="<?= url('views/admin/students/edit/edit.php?id=' . urlencode((string)($student['id'] ?? $id))) ?>"
                               class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit Student</a>
                        <?php endif; ?>
                        <a href="<?= url('views/admin/attendance/index/index.php') ?>"
                           class="btn btn-outline-success btn-sm"><i class="bi bi-calendar-check me-1"></i>View Attendance</a>
                        <a href="<?= url('views/admin/incidents/index/index.php') ?>"
                           class="btn btn-outline-danger btn-sm"><i class="bi bi-flag me-1"></i>View Incidents</a>
                        <a href="<?= url('views/admin/students/index/index.php') ?>"
                           class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Students</a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?php if ($isAdmin): ?>
<div class="modal fade" id="adminEditStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl"><div class="modal-content">
        <form method="POST" action="<?= url('index.php?route=/views/admin/students/edit/edit.php&id=' . urlencode((string) ($student['id'] ?? $id))) ?>">
            <div class="modal-header"><h5 class="modal-title">Edit Student Information</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <h6 class="text-muted text-uppercase small fw-bold mb-3">Personal and academic</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6"><label class="form-label">First Name</label><input name="firstName" class="form-control" value="<?= e($student['firstName'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Last Name</label><input name="lastName" class="form-control" value="<?= e($student['lastName'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Admission No.</label><input name="admissionNo" class="form-control" value="<?= e($student['admissionNo'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">Select Gender</option><?php foreach (['male', 'female', 'other'] as $gender): ?><option value="<?= e($gender) ?>" <?= strtolower((string) ($student['gender'] ?? '')) === $gender ? 'selected' : '' ?>><?= ucfirst($gender) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Class</label><input name="class" class="form-control" value="<?= e($student['class'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Form / Level</label><input name="form" class="form-control" value="<?= e($student['form'] ?? $student['level'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Class Code</label><input name="course" class="form-control" value="<?= e($student['course'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">NHIS Number</label><input name="nhisNumber" class="form-control" value="<?= e($student['nhisNumber'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($student['email'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control" value="<?= e($student['phone'] ?? '') ?>"></div>
                </div>
                <h6 class="text-muted text-uppercase small fw-bold mb-3">Housing and status</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6"><label class="form-label">House</label><select name="houseId" class="form-select"><option value="">— Unassigned —</option><?php foreach ($editHouses as $houseOption): $houseOptionId = (string) ($houseOption['id'] ?? ''); ?><option value="<?= e($houseOptionId) ?>" <?= (string) ($student['houseId'] ?? '') === $houseOptionId ? 'selected' : '' ?>><?= e($houseOption['name'] ?? $houseOptionId) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Residence Type</label><select name="residenceType" class="form-select"><option value="boarding" <?= strtolower((string) ($student['residenceType'] ?? 'boarding')) === 'boarding' ? 'selected' : '' ?>>Boarding</option><option value="day" <?= strtolower((string) ($student['residenceType'] ?? 'boarding')) === 'day' ? 'selected' : '' ?>>Day</option></select></div>
                    <div class="col-md-6"><label class="form-label">Status</label><?php $studentStatus = (string) ($student['status'] ?? 'active'); ?><select name="status" class="form-select"><?php foreach (['active', 'inactive', 'suspended', 'graduated'] as $status): ?><option value="<?= e($status) ?>" <?= $studentStatus === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div>
                </div>
                <h6 class="text-muted text-uppercase small fw-bold mb-3">Parent / guardian</h6>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Guardian Name</label><input name="guardianName" class="form-control" value="<?= e($student['guardianName'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Guardian Phone</label><input type="tel" name="guardianPhone" class="form-control" value="<?= e($student['guardianPhone'] ?? '') ?>"></div>
                    <div class="col-12"><label class="form-label">Guardian Email</label><input type="email" name="guardianEmail" class="form-control" value="<?= e($student['guardianEmail'] ?? '') ?>"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
        </form>
    </div></div>
</div>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
