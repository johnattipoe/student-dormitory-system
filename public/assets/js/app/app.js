// Shared app behavior + a small SDS namespace other page-specific scripts build on.
window.SDS = window.SDS || {};

function runWhenIdle(callback, timeout) {
  if (window.requestIdleCallback) {
    window.requestIdleCallback(callback, { timeout: timeout || 800 });
    return;
  }

  window.setTimeout(callback, 0);
}

SDS.onPageLoad = function (callback) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', callback, { once: true });
  }
  document.addEventListener('sds:page-loaded', callback);
};

SDS.onPageRestore = function (callback) {
  document.addEventListener('sds:page-restored', callback);
};

document.addEventListener('DOMContentLoaded', function () {
  SDS.enhancePageChrome();

  document.querySelectorAll('form[data-live-search]').forEach(form => {
    if (form.dataset.noPageReload === 'true') return;

    const searchInput = form.querySelector('input[name="search"]');
    if (!searchInput) return;

    let submitTimer;
    searchInput.addEventListener('input', () => {
      window.clearTimeout(submitTimer);
      submitTimer = window.setTimeout(() => form.requestSubmit(), 400);
    });
  });

  SDS.setSidebarCollapsed = function (collapsed) {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    sidebar.classList.toggle('collapsed', collapsed);
    const icon = document.getElementById('sidebarCollapseBtn')?.querySelector('i');
    if (icon) {
      icon.classList.toggle('bi-chevron-double-left', !collapsed);
      icon.classList.toggle('bi-chevron-double-right', collapsed);
    }
  };

  const savedSidebarState = window.localStorage.getItem('sds.sidebarCollapsed') === 'true';
  SDS.setSidebarCollapsed(savedSidebarState);

  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const sidebar = document.querySelector('.sidebar');
    if (!target || !sidebar) return;

    const mobileToggle = target.closest('#sidebarToggle');
    if (mobileToggle) {
      const isOpen = sidebar.classList.toggle('show');
      mobileToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      return;
    }

    const desktopToggle = target.closest('#sidebarCollapseBtn');
    if (desktopToggle) {
      const collapsed = !sidebar.classList.contains('collapsed');
      SDS.setSidebarCollapsed(collapsed);
      window.localStorage.setItem('sds.sidebarCollapsed', collapsed ? 'true' : 'false');
      if (window.matchMedia('(max-width: 991.98px)').matches) {
        sidebar.classList.remove('show');
      }
      return;
    }

    if (sidebar.classList.contains('show')
      && !sidebar.contains(target)
      && !target.closest('#sidebarToggle')) {
      sidebar.classList.remove('show');
      document.getElementById('sidebarToggle')?.setAttribute('aria-expanded', 'false');
    }
  });

  // Defer optional table/plugin work so navigation and buttons become usable first.
  runWhenIdle(function () {
    SDS.initializeDataTables(document);

    document.querySelectorAll('form[data-live-search]').forEach(form => {
      if (form.dataset.noPageReload === 'true') return;

      const input = form.querySelector('input[name="search"]');
      const table = document.querySelector(form.dataset.liveSearchTable || '');
      if (!input || !table) return;

      const applySearch = () => {
        if (window.jQuery && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(table)) {
          jQuery(table).DataTable().search(input.value.trim()).draw();
          return;
        }

        const term = input.value.trim().toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
          if (!row.querySelector('[colspan]')) {
            const searchableText = row.dataset.searchText || row.textContent;
            row.hidden = term !== '' && !searchableText.toLowerCase().includes(term);
          }
        });
      };

      form.addEventListener('submit', event => {
        event.preventDefault();
        applySearch();
      });
      input.addEventListener('input', applySearch);
      applySearch();
    });

    // Auto-init date/time pickers
    if (window.flatpickr) {
      document.querySelectorAll('.datepicker, input[type="date"], input[type="datetime-local"]').forEach(el => {
        if (!el._flatpickr) flatpickr(el);
      });
    }

    // Auto-init select2
    if (window.jQuery && jQuery.fn.select2) {
      jQuery('.select2, select.form-select').each(function () {
        if (!jQuery(this).hasClass('select2-hidden-accessible')) {
          const modal = this.closest('.modal');
          jQuery(this).select2({
            width: '100%',
            theme: 'bootstrap-5',
            minimumResultsForSearch: this.dataset.select2Search === 'true'
              ? 0
              : this.options && this.options.length > 8 ? 0 : Infinity,
            ...(modal ? { dropdownParent: jQuery(modal) } : {}),
          });
        }
      });
    }
  }, 900);

  // Generic delete-confirm buttons: <button data-confirm data-action="url" data-message="...">
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      const action = this.dataset.action;
      const message = this.dataset.message || 'This action cannot be undone.';
      SDS.confirmThen(message, () => {
        SDS.showButtonLoading(btn);
        SDS.submitAction(action);
      });
    });
  });

  // Auto-dismiss server-rendered alerts after a few seconds
  document.querySelectorAll('.alert.alert-dismissible').forEach(alertEl => {
    setTimeout(() => {
      if (window.bootstrap) {
        const inst = bootstrap.Alert.getOrCreateInstance(alertEl);
        inst.close();
      } else {
        alertEl.remove();
      }
    }, 5000);
  });

  // Prevent double-submit on any form with a submit button (spinner + disable)
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function (event) {
      const btn = event.submitter instanceof HTMLButtonElement
        ? event.submitter
        : form.querySelector('button[type="submit"], button:not([type])');
      if (btn && !btn.disabled) {
        SDS.showButtonLoading(btn);
      }

      queueMicrotask(() => {
        if (event.defaultPrevented) {
          SDS.hideButtonLoading(btn);
        }
      });
    });
  });

  // Flash toast on load, if server injected window.SDS_FLASH
  if (window.SDS_FLASH) {
    SDS.toast(SDS_FLASH.type || 'success', SDS_FLASH.message);
  }

  document.addEventListener('click', function (event) {
    const target = event.target instanceof Element ? event.target : null;
    const link = target?.closest('a[href]:not([target]):not([download])');
    if (!link) return;

    const href = link.getAttribute('href') || '';
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) {
      return;
    }

    const url = new URL(href, window.location.href);
    if (url.origin !== window.location.origin) return;

    const isLogout = /\/logout\.php$/i.test(url.pathname);
    const isLogin = /\/login\.php$/i.test(url.pathname);
    const isSessionTransition = isLogin || isLogout;
    if (isSessionTransition) {
      SDS.showSessionTransitionLoader(isLogout ? 'Signing out...' : 'Opening sign in...');
    }
    if (link.matches('.btn, [role="button"]') && !link.hasAttribute('data-confirm')) {
      SDS.showButtonLoading(link);
    }
    queueMicrotask(() => {
      if (event.defaultPrevented) {
        SDS.hideButtonLoading(link);
        if (isSessionTransition) {
          SDS.hideSessionTransitionLoader();
        }
      }
    });
  });

  SDS.initializeAjaxNavigation();
});

