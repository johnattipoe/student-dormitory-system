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

use App\Services\FirebaseService;

$firebase = FirebaseService::getInstance();
$errors = [];
$backupCollections = [
    COL_USERS => 'Users',
    COL_STUDENTS => 'Students',
    COL_HOUSES => 'Houses',
    COL_ROOMS => 'Rooms',
    COL_BEDS => 'Beds',
    COL_ROOM_ALLOCATIONS => 'Room Allocations',
    COL_ATTENDANCE => 'Attendance',
    COL_INCIDENTS => 'Incidents',
    COL_VISITORS => 'Visitors',
    COL_MEDICAL_RECORDS => 'Medical Records',
    COL_NOTIFICATIONS => 'Notifications',
    'classes' => 'Classes',
    'announcements' => 'Announcements',
    'emergency_contacts' => 'Emergency Contacts',
];

if (isset($_GET['download'])) {
    $payload = [
        'generatedAt' => date(DATE_ATOM),
        'generatedBy' => current_user_id(),
        'application' => 'student-dormitory-system',
        'collections' => [],
    ];

    foreach (array_keys($backupCollections) as $collection) {
        $payload['collections'][$collection] = $firebase->getCollection($collection, [], 1000);
    }

    $filename = 'sds-backup-' . date('Y-m-d-His') . '.json';
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $restoreCollections = array_values(array_intersect((array) ($_POST['restoreCollections'] ?? []), array_keys($backupCollections)));
    $confirmRestore = !empty($_POST['confirmRestore']);

    if (!$confirmRestore) {
        $errors['general'] = 'Confirm that you understand restore can overwrite existing records.';
    } elseif (empty($restoreCollections)) {
        $errors['general'] = 'Select at least one collection to restore.';
    } elseif (empty($_FILES['backupFile']['tmp_name']) || !is_uploaded_file($_FILES['backupFile']['tmp_name'])) {
        $errors['general'] = 'Upload a valid backup JSON file.';
    } else {
        $raw = file_get_contents($_FILES['backupFile']['tmp_name']);
        $backup = json_decode((string) $raw, true);

        if (!is_array($backup) || !isset($backup['collections']) || !is_array($backup['collections'])) {
            $errors['general'] = 'The uploaded file is not a valid system backup.';
        } else {
            try {
                $restored = 0;
                foreach ($restoreCollections as $collection) {
                    $records = $backup['collections'][$collection] ?? [];
                    if (!is_array($records)) {
                        continue;
                    }

                    foreach ($records as $docId => $docData) {
                        if (!is_array($docData)) {
                            continue;
                        }

                        $docKey = is_string($docId) && trim($docId) !== '' ? $docId : null;
                        if (!empty($docData['id']) && is_string($docData['id'])) {
                            $docKey = $docData['id'];
                        }

                        $firebase->addDocument($collection, $docData, $docKey);
                        $restored++;
                    }
                }

                flash('success', "Restore finished. Processed {$restored} records across " . count($restoreCollections) . ' collection(s).');
                redirect(url('views/admin/backup-restore/index.php'));
            } catch (Throwable $e) {
                $errors['general'] = 'Restore failed: ' . $e->getMessage();
            }
        }
    }
}

$collectionCounts = [];
foreach (array_keys($backupCollections) as $collection) {
    try {
        $collectionCounts[$collection] = count($firebase->getCollection($collection, [], 1000));
    } catch (Throwable $e) {
        $collectionCounts[$collection] = null;
    }
}

$pageTitle = 'Backup & Restore';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-database-down', 'label' => 'Backup & Restore', 'href' => url('views/admin/backup-restore/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-cloud-arrow-down-fill text-primary me-2"></i>Database Backup &amp; Disaster Recovery
                </h4>
                <p class="text-muted mb-0">Generate full JSON snapshots of campus collections or restore database tables</p>
            </div>
            <div>
                <a class="btn btn-primary btn-sm" href="<?= url('views/admin/backup-restore/index.php?download=1') ?>">
                    <i class="bi bi-download me-1"></i> Generate Full System Backup
                </a>
            </div>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Restore Card Column -->
            <div class="col-lg-5">
                <div class="card stat-card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-arrow-clockwise me-2 text-warning"></i>Restore Data from Backup</h6>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data" class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Upload Backup JSON File <span class="text-danger">*</span></label>
                                <input type="file" name="backupFile" class="form-control" accept="application/json,.json" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold mb-2">Select Collections to Restore:</label>
                                <div class="row g-2 p-3 bg-light rounded-3 border" style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($backupCollections as $collection => $label): ?>
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input type="checkbox" name="restoreCollections[]" value="<?= e($collection) ?>" class="form-check-input" id="col_<?= e($collection) ?>">
                                                <label class="form-check-label small" for="col_<?= e($collection) ?>"><?= e($label) ?></label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" name="confirmRestore" class="form-check-input" id="confirmRestore" required>
                                    <label class="form-check-label small text-danger fw-semibold" for="confirmRestore">
                                        I confirm that restoring will overwrite matching record keys.
                                    </label>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <button class="btn btn-warning w-100" type="submit">
                                    <i class="bi bi-arrow-clockwise me-1"></i> Start Database Restore
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Collection Status Table Column -->
            <div class="col-lg-7">
                <div class="card stat-card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-database-check me-2 text-success"></i>Live Database Collection Inventory</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data Collection</th>
                                        <th>Collection Identifier</th>
                                        <th>Stored Records</th>
                                        <th class="text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backupCollections as $collection => $label): ?>
                                        <tr>
                                            <td><strong><?= e($label) ?></strong></td>
                                            <td><code class="small text-muted"><?= e($collection) ?></code></td>
                                            <td><span class="fw-semibold text-dark"><?= $collectionCounts[$collection] === null ? '—' : e((string) $collectionCounts[$collection]) ?></span></td>
                                            <td class="text-end">
                                                <span class="badge bg-<?= $collectionCounts[$collection] === null ? 'danger' : 'success' ?>">
                                                    <?= $collectionCounts[$collection] === null ? 'Offline' : 'Connected' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
