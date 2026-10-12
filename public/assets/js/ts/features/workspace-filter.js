"use strict";
function getWorkspaceFilterOptions(input) {
    const selector = input.dataset.tsWorkspaceFilter;
    if (!selector)
        return null;
    let container = null;
    try {
        container = document.querySelector(selector);
    }
    catch {
        return null;
    }
    if (!container)
        return null;
    const items = Array.from(container.querySelectorAll('[data-ts-workspace-item]'));
    if (items.length === 0)
        return null;
    return {
        input,
        container,
        items,
        emptyState: document.querySelector('[data-ts-workspace-empty]'),
        resultCount: document.querySelector('[data-ts-workspace-count]'),
    };
}
function applyWorkspaceFilter(options) {
    const query = options.input.value.trim().toLocaleLowerCase();
    let visibleCount = 0;
    options.items.forEach((item) => {
        const searchableText = (item.dataset.searchText || item.textContent || '').toLocaleLowerCase();
        const matches = query === '' || searchableText.includes(query);
        item.hidden = !matches;
        if (matches)
            visibleCount++;
    });
    if (options.emptyState) {
        options.emptyState.hidden = visibleCount > 0;
    }
    if (options.resultCount) {
        options.resultCount.textContent = visibleCount + ' module' + (visibleCount === 1 ? '' : 's');
    }
    options.container.setAttribute('aria-busy', 'false');
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ts-workspace-filter]').forEach((input) => {
        const options = getWorkspaceFilterOptions(input);
        if (!options)
            return;
        input.addEventListener('input', () => applyWorkspaceFilter(options));
        applyWorkspaceFilter(options);
    });
});
