/** V2.4.4z1 – page editor continuation, scoped to saved edits only. */
(() => {
  'use strict';
  const form = document.querySelector('form[data-page-save-scroll]');
  if (!form) return; // New pages open their freshly created editor at the top.

  const id = Number(form.dataset.pageSaveScroll);
  if (!Number.isSafeInteger(id) || id < 1) return;
  const key = 'boilerplate:pages:save-scroll:' + id;
  const maxAge = 5 * 60 * 1000;

  const read = () => {
    try {
      const raw = sessionStorage.getItem(key);
      sessionStorage.removeItem(key); // A normal revisit must never restore an old position.
      if (!raw) return null;
      const saved = JSON.parse(raw);
      if (!saved || !Number.isFinite(saved.y) || saved.y < 0 ||
          !Number.isFinite(saved.at) || Date.now() - saved.at > maxAge || saved.at > Date.now()) return null;
      return saved.y;
    } catch { return null; } // Browsers may block session storage.
  };

  const previous = read();
  if (previous !== null) {
    // Wait for Page Builder initialization and final layout before scrolling.
    const restore = () => requestAnimationFrame(() => requestAnimationFrame(() => {
      window.scrollTo({ top: previous, behavior: 'instant' });
    }));
    if (document.readyState === 'complete') restore();
    else window.addEventListener('load', restore, { once: true });
  }

  form.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    try {
      sessionStorage.setItem(key, JSON.stringify({ y: Math.max(0, window.scrollY), at: Date.now() }));
    } catch { /* Submission must work when session storage is unavailable. */ }
  });

  // Submitting a separate confirmed delete form is intentional; do not prompt
  // a second time for unsaved edits before the browser navigates away.
  const deleteForm = document.querySelector('#page-delete-dialog form');
  deleteForm?.addEventListener('submit', () => {
    form.dispatchEvent(new Event('admin:discard-draft'));
    try { sessionStorage.removeItem(key); } catch { /* no storage available */ }
  });
})();
