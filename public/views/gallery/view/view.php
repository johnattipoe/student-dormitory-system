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

use App\Services\FirebaseService;

$galleryDir = APP_ROOT . '/public/uploads/student-gallery';
$fileName = basename((string) ($_GET['file'] ?? ''));
$imagePath = $galleryDir . '/' . $fileName;
$metaPath = $imagePath . '.json';
$metadata = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;
$firebase = FirebaseService::getInstance();
if (!is_array($metadata)) {
    try {
        $records = $firebase->getCollection(COL_GALLERY_PHOTOS, [['file', '=', $fileName]], 1);
        $metadata = $records[0] ?? null;
    } catch (Throwable $e) {
        $metadata = null;
    }
}

if ($fileName === '' || !is_array($metadata) || (!is_file($imagePath) && empty($metadata['storageObject']))) {
    http_response_code(404);
    echo 'Gallery photo not found.';
    exit;
}

$role = current_role();
$currentHouseId = (string) (current_house_id() ?? '');
$photoHouseId = (string) ($metadata['houseId'] ?? '');
if (($role === ROLE_HOUSE_MASTER || $role === ROLE_HOUSE_MISTRESS) && $currentHouseId !== '' && $photoHouseId !== $currentHouseId) {
    http_response_code(403);
    echo 'You do not have access to this gallery photo.';
    exit;
}

$pageTitle = 'View Gallery Photo';
$navItems = [
    ['icon' => 'bi-images', 'label' => 'Gallery', 'href' => url('index.php?route=' . urlencode('/views/gallery/index.php')), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1 fw-bold"><i class="bi bi-image text-info me-2"></i>View Student Photo</h4>
                <p class="text-muted mb-0"><?= e($metadata['studentName'] ?? 'Student') ?></p>
            </div>
            <a href="<?= url('index.php?route=' . urlencode('/views/gallery/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Gallery</a>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center p-4">
                <?php $viewImageUrl = !empty($metadata['storageObject']) ? $firebase->storageUrl((string) $metadata['storageObject']) : url('uploads/student-gallery/' . rawurlencode($fileName)); ?>
                <img src="<?= e($viewImageUrl) ?>" alt="<?= e($metadata['studentName'] ?? 'Student photo') ?>" class="img-fluid rounded" style="max-height: 70vh; object-fit: contain;">
                <dl class="row text-start mt-4 mb-0 mx-auto" style="max-width: 520px;">
                    <dt class="col-sm-4">Student</dt><dd class="col-sm-8"><?= e($metadata['studentName'] ?? 'Student') ?></dd>
                    <dt class="col-sm-4">House</dt><dd class="col-sm-8"><?= e($metadata['houseName'] ?? 'House') ?></dd>
                    <dt class="col-sm-4">Uploaded</dt><dd class="col-sm-8"><?= e($metadata['uploadedAt'] ?? '') ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