window.addEventListener('pageshow', function () {
  document.querySelectorAll('button[aria-busy="true"], a[aria-busy="true"]').forEach(button => {
    SDS.hideButtonLoading(button);
  });
  SDS.hideSessionTransitionLoader();
});

/** Show a confirm dialog (SweetAlert2 if available, else native confirm) then run a callback. */
SDS.confirmThen = function (message, onConfirm) {
  const modalEl = document.getElementById('confirmModal');
  const modalBody = document.getElementById('confirmModalBody');
  const modalForm = document.getElementById('confirmModalForm');

  if (modalEl && modalBody && modalForm && window.bootstrap) {
    modalBody.textContent = message;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    modalForm.addEventListener('submit', function (e) {
      e.preventDefault();
      modal.hide();
      onConfirm();
    }, { once: true });

    modal.show();
    return;
  }

  if (window.Swal) {
    Swal.fire({
      title: 'Are you sure?', text: message, icon: 'warning',
      showCancelButton: true, confirmButtonText: 'Yes, proceed', confirmButtonColor: '#d33',
    }).then(result => { if (result.isConfirmed) onConfirm(); });
  } else if (confirm(message)) {
    onConfirm();
  }
};

/** Build and submit a throwaway POST form to a URL (used by data-confirm buttons). */
SDS.submitAction = function (action, fields) {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = action;
  if (fields) {
    Object.keys(fields).forEach(key => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = key;
      input.value = fields[key];
      form.appendChild(input);
    });
  }
  document.body.appendChild(form);
  form.submit();
};

const sdsButtonLoadingStates = new WeakMap();

