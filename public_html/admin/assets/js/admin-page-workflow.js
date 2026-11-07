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

/** Pages Publishing Visibility QA1: schedule controls follow the chosen status.
 *  Only currently relevant fields are submitted. Values stay in the editor while
 *  the user experiments with statuses, but hidden values never reach the server.
 *  PageScheduler remains the final, authoritative validator on POST.
 */
(() => {
  'use strict';
  const form = document.querySelector('form[data-page-builder-form]');
  if (!form) return;

  const status = form.querySelector('#page-status');
  const publish = form.querySelector('#schedule-publish');
  const unpublish = form.querySelector('#schedule-unpublish');
  const publishField = form.querySelector('[data-page-schedule-field="publish"]');
  const unpublishField = form.querySelector('[data-page-schedule-field="unpublish"]');
  const timezone = form.querySelector('[data-page-schedule-timezone]');
  if (!status || !publish || !unpublish || !publishField || !unpublishField || !timezone) return;

  const show = (field, input, visible) => {
    field.hidden = !visible;
    input.disabled = !visible;
  };
  const sync = () => {
    const draft = status.value === 'draft';
    const published = status.value === 'published';
    // Scheduled unpublishing for a Draft is meaningful only when a publication
    // date is scheduled first. Protected pages have no scheduling controls.
    show(publishField, publish, draft);
    show(unpublishField, unpublish, published || (draft && publish.value.trim() !== ''));
    timezone.hidden = publishField.hidden && unpublishField.hidden;
  };

  status.addEventListener('change', sync);
  publish.addEventListener('input', sync);
  publish.addEventListener('change', sync);
  form.addEventListener('submit', sync);
  sync();
})();

/** Pages Access & Preview QA1: only Protected exposes password settings. */
(() => {
  'use strict';
  const form = document.querySelector('form[data-page-builder-form]');
  if (!form) return;
  const status = form.querySelector('#page-status');
  const controls = form.querySelector('[data-page-access-controls]');
  if (!status || !controls) return;
  const sync = () => {
    const active = status.value === 'protected';
    controls.hidden = !active;
    controls.querySelectorAll('input').forEach(input => { input.disabled = !active; });
  };
  status.addEventListener('change', sync);
  form.addEventListener('submit', sync);
  sync();
})();
