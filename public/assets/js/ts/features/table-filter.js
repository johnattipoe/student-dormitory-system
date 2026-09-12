"use strict";
function getTableFilterElements() {
    const input = document.querySelector('[data-ts-table-filter]');
    const table = document.querySelector('[data-ts-filter-table]');
    if (!input || !table)
        return null;
    return {
        input,
        table,
        count: document.querySelector('[data-ts-filter-count]'),
    };
}
function filterTable(elements) {
    const query = elements.input.value.trim().toLowerCase();
    const rows = Array.from(elements.table.tBodies[0]?.rows ?? []);
    let visibleRows = 0;
    rows.forEach((row) => {
        const matches = query === '' || row.textContent?.toLowerCase().includes(query) === true;
        row.hidden = !matches;
        if (matches)
            visibleRows++;
    });
    if (elements.count) {
        elements.count.textContent = `${visibleRows} shown`;
    }
}
document.addEventListener('DOMContentLoaded', () => {
    const elements = getTableFilterElements();
    if (!elements)
        return;
    elements.input.addEventListener('input', () => filterTable(elements));
    filterTable(elements);
});
