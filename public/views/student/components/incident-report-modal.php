<?php
$studentIncidentModalId = $studentIncidentModalId ?? 'studentIncidentReport';
$studentIncidentReturnTo = $studentIncidentReturnTo ?? 'incidents';
$studentIncidentOld = $_SESSION['_student_incident_report_old'] ?? [];
$studentIncidentErrors = $_SESSION['_student_incident_report_errors'] ?? [];
unset($_SESSION['_student_incident_report_old'], $_SESSION['_student_incident_report_errors']);
?>
<div class="modal fade" id="<?= e($studentIncidentModalId) ?>" tabindex="-1" aria-hidden="true" data-open-on-load="<?= !empty($_GET['openIncidentReport']) ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/student/incidents/create/create.php') ?>" class="student-modal-form">
                <input type="hidden" name="returnToStudentPage" value="<?= e($studentIncidentReturnTo) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Report an Incident</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($studentIncidentErrors['general'])): ?><div class="alert alert-danger"><?= e($studentIncidentErrors['general']) ?></div><?php endif; ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Subject / title</label>
                            <input name="title" class="form-control" value="<?= e($studentIncidentOld['title'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="type" class="form-select">
                                <?php foreach (['disciplinary' => 'Disciplinary', 'maintenance' => 'Maintenance / Facility', 'medical' => 'Medical / Health', 'theft' => 'Lost / Stolen Item', 'conflict' => 'Roommate / Peer Conflict', 'other' => 'Other'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= ($studentIncidentOld['type'] ?? 'other') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Urgency / severity</label>
                            <select name="priority" class="form-select">
                                <?php foreach (['low', 'medium', 'high'] as $value): ?>
                                    <option value="<?= e($value) ?>" <?= ($studentIncidentOld['priority'] ?? 'medium') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Detailed description</label>
                            <textarea name="description" class="form-control" rows="5" required><?= e($studentIncidentOld['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-send me-1"></i>Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('<?= e($studentIncidentModalId) ?>');
    if (modal && modal.dataset.openOnLoad === 'true') {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
});
</script>
