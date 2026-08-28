// Shared app behavior + a small SDS namespace other page-specific scripts build on.
window.SDS = window.SDS || {};

document.addEventListener('DOMContentLoaded', function () {
  SDS.hidePortalStartupLoader();
  SDS.enhancePageChrome();

  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('show'));
    // Close sidebar on outside click when open (mobile)
    document.addEventListener('click', (e) => {
      if (sidebar.classList.contains('show') && !sidebar.contains(e.target) && e.target !== toggle) {
        sidebar.classList.remove('show');
      }
    });
  }

  // Auto-init useful tables. Empty-state tables are kept plain because colspan
  // rows make DataTables report "Incorrect column count".
  if (window.jQuery && jQuery.fn.DataTable) {
    jQuery('table.data-table, table.js-data-table, .content-wrapper table').each(function () {
      const table = this;
      if (table.dataset.noDataTable === 'true' || table.closest('[data-no-data-table="true"]')) {
        return;
      }

      if (!table.tHead || table.querySelector('td[colspan], th[colspan]')) {
        return;
      }

      if (!jQuery.fn.DataTable.isDataTable(table)) {
        const rowCount = table.tBodies[0] ? table.tBodies[0].rows.length : 0;
        const shouldExport = table.classList.contains('data-table')
          || table.classList.contains('js-data-table')
          || table.dataset.export === 'true'
          || rowCount >= 8;

        const options = {
          pageLength: 10,
          order: [],
          responsive: true,
          autoWidth: false,
          language: {
            search: '',
            searchPlaceholder: 'Search records...',
            lengthMenu: 'Show _MENU_',
          },
        };

        if (shouldExport && jQuery.fn.dataTable.Buttons) {
          options.dom = "<'row align-items-center mb-3'<'col-md-6'B><'col-md-6'f>>" +
            "<'row'<'col-12'tr>>" +
            "<'row align-items-center mt-3'<'col-md-5'i><'col-md-7'p>>";
          options.buttons = [
            { extend: 'copyHtml5', className: 'btn btn-sm btn-outline-secondary', text: '<i class="bi bi-clipboard me-1"></i>Copy' },
            { extend: 'csvHtml5', className: 'btn btn-sm btn-outline-success', text: '<i class="bi bi-filetype-csv me-1"></i>CSV' },
            { extend: 'excelHtml5', className: 'btn btn-sm btn-outline-primary', text: '<i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel' },
            { extend: 'pdfHtml5', className: 'btn btn-sm btn-outline-danger', text: '<i class="bi bi-filetype-pdf me-1"></i>PDF' },
            { extend: 'print', className: 'btn btn-sm btn-outline-info', text: '<i class="bi bi-printer me-1"></i>Print' },
          ];
        }

        jQuery(table).DataTable(options);
      }
    });
  }

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
        jQuery(this).select2({
          width: '100%',
          theme: 'bootstrap-5',
          minimumResultsForSearch: this.options && this.options.length > 8 ? 0 : Infinity,
        });
      }
    });
  }

  // Generic delete-confirm buttons: <button data-confirm data-action="url" data-message="...">
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      const action = this.dataset.action;
      const message = this.dataset.message || 'This action cannot be undone.';
      SDS.confirmThen(message, () => SDS.submitAction(action));
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
    form.addEventListener('submit', function () {
      if (!form.closest('[data-disable-loading]')) {
        SDS.showLoading();
      }
      const btn = form.querySelector('button[type="submit"], button:not([type])');
      if (btn && !btn.disabled) {
        btn.dataset.originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + btn.textContent.trim();
      }
    });
  });

  // Flash toast on load, if server injected window.SDS_FLASH
  if (window.SDS_FLASH) {
    SDS.toast(SDS_FLASH.type || 'success', SDS_FLASH.message);
  }

  document.querySelectorAll('a[href]:not([target]):not([download])').forEach(link => {
    link.addEventListener('click', function () {
      const href = link.getAttribute('href') || '';
      if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) {
        return;
      }

      const url = new URL(href, window.location.href);
      if (url.origin === window.location.origin) {
        SDS.showPortalStartupLoader('Opening page...');
      }
    });
  });
});

window.addEventListener('pageshow', function () {
  SDS.hidePortalStartupLoader();
  SDS.hideLoading();
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

SDS.showLoading = function (message) {
  const spinner = document.getElementById('loadingSpinner');
  if (!spinner) return;
  
  // Update loading message if provided
  if (message) {
    const msgEl = spinner.querySelector('.small.text-muted');
    if (msgEl) msgEl.textContent = message;
  }
  
  spinner.hidden = false;
  spinner.classList.remove('d-none');
  spinner.classList.add('d-flex');
  spinner.style.setProperty('display', 'flex', 'important');
  document.body.style.overflow = 'hidden'; // Prevent scrolling during loading
};

SDS.hideLoading = function () {
  const spinner = document.getElementById('loadingSpinner');
  if (spinner) {
    spinner.hidden = true;
    spinner.classList.add('d-none');
    spinner.classList.remove('d-flex');
    spinner.style.setProperty('display', 'none', 'important');
  }
  document.body.style.overflow = 'auto'; // Restore scrolling
};

SDS.showPortalStartupLoader = function (message) {
  const loader = document.getElementById('portalStartupLoader');
  if (!loader) return;
  const text = loader.querySelector('.portal-startup-text');
  if (text && message) text.textContent = message;
  loader.classList.remove('is-hidden');
  loader.hidden = false;
};

SDS.hidePortalStartupLoader = function () {
  const loader = document.getElementById('portalStartupLoader');
  if (!loader) return;
  const startedAt = Number(loader.dataset.startedAt || Date.now());
  if (!loader.dataset.startedAt) loader.dataset.startedAt = String(startedAt);
  const remaining = Math.max(0, 650 - (Date.now() - startedAt));

  window.setTimeout(function () {
    loader.classList.add('is-hidden');
    window.setTimeout(function () {
      loader.hidden = true;
    }, 450);
  }, remaining);
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
SDS.postJSON = async function (url, data, options) {
  options = options || {};
  const showSpinner = options.showLoading !== false; // Default true
  
  if (showSpinner) SDS.showLoading(options.loadingMessage);
  
  try {
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(data || {}),
    });
    if (!res.ok) throw new Error('Request failed: ' + res.status);
    const result = await res.json();
    if (showSpinner) SDS.hideLoading();
    return result;
  } catch (e) {
    if (showSpinner) SDS.hideLoading();
    throw e;
  }
};

SDS.getJSON = async function (url, options) {
  options = options || {};
  const showSpinner = options.showLoading !== false; // Default true
  
  if (showSpinner) SDS.showLoading(options.loadingMessage);
  
  try {
    const res = await fetch(url, { 
      credentials: 'same-origin', 
      headers: { 'X-Requested-With': 'XMLHttpRequest' } 
    });
    if (!res.ok) throw new Error('Request failed: ' + res.status);
    const result = await res.json();
    if (showSpinner) SDS.hideLoading();
    return result;
  } catch (e) {
    if (showSpinner) SDS.hideLoading();
    throw e;
  }
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
