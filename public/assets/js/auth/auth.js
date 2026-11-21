// auth.js - auth page UX: password visibility toggle and caps-lock warning.
document.addEventListener('DOMContentLoaded', function () {
  const passwordInputs = document.querySelectorAll('input[type="password"]');

  passwordInputs.forEach(function (passwordInput) {
    const inputGroup = passwordInput.closest('.input-group');

    if (!inputGroup || inputGroup.querySelector('.password-toggle-btn')) {
      return;
    }

    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'btn btn-outline-secondary password-toggle-btn';
    toggleBtn.innerHTML = '<i class="bi bi-eye"></i>';
    toggleBtn.setAttribute('aria-label', 'Show password');
    inputGroup.appendChild(toggleBtn);

    toggleBtn.addEventListener('click', function () {
      const showing = passwordInput.type === 'text';
      passwordInput.type = showing ? 'password' : 'text';
      toggleBtn.innerHTML = showing ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
      toggleBtn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });

    const capsWarning = document.createElement('div');
    capsWarning.className = 'text-warning small mt-1 d-none';
    capsWarning.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Caps Lock is on';
    inputGroup.parentNode.insertBefore(capsWarning, inputGroup.nextSibling);

    passwordInput.addEventListener('keyup', function (e) {
      const capsOn = typeof e.getModifierState === 'function' && e.getModifierState('CapsLock');
      capsWarning.classList.toggle('d-none', !capsOn);
    });
  });

  const emailInput = document.querySelector('input[type="email"][name="email"]');
  if (emailInput && !emailInput.value && document.activeElement === document.body) {
    emailInput.focus();
  }

  document.addEventListener('click', function (e) {
    const target = e.target;
    const toggle = target.closest && target.closest('.password-toggle');

    if (!toggle) {
      return;
    }

    const input = document.querySelector(toggle.getAttribute('data-target'));
    if (!input) {
      return;
    }

    if (input.type === 'password') {
      input.type = 'text';
      toggle.classList.add('active');
    } else {
      input.type = 'password';
      toggle.classList.remove('active');
    }
  });

  const authButtonStates = new WeakMap();
  const restoreAuthButton = function (button) {
    const state = authButtonStates.get(button);
    if (!state) return;

    button.innerHTML = state.html;
    button.disabled = state.disabled;
    if (state.ariaBusy === null) {
      button.removeAttribute('aria-busy');
    } else {
      button.setAttribute('aria-busy', state.ariaBusy);
    }
    button.removeAttribute('data-auth-loading');
    authButtonStates.delete(button);
  };

  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      const button = event.submitter instanceof HTMLButtonElement
        ? event.submitter
        : form.querySelector('button[type="submit"], button:not([type])');
      if (!(button instanceof HTMLButtonElement) || button.disabled || authButtonStates.has(button)) {
        return;
      }

      authButtonStates.set(button, {
        html: button.innerHTML,
        disabled: button.disabled,
        ariaBusy: button.getAttribute('aria-busy'),
      });
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.setAttribute('data-auth-loading', 'true');
      button.innerHTML = '<span class="startup-spinner startup-button-spinner me-1" aria-hidden="true"></span> ' + button.textContent.trim();

      queueMicrotask(function () {
        if (event.defaultPrevented) {
          restoreAuthButton(button);
          return;
        }

        const actionUrl = new URL(form.action, window.location.href);
        if (actionUrl.pathname.toLowerCase().endsWith('/login.php')) {
          const loader = document.getElementById('sessionTransitionLoader');
          const message = loader && loader.querySelector('[data-session-transition-message]');
          if (message) message.textContent = 'Signing in...';
          if (loader) loader.hidden = false;
        }
      });
    });
  });

  window.addEventListener('pageshow', function () {
    document.querySelectorAll('button[data-auth-loading]').forEach(restoreAuthButton);
    const loader = document.getElementById('sessionTransitionLoader');
    if (loader) loader.hidden = true;
  });
});
