<?php
// Ensure bootstrap is loaded (safe at any view nesting depth)
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) { require $dir . '/bootstrap.php'; break; }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$firebase = FirebaseService::getInstance();
$user = current_user() ?? [];
$userId = (string) ($user['uid'] ?? $user['id'] ?? '');
$userName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: 'Senior Houseparent';

// Fetch all contacts
$allContacts = $firebase->getCollection('emergency_contacts', [], 500);

if (empty($allContacts)) {
    $seed = [
        ['name' => 'Campus Clinic / Infirmary', 'roleTitle' => 'School Nurse Desk', 'phone' => '+233 24 000 0001', 'email' => 'clinic@dormitory.edu', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'Campus Security Main Gate', 'roleTitle' => 'Security Office', 'phone' => '+233 24 000 0002', 'email' => 'security@dormitory.edu', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'Local Police Dispatch', 'roleTitle' => 'Emergency Police Command', 'phone' => '191 / 112', 'email' => '', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'National Fire & Rescue Service', 'roleTitle' => 'Fire Operations', 'phone' => '192 / 112', 'email' => '', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'District Hospital Emergency Unit', 'roleTitle' => 'Hospital Triage Desk', 'phone' => '+233 20 000 0004', 'email' => 'hospital@district.gov', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'Facilities & Electrical Maintenance', 'roleTitle' => 'Emergency Repairs & Power', 'phone' => '+233 24 000 0003', 'email' => 'facilities@dormitory.edu', 'priority' => 'high', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
    ];
    foreach ($seed as $c) {
        $firebase->addDocument('emergency_contacts', $c);
    }
    $allContacts = $firebase->getCollection('emergency_contacts', [], 500);
}

$contacts = array_values(array_filter($allContacts, static fn($c) => ($c['status'] ?? 'active') === 'active'));

usort($contacts, static function ($a, $b) {
    $weights = ['critical' => 0, 'high' => 1, 'normal' => 2];
    $wA = $weights[$a['priority'] ?? 'normal'] ?? 3;
    $wB = $weights[$b['priority'] ?? 'normal'] ?? 3;
    return $wA <=> $wB;
});

// Fetch emergency incident call logs
$incidents = $firebase->getCollection('emergency_incidents', [], 50);
usort($incidents, static fn($a, $b) => strcmp((string) ($b['triggeredAt'] ?? ''), (string) ($a['triggeredAt'] ?? '')));

// Metrics
$totalContacts = count($contacts);
$criticalContacts = count(array_filter($contacts, fn($c) => ($c['priority'] ?? '') === 'critical'));
$todayDate = date('Y-m-d');
$todayCalls = count(array_filter($incidents, fn($i) => str_starts_with((string)($i['triggeredAt'] ?? ''), $todayDate)));

$pageTitle = 'Emergency Alerts & Contacts';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-telephone-inbound', 'label' => 'Emergency Alerts', 'href' => url('views/senior-houseparent/emergency-alerts/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <!-- Header & Action Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1 text-danger"><i class="bi bi-shield-exclamation me-2"></i> Emergency Command Center & Contacts</h5>
                <p class="text-muted mb-0">Emergency directory, broadcast dispatch, and incident call logging for dormitory security.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#seniorBroadcastEmergencyModal">
                    <i class="bi bi-broadcast me-1"></i> Broadcast Emergency Alert
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#seniorAddEmergencyContactModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Contact
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#seniorLogEmergencyCallModal">
                    <i class="bi bi-journal-plus me-1"></i> Log Emergency Call
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/emergency-alerts/export/export.php') ?>">
                    <i class="bi bi-download me-1"></i> Export Directory & Logs
                </a>
            </div>
        </div>

        <!-- KPI Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-danger">
                    <small class="text-muted">Critical First Responders</small>
                    <strong class="fs-2 text-danger my-1"><?= e((string) $criticalContacts) ?></strong>
                    <span class="small text-muted">Direct emergency lines</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-primary">
                    <small class="text-muted">Total Directory Contacts</small>
                    <strong class="fs-2 text-primary my-1"><?= e((string) $totalContacts) ?></strong>
                    <span class="small text-muted">Active emergency services</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-warning">
                    <small class="text-muted">Emergency Calls Logged Today</small>
                    <strong class="fs-2 text-warning my-1"><?= e((string) $todayCalls) ?></strong>
                    <span class="small text-muted"><?= e(date('F d, Y')) ?></span>
                </div>
            </div>
        </div>

        <!-- Emergency Directory Cards -->
        <h6 class="fw-bold mb-3"><i class="bi bi-telephone-fill me-2 text-danger"></i> Quick-Dial First Responders & Directory</h6>
        <div class="row g-3 mb-4">
            <?php foreach ($contacts as $contact): ?>
                <?php 
                    $priority = $contact['priority'] ?? 'normal';
                    $badgeClass = match($priority) {
                        'critical' => 'bg-danger',
                        'high' => 'bg-warning text-dark',
                        default => 'bg-secondary',
                    };
                    $contactId = (string) ($contact['id'] ?? '');
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card p-3 h-100 border-start border-4 <?= $priority === 'critical' ? 'border-danger' : ($priority === 'high' ? 'border-warning' : 'border-primary') ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-bold mb-0"><?= e($contact['name'] ?? 'Contact') ?></h6>
                                <small class="text-muted"><?= e($contact['roleTitle'] ?? 'Emergency Service') ?></small>
                            </div>
                            <span class="badge <?= e($badgeClass) ?>"><?= e(ucfirst($priority)) ?></span>
                        </div>
                        <div class="my-2">
                            <a href="tel:<?= e($contact['phone'] ?? '') ?>" class="btn btn-sm btn-outline-danger w-100 fw-bold fs-6">
                                <i class="bi bi-telephone-fill me-1"></i> <?= e($contact['phone'] ?? '—') ?>
                            </a>
                        </div>
                        <?php if (!empty($contact['email'])): ?>
                            <div class="small text-muted mb-2 text-truncate">
                                <i class="bi bi-envelope me-1"></i> <?= e($contact['email']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#seniorLogEmergencyCallModal" data-contact-name="<?= e($contact['name'] ?? '') ?>" data-contact-phone="<?= e($contact['phone'] ?? '') ?>" title="Log Emergency Call">
                                <i class="bi bi-journal-plus me-1"></i> Log Call
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#seniorContactEdit-<?= e($contactId) ?>" title="Edit Contact">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Incident & Call Log Table -->
        <div class="card stat-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i> Recent Emergency Dispatch & Call Logs</h6>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#seniorLogEmergencyCallModal">
                    <i class="bi bi-plus-circle me-1"></i> Record New Call
                </button>
            </div>

            <?php if (empty($incidents)): ?>
                <p class="text-muted mb-0">No emergency incidents or calls recorded.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Contact Contacted</th>
                                <th>Call Summary & Incident Notes</th>
                                <th>Logged By</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidents as $inc): ?>
                                <?php $incId = (string) ($inc['id'] ?? ''); ?>
                                <tr>
                                    <td class="text-nowrap small text-muted">
                                        <?= !empty($inc['triggeredAt']) ? e(date('M d, Y H:i', strtotime((string)$inc['triggeredAt']))) : '—' ?>
                                    </td>
                                    <td><strong><?= e($inc['contactName'] ?? '—') ?></strong></td>
                                    <td><?= e(mb_strimwidth((string)($inc['notes'] ?? '—'), 0, 80, '...')) ?></td>
                                    <td><?= e($inc['triggeredByName'] ?? 'Staff') ?></td>
                                    <td class="text-end">
                                        <?php if ($incId !== ''): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#seniorEmergencyLogView-<?= e($incId) ?>">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php foreach ($contacts as $contact): ?>
    <?php $modalContactId = (string) ($contact['id'] ?? ''); ?>
    <div class="modal fade" id="seniorContactEdit-<?= e($modalContactId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form method="POST" action="<?= url('views/senior-houseparent/emergency-alerts/contacts/edit/edit.php?id=' . urlencode($modalContactId)) ?>"><div class="modal-header"><h5 class="modal-title">Edit Emergency Contact</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">Name</label><input name="name" class="form-control mb-3" value="<?= e($contact['name'] ?? '') ?>" required><label class="form-label">Role / Title</label><input name="roleTitle" class="form-control mb-3" value="<?= e($contact['roleTitle'] ?? '') ?>"><label class="form-label">Phone</label><input name="phone" class="form-control mb-3" value="<?= e($contact['phone'] ?? '') ?>" required><label class="form-label">Email</label><input name="email" type="email" class="form-control mb-3" value="<?= e($contact['email'] ?? '') ?>"><label class="form-label">Priority</label><select name="priority" class="form-select"><option value="critical" <?= ($contact['priority'] ?? '') === 'critical' ? 'selected' : '' ?>>Critical</option><option value="high" <?= ($contact['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option><option value="normal" <?= ($contact['priority'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>Normal</option></select></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div></form></div></div></div>
<?php endforeach; ?>
<?php foreach ($incidents as $inc): ?>
    <?php $modalEmergencyLogId = (string) ($inc['id'] ?? ''); ?>
    <div class="modal fade" id="seniorEmergencyLogView-<?= e($modalEmergencyLogId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Emergency Call Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-5">Contact</dt><dd class="col-sm-7"><?= e($inc['contactName'] ?? '—') ?></dd><dt class="col-sm-5">Phone</dt><dd class="col-sm-7"><?= e($inc['contactPhone'] ?? '—') ?></dd><dt class="col-sm-5">Time</dt><dd class="col-sm-7"><?= e($inc['triggeredAt'] ?? '—') ?></dd><dt class="col-sm-5">Logged By</dt><dd class="col-sm-7"><?= e($inc['triggeredByName'] ?? 'Staff') ?></dd><dt class="col-sm-5">Notes</dt><dd class="col-sm-7"><?= e($inc['notes'] ?? '—') ?></dd></dl></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
<?php endforeach; ?>
<div class="modal fade" id="seniorBroadcastEmergencyModal" tabindex="-1" aria-hidden="true" data-open-on-load="<?= !empty($_GET['openBroadcastModal']) ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
        <form method="POST" action="<?= url('views/senior-houseparent/emergency-alerts/broadcast/broadcast.php') ?>" onsubmit="return confirm('Are you sure you want to broadcast this urgent emergency alert across the dormitory system?')">
            <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-broadcast me-2"></i>Broadcast Emergency Alert</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-8"><label class="form-label">Alert Title</label><input name="title" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Emergency Category</label><select name="emergencyType" class="form-select"><option value="fire">Fire &amp; Evacuation</option><option value="medical">Medical Emergency</option><option value="security">Security &amp; Lockdown</option><option value="weather">Severe Weather</option><option value="power">Utility / Power Outage</option></select></div>
                <div class="col-md-6"><label class="form-label">Broadcast Audience</label><select name="audience" class="form-select"><option value="all">Entire Dormitory</option><option value="house_master">House Masters &amp; Mistresses Only</option></select></div>
                <div class="col-md-6"><label class="form-label">Severity Level</label><select name="severity" class="form-select"><option value="critical">Critical</option><option value="high">High Alert</option></select></div>
                <div class="col-12"><label class="form-label">Emergency Instructions &amp; Protocol Details</label><textarea name="message" class="form-control" rows="6" required></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger"><i class="bi bi-broadcast me-1"></i>Trigger Broadcast</button></div>
        </form>
    </div></div>
</div>
<div class="modal fade" id="seniorAddEmergencyContactModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
    <form method="POST" action="<?= url('views/senior-houseparent/emergency-alerts/contacts/add/add.php') ?>">
        <div class="modal-header"><h5 class="modal-title">Add Emergency Contact / Responder</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Organization / Service Name</label><input name="name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Department / Role Title</label><input name="roleTitle" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Emergency Hotline / Phone</label><input name="phone" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Alternative Phone</label><input name="altPhone" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Emergency Email</label><input name="email" type="email" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Priority Classification</label><select name="priority" class="form-select"><option value="critical">Critical</option><option value="high">High Priority</option><option value="normal" selected>Normal Support Service</option></select></div>
            <div class="col-12"><label class="form-label">Location / Station Address</label><input name="location" class="form-control"></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Save Emergency Contact</button></div>
    </form>
</div></div></div>
<div class="modal fade" id="seniorLogEmergencyCallModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
    <form method="POST" action="<?= url('views/senior-houseparent/emergency-alerts/log/create.php') ?>">
        <div class="modal-header"><h5 class="modal-title">Log Emergency Call &amp; Incident Response</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Quick Select Agency / Contact</label><select class="form-select" id="seniorEmergencyContactSelect"><option value="">Select from Directory or type below</option><?php foreach ($contacts as $contactOption): ?><option value="<?= e($contactOption['phone'] ?? '') ?>" data-name="<?= e($contactOption['name'] ?? '') ?>"><?= e($contactOption['name'] ?? 'Contact') ?> (<?= e($contactOption['phone'] ?? '') ?>)</option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Involved Student (Optional)</label><select name="studentId" class="form-select" data-house-student-options="<?= e(url('views/house-student-options.php')) ?>" data-placeholder="None / Dormitory-Wide Incident"><option value="">None / Dormitory-Wide Incident</option></select></div>
            <div class="col-md-6"><label class="form-label">Agency / Responder Contacted</label><input id="seniorEmergencyContactName" name="contactName" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Phone Number Called</label><input id="seniorEmergencyContactPhone" name="contactPhone" class="form-control"></div>
            <div class="col-12"><label class="form-label">Incident Reason / Emergency Type</label><input name="reason" class="form-control"></div>
            <div class="col-12"><label class="form-label">Call Summary &amp; Discussion</label><textarea name="notes" class="form-control" rows="4" required></textarea></div>
            <div class="col-12"><label class="form-label">Immediate Action Taken on the Ground</label><textarea name="actionTaken" class="form-control" rows="2"></textarea></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-journal-check me-1"></i>Save Emergency Log</button></div>
    </form>
</div></div></div>
<script>
document.getElementById('seniorEmergencyContactSelect').addEventListener('change', function () {
    const option = this.options[this.selectedIndex];
    if (this.value) {
        document.getElementById('seniorEmergencyContactPhone').value = this.value;
        document.getElementById('seniorEmergencyContactName').value = option.getAttribute('data-name') || '';
    }
});
document.querySelectorAll('[data-bs-target="#seniorLogEmergencyCallModal"][data-contact-name]').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('seniorEmergencyContactName').value = this.getAttribute('data-contact-name') || '';
        document.getElementById('seniorEmergencyContactPhone').value = this.getAttribute('data-contact-phone') || '';
    });
});
document.querySelectorAll('.modal[data-open-on-load="true"]').forEach(function (modalElement) {
    bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
