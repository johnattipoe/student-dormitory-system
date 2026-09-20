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
use App\Services\FirebaseService;

$galleryUrl = url('index.php?route=' . urlencode('/views/gallery/index.php'));
$galleryDir = APP_ROOT . '/public/uploads/student-gallery';
$fileName = basename((string) ($_GET['file'] ?? $_POST['file'] ?? ''));
$metaPath = $galleryDir . '/' . $fileName . '.json';
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
if ($fileName === '' || !is_array($metadata) || (!is_file($galleryDir . '/' . $fileName) && empty($metadata['storageObject']))) {
    http_response_code(404);
    echo 'Gallery photo not found.';
    exit;
}

$role = current_role();
$currentHouseId = (string) (current_house_id() ?? '');
$photoHouseId = (string) ($metadata['houseId'] ?? '');
$oldStorageObject = (string) ($metadata['storageObject'] ?? '');
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
$newFileName = $fileName;
$newStorageObject = (string) ($metadata['storageObject'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $houseId = sanitize($_POST['houseId'] ?? '');
    $studentId = sanitize($_POST['studentId'] ?? '');
    $replacement = $_FILES['photo'] ?? null;
    if ($role !== ROLE_ADMIN && $currentHouseId !== '' && $houseId !== $currentHouseId) {
        $error = 'You can only assign photos to your own house.';
    } elseif ($houseId === '' || $studentId === '') {
        $error = 'Please select a house and student.';
    } else {
        $student = StudentService::find($studentId) ?? [];
        if (!$student) {
            $error = 'The selected student could not be found.';
        }
    }

    if ($error === null) {
        if ($replacement && ($replacement['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
            $extension = strtolower(pathinfo((string) ($replacement['name'] ?? ''), PATHINFO_EXTENSION));
            $mimeType = strtolower((string) ($replacement['type'] ?? ''));
            if (($replacement['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $error = 'The replacement image could not be uploaded.';
            } elseif (!in_array($extension, $allowedExt, true) || !in_array($mimeType, $allowedMime, true)) {
                $error = 'Only JPG, JPEG, PNG, and WEBP images are allowed.';
            } else {
                $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename((string) $replacement['name']));
                $newFileName = 'student-photo-' . time() . '-' . $safeName;
                $newStorageObject = 'student-gallery/' . $newFileName;
                try {
                    $firebase->uploadStorageFile((string) $replacement['tmp_name'], $newStorageObject, $mimeType);
                } catch (Throwable $e) {
                    $error = 'The replacement image could not be saved.';
                    error_log('Gallery Storage replacement failed: ' . $e->getMessage());
                }
            }
        }
    }

    if ($error === null) {
        $metadata['houseId'] = $houseId;
        $metadata['houseName'] = $houses[$houseId] ?? 'House';
        $metadata['studentId'] = $studentId;
        $metadata['studentName'] = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: 'Student';
        $metadata['file'] = $newFileName;
        $metadata['storageObject'] = $newStorageObject;
        $metadata['originalName'] = $replacement && ($replacement['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            ? (string) $replacement['name']
            : ($metadata['originalName'] ?? $fileName);
        $metadata['updatedAt'] = date('Y-m-d H:i:s');
        if ($metadataDocumentId !== '') {
            $firebase->updateDocument(COL_GALLERY_PHOTOS, $metadataDocumentId, $metadata);
        }
        file_put_contents($metaPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($newFileName !== $fileName && $newStorageObject !== '' && is_file($galleryDir . '/' . $fileName)) {
            unlink($galleryDir . '/' . $fileName);
        }
        if ($oldStorageObject !== '' && $oldStorageObject !== $newStorageObject) {
            $firebase->deleteStorageFile($oldStorageObject);
        }
        if ($newFileName !== $fileName) {
            $newMetaPath = $galleryDir . '/' . $newFileName . '.json';
            rename($metaPath, $newMetaPath);
        }
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
                    <?php $editImageUrl = !empty($metadata['storageObject']) ? $firebase->storageUrl((string) $metadata['storageObject']) : url('uploads/student-gallery/' . rawurlencode($fileName)); ?>
                    <div class="col-md-5 text-center"><img src="<?= e($editImageUrl) ?>" alt="Gallery photo" class="img-fluid rounded" style="max-height: 360px; object-fit: contain;"></div>
                    <div class="col-md-7">
                        <form method="POST" enctype="multipart/form-data" action="<?= e(url('index.php?route=' . urlencode('/views/gallery/edit/edit.php') . '&file=' . urlencode($fileName))) ?>">
                            <input type="hidden" name="file" value="<?= e($fileName) ?>">
                            <div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>Update the assignment or optionally replace the image. Leave the image field empty to keep the current photo.</div>
                            <label for="gallery-edit-house" class="form-label fw-semibold">House</label>
                            <select id="gallery-edit-house" name="houseId" class="form-select mb-3" required>
                                <?php foreach ($houses as $houseId => $houseName): ?>
                                    <?php if (($role === ROLE_HOUSE_MASTER || $role === ROLE_HOUSE_MISTRESS) && $currentHouseId !== '' && $houseId !== $currentHouseId) continue; ?>
                                    <option value="<?= e($houseId) ?>" <?= $photoHouseId === $houseId ? 'selected' : '' ?>><?= e($houseName) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="gallery-edit-student" class="form-label fw-semibold">Student</label>
                            <select id="gallery-edit-student" name="studentId" class="form-select mb-4" required>
                                <?php foreach ($students as $student): ?>
                                    <?php $studentId = (string) ($student['id'] ?? ''); $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')); ?>
                                    <?php $studentHouseId = (string) ($student['houseId'] ?? ''); ?>
                                    <?php if ($photoHouseId !== '' && $studentHouseId !== '' && $studentHouseId !== $photoHouseId && (string) ($metadata['studentId'] ?? '') !== $studentId) continue; ?>
                                    <option value="<?= e($studentId) ?>" <?= (string) ($metadata['studentId'] ?? '') === $studentId ? 'selected' : '' ?>><?= e($studentName ?: $studentId) ?><?= !empty($student['admissionNo']) ? ' (' . e($student['admissionNo']) . ')' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text mb-4">The current student remains available even if their house assignment differs.</div>
                            <label for="gallery-edit-photo" class="form-label fw-semibold">Replace Image <span class="text-muted fw-normal">(optional)</span></label>
                            <input id="gallery-edit-photo" type="file" name="photo" class="form-control mb-1" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div class="form-text mb-4">JPG, JPEG, PNG, or WEBP.</div>
                            <div class="d-flex justify-content-end gap-2"><a href="<?= e($galleryUrl) ?>" class="btn btn-outline-secondary">Cancel</a><button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save Changes</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
