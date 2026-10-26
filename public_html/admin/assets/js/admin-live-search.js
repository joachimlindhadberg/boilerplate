/* Boilerplate CMS: shared server-side live search for list pages (V2.2.9).
 * Existing GET forms remain usable with Enter if JavaScript is unavailable.
 * The HTML is rendered by the same permission-checked PHP list endpoints.
 */
(() => {
  'use strict';
  const panel = document.querySelector('[data-admin-live-list]');
  const form = panel?.querySelector('form[data-admin-live-search]');
  const input = form?.querySelector('input[type="search"][name="q"]');
  const toolbar = panel?.querySelector('.admin-list-toolbar');
  if (!panel || !form || !toolbar || !input || !window.fetch || !window.DOMParser) return;

  const translate = (key) => typeof window.cmsTranslate === 'function' ? window.cmsTranslate(key) : key;
  const clear = document.createElement('button');
  clear.type = 'button';
  clear.className = 'admin-list-search__clear';
  clear.setAttribute('aria-label', translate('Nulstil'));
  clear.title = translate('Nulstil');
  clear.textContent = '×';
  clear.hidden = input.value.length === 0;
  const searchLabel = input.closest('label');
  if (!searchLabel) return;
  searchLabel.after(clear);
  // Show only the Core reset control while enhanced. Without JS, retain the
  // browser's native search-clear affordance as a progressive fallback.
  form.classList.add('admin-list-search--enhanced');

  const feedback = document.createElement('span');
  feedback.className = 'admin-list-search__error';
  feedback.setAttribute('role', 'status');
  feedback.setAttribute('aria-live', 'polite');
  feedback.hidden = true;
  form.appendChild(feedback);

  let timer = null;
  let pending = null;
  let serial = 0;
  const currentPath = location.pathname;

  const currentUrl = () => new URL(window.location.href);
  const validListUrl = (url) => url.origin === location.origin && url.pathname === currentPath &&
    url.searchParams.get('mode') === currentUrl().searchParams.get('mode') &&
    url.searchParams.get('screen') === currentUrl().searchParams.get('screen');

  const urlFromForm = () => {
    const url = new URL(form.action, location.href);
    const data = new FormData(form);
    for (const [key, value] of data.entries()) {
      if (typeof value === 'string' && value !== '') url.searchParams.set(key, value);
    }
    // Changing the query or filters always returns to the first results page.
    url.searchParams.delete('page');
    return url;
  };

  const syncFormWithUrl = (url) => {
    for (const element of form.elements) {
      if (!element.name || !('value' in element)) continue;
      if (element instanceof HTMLInputElement && element.type === 'hidden') {
        element.value = url.searchParams.get(element.name) || '';
      } else if (element instanceof HTMLInputElement || element instanceof HTMLSelectElement) {
        element.value = url.searchParams.get(element.name) || '';
      }
    }
    // Tabs may introduce a hidden status field missing from the initial (All) form.
    for (const name of ['status', 'type']) {
      if (url.searchParams.has(name) && !form.querySelector(`input[type="hidden"][name="${name}"]`) &&
          !form.querySelector(`select[name="${name}"]`)) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        hidden.value = url.searchParams.get(name) || '';
        form.prepend(hidden);
      }
    }
    clear.hidden = input.value.length === 0;
  };

  const replaceListContents = (fresh) => {
    const oldToolbar = panel.querySelector('.admin-list-toolbar');
    const newToolbar = fresh.querySelector('.admin-list-toolbar');
    if (!oldToolbar || !newToolbar) throw new Error('Missing list toolbar');
    // Keep the live search input mounted and focused; update tab counts/status only.
    const oldTabs = oldToolbar.querySelector('.admin-filter-tabs');
    const newTabs = newToolbar.querySelector('.admin-filter-tabs');
    if (oldTabs && newTabs) oldTabs.replaceChildren(...Array.from(newTabs.childNodes).map(n => document.importNode(n, true)));
    const newForm = newToolbar.querySelector('form[data-admin-live-search]');
    if (newForm) {
      form.querySelectorAll('input[type="hidden"]').forEach(field => field.remove());
      newForm.querySelectorAll('input[type="hidden"]').forEach(field => form.prepend(document.importNode(field, true)));
    }
    const oldChildren = Array.from(panel.children);
    const start = oldChildren.indexOf(oldToolbar);
    if (start < 0) throw new Error('Missing toolbar in panel');
    const newChildren = Array.from(fresh.children);
    const newStart = newChildren.indexOf(newToolbar);
    const updated = newChildren.slice(newStart + 1).map(node => document.importNode(node, true));
    oldChildren.slice(start + 1).forEach(node => node.remove());
    panel.append(...updated);
    panel.dispatchEvent(new CustomEvent('admin:live-search:updated', {bubbles: true}));
  };

  const load = async (url, historyMode = 'replace') => {
    clearTimeout(timer);
    if (pending) pending.abort();
    const requestId = ++serial;
    const controller = new AbortController();
    pending = controller;
    feedback.textContent = translate('ui.live_search_loading');
    feedback.classList.add('sr-only');
    feedback.hidden = false;
    panel.classList.add('is-live-loading');
    panel.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(url.href, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'},
        signal: controller.signal,
      });
      if (!response.ok || !response.headers.get('content-type')?.toLowerCase().includes('text/html')) throw new Error('Invalid response');
      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      const fresh = doc.querySelector('[data-admin-live-list]');
      if (!fresh || response.url && !validListUrl(new URL(response.url))) {
        // Session expiry or redirect to a different admin view: normal navigation.
        location.assign(url.href);
        return;
      }
      if (requestId !== serial) return;
      replaceListContents(fresh);
      feedback.hidden = true;
      feedback.classList.remove('sr-only');
      if (historyMode === 'push') history.pushState({cmsLiveList: true}, '', url.href);
      else if (historyMode === 'replace') history.replaceState({cmsLiveList: true}, '', url.href);
      // Popstate already changed the browser URL.
    } catch (error) {
      if (error.name === 'AbortError' || requestId !== serial) return;
      feedback.classList.remove('sr-only');
      feedback.textContent = translate('ui.live_search_error');
      feedback.hidden = false;
    } finally {
      if (requestId === serial) {
        pending = null;
        panel.classList.remove('is-live-loading');
        panel.removeAttribute('aria-busy');
      }
    }
  };

  const runSearch = () => load(urlFromForm(), 'replace');
  input.addEventListener('input', () => {
    clear.hidden = !input.value;
    clearTimeout(timer);
    if (pending) {
      serial++;
      pending.abort();
      pending = null;
      panel.classList.remove('is-live-loading');
      panel.removeAttribute('aria-busy');
    }
    timer = window.setTimeout(runSearch, 300);
  });
  clear.addEventListener('click', () => {
    input.value = '';
    clear.hidden = true;
    runSearch();
    input.focus();
  });
  form.addEventListener('change', (event) => {
    if (event.target.matches('select')) runSearch();
  });
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    runSearch();
  });
  panel.addEventListener('click', (event) => {
    const anchor = event.target.closest('.admin-filter-tabs a, .admin-pagination a');
    if (!anchor || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0 || anchor.target || anchor.hasAttribute('download') || anchor.getAttribute('aria-disabled') === 'true') return;
    const url = new URL(anchor.href, location.href);
    if (!validListUrl(url)) return;
    event.preventDefault();
    syncFormWithUrl(url);
    load(url, 'push');
  });
  window.addEventListener('popstate', () => {
    const url = new URL(location.href);
    if (!validListUrl(url)) return;
    syncFormWithUrl(url);
    load(url, 'pop');
  });
})();