SDS.showButtonLoading = function (button) {
  const isButton = button instanceof HTMLButtonElement;
  const isLink = button instanceof HTMLAnchorElement;
  if ((!isButton && !isLink)
    || (isButton && button.disabled)
    || (isLink && button.getAttribute('aria-disabled') === 'true')
    || sdsButtonLoadingStates.has(button)) {
    return false;
  }

  sdsButtonLoadingStates.set(button, {
    html: button.innerHTML,
    ariaBusy: button.getAttribute('aria-busy'),
    ariaDisabled: button.getAttribute('aria-disabled'),
    disabled: isButton ? button.disabled : null,
    pointerEvents: isLink ? button.style.pointerEvents : null,
  });
  if (isButton) {
    button.disabled = true;
  } else {
    button.setAttribute('aria-disabled', 'true');
    button.style.pointerEvents = 'none';
  }
  button.setAttribute('aria-busy', 'true');
  button.innerHTML = '<span class="sds-circle-spinner sds-button-spinner me-1" aria-hidden="true"></span> ' + button.textContent.trim();
  return true;
};

SDS.hideButtonLoading = function (button) {
  const state = button instanceof HTMLButtonElement || button instanceof HTMLAnchorElement
    ? sdsButtonLoadingStates.get(button)
    : null;
  if (!state) return;

  button.innerHTML = state.html;
  if (button instanceof HTMLButtonElement) {
    button.disabled = state.disabled;
  } else {
    button.style.pointerEvents = state.pointerEvents;
  }
  if (state.ariaBusy === null) {
    button.removeAttribute('aria-busy');
  } else {
    button.setAttribute('aria-busy', state.ariaBusy);
  }
  if (state.ariaDisabled === null) {
    button.removeAttribute('aria-disabled');
  } else {
    button.setAttribute('aria-disabled', state.ariaDisabled);
  }
  sdsButtonLoadingStates.delete(button);
};

SDS.showSessionTransitionLoader = function (message) {
  const loader = document.getElementById('sessionTransitionLoader');
  if (!loader) return;

  const messageElement = loader.querySelector('[data-session-transition-message]');
  if (messageElement && message) {
    messageElement.textContent = message;
  }
  loader.hidden = false;
};

SDS.hideSessionTransitionLoader = function () {
  const loader = document.getElementById('sessionTransitionLoader');
  if (loader) loader.hidden = true;
};

/** Toast helper — SweetAlert2 toast if available, else a lightweight Bootstrap-style fallback. */
SDS.toast = function (type, message) {
  if (window.Swal) {
    Swal.fire({ toast: true, position: 'top-end', icon: type, title: message, showConfirmButton: false, timer: 3000, timerProgressBar: true });
    return;
  }
  const el = document.createElement('div');
  el.className = `alert alert-${type === 'error' ? 'danger' : type} position-fixed top-0 end-0 m-3 shadow`;
  el.style.zIndex = 2000;
  el.textContent = message;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 3500);
};

/** Small fetch wrapper: JSON in, JSON out, same-origin credentials, basic error handling. */
SDS.postJSON = async function (url, data) {
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(data || {}),
  });
  if (!res.ok) throw new Error('Request failed: ' + res.status);
  return res.json();
};

SDS.getJSON = async function (url) {
  const res = await fetch(url, {
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });
  if (!res.ok) throw new Error('Request failed: ' + res.status);
  return res.json();
};

SDS.enhancePageChrome = function () {
  document.querySelectorAll('.content-wrapper table').forEach(table => {
    table.classList.add('table', 'table-hover', 'align-middle');
    if (!table.closest('.table-responsive')) {
      const wrapper = document.createElement('div');
      wrapper.className = 'table-responsive sds-table-wrap';
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    }
  });

  document.querySelectorAll('.content-wrapper .card:not(.stat-card)').forEach((card, index) => {
    card.classList.add('sds-card');
    card.style.setProperty('--sds-card-accent-index', String((index % 5) + 1));
  });

  document.querySelectorAll('.content-wrapper form').forEach(form => {
    form.classList.add('sds-form');
    form.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]), textarea').forEach(input => {
      input.classList.add('form-control');
    });
    form.querySelectorAll('select').forEach(select => {
      select.classList.add('form-select');
    });
  });

  document.querySelectorAll('.content-wrapper .card-header, .content-wrapper h5, .content-wrapper h6').forEach(heading => {
    if (!heading.querySelector('.bi') && heading.classList.contains('card-header')) {
      heading.classList.add('sds-section-heading');
    }
  });
};

SDS.disposePageContent = function (root) {
  if (!window.Chart || typeof Chart.getChart !== 'function') return;
  root.querySelectorAll('canvas.report-chart').forEach(canvas => {
    const chart = Chart.getChart(canvas);
    if (chart) chart.destroy();
    delete canvas.dataset.sdsReportChartInitialized;
  });
};

