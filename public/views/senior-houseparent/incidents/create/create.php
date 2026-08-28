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

use App\Services\IncidentService;
use App\Services\StudentService;
use App\Services\HouseService;
use App\Services\FirebaseService;

$user = current_user() ?? [];
$userId = current_user_id();
$userName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: 'Senior Houseparent';
$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

$students = StudentService::all($houseId);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = trim((string) sanitize($_POST['studentId'] ?? ''));
    $title = trim((string) sanitize($_POST['title'] ?? ''));
    $priority = trim((string) sanitize($_POST['priority'] ?? 'medium'));
    $incidentType = trim((string) sanitize($_POST['incidentType'] ?? 'conduct'));
    $location = trim((string) sanitize($_POST['location'] ?? ''));
    $description = trim((string) sanitize($_POST['description'] ?? ''));

    if ($studentId === '') {
        $errors['studentId'] = 'Please select an involved student.';
    }
    if ($title === '') {
        $errors['title'] = 'Incident title is required.';
    }
    if ($description === '') {
        $errors['description'] = 'Incident description is required.';
    }

    if (empty($errors)) {
        $studentName = '';
        foreach ($students as $st) {
            if ((string)($st['id'] ?? '') === $studentId) {
                $studentName = trim(($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? ''));
                break;
            }
        }

        $incidentData = [
            'title' => $title,
            'description' => $description,
            'studentId' => $studentId,
            'studentName' => $studentName,
            'priority' => $priority,
            'type' => $incidentType,
            'location' => $location,
            'houseId' => $houseId,
            'houseName' => $houseName,
            'status' => 'open',
            'reportedBy' => $userId,
            'reportedByName' => $userName . ' (Senior Houseparent)',
            'reportedAt' => date(DATE_ATOM),
            'createdAt' => date(DATE_ATOM),
        ];

        $result = (new IncidentService())->create($incidentData);
        if ($result['success']) {
            // Also log to house activity logs
            FirebaseService::getInstance()->addDocument(COL_ACTIVITY_LOGS, [
                'event' => 'Incident Reported',
                'action' => 'Disciplinary Incident: ' . $title,
                'type' => 'incident_report',
                'details' => 'Reported incident for ' . $studentName . ' (' . $title . ') — Priority: ' . ucfirst($priority),
                'houseId' => $houseId,
                'houseName' => $houseName,
                'studentId' => $studentId,
                'studentName' => $studentName,
                'userId' => $userId,
                'userName' => $userName . ' (Senior Houseparent)',
                'performedBy' => $userId,
                'performedByName' => $userName . ' (Senior Houseparent)',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'timestamp' => date(DATE_ATOM),
            ]);

            flash('success', 'Incident reported successfully.');
            redirect(url('views/senior-houseparent/incidents/index/index.php'));
        } else {
            $errors['general'] = $result['message'] ?? 'Failed to record incident.';
        }
    }
}

$pageTitle = 'Report Disciplinary Incident';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/senior-houseparent/incidents/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Report House Disciplinary Incident</h5>
                <p class="text-muted mb-0">Record a disciplinary infraction, safety concern, or rule breach for <?= e($houseName) ?>.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/incidents/index/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Incidents
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card stat-card p-4" style="max-width: 800px;">
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="studentId">Involved Student <span class="text-danger">*</span></label>
                    <select class="form-select <?= !empty($errors['studentId']) ? 'is-invalid' : '' ?>" id="studentId" name="studentId" required>
                        <option value="">— Select Student —</option>
                        <?php foreach ($students as $st): ?>
                            <?php $fullName = trim(($st['firstName'] ?? '') . ' ' . ($st['lastName'] ?? '')); ?>
                            <option value="<?= e($st['id'] ?? '') ?>" <?= ($_POST['studentId'] ?? '') === ($st['id'] ?? '') ? 'selected' : '' ?>>
                                <?= e($fullName ?: 'Student') ?> <?= !empty($st['admissionNo']) ? '[' . e($st['admissionNo']) . ']' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['studentId'])): ?>
                        <div class="invalid-feedback"><?= e($errors['studentId']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="incidentType">Incident Category</label>
                    <select class="form-select" id="incidentType" name="incidentType">
                        <option value="conduct">Conduct & Discipline</option>
                        <option value="curfew">Curfew / Unauthorized Absence</option>
                        <option value="damage">Property / Dorm Damage</option>
                        <option value="altercation">Physical Altercation / Bullying</option>
                        <option value="noise">Noise / Lights-Out Disturbance</option>
                        <option value="other">Other Incident</option>
                    </select>
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-bold" for="title">Incident Title <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['title']) ? 'is-invalid' : '' ?>" id="title" name="title" value="<?= e($_POST['title'] ?? '') ?>" placeholder="e.g. Curfew Breach after 10:30 PM" required>
                    <?php if (!empty($errors['title'])): ?>
                        <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold" for="priority">Priority Level</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="low">Low (Minor Infraction)</option>
                        <option value="medium" selected>Medium (Standard)</option>
                        <option value="high">High (Major Breach)</option>
                        <option value="critical">Critical (Severe / Immediate Action)</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="location">Location / Room Number</label>
                    <input class="form-control" id="location" name="location" value="<?= e($_POST['location'] ?? '') ?>" placeholder="e.g. Block B, Room 14 / Common Room">
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="description">Detailed Description & Evidence <span class="text-danger">*</span></label>
                    <textarea class="form-control <?= !empty($errors['description']) ? 'is-invalid' : '' ?>" id="description" name="description" rows="5" placeholder="Provide full details of the incident, witnesses present, and statements..." required><?= e($_POST['description'] ?? '') ?></textarea>
                    <?php if (!empty($errors['description'])): ?>
                        <div class="invalid-feedback"><?= e($errors['description']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/senior-houseparent/incidents/index/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-shield-check me-1"></i> Submit Incident Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

