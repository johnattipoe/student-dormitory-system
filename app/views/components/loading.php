<?php
/** Loading overlay component with auto-hide support
 * Params: $message (default: 'Loading, please wait...'), $autoHide (ms, 0 = no auto-hide), $size (sm|md|lg)
 */
$message = $message ?? 'Loading, please wait...';
$autoHide = (int) ($autoHide ?? 0);
$size = $size ?? 'md';
$sizeClass = $size === 'sm' ? 'loading-card-sm' : ($size === 'lg' ? 'loading-card-lg' : '');
?>
<div class="loading-overlay d-none justify-content-center align-items-center" id="loadingSpinner" hidden role="status" aria-live="polite" aria-busy="true" aria-label="Loading content"<?php if ($autoHide > 0): ?> data-auto-hide="<?= $autoHide ?>"<?php endif; ?>>
    <div class="loading-card <?= $sizeClass ?> text-center fade-in">
        <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true">
            <span class="visually-hidden">Processing</span>
        </div>
        <div class="small text-muted"><?= e($message) ?></div>
    </div>
</div>

<style>
    .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.9); z-index: 9999; }
    .loading-card { background: white; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
    .loading-card-sm { padding: 1rem; } .loading-card-lg { padding: 3rem; }
    .fade-in { animation: fadeIn 0.3s ease-in; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<script>
(function() {
    const spinner = document.getElementById('loadingSpinner');
    if (!spinner) return;
    const autoHideMs = parseInt(spinner.getAttribute('data-auto-hide')) || 0;
    if (autoHideMs > 0) {
        setTimeout(() => {
            spinner.hidden = true;
            spinner.style.setProperty('display', 'none', 'important');
        }, autoHideMs);
    }
    // Global helper to show/hide
    window.showLoading = () => {
        spinner.hidden = false;
        spinner.classList.remove('d-none');
        spinner.classList.add('d-flex');
        spinner.style.setProperty('display', 'flex', 'important');
    };
    window.hideLoading = () => {
        spinner.hidden = true;
        spinner.classList.add('d-none');
        spinner.classList.remove('d-flex');
        spinner.style.setProperty('display', 'none', 'important');
    };
    window.hideLoading();
})();
</script>
