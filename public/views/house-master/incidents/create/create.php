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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\IncidentService;
use App\Services\StudentService;

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = sanitize($_POST['studentId'] ?? '');
    $valid = array_filter($students, fn($student) => (string) ($student['id'] ?? '') === $studentId);
    if (!$valid) {
        flash('error', 'Select a valid student from your assigned house.');
    } else {
        $result = (new IncidentService())->create([
            'title' => sanitize($_POST['title'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'studentId' => $studentId,
            'priority' => sanitize($_POST['priority'] ?? 'medium'),
            'reportedBy' => current_user()['uid'] ?? current_user()['id'] ?? null,
        ]);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        if ($result['success']) {
            redirect(url('views/house-master/incidents/index/index.php'));
        }
    }
}

$pageTitle = 'Report Incident';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index/index.php'), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-shield-exclamation text-danger me-2"></i>Report House Incident</h4>
                <p class="text-muted mb-0">Record behavioral infractions, disciplinary reports, or safety issues</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/incidents/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back to Incidents
                </a>
            </div>
        </div>

        <!-- Form Card -->
        <div class="card stat-card shadow-sm border-0" style="max-width: 860px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-danger"></i>Incident Report Form</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/house-master/incidents/create/create.php') ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Student Involved <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <select name="studentId" class="form-select" required>
                                    <option value="">Select student</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= e((string) ($student['id'] ?? '')) ?>">
                                            <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (<?= e($student['admissionNo'] ?? 'No ID') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Incident Title <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-type"></i></span>
                                <input name="title" class="form-control" placeholder="e.g. Lights-out Curfew Violation" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Severity / Priority</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-exclamation-triangle"></i></span>
                                <select name="priority" class="form-select">
                                    <option value="low">Low Priority</option>
                                    <option value="medium" selected>Medium Priority</option>
                                    <option value="high">High Priority</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Detailed Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="5" placeholder="Provide full details about the incident, location, witnesses, and initial actions taken..." required></textarea>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/incidents/index/index.php') ?>">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-send me-1"></i>Submit Incident
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>