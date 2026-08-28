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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;
use App\Services\UserService;

$user = current_user() ?? [];
$userId = current_user_id();
$profile = $userId ? (new UserService())->find($userId) : null;
if (is_array($profile)) {
    $user = array_merge($user, $profile);
}

$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');
$userName = trim((string) ($user['name'] ?? 'Senior Houseparent')) ?: 'Senior Houseparent';
$userRoleKey = (string) ($user['role'] ?? ROLE_SENIOR_HOUSEPARENT);
$userRole = $userRoleKey === ROLE_SENIOR_HOUSEPARENT ? 'Senior Houseparent' : ucwords(str_replace('_', ' ', $userRoleKey));
$nameParts = preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY);
$initials = strtoupper(substr($nameParts[0] ?? 'S', 0, 1) . (count($nameParts) > 1 ? substr($nameParts[count($nameParts) - 1], 0, 1) : 'H'));

$studentCount = $houseId !== '' ? StudentService::count($houseId) : 0;
$roomStats = $houseId !== '' ? RoomService::occupancyStats($houseId) : ['rooms' => 0, 'capacity' => 0, 'occupied' => 0, 'vacant' => 0, 'occupancyRate' => 0];
$attendanceSummary = $houseId !== '' ? AttendanceService::summary(date('Y-m-d'), $houseId) : ['present' => 0, 'absent' => 0, 'total' => 0];

