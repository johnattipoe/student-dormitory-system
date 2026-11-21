<?php
$reportType = (string) ($reportType ?? 'dashboard');
$reportExportBaseUrl = (string) ($reportExportBaseUrl ?? url('reports/export.php'));
$reportExportFormats = $reportExportFormats ?? [
    'pdf' => 'PDF',
    'csv' => 'CSV',
    'xls' => 'Excel',
];
$reportDownloadButtonLabel = (string) ($reportDownloadButtonLabel ?? 'Export Report');
$reportDownloadWrapperClass = (string) ($reportDownloadWrapperClass ?? 'd-flex flex-wrap gap-2 mb-3');
$reportModalId = 'reportExportModal-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $reportType);
$reportExportQuery = ['type' => $reportType];

if (!empty($date)) {
    $reportExportQuery['date'] = (string) $date;
}

$reportQuerySeparator = str_contains($reportExportBaseUrl, '?') ? '&' : '?';
?>
<div class="<?= e($reportDownloadWrapperClass) ?>">
    <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#<?= e($reportModalId) ?>">
        <i class="bi bi-download me-1"></i><?= e($reportDownloadButtonLabel) ?>
    </button>
</div>
<div class="modal fade" id="<?= e($reportModalId) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-download me-2 text-success"></i>Download <?= e(ucwords(str_replace(['_', '-'], ' ', $reportType))) ?> Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Choose a file format. Your report will download without leaving this page.</p>
                <div class="d-grid gap-2">
                    <?php foreach ($reportExportFormats as $format => $label): ?>
                        <?php
                        $format = (string) $format;
                        $formatQuery = $reportExportQuery;
                        $formatQuery['format'] = $format;
                        $exportUrl = $reportExportBaseUrl . $reportQuerySeparator . http_build_query($formatQuery);
                        ?>
                        <button type="button" class="btn btn-outline-<?= $format === 'pdf' ? 'danger' : ($format === 'xls' ? 'primary' : 'success') ?> report-export-download"
                            data-export-url="<?= e($exportUrl) ?>"
                            data-filename="<?= e($reportType . '-' . date('Y-m-d') . '.' . $format) ?>"
                            data-format="<?= e($format) ?>">
                            <i class="bi bi-file-earmark-<?= $format === 'pdf' ? 'pdf' : ($format === 'xls' ? 'spreadsheet' : 'text') ?> me-1"></i>
                            Download <?= e((string) $label) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="alert alert-danger d-none mt-3 mb-0 report-export-error" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.modal').forEach(function (modal) {
    if (modal.id === <?= json_encode($reportModalId) ?>) {
        document.body.appendChild(modal);
    }
});

document.querySelectorAll('#<?= e($reportModalId) ?> .report-export-download').forEach(function (button) {
    button.addEventListener('click', async function () {
        const errorMessage = button.closest('.modal').querySelector('.report-export-error');
        button.disabled = true;
        errorMessage.classList.add('d-none');
        errorMessage.textContent = '';
        try {
            const response = await fetch(button.dataset.exportUrl, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const contentType = (response.headers.get('content-type') || '').toLowerCase();
            const expectedContentType = button.dataset.format === 'pdf'
                ? 'application/pdf'
                : (button.dataset.format === 'xls' ? 'application/vnd.ms-excel' : 'text/csv');
            if (!response.ok || !contentType.includes(expectedContentType)) {
                throw new Error('The report could not be downloaded in the selected format. Please try again.');
            }

            const downloadUrl = URL.createObjectURL(await response.blob());
            const downloadLink = document.createElement('a');
            const disposition = response.headers.get('content-disposition') || '';
            const filenameMatch = disposition.match(/filename="([^"]+)"/i);
            downloadLink.href = downloadUrl;
            downloadLink.download = filenameMatch ? filenameMatch[1] : button.dataset.filename;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            downloadLink.remove();
            window.setTimeout(function () { URL.revokeObjectURL(downloadUrl); }, 1000);
        } catch (error) {
            errorMessage.textContent = error instanceof Error ? error.message : 'The report could not be downloaded. Please try again.';
            errorMessage.classList.remove('d-none');
        } finally {
            button.disabled = false;
        }
    });
});
</script>
