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
use App\Services\BmsSmsService;

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
$studentDataMap = [];

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

    foreach ($houseStudents as $student) {
        $studentId = (string) ($student['id'] ?? '');
        if ($studentId === '') {
            continue;
        }

        $studentDataMap[$studentId] = [
            'name' => trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')),
            'guardianPhone' => (string) ($student['guardianPhone'] ?? ''),
            'admissionNo' => (string) ($student['admissionNo'] ?? ''),
        ];
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
        'guardianPhone' => sanitize($_POST['guardianPhone'] ?? $requestStudent['guardianPhone'] ?? ''),
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
        // Send SMS notification to parent/guardian
        $guardianPhone = sanitize($_POST['guardianPhone'] ?? $requestStudent['guardianPhone'] ?? '');
        $appConfig = app_config();
        $smsEnabled = (string) ($appConfig['advanced']['sms_notifications'] ?? '0');
        
        // Log to file
        $logFile = APP_ROOT . '/storage/logs/sms-exeat-' . date('Y-m-d') . '.log';
        $logMsg = "[" . date('H:i:s') . "] [CREATE] SMS enabled: {$smsEnabled}, Guardian phone: " . (!empty($guardianPhone) ? substr($guardianPhone, -4, 4) : 'EMPTY') . "\n";
        @file_put_contents($logFile, $logMsg, FILE_APPEND);
        
        if ($guardianPhone !== '' && $smsEnabled === '1') {
            try {
                $smsService = new BmsSmsService();
                $studentName = trim(($requestStudent['firstName'] ?? '') . ' ' . ($requestStudent['lastName'] ?? ''));
                $exeatType = sanitize($_POST['exeatType'] ?? 'external');
                $startDate = sanitize($_POST['startDate'] ?? $_POST['date'] ?? '');
                $endDate = sanitize($_POST['endDate'] ?? $startDate);
                $destination = sanitize($_POST['destination'] ?? '');
                $reason = sanitize($_POST['reason'] ?? '');
                
                // Determine sender role label
                $roleSender = match($role) {
                    ROLE_ADMIN => 'Dormitory Administration',
                    ROLE_HOUSE_MASTER => 'House Master',
                    ROLE_HOUSE_MISTRESS => 'House Mistress',
                    ROLE_SENIOR_HOUSEPARENT => 'Senior Houseparent',
                    default => 'Dormitory Staff',
                };
                
                if ($exeatType === 'internal') {
                    $reasonStr = !empty($reason) ? " for {$reason}" : '';
                    $smsMessage = "Your ward {$studentName} has been approved for internal exeat on {$startDate}{$reasonStr} by the {$roleSender}.";
                } else {
                    $destStr = !empty($destination) ? " to {$destination}" : '';
                    $reasonStr = !empty($reason) ? " for {$reason}" : '';
                    $smsMessage = "Your ward {$studentName} has been approved for external exeat from {$startDate} to {$endDate}{$destStr}{$reasonStr} by the {$roleSender}.";
                }
                
                $logMsg = "[" . date('H:i:s') . "] [CREATE] Sending SMS to {$guardianPhone}, length: " . mb_strlen($smsMessage) . "\n";
                @file_put_contents($logFile, $logMsg, FILE_APPEND);
                
                $smsResult = $smsService->send($guardianPhone, $smsMessage);
                
                if ($smsResult['success']) {
                    $logMsg = "[" . date('H:i:s') . "] [CREATE] ✓ SMS SENT to {$guardianPhone}\n";
                    @file_put_contents($logFile, $logMsg, FILE_APPEND);
                    flash('info', 'Exeat created successfully. Parent/guardian has been notified via SMS.');
                } else {
                    $logMsg = "[" . date('H:i:s') . "] [CREATE] ✗ SMS FAILED: " . ($smsResult['message'] ?? 'Unknown error') . " | Response: " . json_encode($smsResult['provider_response'] ?? []) . "\n";
                    @file_put_contents($logFile, $logMsg, FILE_APPEND);
                    flash('warning', 'Exeat created successfully, but SMS notification could not be sent: ' . ($smsResult['message'] ?? 'Unknown error'));
                }
            } catch (Throwable $e) {
                $logMsg = "[" . date('H:i:s') . "] [CREATE] ✗ ERROR: " . $e->getMessage() . "\n";
                @file_put_contents($logFile, $logMsg, FILE_APPEND);
                flash('warning', 'Exeat created successfully, but SMS notification failed: ' . $e->getMessage());
            }
        } elseif ($guardianPhone === '' && $smsEnabled === '1') {
            $logMsg = "[" . date('H:i:s') . "] [CREATE] ⚠ No guardian phone for student " . ($requestStudent['id'] ?? 'UNKNOWN') . "\n";
            @file_put_contents($logFile, $logMsg, FILE_APPEND);
            flash('info', 'Exeat created successfully. No guardian phone number was found to send SMS notification.');
        } elseif ($guardianPhone !== '' && $smsEnabled !== '1') {
            $logMsg = "[" . date('H:i:s') . "] [CREATE] ⚠ SMS notifications disabled in app settings\n";
            @file_put_contents($logFile, $logMsg, FILE_APPEND);
        }
        
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
                                <select name="studentId" id="studentId" class="form-select" required <?= empty($houseStudents) ? 'disabled' : '' ?> onchange="updateStudentDetails()">
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

                        <div id="studentContactBox" class="alert alert-light border mb-4 d-none">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="small text-muted text-uppercase fw-semibold">Selected student</div>
                                    <div class="fw-bold" id="studentContactName">—</div>
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted text-uppercase fw-semibold">Parent / Guardian</div>
                                    <div id="studentContactPhone" class="fw-semibold">—</div>
                                </div>
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
                            <label class="form-label fw-semibold">Parent / Guardian Phone Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-telephone"></i></span>
                                <input type="tel" name="guardianPhone" id="guardianPhone" class="form-control" value="<?= e($requestStudent['guardianPhone'] ?? '') ?>" placeholder="e.g. +233 24 000 0000" required>
                            </div>
                            <small id="guardianPhoneHint" class="text-muted">This will be filled from the selected student's registered parent contact when available.</small>
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
const studentData = <?php echo json_encode($studentDataMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

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

function updateStudentDetails() {
    const studentSelect = document.getElementById('studentId');
    const guardianPhoneInput = document.getElementById('guardianPhone');
    const studentContactBox = document.getElementById('studentContactBox');
    const studentContactName = document.getElementById('studentContactName');
    const studentContactPhone = document.getElementById('studentContactPhone');
    const guardianPhoneHint = document.getElementById('guardianPhoneHint');

    if (!studentSelect || !guardianPhoneInput) return;

    const studentId = studentSelect.value;
    const student = studentData[studentId] || null;
    const guardianPhone = student && student.guardianPhone ? student.guardianPhone : '';

    guardianPhoneInput.value = guardianPhone;

    if (studentContactBox) {
        if (student && studentId) {
            const name = student.name || 'Student';
            const phone = guardianPhone || 'No parent/guardian number on file';
            studentContactName.textContent = name;
            studentContactPhone.textContent = phone;
            studentContactBox.classList.remove('d-none');
        } else {
            studentContactBox.classList.add('d-none');
        }
    }

    if (guardianPhoneHint) {
        guardianPhoneHint.textContent = guardianPhone
            ? 'Using the selected student\'s registered parent/guardian phone number.'
            : 'No parent/guardian phone number is registered for this student.';
    }
}

window.addEventListener('DOMContentLoaded', function () {
    updateStudentDetails();
});
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
