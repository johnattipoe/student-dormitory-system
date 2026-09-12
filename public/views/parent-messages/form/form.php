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

$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\ParentMessageService;

$currentUser = current_user();
$role = current_role();
$channel = $messageChannel === 'sms' ? 'sms' : 'mail';
$channelLabel = $channel === 'sms' ? 'SMS' : 'Mail';
$channelRoute = $channel === 'sms' ? 'sms/sms.php' : 'mail/mail.php';
$houseId = in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT], true) ? ($currentUser['houseId'] ?? $currentUser['house_id'] ?? null) : null;
$students = array_values(array_filter(StudentService::all($houseId), fn($student) => ($student['status'] ?? 'active') === 'active'));
$messageService = new ParentMessageService();
$selectedId = sanitize($_POST['studentId'] ?? $_GET['studentId'] ?? '');
$selectedStudent = null;
foreach ($students as $student) {
    if ((string) ($student['id'] ?? '') === $selectedId) {
        $selectedStudent = $student;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $selectedStudent
        ? $messageService->send($selectedStudent, sanitize($_POST['subject'] ?? ''), sanitize($_POST['message'] ?? ''), $currentUser, $channel)
        : ['success' => false, 'message' => 'Select a valid student.'];
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) {
        redirect(url('views/parent-messages/create/create.php'));
    } else {
        redirect(url('views/parent-messages/' . $channelRoute . '?studentId=' . urlencode($selectedId)));
    }
}

