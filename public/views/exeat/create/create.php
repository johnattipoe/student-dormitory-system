<?php
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

$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT, ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\ExeatService;
use App\Services\HouseService;
use App\Services\StudentService;

$role = current_role() ?? '';
$user = current_user() ?? [];
$userId = current_user_id();
$houseId = current_house_id();
$isStudent = $role === ROLE_STUDENT;
$staffRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT];
$canCreateForStudent = in_array($role, $staffRoles, true);
$service = new ExeatService();
$studentProfile = $isStudent ? $service->studentForUser($user) : null;
$houseStudents = [];
$houseMap = [];

if ($canCreateForStudent) {
    try {
        if ($role === ROLE_ADMIN) {
            $houseStudents = StudentService::all();
        } elseif ($houseId) {
            $houseStudents = StudentService::all($houseId);
        } else {
            $houseStudents = StudentService::all();
        }
    } catch (Throwable $e) {
        $houseStudents = [];
    }

    try {
        foreach (HouseService::all() as $house) {
            $hId = (string) ($house['id'] ?? '');
            if ($hId !== '') {
                $houseMap[$hId] = (string) ($house['name'] ?? $hId);
            }
        }
    } catch (Throwable $e) {
        $houseMap = [];
    }
}

$selectedType = sanitize($_GET['type'] ?? 'internal');
if (!in_array($selectedType, ['internal', 'external'], true)) {
    $selectedType = 'internal';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'The form expired. Please refresh the page and try again.');
        redirect(url('views/exeat/create/create.php'));
    }

    $requestStudent = $studentProfile;

    if ($canCreateForStudent) {
        $selectedStudentId = sanitize($_POST['studentId'] ?? '');
        foreach ($houseStudents as $houseStudent) {
            if ((string) ($houseStudent['id'] ?? '') === $selectedStudentId) {
                $requestStudent = $houseStudent;
                break;
            }
        }
    }

    if (!$requestStudent) {
        flash('error', $canCreateForStudent ? 'Please select a valid student.' : 'Student profile was not found for this account.');
        redirect(url('views/exeat/create/create.php'));
    }

    $exeatType = sanitize($_POST['exeatType'] ?? 'internal');

    $result = $service->create([
        'studentId' => (string) ($requestStudent['id'] ?? ''),
        'studentName' => trim(($requestStudent['firstName'] ?? '') . ' ' . ($requestStudent['lastName'] ?? '')),
        'houseId' => $requestStudent['houseId'] ?? null,
        'roomId' => $requestStudent['roomId'] ?? null,
        'guardianPhone' => $requestStudent['guardianPhone'] ?? '',
        'exeatType' => $exeatType,
        'startDate' => sanitize($_POST['startDate'] ?? $_POST['date'] ?? ''),
        'endDate' => sanitize($_POST['endDate'] ?? $_POST['date'] ?? ''),
        'startTime' => sanitize($_POST['startTime'] ?? ''),
        'closeTime' => sanitize($_POST['closeTime'] ?? ''),
        'destination' => sanitize($_POST['destination'] ?? ''),
        'reason' => sanitize($_POST['reason'] ?? ''),
        'requestedBy' => $userId,
        'createdByRole' => $role,
    ]);

    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) {
        redirect(url('views/exeat/index.php'));
    }
}

