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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\BedService;
use App\Services\HouseService;
use App\Services\RoomService;
use PhpOffice\PhpSpreadsheet\IOFactory;

$rooms = RoomService::all();
$houses = [];
foreach (HouseService::all() as $house) $houses[(string) ($house['id'] ?? '')] = $house['name'] ?? $house['id'] ?? '';
$errors = [];
$success = 0;

if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bed-import-template.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['BedNumber', 'RoomId', 'Status']);
    fputcsv($output, ['Bed 1', 'ROOM_ID', 'available']);
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['beds_file'])) {
    $file = $_FILES['beds_file'];
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (validate_uploaded_file($file, ['csv', 'xlsx']) !== null) {
        $errors[] = 'Upload a valid CSV or XLSX file.';
    } else {
        try {
            $rows = IOFactory::load($file['tmp_name'])->getActiveSheet()->toArray(null, true, true, false);
            $header = array_map(static fn ($value) => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $value)), array_shift($rows) ?: []);
            $map = [];
            foreach ($header as $index => $name) $map[$name] = $index;
            $defaultRoomId = sanitize($_POST['defaultRoomId'] ?? '');
            foreach (array_slice($rows, 0, (int) (app_config()['import_max_records'] ?? 1000)) as $row) {
                $value = static fn ($name, $fallback = -1) => $row[$map[strtolower(preg_replace('/[^a-z0-9]/i', '', $name))] ?? $fallback] ?? '';
                $result = BedService::create([
                    'bedNumber' => sanitize($value('bednumber', 0)),
                    'roomId' => sanitize($value('roomid', 1) ?: $defaultRoomId),
                    'status' => sanitize($value('status', 2) ?: 'available'),
                ]);
                if (!empty($result['success'])) $success++;
                elseif (($result['message'] ?? '') !== '') $errors[] = $result['message'];
            }
            if ($success > 0) {
                flash('success', "Imported {$success} bed(s) successfully.");
                redirect(url('views/admin/beds/index/index.php'));
            }
        } catch (Throwable $e) {
            $errors[] = 'Import failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Import Beds';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-grid-3x3-gap', 'label' => 'Beds', 'href' => url('views/admin/beds/index/index.php')],
    ['icon' => 'bi-upload', 'label' => 'Import Beds', 'href' => url('views/admin/beds/import/import.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width: 760px;">
            <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Import Beds</h5><a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/beds/index/index.php') ?>">Back</a></div>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
            <form method="POST" enctype="multipart/form-data">
                <label class="form-label">CSV or XLSX file</label>
                <input type="file" name="beds_file" class="form-control mb-3" accept=".csv,.xlsx" required>
                <label class="form-label">Default room</label>
                <select name="defaultRoomId" class="form-select mb-3"><option value="">Use RoomId column</option><?php foreach ($rooms as $room): ?><option value="<?= e((string) ($room['id'] ?? '')) ?>"><?= e($room['roomNumber'] ?? '-') ?> - <?= e($houses[(string) ($room['houseId'] ?? '')] ?? '-') ?></option><?php endforeach; ?></select>
                <p class="small text-muted">Headers: BedNumber, RoomId, Status. Maximum 1,000 rows.</p>
                <button class="btn btn-primary">Upload and import</button>
                <a class="btn btn-outline-secondary" href="<?= url('views/admin/beds/import/import.php?download_template=1') ?>">Download template</a>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