SDS.initializePageContent = function (root) {
  const scope = root || document;
  SDS.enhancePageChrome();

  scope.querySelectorAll('form[data-live-search]').forEach(form => {
    if (form.dataset.sdsLiveSearchInitialized === 'true' || form.dataset.noPageReload === 'true') return;
    const searchInput = form.querySelector('input[name="search"]');
    if (!searchInput) return;

    form.dataset.sdsLiveSearchInitialized = 'true';
    let submitTimer;
    searchInput.addEventListener('input', () => {
      window.clearTimeout(submitTimer);
      submitTimer = window.setTimeout(() => form.requestSubmit(), 400);
    });
  });

  scope.querySelectorAll('[data-confirm]').forEach(button => {
    if (button.dataset.sdsConfirmInitialized === 'true') return;
    button.dataset.sdsConfirmInitialized = 'true';
    button.addEventListener('click', function (event) {
      event.preventDefault();
      SDS.confirmThen(this.dataset.message || 'This action cannot be undone.', () => {
        SDS.showButtonLoading(button);
        SDS.submitAction(this.dataset.action);
      });
    });
  });

  scope.querySelectorAll('.alert.alert-dismissible').forEach(alert => {
    if (alert.dataset.sdsAutoDismissInitialized === 'true') return;
    alert.dataset.sdsAutoDismissInitialized = 'true';
    window.setTimeout(() => {
      if (!alert.isConnected) return;
      if (window.bootstrap) {
        bootstrap.Alert.getOrCreateInstance(alert).close();
      } else {
        alert.remove();
      }
    }, 5000);
  });

  scope.querySelectorAll('form').forEach(form => {
    if (form.dataset.sdsSubmitInitialized === 'true') return;
    form.dataset.sdsSubmitInitialized = 'true';
    form.addEventListener('submit', function (event) {
      const button = event.submitter instanceof HTMLButtonElement
        ? event.submitter
        : form.querySelector('button[type="submit"], button:not([type])');
      if (button && !button.disabled) SDS.showButtonLoading(button);
      queueMicrotask(() => {
        if (event.defaultPrevented) SDS.hideButtonLoading(button);
      });
    });
  });

  runWhenIdle(() => {
    if (scope !== document && !scope.isConnected) return;
    SDS.initializeDataTables(scope);

    scope.querySelectorAll('form[data-live-search]').forEach(form => {
      if (form.dataset.sdsTableSearchInitialized === 'true' || form.dataset.noPageReload === 'true') return;
      const input = form.querySelector('input[name="search"]');
      const tableSelector = form.dataset.liveSearchTable;
      const table = tableSelector ? scope.querySelector(tableSelector) : null;
      if (!input || !table) return;

      form.dataset.sdsTableSearchInitialized = 'true';
      const applySearch = () => {
        if (window.jQuery && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(table)) {
          jQuery(table).DataTable().search(input.value.trim()).draw();
          return;
        }

        const term = input.value.trim().toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
          if (!row.querySelector('[colspan]')) {
            const text = row.dataset.searchText || row.textContent;
            row.hidden = term !== '' && !text.toLowerCase().includes(term);
          }
        });
      };

      form.addEventListener('submit', event => {
        event.preventDefault();
        applySearch();
      });
      input.addEventListener('input', applySearch);
      applySearch();
    });

    if (window.flatpickr) {
      scope.querySelectorAll('.datepicker, input[type="date"], input[type="datetime-local"]').forEach(input => {
        if (!input._flatpickr) flatpickr(input);
      });
    }

    if (window.jQuery && jQuery.fn.select2) {
      scope.querySelectorAll('.select2, select.form-select').forEach(select => {
        if (jQuery(select).hasClass('select2-hidden-accessible')) return;
        const modal = select.closest('.modal');
        jQuery(select).select2({
          width: '100%',
          theme: 'bootstrap-5',
          minimumResultsForSearch: select.dataset.select2Search === 'true'
            ? 0
            : select.options && select.options.length > 8 ? 0 : Infinity,
          ...(modal ? { dropdownParent: jQuery(modal) } : {}),
        });
      });
    }
  }, 900);
};

