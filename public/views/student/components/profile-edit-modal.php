<div class="modal fade" id="studentProfileEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/student/profile/edit/edit.php') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Edit Contact Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="p-3 bg-light rounded mb-3 small">
                        <strong>Student:</strong> <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?>
                        (<?= e($student['admissionNo'] ?? '') ?>) — <?= e($student['class'] ?? 'Class not set') ?>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label" for="studentProfilePhone">Student Contact Phone</label>
                            <input id="studentProfilePhone" name="phone" class="form-control" value="<?= e($student['phone'] ?? '') ?>" placeholder="Your personal phone number">
                        </div>
                        <div class="col-12"><hr class="my-2"><h6 class="text-primary mb-0">Guardian / Emergency Contact</h6></div>
                        <div class="col-md-6">
                            <label class="form-label" for="studentGuardianName">Guardian Name</label>
                            <input id="studentGuardianName" name="guardianName" class="form-control" value="<?= e($student['guardianName'] ?? '') ?>" placeholder="Parent or Guardian name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="studentGuardianPhone">Guardian Phone</label>
                            <input id="studentGuardianPhone" name="guardianPhone" class="form-control" value="<?= e($student['guardianPhone'] ?? '') ?>" placeholder="Guardian phone number">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="studentGuardianEmail">Guardian Email</label>
                            <input id="studentGuardianEmail" type="email" name="guardianEmail" class="form-control" value="<?= e($student['guardianEmail'] ?? '') ?>" placeholder="guardian@example.com">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
