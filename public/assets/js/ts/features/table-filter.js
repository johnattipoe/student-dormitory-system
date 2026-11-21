"use strict";
function getTableFilterElements() {
    const input = document.querySelector('[data-ts-table-filter]');
    const table = document.querySelector('[data-ts-filter-table]');
    if (!table)
        return null;
    const pagination = document.querySelector('[data-ts-pagination]');
    const configuredPageLength = Number(pagination?.dataset.pageLength);
    return {
        input,
        table,
        count: document.querySelector('[data-ts-filter-count]'),
        pagination,
        pageInfo: pagination?.querySelector('[data-ts-page-info]') ?? null,
        pageButtons: pagination?.querySelector('[data-ts-pagination-pages]') ?? null,
        pageLength: pagination
            ? Math.max(1, Number.isFinite(configuredPageLength) && configuredPageLength > 0 ? Math.floor(configuredPageLength) : 25)
            : Number.MAX_SAFE_INTEGER,
        currentPage: 1,
    };
}
function appendPageButton(elements, label, page, options = {}) {
    if (!elements.pageButtons)
        return;
    const item = document.createElement('li');
    item.className = `page-item${options.active ? ' active' : ''}${options.disabled ? ' disabled' : ''}`;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'page-link';
    button.textContent = label;
    button.disabled = options.disabled ?? false;
    if (options.ariaLabel)
        button.setAttribute('aria-label', options.ariaLabel);
    if (options.active)
        button.setAttribute('aria-current', 'page');
    button.addEventListener('click', () => {
        elements.currentPage = page;
        filterTable(elements);
    });
    item.append(button);
    elements.pageButtons.append(item);
}
function appendPageEllipsis(elements) {
    if (!elements.pageButtons)
        return;
    const item = document.createElement('li');
    item.className = 'page-item disabled';
    const label = document.createElement('span');
    label.className = 'page-link';
    label.textContent = '...';
    item.append(label);
    elements.pageButtons.append(item);
}
function renderPagination(elements, totalRows, pageCount) {
    if (!elements.pagination || !elements.pageButtons || !elements.pageInfo)
        return;
    elements.pagination.classList.toggle('d-none', pageCount <= 1);
    elements.pageButtons.replaceChildren();
    if (totalRows === 0) {
        elements.pageInfo.textContent = 'Showing 0 of 0';
        return;
    }
    const firstRow = (elements.currentPage - 1) * elements.pageLength + 1;
    const lastRow = Math.min(firstRow + elements.pageLength - 1, totalRows);
    elements.pageInfo.textContent = `Showing ${firstRow}-${lastRow} of ${totalRows}`;
    appendPageButton(elements, 'Previous', Math.max(1, elements.currentPage - 1), {
        disabled: elements.currentPage === 1,
        ariaLabel: 'Previous page',
    });
    let visiblePages;
    if (pageCount <= 7) {
        visiblePages = Array.from({ length: pageCount }, (_, index) => index + 1);
    }
    else {
        const startPage = Math.max(2, elements.currentPage - 1);
        const endPage = Math.min(pageCount - 1, elements.currentPage + 1);
        visiblePages = [1];
        if (startPage > 2)
            visiblePages.push(-1);
        for (let page = startPage; page <= endPage; page++) {
            visiblePages.push(page);
        }
        if (endPage < pageCount - 1)
            visiblePages.push(-1);
        visiblePages.push(pageCount);
    }
    visiblePages.forEach((page) => {
        if (page === -1) {
            appendPageEllipsis(elements);
            return;
        }
        appendPageButton(elements, String(page), page, {
            active: page === elements.currentPage,
            ariaLabel: `Page ${page}`,
        });
    });
    appendPageButton(elements, 'Next', Math.min(pageCount, elements.currentPage + 1), {
        disabled: elements.currentPage === pageCount,
        ariaLabel: 'Next page',
    });
}
function filterTable(elements) {
    const query = elements.input?.value.trim().toLowerCase() ?? '';
    const rows = Array.from(elements.table.tBodies[0]?.rows ?? []);
    const dataRows = rows.filter((row) => !row.querySelector('td[colspan]'));
    const emptyStateRows = rows.filter((row) => row.querySelector('td[colspan]'));
    const matchingRows = dataRows.filter((row) => query === '' || row.textContent?.toLowerCase().includes(query) === true);
    const pageCount = Math.max(1, Math.ceil(matchingRows.length / elements.pageLength));
    elements.currentPage = Math.min(elements.currentPage, pageCount);
    const firstPageRow = (elements.currentPage - 1) * elements.pageLength;
    const lastPageRow = firstPageRow + elements.pageLength;
    dataRows.forEach((row) => {
        row.hidden = true;
    });
    matchingRows.forEach((row, index) => {
        row.hidden = index < firstPageRow || index >= lastPageRow;
    });
    emptyStateRows.forEach((row) => {
        row.hidden = dataRows.length > 0;
    });
    if (elements.count) {
        elements.count.textContent = `${matchingRows.length} shown`;
    }
    renderPagination(elements, matchingRows.length, pageCount);
}
document.addEventListener('DOMContentLoaded', () => {
    const elements = getTableFilterElements();
    if (!elements)
        return;
    elements.input?.addEventListener('input', () => {
        elements.currentPage = 1;
        filterTable(elements);
    });
    filterTable(elements);
});