SDS.initializeDataTables = function (root) {
  if (!window.jQuery || !jQuery.fn.DataTable) return;

  root.querySelectorAll('table.data-table, table.js-data-table, .content-wrapper table').forEach(table => {
    if (table.dataset.noDataTable === 'true' || table.closest('[data-no-data-table="true"]')) return;
    if (!table.tHead || table.querySelector('td[colspan], th[colspan]')) return;
    if (jQuery.fn.DataTable.isDataTable(table)) return;

    const rowCount = table.tBodies[0] ? table.tBodies[0].rows.length : 0;
    const shouldExport = table.classList.contains('data-table')
      || table.classList.contains('js-data-table')
      || table.dataset.export === 'true'
      || rowCount >= 8;
    const options = {
      pageLength: Number(window.SDS.paginationPerPage || 25),
      deferRender: true,
      order: [],
      responsive: true,
      autoWidth: false,
      language: {
        search: '',
        searchPlaceholder: 'Search records...',
        lengthMenu: 'Show _MENU_',
      },
    };

    if (table.dataset.externalTableSearch === 'true') {
      options.dom = "<'row'<'col-12'tr>>" +
        "<'row align-items-center mt-3'<'col-md-5'i><'col-md-7'p>>";
    }
    if (table.dataset.noTableSearch === 'true') options.searching = false;

    if (shouldExport && jQuery.fn.dataTable.Buttons) {
      options.dom = table.dataset.externalTableSearch === 'true'
        ? "<'row align-items-center mb-3'<'col-12'B>>" +
          "<'row'<'col-12'tr>>" +
          "<'row align-items-center mt-3'<'col-md-5'i><'col-md-7'p>>"
        : "<'row align-items-center mb-3'<'col-md-6'B><'col-md-6'f>>" +
          "<'row'<'col-12'tr>>" +
          "<'row align-items-center mt-3'<'col-md-5'i><'col-md-7'p>>";
      options.buttons = [
        { extend: 'copyHtml5', className: 'btn btn-sm btn-outline-secondary', text: '<i class="bi bi-clipboard me-1"></i>Copy' },
        { extend: 'csvHtml5', className: 'btn btn-sm btn-outline-success', text: '<i class="bi bi-filetype-csv me-1"></i>CSV' },
        { extend: 'excelHtml5', className: 'btn btn-sm btn-outline-primary', text: '<i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel' },
        { extend: 'pdfHtml5', className: 'btn btn-sm btn-outline-danger', text: '<i class="bi bi-file-earmark-pdf me-1"></i>PDF' },
        { extend: 'print', className: 'btn btn-sm btn-outline-info', text: '<i class="bi bi-printer me-1"></i>Print' },
      ];
      if (table.dataset.noCopyExport === 'true') {
        options.buttons = options.buttons.filter(button => button.extend !== 'copyHtml5');
      }
      if (table.dataset.exportExcludeLastColumn === 'true') {
        const exportableColumns = Array.from({ length: table.tHead.rows[0].cells.length - 1 }, (_, index) => index);
        options.buttons.forEach(button => {
          button.exportOptions = { columns: exportableColumns };
        });
      }
    }

    jQuery(table).DataTable(options);
  });
};

const sdsNavigationCache = new Map();
const sdsPageScriptLoads = new Map();
const sdsInlinePageListeners = new Map();
const sdsInlinePageGlobals = new Map();
let sdsCurrentAjaxUrl = window.location.href;
let sdsNavigationRequestId = 0;

SDS.initializeAjaxNavigation = function () {
  history.replaceState({ ...(history.state || {}), sdsAjaxNavigation: true }, '', window.location.href);

  document.addEventListener('click', function (event) {
    const target = event.target instanceof Element ? event.target : null;
    const link = target?.closest('a.sidebar-link[href]');
    if (!link || event.defaultPrevented || event.button !== 0
      || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
      || link.hasAttribute('target') || link.hasAttribute('download')) {
      return;
    }

    const url = new URL(link.href, window.location.href);
    if (!SDS.isAjaxSidebarRoute(url) || url.href === sdsCurrentAjaxUrl) return;

    event.preventDefault();
    SDS.navigateSidebar(url).catch(error => {
      console.error('Sidebar navigation failed; falling back to a full page load.', error);
      window.location.assign(url.href);
    });
  });

  window.addEventListener('popstate', function (event) {
    if (!event.state?.sdsAjaxNavigation || window.location.href === sdsCurrentAjaxUrl) return;

    const url = new URL(window.location.href);
    SDS.navigateSidebar(url, { updateHistory: false, restoreScroll: true }).catch(error => {
      console.error('Back navigation failed; falling back to a full page load.', error);
      window.location.assign(url.href);
    });
  });
};

SDS.isAjaxSidebarRoute = function (url) {
  if (url.origin !== window.location.origin) return false;
  return url.searchParams.has('route');
};

