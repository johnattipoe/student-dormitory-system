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

use App\Services\FirebaseService;

$galleryUrl = url('index.php?route=' . urlencode('/views/gallery/index.php'));
$galleryDir = APP_ROOT . '/public/uploads/student-gallery';
$fileName = basename((string) ($_GET['file'] ?? $_POST['file'] ?? ''));
$imagePath = $galleryDir . '/' . $fileName;
$metaPath = $imagePath . '.json';
$metadata = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;
$firebase = FirebaseService::getInstance();
$metadataDocumentId = is_array($metadata) ? (string) ($metadata['id'] ?? '') : '';
if (str_starts_with($metadataDocumentId, 'G-')) {
    $metadataDocumentId = '';
}
if (!is_array($metadata)) {
    try {
        $records = $firebase->getCollection(COL_GALLERY_PHOTOS, [['file', '=', $fileName]], 1);
        $metadata = $records[0] ?? null;
        $metadataDocumentId = is_array($metadata) ? (string) ($metadata['id'] ?? '') : '';
        if (str_starts_with($metadataDocumentId, 'G-')) {
            $metadataDocumentId = '';
        }
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
    echo 'You can only delete photos from your own house.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($metadata['storageObject'])) {
        $firebase->deleteStorageFile((string) $metadata['storageObject']);
    }
    if (is_file($imagePath)) unlink($imagePath);
    if (is_file($metaPath)) unlink($metaPath);
    if ($metadataDocumentId !== '') {
        $firebase->deleteDocument(COL_GALLERY_PHOTOS, $metadataDocumentId);
    }
    flash('success', 'Student photo deleted successfully.');
    redirect($galleryUrl);
}

$pageTitle = 'Delete Gallery Photo';
$navItems = [
    ['icon' => 'bi-images', 'label' => 'Gallery', 'href' => $galleryUrl, 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card border-0 shadow-sm mx-auto" style="max-width: 640px;">
            <div class="card-body p-4">
                <h4 class="fw-bold text-danger"><i class="bi bi-trash3 me-2"></i>Delete Student Photo</h4>
                <p>Are you sure you want to permanently delete the photo for <strong><?= e($metadata['studentName'] ?? 'Student') ?></strong>?</p>
                <?php $deleteImageUrl = !empty($metadata['storageObject']) ? $firebase->storageUrl((string) $metadata['storageObject']) : url('uploads/student-gallery/' . rawurlencode($fileName)); ?>
                <img src="<?= e($deleteImageUrl) ?>" alt="<?= e($metadata['studentName'] ?? 'Student photo') ?>" class="img-fluid rounded mb-4" style="max-height: 360px; object-fit: contain;">
                <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/gallery/delete/delete.php') . '&file=' . urlencode($fileName))) ?>" class="d-flex gap-2 justify-content-end">
                    <input type="hidden" name="file" value="<?= e($fileName) ?>">
                    <a href="<?= e($galleryUrl) ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash3 me-1"></i>Delete Photo</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