$pageTitle = 'Send ' . $channelLabel . ' to Parent';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url(ROLE_DASHBOARD[$role] ?? 'index.php')],
    ['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/' . $channelRoute), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="mb-4 d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h5 class="mb-1">Compose <?= e($channelLabel) ?> to Guardian</h5>
                <p class="text-muted mb-0"><?= $channel === 'sms' ? 'Send a concise text message to the registered guardian phone number.' : 'Send an official email notification with a subject and message body.' ?></p>
            </div>
            <a class="btn btn-outline-secondary" href="<?= url('views/parent-messages/create/create.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Message Log
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card stat-card p-4 h-100">
                    <!-- Quick Template Buttons -->
                    <div class="mb-3">
                        <label class="form-label text-muted small text-uppercase fw-semibold">Quick Templates</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="applyTemplate('General Update', 'Dear Parent/Guardian,\n\nWe would like to share a brief update regarding your ward. Please feel free to reach out to the house office if you have any questions.\n\nBest regards,\nHouse Office')">General Update</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="applyTemplate('Health Notice', 'Dear Parent/Guardian,\n\nThis is to notify you that your ward visited the school infirmary today for routine observation/treatment. They are resting comfortably in the dormitory.\n\nBest regards,\nHouse Master')">Health Notice</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="applyTemplate('Exeat / Travel Notice', 'Dear Parent/Guardian,\n\nYour ward has been granted weekend exeat permission and is expected to return to the dormitory by 5:00 PM on Sunday.\n\nBest regards,\nHouse Office')">Exeat Notice</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="applyTemplate('Important Reminder', 'Dear Parent/Guardian,\n\nPlease be reminded regarding the upcoming dormitory inspection and mid-term items collection.\n\nBest regards,\nHouse Office')">Reminder</button>
                        </div>
                    </div>

                    <form method="POST" class="row g-3" id="parentMessageForm">
                        <div class="col-12">
                            <label class="form-label fw-bold d-flex justify-content-between align-items-center mb-1" for="studentSearchInput">
                                <span>Select Student <span class="text-danger">*</span></span>
                                <span class="badge bg-light text-muted border fw-normal" id="studentCountBadge"><?= count($students) ?> students available</span>
                            </label>

                            <!-- Selected Student Pill Badge (Shown when a student is selected) -->
                            <div id="selectedStudentBadgeContainer" class="p-2 mb-2 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 align-items-center justify-content-between <?= $selectedStudent ? 'd-flex' : 'd-none' ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="rounded-circle bg-primary text-white p-1 d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        <i class="bi bi-person-check-fill"></i>
                                    </span>
                                    <div>
                                        <strong id="selectedStudentDisplayName" class="text-dark small d-block">
                                            <?= e(trim(($selectedStudent['firstName'] ?? '') . ' ' . ($selectedStudent['lastName'] ?? ''))) ?> (<?= e((string) ($selectedStudent['admissionNo'] ?? '')) ?>)
                                        </strong>
                                        <span id="selectedStudentDisplayMeta" class="text-muted smaller">
                                            Class: <?= e($selectedStudent['class'] ?? '—') ?><?= !empty($selectedStudent['guardianName']) ? ' &bull; Guardian: ' . e($selectedStudent['guardianName']) : '' ?>
                                        </span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 small" id="changeStudentBtn" title="Search another student">
                                    <i class="bi bi-arrow-repeat me-1"></i>Change Student
                                </button>
                            </div>

                            <!-- Search Input with Floating Pop-up Dropdown -->
                            <div class="position-relative <?= $selectedStudent ? 'd-none' : '' ?>" id="studentSearchWrapper">
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-search text-primary"></i></span>
                                    <input type="text" id="studentSearchInput" class="form-control" placeholder="Type student name, admission number, or class..." autocomplete="off">
                                    <button class="btn btn-outline-secondary" type="button" id="clearStudentSearch" style="display: none;" title="Clear search">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                <!-- Floating Search Pop-up Results Box -->
                                <div id="studentSearchResultsPopup" class="shadow-lg border bg-white rounded-3 mt-1" style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 1060; max-height: 290px; overflow-y: auto;">
                                    <div class="p-2 border-bottom bg-light d-flex justify-content-between align-items-center">
                                        <span class="small fw-semibold text-muted" id="popupHeaderTitle">Matching Students</span>
                                        <span class="smaller text-muted" id="popupCountIndicator">0 found</span>
                                    </div>
                                    <div id="popupResultsList" class="list-group list-group-flush">
                                        <!-- Dynamically injected student search items -->
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden Form Input for Selected Student ID -->
                            <input type="hidden" id="studentId" name="studentId" value="<?= e($selectedId) ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold" for="subject">Subject / Title <span class="text-danger">*</span></label>
                            <input class="form-control" id="subject" name="subject" maxlength="160" value="<?= $channel === 'sms' ? 'Dormitory Notice' : '' ?>" placeholder="e.g. Health Notice, Exeat Update, General Check-in" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold" for="message">Message Body <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="<?= $channel === 'sms' ? '4' : '7' ?>" maxlength="<?= $channel === 'sms' ? '160' : '4000' ?>" placeholder="Type your message to the parent here..." required></textarea>
                            <?php if ($channel === 'sms'): ?>
                                <div class="d-flex justify-content-between form-text mt-1">
                                    <span>Standard SMS limit: 160 characters</span>
                                    <span><span id="smsCharacterCount">0</span> / 160 characters</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                            <a href="<?= url('views/parent-messages/create/create.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" id="submitBtn" class="btn btn-primary" <?= $selectedStudent ? '' : 'disabled' ?>>
                                <i class="bi <?= $channel === 'sms' ? 'bi-chat-dots' : 'bi-envelope' ?> me-1"></i> Send <?= e($channelLabel) ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card stat-card p-4 h-100" id="guardianCard">
                    <h6 class="mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-person-lines-fill text-primary"></i> Guardian Contact Info
                    </h6>
                    <div id="guardianDetailsContainer" class="<?= $selectedStudent ? '' : 'd-none' ?>">
                        <div class="mb-3">
                            <div class="text-muted small">Student Name</div>
                            <strong id="cardStudentName"><?= e(trim(($selectedStudent['firstName'] ?? '') . ' ' . ($selectedStudent['lastName'] ?? ''))) ?></strong>
                            <div class="small text-muted">Admission: <span id="cardStudentAdm"><?= e($selectedStudent['admissionNo'] ?? '—') ?></span> &bull; Class: <span id="cardStudentClass"><?= e($selectedStudent['class'] ?? '—') ?></span></div>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Guardian Name</div>
                            <strong id="cardGuardianName"><?= e((string) ($selectedStudent['guardianName'] ?? 'Not provided')) ?></strong>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Guardian Phone</div>
                            <strong id="cardGuardianPhone"><?= e((string) ($selectedStudent['guardianPhone'] ?? 'Not provided')) ?></strong>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Guardian Email</div>
                            <strong id="cardGuardianEmail"><?= e((string) ($selectedStudent['guardianEmail'] ?? 'Not provided')) ?></strong>
                        </div>
                        <div id="cardChannelAlert" class="alert alert-<?= $channel === 'sms' ? (!empty($selectedStudent['guardianPhone']) ? 'success' : 'danger') : (!empty($selectedStudent['guardianEmail']) ? 'success' : 'danger') ?> small py-2 mb-0">
                            <i class="bi bi-info-circle me-1"></i> <span id="cardChannelAlertText"><?= $channel === 'sms' ? (!empty($selectedStudent['guardianPhone']) ? 'Valid phone on file for SMS.' : 'No phone number found for guardian.') : (!empty($selectedStudent['guardianEmail']) ? 'Valid email on file for delivery.' : 'No email address found for guardian.') ?></span>
                        </div>
                    </div>
                    <div id="guardianEmptyPlaceholder" class="text-center text-muted py-4 <?= $selectedStudent ? 'd-none' : '' ?>">
                        <i class="bi bi-person-bounding-box fs-1 d-block mb-2 text-secondary opacity-75"></i>
                        <p class="mb-0">Search and click on a student to review guardian contact details.</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($channel === 'sms'): ?>
            <div class="row g-4 mt-2">
                <div class="col-12">
                    <div class="card stat-card p-4">
                        <h6 class="mb-2"><i class="bi bi-phone me-1"></i> SMS Message Preview</h6>
                        <div class="border rounded p-3 bg-light" style="min-height:80px; white-space:pre-line" id="smsPreview">Your SMS message will appear here.</div>
                        <div class="small text-muted mt-2"><span id="smsSegmentCount">0</span> SMS segment(s)</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Embedded student dataset for instant popup search -->