SDS.navigateSidebar = async function (url, options) {
  const requestId = ++sdsNavigationRequestId;
  const settings = options || {};
  const cachedPage = sdsNavigationCache.get(url.href);
  let page;

  if (cachedPage && Date.now() - cachedPage.savedAt < 30000) {
    page = cachedPage;
    page.fromCache = true;
    sdsNavigationCache.delete(url.href);
  } else {
    const response = await fetch(url.href, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!response.ok) {
      throw new Error('Navigation request failed: HTTP ' + response.status);
    }

    const html = await response.text();
    const parsedPage = new DOMParser().parseFromString(html, 'text/html');
    const mainContent = parsedPage.querySelector('.main-content');
    const sidebar = parsedPage.querySelector('.sidebar');
    if (!mainContent || !sidebar) {
      throw new Error('Navigation response did not contain the application shell.');
    }

    page = {
      mainContent: document.importNode(mainContent, true),
      activeLinks: Array.from(sidebar.querySelectorAll('a.sidebar-link.active'), activeLink => activeLink.href),
      title: parsedPage.title,
      styles: SDS.getPageStyles(parsedPage, response.url),
      scripts: Array.from(parsedPage.scripts, script => ({
        src: script.getAttribute('src')
          ? new URL(script.getAttribute('src'), response.url).href
          : null,
        type: script.getAttribute('type') || '',
        nonce: script.nonce || '',
        code: script.textContent || '',
      })),
      savedAt: Date.now(),
      scrollY: 0,
      fromCache: false,
    };
  }

  if (requestId !== sdsNavigationRequestId) return;

  const currentMain = document.querySelector('.main-content');
  if (!currentMain || !document.querySelector('.sidebar')) throw new Error('Current page shell was not found.');

  SDS.disposePageContent(currentMain);
  sdsNavigationCache.set(sdsCurrentAjaxUrl, {
    mainContent: currentMain,
    activeLinks: Array.from(document.querySelectorAll('.sidebar a.sidebar-link.active'), link => link.href),
    title: document.title,
    styles: SDS.getPageStyles(document, window.location.href),
    savedAt: Date.now(),
    scrollY: window.scrollY,
  });
  sdsNavigationCache.forEach((cached, cachedUrl) => {
    if (Date.now() - cached.savedAt >= 30000) {
      sdsNavigationCache.delete(cachedUrl);
      SDS.clearInlinePageListeners(cachedUrl);
    }
  });

  if (settings.updateHistory !== false) {
    history.pushState({ sdsAjaxNavigation: true }, '', url.href);
  }
  sdsCurrentAjaxUrl = url.href;

  currentMain.replaceWith(page.mainContent);
  document.title = page.title;
  SDS.syncPageStyles(page.styles);
  SDS.setSidebarCollapsed(window.localStorage.getItem('sds.sidebarCollapsed') === 'true');
  document.querySelectorAll('.sidebar a.sidebar-link').forEach(link => {
    const active = page.activeLinks.includes(link.href);
    link.classList.toggle('active', active);
    if (active) {
      link.setAttribute('aria-current', 'page');
    } else {
      link.removeAttribute('aria-current');
    }
  });
  document.querySelector('.sidebar')?.classList.remove('show');
  document.getElementById('sidebarToggle')?.setAttribute('aria-expanded', 'false');
  window.scrollTo(0, settings.restoreScroll ? page.scrollY : 0);

  if (!page.fromCache) {
    SDS.initializePageContent(page.mainContent);
    await SDS.loadPageScripts(page.scripts, url.href, page.mainContent, requestId);
    if (requestId !== sdsNavigationRequestId) return;
    SDS.dispatchPageLoaded(url.href);
  } else {
    SDS.initializePageContent(page.mainContent);
    SDS.dispatchPageRestored(url.href);
  }
};

SDS.getPageStyles = function (root, baseUrl) {
  return Array.from(root.querySelectorAll('link[rel="stylesheet"][href]'))
    .map(link => new URL(link.getAttribute('href'), baseUrl).href)
    .filter(href => SDS.isManagedPageStyle(href));
};

SDS.isManagedPageStyle = function (href) {
  const url = new URL(href, window.location.href);
  if (url.origin !== window.location.origin || !url.pathname.includes('/assets/css/')) return false;
  return !['style.css', 'dashboard.css', 'responsive.css'].includes(url.pathname.split('/').pop());
};

