interface WorkspaceFilterOptions {
  input: HTMLInputElement;
  container: HTMLElement;
  items: HTMLElement[];
  emptyState: HTMLElement | null;
  resultCount: HTMLElement | null;
}

function getWorkspaceFilterOptions(input: HTMLInputElement): WorkspaceFilterOptions | null {
  const selector = input.dataset.tsWorkspaceFilter;
  if (!selector) return null;

  let container: HTMLElement | null = null;
  try {
    container = document.querySelector<HTMLElement>(selector);
  } catch {
    return null;
  }
  if (!container) return null;

  const items = Array.from(container.querySelectorAll<HTMLElement>('[data-ts-workspace-item]'));
  if (items.length === 0) return null;

  return {
    input,
    container,
    items,
    emptyState: document.querySelector<HTMLElement>('[data-ts-workspace-empty]'),
    resultCount: document.querySelector<HTMLElement>('[data-ts-workspace-count]'),
  };
}

function applyWorkspaceFilter(options: WorkspaceFilterOptions): void {
  const query = options.input.value.trim().toLocaleLowerCase();
  let visibleCount = 0;

  options.items.forEach((item) => {
    const searchableText = (item.dataset.searchText || item.textContent || '').toLocaleLowerCase();
    const matches = query === '' || searchableText.includes(query);
    item.hidden = !matches;
    if (matches) visibleCount++;
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
  document.querySelectorAll<HTMLInputElement>('[data-ts-workspace-filter]').forEach((input) => {
    const options = getWorkspaceFilterOptions(input);
    if (!options) return;

    input.addEventListener('input', () => applyWorkspaceFilter(options));
    applyWorkspaceFilter(options);
  });
});