$dashboardHref = match ($role) {
    ROLE_ADMIN => 'views/admin/dashboard.php',
    ROLE_STUDENT => 'views/student/dashboard/index.php',
    ROLE_SENIOR_HOUSEPARENT => 'views/senior-houseparent/dashboard/index.php',
    default => 'views/house-master/dashboard/index.php',
};
$pageTitle = $canCreateForStudent ? 'Create Exeat' : 'Request Exeat';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url($dashboardHref)],
    ['icon' => 'bi-calendar2-week', 'label' => 'Exeat', 'href' => url('views/exeat/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-calendar2-plus text-primary me-2"></i><?= e($pageTitle) ?></h4>
                <p class="text-muted mb-0"><?= $canCreateForStudent ? 'Issue internal (hours/day pass) or external (travel/home pass) exeat for students' : 'Submit your dormitory exeat request' ?></p>
            </div>
            <a href="<?= url('views/exeat/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Exeat List
            </a>
        </div>

        <div class="card stat-card shadow-sm border-0" style="max-width: 820px;">
            <div class="card-header bg-white py-3">
                <ul class="nav nav-pills card-header-pills" id="exeatTypeTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $selectedType === 'internal' ? 'active' : '' ?>" id="internal-tab" data-bs-toggle="pill" data-bs-target="#internal-form" type="button" role="tab" onclick="setType('internal')">
                            <i class="bi bi-clock-history me-1"></i>Internal Exeat (Start &amp; Close Time)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $selectedType === 'external' ? 'active' : '' ?>" id="external-tab" data-bs-toggle="pill" data-bs-target="#external-form" type="button" role="tab" onclick="setType('external')">
                            <i class="bi bi-calendar2-range me-1"></i>External Exeat (Start &amp; End Date)
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/exeat/create/create.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="exeatType" id="selectedExeatType" value="<?= e($selectedType) ?>">

                    <?php if ($canCreateForStudent): ?>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Select Student <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <select name="studentId" class="form-select" required <?= empty($houseStudents) ? 'disabled' : '' ?>>
                                    <option value=""><?= empty($houseStudents) ? 'No students available' : 'Select student...' ?></option>
                                    <?php foreach ($houseStudents as $student): ?>
                                        <?php
                                        $optionStudentId = (string) ($student['id'] ?? '');
                                        $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
                                        $sHouseId = (string) ($student['houseId'] ?? '');
                                        $sHouseName = $sHouseId !== '' ? ($houseMap[$sHouseId] ?? $sHouseId) : '';
                                        ?>
                                        <?php if ($optionStudentId !== ''): ?>
                                            <option value="<?= e($optionStudentId) ?>">
                                                <?= e($studentName !== '' ? $studentName : 'Unnamed student') ?> (<?= e($student['admissionNo'] ?? $student['studentId'] ?? $optionStudentId) ?>)<?= $sHouseName ? ' — ' . e($sHouseName) : '' ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Internal Exeat Section -->
                    <div id="internalFields" class="<?= $selectedType === 'external' ? 'd-none' : '' ?>">
                        <div class="alert alert-info small mb-3">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            <strong>Internal Exeat:</strong> Single-day pass with specific time to start and time to close/return.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-calendar-event"></i></span>
                                    <input type="date" name="date" class="form-control" value="<?= e(date('Y-m-d')) ?>" min="<?= e(date('Y-m-d')) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Time to Start <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-alarm"></i></span>
                                    <input type="time" name="startTime" class="form-control" value="08:00">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Time to Close / Return <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-clock-fill text-danger"></i></span>
                                    <input type="time" name="closeTime" class="form-control" value="17:00">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- External Exeat Section -->
                    <div id="externalFields" class="<?= $selectedType === 'internal' ? 'd-none' : '' ?>">
                        <div class="alert alert-primary small mb-3">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            <strong>External Exeat:</strong> Multi-day off-campus pass with start date and end date.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-calendar-check"></i></span>
                                    <input type="date" name="startDate" class="form-control" value="<?= e(date('Y-m-d')) ?>" min="<?= e(date('Y-m-d')) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-calendar-x"></i></span>
                                    <input type="date" name="endDate" class="form-control" value="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" min="<?= e(date('Y-m-d')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Common Fields -->
                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Destination Address / Location</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-geo-alt"></i></span>
                                <input name="destination" class="form-control" placeholder="Where will the student be going?">
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="4" required placeholder="Provide clear explanation for the exeat request..."></textarea>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="<?= url('views/exeat/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="submitBtn" <?= ($canCreateForStudent && empty($houseStudents)) ? 'disabled' : '' ?>>
                            <i class="bi bi-send me-1"></i><?= $canCreateForStudent ? 'Create Exeat' : 'Submit Request' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function setType(type) {
    document.getElementById('selectedExeatType').value = type;
    const internalFields = document.getElementById('internalFields');
    const externalFields = document.getElementById('externalFields');
    
    if (type === 'internal') {
        internalFields.classList.remove('d-none');
        externalFields.classList.add('d-none');
    } else {
        internalFields.classList.add('d-none');
        externalFields.classList.remove('d-none');
    }
}
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