SDS.syncPageStyles = function (styles) {
  document.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
    if (SDS.isManagedPageStyle(link.href)) link.remove();
  });

  const responsiveStylesheet = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
    .find(link => link.href.endsWith('/assets/css/responsive.css'));
  styles.forEach(href => {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    if (responsiveStylesheet) {
      document.head.insertBefore(link, responsiveStylesheet);
    } else {
      document.head.appendChild(link);
    }
  });
};

SDS.loadPageScripts = async function (scripts, pageUrl, pageContent, requestId) {
  SDS.clearInlinePageListeners(pageUrl);
  for (const script of scripts) {
    if (requestId !== undefined && requestId !== sdsNavigationRequestId) return;
    if (script.type && !['text/javascript', 'application/javascript', 'module'].includes(script.type)) {
      continue;
    }
    if (script.src) {
      const pendingLoad = sdsPageScriptLoads.get(script.src);
      const alreadyLoaded = Array.from(document.scripts).some(loaded => loaded.src === script.src);
      if (pendingLoad) {
        await pendingLoad;
      } else if (!alreadyLoaded) {
        await SDS.loadPageScript(script);
      }
      if (requestId !== undefined && requestId !== sdsNavigationRequestId) return;
    } else if (script.code.trim()) {
      SDS.executeInlinePageScript(script, pageUrl, pageContent);
    }
  }
};

SDS.clearInlinePageListeners = function (pageUrl) {
  const listeners = sdsInlinePageListeners.get(pageUrl) || [];
  listeners.forEach(remove => remove());
  sdsInlinePageListeners.delete(pageUrl);
};

SDS.registerInlinePageHandler = function (pageUrl, name, handler) {
  const current = sdsInlinePageGlobals.get(name);
  const original = current ? current.original : Object.getOwnPropertyDescriptor(window, name);
  window[name] = handler;
  sdsInlinePageGlobals.set(name, { owner: pageUrl, original: original || null, handler: handler });

  const listeners = sdsInlinePageListeners.get(pageUrl) || [];
  listeners.push(() => {
    const registered = sdsInlinePageGlobals.get(name);
    if (!registered || registered.owner !== pageUrl || window[name] !== registered.handler) return;
    if (registered.original) {
      Object.defineProperty(window, name, registered.original);
    } else {
      delete window[name];
    }
    sdsInlinePageGlobals.delete(name);
  });
  sdsInlinePageListeners.set(pageUrl, listeners);
};

SDS.loadPageScript = function (scriptInfo) {
  if (sdsPageScriptLoads.has(scriptInfo.src)) return sdsPageScriptLoads.get(scriptInfo.src);

  const loadPromise = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    if (scriptInfo.type) script.type = scriptInfo.type;
    if (scriptInfo.nonce) script.nonce = scriptInfo.nonce;
    script.async = false;
    script.src = scriptInfo.src;
    script.onload = resolve;
    script.onerror = () => {
      script.remove();
      reject(new Error('Failed to load page script: ' + scriptInfo.src));
    };
    document.body.appendChild(script);
  });
  sdsPageScriptLoads.set(scriptInfo.src, loadPromise);
  loadPromise.catch(() => sdsPageScriptLoads.delete(scriptInfo.src));
  return loadPromise;
};

