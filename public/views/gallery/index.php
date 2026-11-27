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

$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\HouseService;
use App\Services\StudentService;
use App\Services\FirebaseService;

$role = current_role();
$firebase = FirebaseService::getInstance();
$currentUser = current_user() ?? [];
$currentHouseId = current_house_id();
$isHouseScopedRole = in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true);
$houseScopeUnavailable = $isHouseScopedRole && empty($currentHouseId);

$houses = [];
if ($isHouseScopedRole) {
    $house = $currentHouseId ? HouseService::find((string) $currentHouseId) : null;
    if (!empty($house['id'])) {
        $houses[(string) $house['id']] = (string) ($house['name'] ?? $house['id']);
    }
} else {
    foreach (HouseService::all() as $house) {
        if (!empty($house['id'])) {
            $houses[(string) $house['id']] = (string) ($house['name'] ?? $house['id']);
        }
    }
}

$selectedHouseId = sanitize($_GET['houseId'] ?? ($_POST['houseId'] ?? ''));
$selectedStudentId = sanitize($_GET['studentId'] ?? ($_POST['studentId'] ?? ''));
$searchTerm = sanitize($_GET['q'] ?? ($_POST['q'] ?? ''));
$searchTermNormalized = strtolower(trim((string) $searchTerm));
if ($isHouseScopedRole) {
    $selectedHouseId = (string) ($currentHouseId ?? '');
    if ($selectedStudentId !== '' && !$houseScopeUnavailable) {
        $selectedStudent = StudentService::find($selectedStudentId) ?? [];
        if ((string) ($selectedStudent['houseId'] ?? '') !== $selectedHouseId) {
            $selectedStudentId = '';
        }
    } elseif ($houseScopeUnavailable) {
        $selectedStudentId = '';
    }
}
if ($selectedStudentId !== '' && $selectedHouseId === '') {
    $selectedStudent = StudentService::find($selectedStudentId) ?? [];
    $selectedHouseId = (string) ($selectedStudent['houseId'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_student_photo'])) {
    $houseId = sanitize($_POST['houseId'] ?? '');
    $studentId = sanitize($_POST['studentId'] ?? '');
    $photo = $_FILES['photo'] ?? null;
    $uploadError = null;
    $mimeType = '';

    if ($isHouseScopedRole) {
        if (!$currentHouseId || $houseId !== $currentHouseId) {
            $uploadError = 'You can only upload photos for your own house.';
        }
    }

    if ($uploadError === null && ($houseId === '' || $studentId === '')) {
        $uploadError = 'Please select a house and a student before uploading.';
    }

    if ($uploadError === null && (!$photo || empty($photo['name']))) {
        $uploadError = 'Please choose an image to upload.';
    }

    if ($uploadError === null) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $extension = strtolower(pathinfo((string) $photo['name'], PATHINFO_EXTENSION));
        $mimeType = '';
        if (!empty($photo['tmp_name']) && is_file($photo['tmp_name']) && function_exists('finfo_open')) {
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($fileInfo !== false) {
                $mimeType = strtolower((string) finfo_file($fileInfo, $photo['tmp_name']));
                finfo_close($fileInfo);
            }
        }
        if ($mimeType === '') {
            $mimeType = strtolower((string) ($photo['type'] ?? ''));
        }

        if ($photo['error'] !== UPLOAD_ERR_OK) {
            $uploadError = 'The selected image could not be uploaded. Please try again.';
        } elseif (!in_array($extension, $allowedExt, true) || !in_array($mimeType, $allowedMime, true)) {
            $uploadError = 'Only JPG, JPEG, PNG, and WEBP images are allowed.';
        }
    }

    if ($uploadError === null) {
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename((string) $photo['name']));
        $filename = 'student-photo-' . time() . '-' . $safeName;
        $storageObject = 'student-gallery/' . $filename;

        $uploadStage = 'storage upload';
        try {
            $firebase->uploadStorageFile((string) $photo['tmp_name'], $storageObject, $mimeType);
            $uploadStage = 'student lookup';
            $student = StudentService::find($studentId) ?? [];
            $studentName = trim((($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')));
            $metadata = [
                'studentId' => $studentId,
                'studentName' => $studentName !== '' ? $studentName : 'Student',
                'houseId' => $houseId,
                'houseName' => $houses[$houseId] ?? 'House',
                'file' => $filename,
                'storageObject' => $storageObject,
                'originalName' => $photo['name'],
                'uploadedBy' => $role,
                'uploadedAt' => date('Y-m-d H:i:s'),
            ];
            $uploadStage = 'Firestore metadata write';
            $metadata['id'] = $firebase->addDocument(COL_GALLERY_PHOTOS, $metadata);
            $uploadStage = 'legacy metadata cache';
            $uploadDir = APP_ROOT . '/public/uploads/student-gallery';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }
            if (is_dir($uploadDir) && is_writable($uploadDir)) {
                file_put_contents($uploadDir . '/' . $filename . '.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            flash('success', 'Student photo uploaded successfully.');
            redirect(url('index.php?route=' . urlencode('/views/gallery/index.php') . '&houseId=' . urlencode($houseId)));
        } catch (Throwable $e) {
            try {
                $firebase->deleteStorageFile($storageObject);
            } catch (Throwable $cleanupError) {
                error_log('Gallery Storage cleanup failed: ' . $cleanupError->getMessage());
            }
            $uploadError = 'Image upload failed during ' . $uploadStage . '. Please try again.';
            error_log('Gallery upload failed during ' . $uploadStage . ': ' . $e->getMessage());
        }
    }

    if ($uploadError !== null) {
        flash('error', $uploadError);
    }
}

$gallery = [];
$galleryDir = APP_ROOT . '/public/uploads/student-gallery';
$metadataByFile = [];
if (is_dir($galleryDir)) {
    $files = glob($galleryDir . '/*.json');
    foreach ($files as $jsonFile) {
        $meta = json_decode((string) file_get_contents($jsonFile), true);
        if (!is_array($meta) || empty($meta['file'])) {
            continue;
        }

        $metadataByFile[(string) $meta['file']] = $meta;
    }
}
if (!$houseScopeUnavailable) {
    try {
        $galleryFilters = $selectedStudentId !== ''
            ? [['studentId', '=', $selectedStudentId]]
            : ($selectedHouseId !== '' ? [['houseId', '=', $selectedHouseId]] : []);
        foreach ($firebase->getCollection(COL_GALLERY_PHOTOS, $galleryFilters, 500) as $meta) {
            if (!empty($meta['file'])) {
                $metadataByFile[(string) $meta['file']] = $meta;
            }
        }
    } catch (Throwable $e) {
        error_log('Gallery metadata read failed: ' . $e->getMessage());
    }
}

foreach ($metadataByFile as $meta) {
    if ($houseScopeUnavailable) {
        continue;
    }
    if (!is_array($meta) || empty($meta['file'])) {
        continue;
    }

    $fileHouseId = (string) ($meta['houseId'] ?? '');
    $filePath = $galleryDir . '/' . $meta['file'];
    $storageObject = (string) ($meta['storageObject'] ?? '');
    if ($storageObject === '' && !file_exists($filePath)) {
        continue;
    }

    if ($isHouseScopedRole) {
        if ($currentHouseId && $fileHouseId !== $currentHouseId) {
            continue;
        }
    }

    if ($selectedHouseId !== '' && $fileHouseId !== $selectedHouseId) {
        continue;
    }

    if ($selectedStudentId !== '' && ($meta['studentId'] ?? '') !== $selectedStudentId) {
        continue;
    }

    $matchText = strtolower(trim((($meta['studentName'] ?? '') . ' ' . ($meta['studentId'] ?? '') . ' ' . ($meta['admissionNo'] ?? '') . ' ' . ($meta['originalName'] ?? ''))));
    if ($searchTermNormalized !== '' && stripos($matchText, $searchTermNormalized) === false) {
        continue;
    }

    $imageUrl = $storageObject !== '' ? $firebase->storageUrl($storageObject) : url('uploads/student-gallery/' . $meta['file']);
    $gallery[] = [
        'id' => $meta['id'] ?? $meta['studentId'] ?? uniqid('gallery_', true),
        'studentId' => $meta['studentId'] ?? '',
        'studentName' => $meta['studentName'] ?? 'Student',
        'houseId' => $fileHouseId,
        'houseName' => $meta['houseName'] ?? ($houses[$fileHouseId] ?? 'House'),
        'file' => $imageUrl,
        'fileName' => $meta['file'],
        'storageObject' => $storageObject,
        'originalName' => $meta['originalName'] ?? $meta['file'],
        'uploadedAt' => $meta['uploadedAt'] ?? '',
    ];
}

usort($gallery, static function ($a, $b) {
    return strcmp(($b['uploadedAt'] ?? ''), ($a['uploadedAt'] ?? ''));
});

$studentOptionsUrl = url('views/gallery/student-options.php?houseId=' . urlencode($selectedHouseId));
$galleryStudentOptionsEndpoint = url('views/gallery/student-options.php');
if ($role === ROLE_ADMIN) {
    $pageScripts[] = 'admin.js';
} elseif ($role === ROLE_HOUSE_MASTER || $role === ROLE_HOUSE_MISTRESS) {
    $pageScripts[] = 'house-master.js';
} elseif ($role === ROLE_SENIOR_HOUSEPARENT) {
    $pageScripts[] = 'senior-houseparent.js';
}

$pageTitle = 'Gallery';
$dashboardHref = ROLE_DASHBOARD[$role] ?? '/views/dashboard/dashboard.php';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('index.php?route=' . urlencode($dashboardHref))],
    ['icon' => 'bi-images', 'label' => 'Gallery', 'href' => url('index.php?route=' . urlencode('/views/gallery/index.php')), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-images text-info me-2"></i>Student Gallery</h4>
                <p class="text-muted mb-0">Upload and view student photos by house.</p>
            </div>
        </div>

        <?php if (in_array($role, [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT], true)): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <form method="GET" action="<?= url('index.php?route=' . urlencode('/views/gallery/index.php')) ?>" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Search Student</label>
                            <input type="text" name="q" class="form-control" value="<?= e($searchTerm) ?>" placeholder="Name or admission number">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Student</label>
                            <select name="studentId" class="form-select"
                                <?php if ($role === ROLE_ADMIN): ?>
                                    data-admin-student-options="<?= e($studentOptionsUrl) ?>"
                                <?php else: ?>
                                    data-house-student-options="<?= e($studentOptionsUrl) ?>"
                                <?php endif; ?>
                                data-selected-id="<?= e($selectedStudentId) ?>"
                                data-placeholder="All students">
                                <option value="">All students</option>
                                <?php if ($selectedStudentId !== ''): ?>
                                    <option value="<?= e($selectedStudentId) ?>" selected>Selected student</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Select House</label>
                            <select name="houseId" class="form-select">
                                <option value="">All houses</option>
                                <?php foreach ($houses as $houseId => $houseName): ?>
                                    <?php if ($isHouseScopedRole) {
                                        if ($currentHouseId && $houseId !== $currentHouseId) continue;
                                    } ?>
                                    <option value="<?= e($houseId) ?>" <?= ($selectedHouseId === $houseId) ? 'selected' : '' ?>><?= e($houseName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2 justify-content-md-end">
                            <button class="btn btn-primary" type="submit">Apply Filter</button>
                            <?php if ($selectedHouseId !== '' || $selectedStudentId !== '' || $searchTerm !== ''): ?>
                                <a class="btn btn-outline-secondary" href="<?= url('index.php?route=' . urlencode('/views/gallery/index.php')) ?>">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (in_array($role, [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true)): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-3 fw-bold">Upload Student Photo</h5>
                    <form method="POST" enctype="multipart/form-data" action="<?= url('index.php?route=' . urlencode('/views/gallery/index.php')) ?>">
                        <input type="hidden" name="upload_student_photo" value="1">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">House</label>
                                <select name="houseId" class="form-select" required>
                                    <option value="">Select house</option>
                                    <?php foreach ($houses as $houseId => $houseName): ?>
                                        <?php if ($role === ROLE_HOUSE_MASTER || $role === ROLE_HOUSE_MISTRESS) {
                                            if ($currentHouseId && $houseId !== $currentHouseId) continue;
                                        } ?>
                                        <option value="<?= e($houseId) ?>" <?= ($selectedHouseId === $houseId) ? 'selected' : '' ?>><?= e($houseName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Student</label>
                                <select name="studentId" class="form-select" required
                                    data-gallery-student-options="<?= e($galleryStudentOptionsEndpoint) ?>"
                                    data-placeholder="Select student">
                                    <option value="">Select house first</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Image</label>
                                <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-end">
                            <button class="btn btn-primary" type="submit">Upload Photo</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 fw-bold">Photo Records</h5>
                    <span class="badge bg-light text-dark border"><?= count($gallery) ?> photo(s)</span>
                </div>

                <style>
                    .gallery-masonry {
                        column-count: 3;
                        column-gap: 1rem;
                    }
                    .gallery-item {
                        break-inside: avoid;
                        margin-bottom: 1rem;
                    }
                    @media (max-width: 992px) {
                        .gallery-masonry { column-count: 2; }
                    }
                    @media (max-width: 576px) {
                        .gallery-masonry { column-count: 1; }
                    }
                </style>

                <?php if (empty($gallery)): ?>
                    <div class="text-center py-5 text-muted border rounded bg-light">
                        <i class="bi bi-images display-6 d-block mb-2"></i>
                        No student photos available for this selection.
                    </div>
                <?php else: ?>
                    <div class="gallery-masonry">
                        <?php foreach ($gallery as $item): ?>
                            <div class="gallery-item">
                                <div class="card border-0 shadow-sm overflow-hidden h-100">
                                    <button type="button" class="btn p-0 border-0 w-100" data-bs-toggle="modal" data-bs-target="#galleryViewModal-<?= e(md5((string) $item['fileName'])) ?>" aria-label="View photo of <?= e($item['studentName']) ?>">
                                        <img src="<?= e($item['file']) ?>" class="card-img-top" alt="<?= e($item['studentName']) ?>" style="height: 220px; object-fit: cover;" onerror="this.onerror=null;this.src='<?= e(asset('images/gallery-photo-unavailable.svg')) ?>';this.alt='Photo unavailable. Edit this record to upload a replacement.';">
                                    </button>
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-1"><?= e($item['studentName']) ?></h6>
                                        <p class="mb-1 small text-muted"><?= e($item['houseName']) ?></p>
                                        <p class="mb-2 small text-muted">Uploaded: <?= e($item['uploadedAt']) ?></p>
                                        <div class="d-flex justify-content-between align-items-center gap-2">
                                            <?php $modalId = md5((string) $item['fileName']); ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#galleryViewModal-<?= e($modalId) ?>">
                                                <i class="bi bi-eye me-1"></i>View
                                            </button>
                                            <?php if (in_array($role, [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true)): ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#galleryEditModal-<?= e($modalId) ?>">
                                                    <i class="bi bi-pencil me-1"></i>Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#galleryDeleteModal-<?= e($modalId) ?>">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="galleryViewModal-<?= e($modalId) ?>" tabindex="-1" aria-labelledby="galleryViewModalLabel-<?= e($modalId) ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content border-0 shadow">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold" id="galleryViewModalLabel-<?= e($modalId) ?>"><i class="bi bi-image text-info me-2"></i><?= e($item['studentName']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <img src="<?= e($item['file']) ?>" alt="<?= e($item['studentName']) ?>" class="img-fluid rounded" style="max-height: 65vh; object-fit: contain;" onerror="this.onerror=null;this.src='<?= e(asset('images/gallery-photo-unavailable.svg')) ?>';this.alt='Photo unavailable. Edit this record to upload a replacement.';">
                                            <p class="small text-muted mt-3 mb-0"><?= e($item['houseName']) ?><?php if ($item['uploadedAt'] !== ''): ?> | Uploaded: <?= e($item['uploadedAt']) ?><?php endif; ?></p>
                                        </div>
                                        <div class="modal-footer">
                                            <a href="<?= url('index.php?route=' . urlencode('/views/gallery/view/view.php') . '&file=' . urlencode($item['fileName'])) ?>" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i>Open Page</a>
                                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (in_array($role, [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true)): ?>
                                <div class="modal fade" id="galleryEditModal-<?= e($modalId) ?>" tabindex="-1" aria-labelledby="galleryEditModalLabel-<?= e($modalId) ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content border-0 shadow">
                                            <form method="POST" enctype="multipart/form-data" action="<?= e(url('index.php?route=' . urlencode('/views/gallery/edit/edit.php') . '&file=' . urlencode($item['fileName']))) ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold" id="galleryEditModalLabel-<?= e($modalId) ?>"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Photo Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="file" value="<?= e($item['fileName']) ?>">
                                                    <div class="row g-4 align-items-start">
                                                        <div class="col-md-4 text-center">
                                                            <img src="<?= e($item['file']) ?>" alt="<?= e($item['studentName']) ?>" class="img-fluid rounded border" style="max-height: 220px; object-fit: contain;" onerror="this.onerror=null;this.src='<?= e(asset('images/gallery-photo-unavailable.svg')) ?>';this.alt='Photo unavailable. Upload a replacement image below.';">
                                                            <p class="small text-muted mt-2 mb-0"><?= e($item['fileName']) ?></p>
                                                        </div>
                                                        <div class="col-md-8">
                                                            <div class="alert alert-light border small mb-3"><i class="bi bi-info-circle me-1"></i>Update the assignment or optionally replace the image.</div>
                                                            <label for="gallery-house-<?= e($modalId) ?>" class="form-label fw-semibold">House</label>
                                                            <select id="gallery-house-<?= e($modalId) ?>" name="houseId" class="form-select mb-3" required>
                                                                <?php foreach ($houses as $houseId => $houseName): ?>
                                                                    <?php if (($role === ROLE_HOUSE_MASTER || $role === ROLE_HOUSE_MISTRESS) && $currentHouseId && $houseId !== $currentHouseId) continue; ?>
                                                                    <option value="<?= e($houseId) ?>" <?= $item['houseId'] === $houseId ? 'selected' : '' ?>><?= e($houseName) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <label for="gallery-student-<?= e($modalId) ?>" class="form-label fw-semibold">Student</label>
                                                            <select id="gallery-student-<?= e($modalId) ?>" name="studentId" class="form-select" required
                                                                data-gallery-student-options="<?= e($galleryStudentOptionsEndpoint) ?>"
                                                                data-selected-id="<?= e($item['studentId']) ?>"
                                                                data-selected-label="<?= e($item['studentName']) ?>"
                                                                data-placeholder="Select student">
                                                                <option value="<?= e($item['studentId']) ?>" selected><?= e($item['studentName']) ?></option>
                                                            </select>
                                                            <div class="form-text">The current student remains available even if their house assignment differs.</div>
                                                            <label for="gallery-photo-<?= e($modalId) ?>" class="form-label fw-semibold mt-3">Replace Image <span class="text-muted fw-normal">(optional)</span></label>
                                                            <input id="gallery-photo-<?= e($modalId) ?>" type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                                            <div class="form-text">Leave empty to keep the current image.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal fade" id="galleryDeleteModal-<?= e($modalId) ?>" tabindex="-1" aria-labelledby="galleryDeleteModalLabel-<?= e($modalId) ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow">
                                            <form method="POST" action="<?= e(url('index.php?route=' . urlencode('/views/gallery/delete/delete.php') . '&file=' . urlencode($item['fileName']))) ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold text-danger" id="galleryDeleteModalLabel-<?= e($modalId) ?>"><i class="bi bi-trash3 me-2"></i>Delete Student Photo</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="file" value="<?= e($item['fileName']) ?>">
                                                    <p class="mb-0">Delete the photo for <strong><?= e($item['studentName']) ?></strong>? This cannot be undone.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash3 me-1"></i>Delete Photo</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const selects = Array.from(document.querySelectorAll('select[data-gallery-student-options]'));
    const requests = new Map();

    function loadOptions(select) {
        if (select.dataset.sdsStudentsLoaded === 'true' || select.dataset.sdsStudentsLoading === 'true') {
            return;
        }

        const form = select.form;
        const houseSelect = form ? form.querySelector('select[name="houseId"]') : null;
        const endpoint = new URL(select.dataset.galleryStudentOptions, window.location.href);
        if (houseSelect && houseSelect.value) {
            endpoint.searchParams.set('houseId', houseSelect.value);
        } else {
            endpoint.searchParams.delete('houseId');
        }

        const selectedId = select.dataset.selectedId || select.value;
        const selectedLabel = select.dataset.selectedLabel || select.selectedOptions[0]?.textContent || '';
        const requestVersion = String(Number(select.dataset.sdsStudentRequestVersion || 0) + 1);
        select.dataset.sdsStudentRequestVersion = requestVersion;
        select.dataset.sdsStudentsLoading = 'true';
        select.disabled = true;

        let request = requests.get(endpoint.href);
        if (!request) {
            request = fetch(endpoint.href, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('Student options request failed (HTTP ' + response.status + ').');
                    return response.json();
                })
                .then(function (response) {
                    if (!response.success || !Array.isArray(response.students)) {
                        throw new Error(response.message || 'Student options response was invalid.');
                    }
                    return response.students;
                });
            requests.set(endpoint.href, request);
            request.catch(function () { requests.delete(endpoint.href); });
        }

        request
            .then(function (students) {
                if (select.dataset.sdsStudentRequestVersion !== requestVersion) return;

                select.replaceChildren(new Option(select.dataset.placeholder || 'Select student', ''));
                if (selectedId && !students.some(function (student) { return String(student.id) === selectedId; })) {
                    select.add(new Option(selectedLabel || selectedId, selectedId));
                }
                students.forEach(function (student) {
                    select.add(new Option(student.label, student.id));
                });
                if (!students.length && !selectedId) {
                    select.add(new Option('No students available', '', true, true));
                }
                select.value = selectedId;
                select.dataset.sdsStudentsLoaded = 'true';
            })
            .catch(function (error) {
                if (select.dataset.sdsStudentRequestVersion !== requestVersion) return;
                console.error('Unable to load gallery student options:', error);
                if (window.SDS && typeof window.SDS.toast === 'function') {
                    window.SDS.toast('error', 'Student options could not be loaded. Please try again.');
                }
            })
            .finally(function () {
                if (select.dataset.sdsStudentRequestVersion !== requestVersion) return;
                select.dataset.sdsStudentsLoading = 'false';
                select.disabled = false;
            });
    }

    selects.forEach(function (select) {
        const form = select.form;
        const houseSelect = form ? form.querySelector('select[name="houseId"]') : null;
        if (houseSelect) {
            houseSelect.addEventListener('change', function () {
                select.dataset.sdsStudentRequestVersion = String(Number(select.dataset.sdsStudentRequestVersion || 0) + 1);
                select.dataset.sdsStudentsLoaded = 'false';
                select.dataset.sdsStudentsLoading = 'false';
                select.dataset.selectedId = '';
                select.dataset.selectedLabel = '';
                select.replaceChildren(new Option(select.dataset.placeholder || 'Select student', ''));
                select.disabled = false;
            });
        }

        select.addEventListener('focus', function () { loadOptions(select); });
        const modal = select.closest('.modal');
        if (modal) {
            modal.addEventListener('show.bs.modal', function () { loadOptions(select); });
        }
    });
})();
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
