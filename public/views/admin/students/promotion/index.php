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

$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';
if (current_role() !== ROLE_ADMIN) {
    http_response_code(403);
    require APP_ROOT . '/public/views/errors/403.php';
    exit;
}

use App\Services\StudentPromotionService;

$pageTitle = 'Promote Students';
$promotionService = new StudentPromotionService();
$errors = [];
$preview = null;
$candidateDecisions = [];
$sourceForm = trim(sanitize($_POST['sourceForm'] ?? $_GET['sourceForm'] ?? ''));
if ($sourceForm === '') {
    $sourceForm = 'Form 1';
}
$runKey = 'promotion_' . strtolower(str_replace(' ', '_', $sourceForm));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'The form expired. Please refresh the page and try again.');
        redirect(url('views/admin/students/promotion/index.php'));
    }

    $action = sanitize($_POST['action'] ?? '');
    if (!StudentPromotionService::isValidSourceForm($sourceForm)) {
        $errors[] = 'Choose a valid source form: Form 1, Form 2, or Form 3.';
    } elseif ($action === 'preview' || $action === 'apply') {
        try {
            $preview = $promotionService->preview($sourceForm);
            $candidateDecisions = is_array($_POST['decisions'] ?? null) ? $_POST['decisions'] : [];

            if ($action === 'apply') {
                $plan = $promotionService->buildPlan($preview['candidates'], $candidateDecisions);
                $batchLimit = max(0, min(500, (int) ($_POST['batchLimit'] ?? 0)));
                $result = $promotionService->applyBySourceForm(
                    $sourceForm,
                    $plan,
                    (string) (current_user_id() ?? 'admin'),
                    $batchLimit
                );
                if (($result['status'] ?? '') === 'partial') {
                    flash('success', 'The promotion batch is saved. ' . (int) ($result['remaining'] ?? 0) . ' students remain in ' . $sourceForm . '.');
                } else {
                    flash('success', 'Student promotion completed for ' . $sourceForm . '. The promotion history has been saved.');
                }
                redirect(url('views/admin/students/promotion/index.php'));
            }
        } catch (\Throwable $exception) {
            error_log('Student promotion page failed: ' . $exception->getMessage());
            $errors[] = $exception->getMessage();
        }
    } elseif ($action === 'resume') {
        try {
            $batchLimit = max(0, min(500, (int) ($_POST['batchLimit'] ?? 0)));
            $result = $promotionService->applyBySourceForm(
                $sourceForm,
                [],
                (string) (current_user_id() ?? 'admin'),
                $batchLimit
            );
            flash(
                'success',
                ($result['status'] ?? '') === 'partial'
                    ? 'Promotion paused after the current batch. Resume when ready.'
                    : 'The saved student promotion run for ' . $sourceForm . ' has been completed.'
            );
            redirect(url('views/admin/students/promotion/index.php'));
        } catch (\Throwable $exception) {
            error_log('Student promotion resume failed: ' . $exception->getMessage());
            $errors[] = $exception->getMessage();
        }
    } elseif ($action === 'undo') {
        try {
            $result = $promotionService->undoBySourceForm($sourceForm, (string) (current_user_id() ?? 'admin'));
            flash('success', 'Promotion for ' . $sourceForm . ' was undone for ' . (int) $result['undone'] . ' students.');
            redirect(url('views/admin/students/promotion/index.php'));
        } catch (\Throwable $exception) {
            error_log('Student promotion undo failed: ' . $exception->getMessage());
            $errors[] = $exception->getMessage();
        }
    } else {
        $errors[] = 'Choose a valid promotion action.';
    }
}

