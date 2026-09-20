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

$role = current_role();
$currentUser = current_user() ?? [];
$currentHouseId = current_house_id();

$houses = [];
foreach (HouseService::all() as $house) {
    if (!empty($house['id'])) {
        $houses[(string) $house['id']] = (string) ($house['name'] ?? $house['id']);
    }
}

$selectedHouseId = sanitize($_GET['houseId'] ?? ($_POST['houseId'] ?? ''));
$selectedStudentId = sanitize($_GET['studentId'] ?? ($_POST['studentId'] ?? ''));
$searchTerm = sanitize($_GET['q'] ?? ($_POST['q'] ?? ''));
$searchTermNormalized = strtolower(trim((string) $searchTerm));
if ($role === ROLE_HOUSE_MASTER || $role === ROLE_HOUSE_MISTRESS) {
    $selectedHouseId = $selectedHouseId !== '' ? $selectedHouseId : ($currentHouseId ?? '');
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

    if ($role === ROLE_HOUSE_MASTER || $role === ROLE_HOUSE_MISTRESS) {
        if ($currentHouseId && $houseId !== $currentHouseId) {
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
        $mimeType = strtolower((string) ($photo['type'] ?? ''));

        if ($photo['error'] !== UPLOAD_ERR_OK) {
            $uploadError = 'The selected image could not be uploaded. Please try again.';
        } elseif (!in_array($extension, $allowedExt, true) || !in_array($mimeType, $allowedMime, true)) {
            $uploadError = 'Only JPG, JPEG, PNG, and WEBP images are allowed.';
        }
    }

    if ($uploadError === null) {
        $uploadDir = APP_ROOT . '/public/uploads/student-gallery';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                $uploadError = 'The upload folder is not writable. Please contact an administrator.';
            }
        }

        if ($uploadError === null && !is_writable($uploadDir)) {
            $uploadError = 'The upload folder is not writable. Please contact an administrator.';
        }

        if ($uploadError === null) {
            $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename((string) $photo['name']));
            $filename = 'student-photo-' . time() . '-' . $safeName;
            $target = $uploadDir . '/' . $filename;

            if (!@move_uploaded_file($photo['tmp_name'], $target)) {
                $uploadError = 'Image upload failed. Please try again.';
            } else {
                $student = StudentService::find($studentId) ?? [];
                $studentName = trim((($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')));
                $metadata = [
                    'id' => 'G-' . date('YmdHis'),
                    'studentId' => $studentId,
                    'studentName' => $studentName !== '' ? $studentName : 'Student',
                    'houseId' => $houseId,
                    'houseName' => $houses[$houseId] ?? 'House',
                    'file' => $filename,
                    'originalName' => $photo['name'],
                    'uploadedBy' => $role,
                    'uploadedAt' => date('Y-m-d H:i:s'),
                ];
                file_put_contents($target . '.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                flash('success', 'Student photo uploaded successfully.');
                redirect(url('index.php?route=' . urlencode('/views/gallery/index.php') . '&houseId=' . urlencode($houseId)));
            }
        }
    }

    if ($uploadError !== null) {
        flash('error', $uploadError);
    }
}

$gallery = [];
$galleryDir = APP_ROOT . '/public/uploads/student-gallery';
if (is_dir($galleryDir)) {
    $files = glob($galleryDir . '/*.json');
    foreach ($files as $jsonFile) {
        $meta = json_decode((string) file_get_contents($jsonFile), true);
        if (!is_array($meta) || empty($meta['file'])) {
            continue;
        }

        $fileHouseId = (string) ($meta['houseId'] ?? '');
        $filePath = $galleryDir . '/' . $meta['file'];
        if (!file_exists($filePath)) {
            continue;
        }

        if ($role === ROLE_HOUSE_MASTER || $role === ROLE_HOUSE_MISTRESS) {
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

        $gallery[] = [
            'id' => $meta['id'] ?? $meta['studentId'] ?? uniqid('gallery_', true),
            'studentId' => $meta['studentId'] ?? '',
            'studentName' => $meta['studentName'] ?? 'Student',
            'houseId' => $fileHouseId,
            'houseName' => $meta['houseName'] ?? ($houses[$fileHouseId] ?? 'House'),
            'file' => url('uploads/student-gallery/' . $meta['file']),
            'fileName' => $meta['file'],
            'originalName' => $meta['originalName'] ?? $meta['file'],
            'uploadedAt' => $meta['uploadedAt'] ?? '',
        ];
    }
}

usort($gallery, static function ($a, $b) {
    return strcmp(($b['uploadedAt'] ?? ''), ($a['uploadedAt'] ?? ''));
});

$studentOptions = [];
if ($selectedHouseId !== '') {
    $studentOptions = StudentService::all($selectedHouseId);
} elseif ($role === ROLE_HOUSE_MASTER || $role === ROLE_HOUSE_MISTRESS) {
    $studentOptions = StudentService::all($currentHouseId);
} elseif ($role === ROLE_SENIOR_HOUSEPARENT) {
    $studentOptions = StudentService::all($currentHouseId ?: null);
} else {
    $studentOptions = StudentService::all();
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
                            <select name="studentId" class="form-select">
                                <option value="">All students</option>
                                <?php foreach ($studentOptions as $student): ?>
                                    <?php $studentHouseId = (string) ($student['houseId'] ?? ''); ?>
                                    <?php if ($selectedHouseId !== '' && $studentHouseId !== $selectedHouseId) continue; ?>
                                    <option value="<?= e($student['id'] ?? '') ?>" <?= ($selectedStudentId === (string) ($student['id'] ?? '')) ? 'selected' : '' ?>><?= e(trim((($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')))) ?> (<?= e($student['admissionNo'] ?? '') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Select House</label>
                            <select name="houseId" class="form-select">
                                <option value="">All houses</option>
                                <?php foreach ($houses as $houseId => $houseName): ?>
                                    <?php if ($role === ROLE_HOUSE_MASTER || $role === ROLE_HOUSE_MISTRESS) {
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
                                <select name="studentId" class="form-select" required>
                                    <option value="">Select student</option>
                                    <?php foreach ($studentOptions as $student): ?>
                                        <?php $studentHouseId = (string) ($student['houseId'] ?? ''); ?>
                                        <?php if ($selectedHouseId !== '' && $studentHouseId !== $selectedHouseId) continue; ?>
                                        <option value="<?= e($student['id'] ?? '') ?>"><?= e(trim((($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')))) ?> (<?= e($student['admissionNo'] ?? '') ?>)</option>
                                    <?php endforeach; ?>
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
                                    <a href="<?= e($item['file']) ?>" target="_blank" rel="noopener" data-bs-toggle="modal" data-bs-target="#galleryLightbox" data-gallery-image="<?= e($item['file']) ?>" data-gallery-title="<?= e($item['studentName']) ?>">
                                        <img src="<?= e($item['file']) ?>" class="card-img-top" alt="<?= e($item['studentName']) ?>" style="height: 220px; object-fit: cover;">
                                    </a>
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-1"><?= e($item['studentName']) ?></h6>
                                        <p class="mb-1 small text-muted"><?= e($item['houseName']) ?></p>
                                        <p class="mb-2 small text-muted">Uploaded: <?= e($item['uploadedAt']) ?></p>
                                        <div class="d-flex justify-content-between align-items-center gap-2">
                                            <a href="<?= url('index.php?route=' . urlencode('/views/gallery/view/view.php') . '&file=' . urlencode($item['fileName'])) ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
                                            <?php if (in_array($role, [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true)): ?>
                                                <a href="<?= url('index.php?route=' . urlencode('/views/gallery/edit/edit.php') . '&file=' . urlencode($item['fileName'])) ?>" class="btn btn-sm btn-outline-warning">
                                                    <i class="bi bi-pencil me-1"></i>Edit
                                                </a>
                                                <a href="<?= url('index.php?route=' . urlencode('/views/gallery/delete/delete.php') . '&file=' . urlencode($item['fileName'])) ?>" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
