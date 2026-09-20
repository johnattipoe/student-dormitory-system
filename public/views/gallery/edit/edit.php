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

$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\HouseService;
use App\Services\StudentService;

$galleryUrl = url('index.php?route=' . urlencode('/views/gallery/index.php'));
$galleryDir = APP_ROOT . '/public/uploads/student-gallery';
$fileName = basename((string) ($_GET['file'] ?? $_POST['file'] ?? ''));
$metaPath = $galleryDir . '/' . $fileName . '.json';
$metadata = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;
if ($fileName === '' || !is_array($metadata) || !is_file($galleryDir . '/' . $fileName)) {
    http_response_code(404);
    echo 'Gallery photo not found.';
    exit;
}

$role = current_role();
$currentHouseId = (string) (current_house_id() ?? '');
$photoHouseId = (string) ($metadata['houseId'] ?? '');
if (($role === ROLE_HOUSE_MASTER || $role === ROLE_HOUSE_MISTRESS) && $currentHouseId !== '' && $photoHouseId !== $currentHouseId) {
    http_response_code(403);
    echo 'You can only edit photos from your own house.';
    exit;
}

$houses = [];
foreach (HouseService::all() as $house) {
    if (!empty($house['id'])) $houses[(string) $house['id']] = (string) ($house['name'] ?? $house['id']);
}
$students = StudentService::all($role === ROLE_ADMIN ? null : ($currentHouseId ?: null));
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $houseId = sanitize($_POST['houseId'] ?? '');
    $studentId = sanitize($_POST['studentId'] ?? '');
    if ($role !== ROLE_ADMIN && $currentHouseId !== '' && $houseId !== $currentHouseId) {
        $error = 'You can only assign photos to your own house.';
    } elseif ($houseId === '' || $studentId === '') {
        $error = 'Please select a house and student.';
    } else {
        $student = StudentService::find($studentId) ?? [];
        $metadata['houseId'] = $houseId;
        $metadata['houseName'] = $houses[$houseId] ?? 'House';
        $metadata['studentId'] = $studentId;
        $metadata['studentName'] = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: 'Student';
        file_put_contents($metaPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        flash('success', 'Gallery photo details updated successfully.');
        redirect($galleryUrl);
    }
}

$pageTitle = 'Edit Gallery Photo';
$navItems = [
    ['icon' => 'bi-images', 'label' => 'Gallery', 'href' => $galleryUrl, 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h4 class="mb-0 fw-bold"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Gallery Photo</h4>
            <a href="<?= e($galleryUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Gallery</a>
        </div>
        <?php if ($error !== null): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-5 text-center"><img src="<?= e(url('uploads/student-gallery/' . rawurlencode($fileName))) ?>" alt="Gallery photo" class="img-fluid rounded" style="max-height: 360px; object-fit: contain;"></div>
                    <div class="col-md-7">
                        <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/gallery/edit/edit.php') . '&file=' . urlencode($fileName))) ?>">
                            <input type="hidden" name="file" value="<?= e($fileName) ?>">
                            <label class="form-label fw-semibold">House</label>
                            <select name="houseId" class="form-select mb-3" required>
                                <?php foreach ($houses as $houseId => $houseName): ?>
                                    <?php if (($role === ROLE_HOUSE_MASTER || $role === ROLE_HOUSE_MISTRESS) && $currentHouseId !== '' && $houseId !== $currentHouseId) continue; ?>
                                    <option value="<?= e($houseId) ?>" <?= $photoHouseId === $houseId ? 'selected' : '' ?>><?= e($houseName) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="form-label fw-semibold">Student</label>
                            <select name="studentId" class="form-select mb-4" required>
                                <?php foreach ($students as $student): ?>
                                    <?php $studentId = (string) ($student['id'] ?? ''); $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')); ?>
                                    <option value="<?= e($studentId) ?>" <?= (string) ($metadata['studentId'] ?? '') === $studentId ? 'selected' : '' ?>><?= e($studentName ?: $studentId) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="d-flex justify-content-end gap-2"><a href="<?= e($galleryUrl) ?>" class="btn btn-outline-secondary">Cancel</a><button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save Changes</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