$runForYear = null;
$recentRuns = [];
$showHistory = ($_GET['history'] ?? '') === '1';
$promotionHistory = [];
$promotionPageRoute = urlencode('/views/admin/students/promotion/index.php');
try {
    $runForYear = $promotionService->findRunForSourceForm($sourceForm);
    $recentRuns = $promotionService->recentRuns();
    if ($showHistory && $runForYear !== null) {
        $runAcademicYear = (string) ($runForYear['academicYear'] ?? '');
        $promotionHistory = $runAcademicYear !== ''
            ? $promotionService->historyForYear($runAcademicYear)
            : [];
    }
} catch (\Throwable $exception) {
    error_log('Unable to load student promotion records: ' . $exception->getMessage());
    $errors[] = 'Promotion history could not be loaded. Please try again later.';
}

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/admin/students/index/index.php')],
    ['icon' => 'bi-arrow-up-circle', 'label' => 'Promote Students', 'href' => url('views/admin/students/promotion/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<style>
.promotion-preview-table-wrap {
    overflow-x: auto;
    -ms-overflow-style: auto;
    scrollbar-width: thin;
    scrollbar-color: #7c93b8 #e8eef8;
}
.promotion-preview-table-wrap::-webkit-scrollbar {
    display: block;
    width: 10px;
    height: 10px;
}
.promotion-preview-table-wrap::-webkit-scrollbar-track {
    background: #e8eef8;
    border-radius: 999px;
}
.promotion-preview-table-wrap::-webkit-scrollbar-thumb {
    background: #7c93b8;
    border: 2px solid #e8eef8;
    border-radius: 999px;
}
.promotion-preview-table .promotion-proposed-class {
    min-width: 140px;
    white-space: nowrap;
}
.promotion-decision {
    width: 150px;
    min-width: 150px;
}
</style>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <h4 class="mb-1 fw-bold"><i class="bi bi-arrow-up-circle text-primary me-2"></i>Promote Students</h4>
                <p class="text-muted mb-0">Review year-level changes, mark repeaters, and graduate Form 3 students.</p>
            </div>
            <a href="<?= url('views/admin/students/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-people me-1"></i> Student List
            </a>
        </div>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
        <?php endforeach; ?>

        <div class="alert alert-info d-flex gap-2" role="note">
            <i class="bi bi-info-circle fs-5"></i>
            <div>
                Promotion uses the student's <strong>Form / Level</strong>. A matching <strong>Class</strong> value such as SHS 1 or Form 1 is advanced too; other class names are preserved. Students whose level cannot be identified are excluded and listed for review.
                Form 3 graduates are marked <strong>graduated</strong>; their house and room assignments are left unchanged.
            </div>
        </div>

        <div class="card stat-card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/admin/students/promotion/index.php') ?>" class="row g-3 align-items-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="preview">
                    <div class="col-md-5">
                        <label for="sourceForm" class="form-label fw-semibold">Source form</label>
                        <select id="sourceForm" name="sourceForm" class="form-select" required>
                            <?php foreach (['Form 1', 'Form 2', 'Form 3'] as $formOption): ?>
                                <option value="<?= e($formOption) ?>" <?= $sourceForm === $formOption ? 'selected' : '' ?>><?= e($formOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i> Preview Promotion</button>
                    </div>
                    <div class="col-12">
                        <div class="form-text">Promote students from the selected form to the next form. Form 3 students graduate.</div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($runForYear && ($runForYear['status'] ?? '') === 'completed'): ?>
            <div class="alert alert-success">
                A promotion run for <strong><?= e($sourceForm) ?></strong> has already been completed:
                <?= e((string) ($runForYear['promoted'] ?? 0)) ?> promoted,
                <?= e((string) ($runForYear['repeated'] ?? 0)) ?> repeated, and
                <?= e((string) ($runForYear['graduated'] ?? 0)) ?> graduated.
                This year cannot be applied a second time.
                <form method="POST" action="<?= url('views/admin/students/promotion/index.php') ?>" class="mt-3"
                      onsubmit="return confirm('Undo all verified changes from this promotion year? Records changed after promotion will stop the undo safely.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="undo">
                    <input type="hidden" name="sourceForm" value="<?= e($sourceForm) ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i> Undo This Promotion</button>
                </form>
            </div>
        <?php elseif ($runForYear && in_array(($runForYear['status'] ?? ''), ['partial', 'pending', 'processing'], true)): ?>
            <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <strong>This promotion run is <?= e((string) $runForYear['status']) ?>.</strong>
                    <?= e((string) ($runForYear['processed'] ?? 0)) ?> of <?= e((string) ($runForYear['total'] ?? 0)) ?> student records are complete.
                    Resume uses the saved preview and safely skips completed records.
                </div>
                <form method="POST" action="<?= url('views/admin/students/promotion/index.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="resume">
                    <input type="hidden" name="sourceForm" value="<?= e($sourceForm) ?>">
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-nowrap" for="batchLimitResume">Batch size</label>
                        <select id="batchLimitResume" name="batchLimit" class="form-select form-select-sm">
                            <option value="25">25 students</option>
                            <option value="50">50 students</option>
                            <option value="100">100 students</option>
                            <option value="0" selected>All remaining</option>
                        </select>
                    </div>
                    <button class="btn btn-warning" type="submit"><i class="bi bi-arrow-repeat me-1"></i> Resume Run</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (is_array($preview)): ?>
            <?php $candidateCount = count($preview['candidates']); ?>
            <div class="card stat-card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-1">Promotion Preview: <?= e($sourceForm) ?></h5>
                    <p class="text-muted small mb-0"><?= e((string) $candidateCount) ?> active students have a recognized Form 1–3 level. Review each outcome before applying.</p>
                </div>
                <?php if ($candidateCount > 0 && (!$runForYear || ($runForYear['status'] ?? '') === 'completed')): ?>
                    <form method="POST" action="<?= url('views/admin/students/promotion/index.php') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="apply">
                        <input type="hidden" name="sourceForm" value="<?= e($sourceForm) ?>">
                        <div class="table-responsive promotion-preview-table-wrap" aria-label="Promotion preview table, horizontally scrollable">
                            <table class="table table-hover align-middle mb-0 promotion-preview-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Admission No.</th>
                                        <th>Current Form</th>
                                        <th>Current Class</th>
                                        <th class="all" data-priority="1">Proposed Class</th>
                                        <th style="min-width: 150px;">Outcome</th>
                                        <th>Proposed Form</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preview['candidates'] as $candidate): ?>
                                        <?php
                                        $studentId = (string) $candidate['id'];
                                        $decision = (string) ($candidateDecisions[$studentId] ?? $candidate['defaultDecision']);
                                        $candidateForm = $decision === 'promote'
                                            ? StudentPromotionService::nextGradeValue((string) $candidate['fromForm'], (int) $candidate['grade'] + 1)
                                            : (string) $candidate['fromForm'];
                                        $candidateClass = $decision === 'promote'
                                            ? StudentPromotionService::nextClassValue((string) $candidate['fromClass'], (int) $candidate['grade'], (int) $candidate['grade'] + 1)
                                            : (string) $candidate['fromClass'];
                                        ?>
                                        <tr>
                                            <td><?= e($candidate['name'] ?: 'Unnamed student') ?></td>
                                            <td><?= e($candidate['admissionNo'] ?: '—') ?></td>
                                            <td><?= e($candidate['fromForm']) ?></td>
                                            <td><?= e($candidate['fromClass'] ?: '—') ?></td>
                                            <td class="promotion-proposed-class"><?= e($candidateClass ?: '—') ?></td>
                                            <td>
                                                <select name="decisions[<?= e($studentId) ?>]" class="form-select form-select-sm promotion-decision" data-grade="<?= e((string) $candidate['grade']) ?>">
                                                    <?php if ((int) $candidate['grade'] < 3): ?>
                                                        <option value="promote" <?= $decision === 'promote' ? 'selected' : '' ?>>Next Form</option>
                                                    <?php else: ?>
                                                        <option value="graduate" <?= $decision === 'graduate' ? 'selected' : '' ?>>Graduate</option>
                                                    <?php endif; ?>
                                                    <option value="repeat" <?= $decision === 'repeat' ? 'selected' : '' ?>>Current Form</option>
                                                </select>
                                            </td>
                                            <td class="promotion-target-form"><?= e($decision === 'graduate' ? '—' : $candidateForm) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="px-3 py-2 small text-muted d-md-none">
                            <i class="bi bi-arrow-left-right me-1"></i>Swipe sideways to review every column.
                        </div>
                        <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <small class="text-muted"><i class="bi bi-shield-check me-1"></i>House and room assignments are not changed.</small>
                            <div class="d-flex align-items-center gap-2">
                                <label class="small text-nowrap" for="batchLimitApply">Batch size</label>
                                <select id="batchLimitApply" name="batchLimit" class="form-select form-select-sm">
                                    <option value="25">25 students</option>
                                    <option value="50">50 students</option>
                                    <option value="100">100 students</option>
                                    <option value="0" selected>Apply all</option>
                                </select>
                                <button class="btn btn-success text-nowrap" type="submit" onclick="return confirm('Apply the reviewed promotion decisions for <?= e($sourceForm) ?>? This form run can only be completed once.');">
                                    <i class="bi bi-check2-circle me-1"></i> Apply Promotion
                                </button>
                            </div>
                        </div>
                    </form>
                <?php elseif ($candidateCount === 0): ?>
                    <div class="card-body text-muted">No active Form 1, Form 2, or Form 3 students were found for promotion.</div>
                <?php endif; ?>
            </div>

            <?php if (!empty($preview['unclassified'])): ?>
                <div class="card stat-card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0">Needs Form / Level Review <span class="badge bg-warning text-dark"><?= e((string) count($preview['unclassified'])) ?></span></h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light"><tr><th>Student</th><th>Admission No.</th><th>Form / Level</th><th>Class</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($preview['unclassified'] as $student): ?>
                                <tr>
                                    <td><?= e(trim((string) (($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?: 'Unnamed student') ?></td>
                                    <td><?= e((string) ($student['admissionNo'] ?? '—')) ?></td>
                                    <td><?= e((string) ($student['form'] ?? $student['level'] ?? '—')) ?></td>
                                    <td><?= e((string) ($student['class'] ?? '—')) ?></td>
                                    <td><?= e((string) ($student['status'] ?? 'active')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($showHistory && $runForYear !== null): ?>
            <div class="card stat-card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Student History: <?= e($sourceForm) ?></h5>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/students/promotion/index.php') ?>">Close History</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Student</th><th>Admission No.</th><th>Form Change</th><th>Class Change</th><th>Outcome</th><th>Record Status</th><th>Applied At</th></tr>
                        </thead>
                        <tbody>
                        <?php if ($promotionHistory === []): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No student history has been recorded for this run yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($promotionHistory as $history): ?>
                                <tr>
                                    <td><?= e((string) ($history['studentName'] ?? 'Unnamed student')) ?></td>
                                    <td><?= e((string) ($history['admissionNo'] ?? '—')) ?></td>
                                    <td><?= e((string) ($history['fromForm'] ?? '—')) ?> → <?= e((string) ($history['toForm'] ?? '—')) ?></td>
                                    <td><?= e((string) ($history['fromClass'] ?? '—')) ?> → <?= e((string) ($history['toClass'] ?? '—')) ?></td>
                                    <td><?= e(ucfirst((string) ($history['action'] ?? 'unknown'))) ?></td>
                                    <td><?= e(ucfirst((string) ($history['status'] ?? 'unknown'))) ?></td>
                                    <td><?= !empty($history['appliedAt']) && strtotime((string) $history['appliedAt']) ? e(date('M j, Y H:i', strtotime((string) $history['appliedAt']))) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="card stat-card border-0 shadow-sm">
            <div class="card-header bg-white py-3"><h5 class="mb-0">Recent Promotion Runs</h5></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Source Form</th><th>Status</th><th>Processed</th><th>Promoted</th><th>Repeated</th><th>Graduated</th><th>Started</th><th>History</th></tr></thead>
                    <tbody>
                    <?php if ($recentRuns === []): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No promotion runs yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentRuns as $run): ?>
                            <tr>
                                <?php
                                $historySourceForm = match ((string) ($run['academicYear'] ?? '')) {
                                    '2001/2002' => 'Form 1',
                                    '2002/2003' => 'Form 2',
                                    '2003/2004' => 'Form 3',
                                    default => (string) ($run['sourceForm'] ?? '—'),
                                };
                                ?>
                                <td><?= e($historySourceForm) ?></td>
                                <td><span class="badge bg-<?= ($run['status'] ?? '') === 'completed' ? 'success' : (in_array(($run['status'] ?? ''), ['partial', 'processing'], true) ? 'warning text-dark' : 'secondary') ?>"><?= e(ucfirst((string) ($run['status'] ?? 'unknown'))) ?></span></td>
                                <td><?= e((string) ($run['processed'] ?? 0)) ?> / <?= e((string) ($run['total'] ?? 0)) ?></td>
                                <td><?= e((string) ($run['promoted'] ?? '—')) ?></td>
                                <td><?= e((string) ($run['repeated'] ?? '—')) ?></td>
                                <td><?= e((string) ($run['graduated'] ?? '—')) ?></td>
                                <td><?= !empty($run['createdAt']) && strtotime((string) $run['createdAt']) ? e(date('M j, Y', strtotime((string) $run['createdAt']))) : '—' ?></td>
                                <td><a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('index.php?route=' . $promotionPageRoute . '&sourceForm=' . urlencode($historySourceForm) . '&history=1')) ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.promotion-decision').forEach(function (select) {
    select.addEventListener('change', function () {
        const row = select.closest('tr');
        const grade = Number(select.dataset.grade);
        const promotes = select.value === 'promote';
        const targetForm = row.querySelector('.promotion-target-form');
        const targetClass = row.querySelector('.promotion-proposed-class');
        const currentForm = row.children[2].textContent.trim();
        const currentClass = row.children[3].textContent.trim();
        const nextNumber = grade + 1;

        if (select.value === 'graduate') {
            targetForm.textContent = '—';
            targetClass.textContent = currentClass || '—';
            return;
        }

        targetForm.textContent = promotes
            ? currentForm.replace(/([1-3])\s*$/, String(nextNumber))
            : currentForm;
        if (promotes) {
            const namedClass = currentClass.match(/^(SHS|Form)(\s*)([1-3])$/i);
            const numberedClass = currentClass.match(/^([1-3])(\s+.+)$/);
            targetClass.textContent = namedClass && Number(namedClass[3]) === grade
                ? namedClass[1] + namedClass[2] + nextNumber
                : (numberedClass && Number(numberedClass[1]) === grade
                    ? nextNumber + numberedClass[2]
                    : currentClass);
        } else {
            targetClass.textContent = currentClass;
        }
    });
});
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
