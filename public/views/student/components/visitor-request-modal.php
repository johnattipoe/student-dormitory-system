<?php
$studentVisitorModalId = $studentVisitorModalId ?? 'studentVisitorRequest';
$studentVisitorReturnTo = $studentVisitorReturnTo ?? 'visitors';
$studentVisitorOld = $_SESSION['_student_visitor_request_old'] ?? [];
$studentVisitorErrors = $_SESSION['_student_visitor_request_errors'] ?? [];
unset($_SESSION['_student_visitor_request_old'], $_SESSION['_student_visitor_request_errors']);
?>
<div class="modal fade" id="<?= e($studentVisitorModalId) ?>" tabindex="-1" aria-hidden="true" data-open-on-load="<?= !empty($_GET['openVisitorRequest']) ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/visitors/request/request.php') ?>" class="student-modal-form">
                <input type="hidden" name="returnToStudentPage" value="<?= e($studentVisitorReturnTo) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Request a Visitor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Visitor name</label>
                            <input name="visitorName" class="form-control" value="<?= e($studentVisitorOld['visitorName'] ?? '') ?>" required>
                            <?php if (!empty($studentVisitorErrors['visitorName'])): ?><div class="text-danger small mt-1"><?= e($studentVisitorErrors['visitorName']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($studentVisitorOld['phone'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Relationship</label><input name="relationship" class="form-control" value="<?= e($studentVisitorOld['relationship'] ?? '') ?>" placeholder="Parent, sibling, or guardian"></div>
                        <div class="col-md-6">
                            <label class="form-label">Visit date</label>
                            <input type="date" name="visitDate" class="form-control" value="<?= e($studentVisitorOld['visitDate'] ?? '') ?>" required>
                            <?php if (!empty($studentVisitorErrors['visitDate'])): ?><div class="text-danger small mt-1"><?= e($studentVisitorErrors['visitDate']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12"><label class="form-label">Purpose</label><textarea name="purpose" class="form-control" rows="4"><?= e($studentVisitorOld['purpose'] ?? '') ?></textarea></div>
                        <?php if (!empty($studentVisitorErrors['general'])): ?><div class="col-12"><div class="alert alert-danger mb-0"><?= e($studentVisitorErrors['general']) ?></div></div><?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('<?= e($studentVisitorModalId) ?>');
    if (modal && modal.dataset.openOnLoad === 'true') {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
});
</script>
