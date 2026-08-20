// auth.js - auth page UX: password visibility toggle and caps-lock warning.
document.addEventListener('DOMContentLoaded', function () {
  const startupLoader = document.getElementById('startupLoader');
  if (startupLoader) {
    const startedAt = Date.now();
    const hideStartupLoader = function () {
      const remaining = Math.max(0, 900 - (Date.now() - startedAt));
      window.setTimeout(function () {
        startupLoader.classList.add('is-hidden');
        window.setTimeout(function () {
          startupLoader.remove();
        }, 500);
      }, remaining);
    };

    if (document.readyState === 'complete') {
      hideStartupLoader();
    } else {
      window.addEventListener('load', hideStartupLoader, { once: true });
    }
  }

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

  document.querySelectorAll('form[action="/login.php"]').forEach(function (form) {
    form.addEventListener('submit', function () {
      const loader = document.createElement('div');
      loader.className = 'startup-loader';
      loader.innerHTML = '<div class="startup-loader-card"><div class="startup-logo"><i class="bi bi-shield-lock"></i></div><div class="startup-loader-title">Signing you in</div><div class="startup-loader-text">Checking your account securely...</div><div class="startup-progress" aria-hidden="true"><span></span></div></div>';
      document.body.appendChild(loader);
    });
  });
});