$pageTitle = 'Senior Houseparent Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/senior-houseparent/profile/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <!-- Hero Header -->
        <section class="card stat-card p-4 mb-4 border-start border-4 border-primary">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-3 shadow-sm" style="width:76px;height:76px;">
                        <?= e($initials) ?>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h4 class="mb-0 fw-bold"><?= e($userName) ?></h4>
                            <span class="badge bg-success-subtle text-success border"><i class="bi bi-shield-check me-1"></i>Active</span>
                        </div>
                        <p class="text-muted mb-1"><?= e($userRole) ?> • <strong><?= e($houseName) ?></strong></p>
                        <small class="text-muted"><i class="bi bi-envelope me-1"></i><?= e($user['email'] ?? '—') ?> | <i class="bi bi-telephone me-1"></i><?= e($user['phone'] ?? 'No phone set') ?></small>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-primary btn-sm" href="<?= url('views/senior-houseparent/profile/edit/edit.php') ?>">
                        <i class="bi bi-pencil me-1"></i> Edit Profile
                    </a>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/profile/security/security.php') ?>">
                        <i class="bi bi-shield-lock me-1"></i> Security & Password
                    </a>
                    <a class="btn btn-outline-primary btn-sm" href="<?= url('views/senior-houseparent/profile/house/house.php') ?>">
                        <i class="bi bi-house-gear me-1"></i> House Details
                    </a>
                </div>
            </div>
        </section>

        <!-- KPI Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Assigned Students</small>
                    <strong class="fs-2 text-primary my-1"><?= e((string) $studentCount) ?></strong>
                    <span class="small text-muted">Enrolled in <?= e($houseName) ?></span>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Dormitory Rooms</small>
                    <strong class="fs-2 text-info my-1"><?= e((string) ($roomStats['rooms'] ?? 0)) ?></strong>
                    <span class="small text-muted"><?= e((string) ($roomStats['capacity'] ?? 0)) ?> Total Bed Capacity</span>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Present Today (Roll Call)</small>
                    <strong class="fs-2 text-success my-1"><?= e((string) ($attendanceSummary['present'] ?? 0)) ?></strong>
                    <span class="small text-muted">of <?= e((string) ($attendanceSummary['total'] ?? $studentCount)) ?> checked</span>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card p-3 h-100">
                    <small class="text-muted">Bed Occupancy Rate</small>
                    <strong class="fs-2 text-warning my-1"><?= e((string) ($roomStats['occupancyRate'] ?? 0)) ?>%</strong>
                    <span class="small text-muted"><?= e((string) ($roomStats['vacant'] ?? 0)) ?> Available Spaces</span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Profile Details Card -->
            <div class="col-lg-6">
                <div class="card stat-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2 text-primary"></i> Personal & Staff Details</h6>
                        <a href="<?= url('views/senior-houseparent/profile/edit/edit.php') ?>" class="small text-decoration-none">Edit</a>
                    </div>
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted">Full Name</dt>
                        <dd class="col-sm-8 fw-semibold"><?= e($userName) ?></dd>

                        <dt class="col-sm-4 text-muted">Official Email</dt>
                        <dd class="col-sm-8"><?= e($user['email'] ?? '—') ?></dd>

                        <dt class="col-sm-4 text-muted">Primary Phone</dt>
                        <dd class="col-sm-8"><?= e($user['phone'] ?? 'Not provided') ?></dd>

                        <dt class="col-sm-4 text-muted">Alternative Phone</dt>
                        <dd class="col-sm-8"><?= e($user['altPhone'] ?? $user['emergencyPhone'] ?? 'Not provided') ?></dd>

                        <dt class="col-sm-4 text-muted">Office Location</dt>
                        <dd class="col-sm-8"><?= e($user['officeRoom'] ?? 'Senior Houseparent Office') ?></dd>

                        <dt class="col-sm-4 text-muted">Office Hours</dt>
                        <dd class="col-sm-8"><?= e($user['officeHours'] ?? 'Monday – Friday (8:00 AM – 5:00 PM)') ?></dd>
                    </dl>
                </div>
            </div>

            <!-- House & Administrative Role Details -->
            <div class="col-lg-6">
                <div class="card stat-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-building me-2 text-primary"></i> House Management Assignment</h6>
                        <a href="<?= url('views/senior-houseparent/profile/house/house.php') ?>" class="small text-decoration-none">View Team</a>
                    </div>
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted">Assigned Dormitory</dt>
                        <dd class="col-sm-8"><strong><?= e($houseName) ?></strong></dd>

                        <dt class="col-sm-4 text-muted">Role Authority</dt>
                        <dd class="col-sm-8"><span class="badge bg-primary-subtle text-primary border"><?= e($userRole) ?></span></dd>

                        <dt class="col-sm-4 text-muted">Gender / Type</dt>
                        <dd class="col-sm-8"><?= e(ucfirst((string)($house['gender'] ?? $house['type'] ?? 'General Dormitory'))) ?></dd>

                        <dt class="col-sm-4 text-muted">Building Block</dt>
                        <dd class="col-sm-8"><?= e($house['block'] ?? $house['location'] ?? 'Main Campus') ?></dd>

                        <dt class="col-sm-4 text-muted">System User ID</dt>
                        <dd class="col-sm-8"><code class="small"><?= e($userId ?: '—') ?></code></dd>

                        <dt class="col-sm-4 text-muted">Last Updated</dt>
                        <dd class="col-sm-8 small text-muted"><?= !empty($user['updatedAt']) ? e(date('M d, Y H:i', strtotime((string)$user['updatedAt']))) : 'Recently' ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Quick Access Sub-modules -->
        <div class="row g-3 mt-2">
            <div class="col-md-4">
                <div class="card stat-card p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-primary-subtle text-primary rounded"><i class="bi bi-pencil-square fs-4"></i></div>
                        <div>
                            <h6 class="mb-1 fw-bold">Edit Profile</h6>
                            <p class="text-muted small mb-1">Update contact phone, bio, and office details.</p>
                            <a href="<?= url('views/senior-houseparent/profile/edit/edit.php') ?>" class="small fw-semibold text-decoration-none">Modify Details &rarr;</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-danger-subtle text-danger rounded"><i class="bi bi-shield-lock fs-4"></i></div>
                        <div>
                            <h6 class="mb-1 fw-bold">Security & Password</h6>
                            <p class="text-muted small mb-1">Update login password and review active sessions.</p>
                            <a href="<?= url('views/senior-houseparent/profile/security/security.php') ?>" class="small fw-semibold text-decoration-none">Security Options &rarr;</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-success-subtle text-success rounded"><i class="bi bi-people fs-4"></i></div>
                        <div>
                            <h6 class="mb-1 fw-bold">House Team & Details</h6>
                            <p class="text-muted small mb-1">Review house staff, prefects, and room metrics.</p>
                            <a href="<?= url('views/senior-houseparent/profile/house/house.php') ?>" class="small fw-semibold text-decoration-none">House Overview &rarr;</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
