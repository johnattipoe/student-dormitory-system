<?php
$nurseMedicalModalId = $nurseMedicalModalId ?? 'nurseMedicalRecordCreate';
$nurseMedicalReturnTo = $nurseMedicalReturnTo ?? 'records';
$nurseMedicalSelectedStudentId = (string) ($nurseMedicalSelectedStudentId ?? '');
?>
<div class="modal fade" id="<?= e($nurseMedicalModalId) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/nurse/create-record/create-record.php') ?>" class="nurse-modal-form">
                <input type="hidden" name="returnToNursePage" value="<?= e($nurseMedicalReturnTo) ?>">
                <input type="hidden" name="returnStudentId" value="<?= e($nurseMedicalSelectedStudentId) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Create Medical Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Student</label>
                            <select name="studentId" class="form-select nurse-medical-student-select" required
                                <?= !empty($nurseMedicalLazyStudentOptions) ? 'data-nurse-student-options="' . e(url('views/nurse/dashboard/student-options.php')) . '"' : '' ?>>
                                <option value="">Select student</option>
                                <?php foreach ($nurseMedicalStudents as $student): ?>
                                    <?php
                                    $studentId = (string) ($student['id'] ?? '');
                                    if ($studentId === '') continue;
                                    $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
                                    ?>
                                    <option value="<?= e($studentId) ?>" data-guardian-phone="<?= e($student['guardianPhone'] ?? '') ?>" <?= $nurseMedicalSelectedStudentId === $studentId ? 'selected' : '' ?>>
                                        <?= e($studentName !== '' ? $studentName : 'Unnamed student') ?>
                                        (<?= e($student['admissionNo'] ?? $student['studentId'] ?? $studentId) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Severity</label>
                            <select name="severity" class="form-select">
                                <?php foreach (['normal', 'moderate', 'critical'] as $severity): ?>
                                    <option value="<?= e($severity) ?>"><?= e(ucfirst($severity)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Guardian phone for SMS</label>
                            <input type="tel" name="guardianPhone" class="form-control nurse-medical-guardian-phone">
                            <small class="text-muted">Prefilled from the student record; editable if needed.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Diagnosis</label>
                            <input type="text" name="diagnosis" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Treatment</label>
                            <input type="text" name="treatment" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="4"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save record</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('#<?= e($nurseMedicalModalId) ?> .nurse-medical-student-select').forEach(function (select) {
    if (select.dataset.guardianPhoneBound === 'true') return;
    const phone = select.closest('form').querySelector('.nurse-medical-guardian-phone');
    const updatePhone = function () {
        const option = select.options[select.selectedIndex];
        phone.value = option ? (option.dataset.guardianPhone || '') : '';
    };
    select.dataset.guardianPhoneBound = 'true';
    select.addEventListener('change', updatePhone);
    updatePhone();
});
</script>