<script>
const currentChannel = '<?= e($channel) ?>';
const channelRoute = '<?= e(url('views/parent-messages/' . $channelRoute)) ?>';

const allStudentsData = <?= json_encode(array_map(function ($s) {
    return [
        'id' => (string) ($s['id'] ?? ''),
        'fullName' => trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? '')),
        'admissionNo' => (string) ($s['admissionNo'] ?? ''),
        'class' => (string) ($s['class'] ?? '—'),
        'guardianName' => (string) ($s['guardianName'] ?? ''),
        'guardianPhone' => (string) ($s['guardianPhone'] ?? ''),
        'guardianEmail' => (string) ($s['guardianEmail'] ?? '')
    ];
}, $students), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

function applyTemplate(subj, body) {
    const subjEl = document.getElementById('subject');
    const msgEl = document.getElementById('message');
    if (subjEl) subjEl.value = subj;
    if (msgEl) {
        msgEl.value = body;
        msgEl.dispatchEvent(new Event('input'));
    }
}

function selectStudent(studentObj) {
    const hiddenId = document.getElementById('studentId');
    const searchWrapper = document.getElementById('studentSearchWrapper');
    const badgeContainer = document.getElementById('selectedStudentBadgeContainer');
    const displayName = document.getElementById('selectedStudentDisplayName');
    const displayMeta = document.getElementById('selectedStudentDisplayMeta');
    const popup = document.getElementById('studentSearchResultsPopup');
    const searchInput = document.getElementById('studentSearchInput');

    const detailsBox = document.getElementById('guardianDetailsContainer');
    const emptyBox = document.getElementById('guardianEmptyPlaceholder');
    const submitBtn = document.getElementById('submitBtn');

    if (!studentObj || !studentObj.id) {
        if (hiddenId) hiddenId.value = '';
        if (detailsBox) detailsBox.classList.add('d-none');
        if (emptyBox) emptyBox.classList.remove('d-none');
        if (submitBtn) submitBtn.disabled = true;
        if (badgeContainer) badgeContainer.classList.add('d-none');
        if (searchWrapper) searchWrapper.classList.remove('d-none');
        return;
    }

    if (hiddenId) hiddenId.value = studentObj.id;

    // Display selected student pill
    if (displayName) displayName.textContent = studentObj.fullName + ' (' + studentObj.admissionNo + ')';
    if (displayMeta) displayMeta.innerHTML = 'Class: ' + (studentObj.class || '—') + (studentObj.guardianName ? ' &bull; Guardian: ' + studentObj.guardianName : '');
    if (badgeContainer) {
        badgeContainer.classList.remove('d-none');
        badgeContainer.classList.add('d-flex');
    }
    if (searchWrapper) searchWrapper.classList.add('d-none');
    if (popup) popup.style.display = 'none';
    if (searchInput) searchInput.value = '';

    // Update Guardian Details Card
    const cardName = document.getElementById('cardStudentName');
    const cardAdm = document.getElementById('cardStudentAdm');
    const cardClass = document.getElementById('cardStudentClass');
    const cardGName = document.getElementById('cardGuardianName');
    const cardGPhone = document.getElementById('cardGuardianPhone');
    const cardGEmail = document.getElementById('cardGuardianEmail');
    const alertBox = document.getElementById('cardChannelAlert');
    const alertText = document.getElementById('cardChannelAlertText');

    if (cardName) cardName.textContent = studentObj.fullName || '—';
    if (cardAdm) cardAdm.textContent = studentObj.admissionNo || '—';
    if (cardClass) cardClass.textContent = studentObj.class || '—';
    if (cardGName) cardGName.textContent = studentObj.guardianName || 'Not provided';
    if (cardGPhone) cardGPhone.textContent = studentObj.guardianPhone || 'Not provided';
    if (cardGEmail) cardGEmail.textContent = studentObj.guardianEmail || 'Not provided';

    if (alertBox && alertText) {
        if (currentChannel === 'sms') {
            const hasPhone = studentObj.guardianPhone && studentObj.guardianPhone !== 'Not provided' && studentObj.guardianPhone.trim() !== '';
            alertBox.className = 'alert alert-' + (hasPhone ? 'success' : 'danger') + ' small py-2 mb-0';
            alertText.textContent = hasPhone ? 'Valid phone on file for SMS.' : 'No phone number found for guardian.';
        } else {
            const hasEmail = studentObj.guardianEmail && studentObj.guardianEmail !== 'Not provided' && studentObj.guardianEmail.trim() !== '';
            alertBox.className = 'alert alert-' + (hasEmail ? 'success' : 'danger') + ' small py-2 mb-0';
            alertText.textContent = hasEmail ? 'Valid email on file for delivery.' : 'No email address found for guardian.';
        }
    }

    if (detailsBox) detailsBox.classList.remove('d-none');
    if (emptyBox) emptyBox.classList.add('d-none');
    if (submitBtn) submitBtn.disabled = false;

    // Update browser URL query parameter without full reload
    if (window.history && window.history.replaceState) {
        window.history.replaceState(null, '', channelRoute + '?studentId=' + encodeURIComponent(studentObj.id));
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // SMS Character count & preview
    const message = document.getElementById('message');
    const count = document.getElementById('smsCharacterCount');
    const preview = document.getElementById('smsPreview');
    const segments = document.getElementById('smsSegmentCount');
    if (message) {
        const update = () => {
            const length = message.value.length;
            if (count) {
                count.textContent = length;
                count.classList.toggle('text-danger', length > 160);
            }
            if (preview) {
                preview.textContent = message.value || 'Your SMS message will appear here.';
            }
            if (segments) {
                segments.textContent = Math.max(1, Math.ceil(length / 160));
            }
        };
        message.addEventListener('input', update);
        update();
    }

    // Popup Search Implementation
    const searchInput = document.getElementById('studentSearchInput');
    const clearBtn = document.getElementById('clearStudentSearch');
    const popup = document.getElementById('studentSearchResultsPopup');
    const resultsList = document.getElementById('popupResultsList');
    const countIndicator = document.getElementById('popupCountIndicator');
    const changeBtn = document.getElementById('changeStudentBtn');
    const searchWrapper = document.getElementById('studentSearchWrapper');

    if (changeBtn) {
        changeBtn.addEventListener('click', function() {
            if (searchWrapper) searchWrapper.classList.remove('d-none');
            const badgeContainer = document.getElementById('selectedStudentBadgeContainer');
            if (badgeContainer) badgeContainer.classList.add('d-none');
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
                renderPopupResults('');
            }
        });
    }

    function highlightMatch(text, query) {
        if (!query) return text;
        const reg = new RegExp('(' + query.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&') + ')', 'gi');
        return text.replace(reg, '<mark class="bg-warning p-0 rounded-1">$1</mark>');
    }

    function renderPopupResults(query) {
        const q = (query || '').trim().toLowerCase();
        if (clearBtn) {
            clearBtn.style.display = q !== '' ? 'block' : 'none';
        }

        const matches = allStudentsData.filter(student => {
            if (q === '') return true;
            const searchTarget = (
                student.fullName + ' ' +
                student.admissionNo + ' ' +
                student.class + ' ' +
                student.guardianName + ' ' +
                student.guardianPhone + ' ' +
                student.guardianEmail
            ).toLowerCase();
            return searchTarget.includes(q);
        });

        resultsList.innerHTML = '';
        if (countIndicator) {
            countIndicator.textContent = matches.length + ' found';
        }

        if (matches.length === 0) {
            const emptyItem = document.createElement('div');
            emptyItem.className = 'p-3 text-center text-muted small';
            emptyItem.innerHTML = '<i class="bi bi-search me-1"></i> No matching students found.';
            resultsList.appendChild(emptyItem);
            popup.style.display = 'block';
            return;
        }

        // Render up to 25 items for speed
        const displayMatches = matches.slice(0, 25);
        displayMatches.forEach(st => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action p-2 d-flex justify-content-between align-items-center border-bottom text-start';
            btn.style.cursor = 'pointer';

            const nameHtml = highlightMatch(st.fullName, q);
            const admHtml = highlightMatch(st.admissionNo, q);
            const classHtml = highlightMatch(st.class, q);

            btn.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; flex-shrink: 0;">
                        <i class="bi bi-person-fill small"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark small">${nameHtml} <span class="badge bg-secondary bg-opacity-10 text-secondary border font-monospace ms-1">${admHtml}</span></div>
                        <div class="text-muted smaller">Class: ${classHtml} ${st.guardianName ? ' &bull; Guardian: ' + st.guardianName : ''}</div>
                    </div>
                </div>
                <span class="badge bg-primary rounded-pill px-2 py-1 small">Select <i class="bi bi-chevron-right ms-1"></i></span>
            `;

            btn.addEventListener('click', function() {
                selectStudent(st);
            });

            resultsList.appendChild(btn);
        });

        popup.style.display = 'block';
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            renderPopupResults(this.value);
        });

        searchInput.addEventListener('focus', function() {
            renderPopupResults(this.value);
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            renderPopupResults('');
            searchInput.focus();
        });
    }

    // Close popup on click outside
    document.addEventListener('click', function(e) {
        if (popup && searchWrapper && !searchWrapper.contains(e.target)) {
            popup.style.display = 'none';
        }
    });
});
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

