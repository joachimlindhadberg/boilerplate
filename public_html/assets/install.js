'use strict';
// Standalone installer enhancements. All forms work without JavaScript.
(() => {
  const problem = document.querySelector('[data-install-error][role="alert"]');
  if (problem) problem.focus({ preventScroll: true });

  for (const toggle of document.querySelectorAll('[data-password-toggle]')) {
    const input = document.getElementById(toggle.dataset.passwordToggle);
    if (!(input instanceof HTMLInputElement)) continue;
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      toggle.textContent = show ? 'Hide' : 'Show';
      toggle.setAttribute('aria-pressed', String(show));
      toggle.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} ${input.id === 'db-pass' ? 'database' : 'administrator'} password`);
      input.focus();
    });
  }
})();
