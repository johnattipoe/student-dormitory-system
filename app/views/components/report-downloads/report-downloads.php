<?php
$reportType = (string) ($reportType ?? 'dashboard');
$reportLabel = (string) ($reportLabel ?? 'report');
$exportQuery = ['type' => $reportType];

if (!empty($date)) {
    $exportQuery['date'] = (string) $date;
}

$exportBase = 'reports/export.php?' . http_build_query($exportQuery) . '&format=';
?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-danger btn-sm" href="<?= url($exportBase . 'pdf') ?>">
        <i class="bi bi-file-earmark-pdf"></i> Download PDF
    </a>
    <a class="btn btn-outline-success btn-sm" href="<?= url($exportBase . 'csv') ?>">
        <i class="bi bi-filetype-csv"></i> Download CSV
    </a>
    <a class="btn btn-outline-primary btn-sm" href="<?= url($exportBase . 'xls') ?>">
        <i class="bi bi-file-earmark-spreadsheet"></i> Download Excel
    </a>
</div>