SDS.executeInlinePageScript = function (scriptInfo, pageUrl, pageContent) {
  const originalDocumentAdd = document.addEventListener;
  const originalDocumentRemove = document.removeEventListener;
  const originalWindowAdd = window.addEventListener;
  const originalWindowRemove = window.removeEventListener;
  const documentListenerMap = new Map();
  const windowListenerMap = new Map();
  const pageListeners = sdsInlinePageListeners.get(pageUrl) || [];
  sdsInlinePageListeners.set(pageUrl, pageListeners);
  const inlineHandlerNames = new Set();
  const collectInlineHandlerNames = source => {
    const handlerPattern = /\bon[a-z]+\s*=\s*["']\s*(?:return\s+)?([A-Za-z_$][\w$]*)\s*\(/gi;
    let match;
    while ((match = handlerPattern.exec(source)) !== null) {
      inlineHandlerNames.add(match[1]);
    }
  };
  if (pageContent) {
    pageContent.querySelectorAll('*').forEach(element => {
      Array.from(element.attributes).forEach(attribute => {
        if (attribute.name.startsWith('on')) {
          const handler = attribute.value.match(/^\s*(?:return\s+)?([A-Za-z_$][\w$]*)\s*(?:\(|$)/);
          if (handler) inlineHandlerNames.add(handler[1]);
        }
      });
    });
  }
  collectInlineHandlerNames(scriptInfo.code);
  document.addEventListener = function (type, listener, options) {
    if (type !== 'DOMContentLoaded') return originalDocumentAdd.call(this, type, listener, options);
    const capture = typeof options === 'boolean' ? options : Boolean(options && options.capture);
    let hasRun = false;
    const wrapped = event => {
      if (event.detail?.url !== pageUrl || (options && options.once && hasRun)) return;
      hasRun = true;
      if (options && options.once) {
        originalDocumentRemove.call(document, 'sds:page-loaded', wrapped, capture);
      }
      listener.call(document, event);
    };
    const listenerOptions = typeof options === 'object' && options !== null
      ? { capture: capture, signal: options.signal }
      : capture;
    pageListeners.push(() => originalDocumentRemove.call(document, 'sds:page-loaded', wrapped, capture));
    documentListenerMap.set(listener, wrapped);
    originalDocumentAdd.call(document, 'sds:page-loaded', wrapped, listenerOptions);
  };
  document.removeEventListener = function (type, listener, options) {
    if (type === 'DOMContentLoaded' && documentListenerMap.has(listener)) {
      const wrapped = documentListenerMap.get(listener);
      originalDocumentRemove.call(this, 'sds:page-loaded', wrapped, options);
      documentListenerMap.delete(listener);
      return;
    }
    return originalDocumentRemove.call(this, type, listener, options);
  };
  window.addEventListener = function (type, listener, options) {
    if (type !== 'DOMContentLoaded') return originalWindowAdd.call(this, type, listener, options);
    const capture = typeof options === 'boolean' ? options : Boolean(options && options.capture);
    let hasRun = false;
    const wrapped = event => {
      if (event.detail?.url !== pageUrl || (options && options.once && hasRun)) return;
      hasRun = true;
      if (options && options.once) {
        originalWindowRemove.call(window, 'sds:page-loaded', wrapped, capture);
      }
      listener.call(window, event);
    };
    const listenerOptions = typeof options === 'object' && options !== null
      ? { capture: capture, signal: options.signal }
      : capture;
    pageListeners.push(() => originalWindowRemove.call(window, 'sds:page-loaded', wrapped, capture));
    windowListenerMap.set(listener, wrapped);
    originalWindowAdd.call(window, 'sds:page-loaded', wrapped, listenerOptions);
  };
  window.removeEventListener = function (type, listener, options) {
    if (type === 'DOMContentLoaded' && windowListenerMap.has(listener)) {
      const wrapped = windowListenerMap.get(listener);
      originalWindowRemove.call(this, 'sds:page-loaded', wrapped, options);
      windowListenerMap.delete(listener);
      return;
    }
    return originalWindowRemove.call(this, type, listener, options);
  };

  try {
    const script = document.createElement('script');
    if (scriptInfo.type) script.type = scriptInfo.type;
    if (scriptInfo.nonce) script.nonce = scriptInfo.nonce;
    const exportedHandlers = Array.from(inlineHandlerNames)
      .filter(name => new RegExp('\\b(?:function|const|let|var)\\s+' + name.replace(/\$/g, '\\$') + '\\b').test(scriptInfo.code))
      .map(name => 'if (typeof ' + name + ' === "function") window.SDS.registerInlinePageHandler('
        + JSON.stringify(pageUrl) + ', ' + JSON.stringify(name) + ', ' + name + ');')
      .join('\n');
    // Isolate page-local declarations but keep legacy inline event handlers reachable.
    script.textContent = scriptInfo.type === 'module'
      ? scriptInfo.code + '\n' + exportedHandlers
      : '(function () {\n' + scriptInfo.code + '\n' + exportedHandlers + '\n})();';
    document.head.appendChild(script);
    script.remove();
  } finally {
    document.addEventListener = originalDocumentAdd;
    document.removeEventListener = originalDocumentRemove;
    window.addEventListener = originalWindowAdd;
    window.removeEventListener = originalWindowRemove;
  }
  sdsInlinePageListeners.set(pageUrl, pageListeners);
};

SDS.dispatchPageLoaded = function (url) {
  const detail = { url: url };
  document.dispatchEvent(new CustomEvent('sds:page-loaded', { detail: detail }));
  window.dispatchEvent(new CustomEvent('sds:page-loaded', { detail: detail }));
};

SDS.dispatchPageRestored = function (url) {
  const detail = { url: url };
  document.dispatchEvent(new CustomEvent('sds:page-restored', { detail: detail }));
  window.dispatchEvent(new CustomEvent('sds:page-restored', { detail: detail }));
};
