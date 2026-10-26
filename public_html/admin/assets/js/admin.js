(() => {
  'use strict';
  const adminTr = (key) => (typeof window.cmsTranslate === 'function' ? window.cmsTranslate(key) : key);

  // Core owns admin notice behavior. Modules output only .admin-notice markup.
  // Inline notices added by scripts are enhanced with the same close control.
  const mainNotices = document.querySelector('#admin-main');
  if (mainNotices) {
    const enhanceNotice = (notice) => {
      if (!(notice instanceof HTMLElement) || !notice.matches('.admin-notice') || notice.querySelector('[data-admin-notice-dismiss]')) return;
      const close = document.createElement('button');
      close.type = 'button';
      close.className = 'admin-notice__close';
      close.dataset.adminNoticeDismiss = '1';
      close.setAttribute('aria-label', adminTr('notice.dismiss'));
      close.title = adminTr('notice.dismiss');
      // A fixed-size vector avoids font glyph baseline drift inside the close button.
      const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      icon.setAttribute('viewBox', '0 0 24 24');
      icon.setAttribute('aria-hidden', 'true');
      icon.setAttribute('focusable', 'false');
      const strokes = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      strokes.setAttribute('d', 'M18 6 6 18 M6 6 18 18');
      icon.appendChild(strokes);
      close.appendChild(icon);
      notice.appendChild(close);
    };
    mainNotices.querySelectorAll('.admin-notice').forEach(enhanceNotice);
    mainNotices.addEventListener('click', (event) => {
      const close = event.target.closest?.('[data-admin-notice-dismiss]');
      if (close && mainNotices.contains(close)) close.closest('.admin-notice')?.remove();
    });
    // Observe only added elements; avoid reprocessing the full admin tree.
    new MutationObserver((mutations) => {
      for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
          if (!(node instanceof HTMLElement)) continue;
          enhanceNotice(node);
          node.querySelectorAll('.admin-notice').forEach(enhanceNotice);
        }
      }
    }).observe(mainNotices, { childList: true, subtree: true });
  }

  // RC5b: Core-owned, per-card metric menu with authenticated AJAX autosave.
  const cardsRoot = document.querySelector('[data-dashboard-cards]');
  if (cardsRoot instanceof HTMLElement) {
    const cards = Array.from(cardsRoot.querySelectorAll('[data-dashboard-card]'));
    const csrf = cardsRoot.querySelector('[data-dashboard-cards-csrf]');
    const announce = cardsRoot.querySelector('[data-dashboard-cards-status]');
    const notice = document.querySelector('[data-dashboard-cards-notice]');
    const iconTemplates = document.querySelector('[data-dashboard-cards-icons]');
    const endpoint = cardsRoot.dataset.saveUrl;
    let busy = false;
    let openCard = null;

    const closeMenu = (card, restoreFocus = false) => {
      const trigger = card?.querySelector('[data-dashboard-card-trigger]');
      const menu = card?.querySelector('[data-dashboard-card-menu]');
      if (!(trigger instanceof HTMLButtonElement) || !(menu instanceof HTMLElement)) return;
      menu.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      card.classList.remove('is-menu-open');
      if (openCard === card) openCard = null;
      if (restoreFocus) trigger.focus({ preventScroll: true });
    };
    const openMenu = (card) => {
      if (busy) return;
      if (openCard && openCard !== card) closeMenu(openCard);
      const trigger = card.querySelector('[data-dashboard-card-trigger]');
      const menu = card.querySelector('[data-dashboard-card-menu]');
      if (!(trigger instanceof HTMLButtonElement) || !(menu instanceof HTMLElement)) return;
      menu.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
      card.classList.add('is-menu-open');
      openCard = card;
      menu.querySelector('[aria-pressed="true"]')?.focus({ preventScroll: true });
    };
    const renderCards = (records) => {
      records.forEach((record, index) => {
        const card = cards[index];
        if (!(card instanceof HTMLElement)) return;
        const link = card.querySelector('[data-dashboard-card-link]');
        const label = card.querySelector('[data-dashboard-card-label]');
        const count = card.querySelector('[data-dashboard-card-count]');
        const icon = card.querySelector('[data-dashboard-card-icon]');
        const template = iconTemplates?.querySelector(`template[data-dashboard-icon="${record.key}"]`);
        // Keys returned by Core come from the allowlist. Never interpret response text as HTML.
        card.dataset.metric = record.key;
        if (link instanceof HTMLAnchorElement) link.href = record.url;
        if (label) label.textContent = record.label;
        if (count) count.textContent = record.count;
        if (icon && template instanceof HTMLTemplateElement) icon.replaceChildren(template.content.cloneNode(true));
        card.querySelectorAll('[data-dashboard-card-choice]').forEach((choice) => {
          choice.setAttribute('aria-pressed', choice.dataset.dashboardCardChoice === record.key ? 'true' : 'false');
        });
      });
    };
    const saveChoice = async (card, requestedKey) => {
      if (busy || !endpoint || !(csrf instanceof HTMLInputElement) || cards.length !== 4) return;
      const slot = cards.indexOf(card);
      if (slot < 0) return;
      const next = cards.map((element) => element.dataset.metric || '');
      if (next[slot] === requestedKey) { closeMenu(card, true); return; }
      const other = next.indexOf(requestedKey);
      if (other >= 0) next[other] = next[slot]; // Swap instead of duplicate.
      next[slot] = requestedKey;
      if (next.some((key) => key === '') || new Set(next).size !== 4) return;
      busy = true;
      cardsRoot.setAttribute('aria-busy', 'true');
      if (notice instanceof HTMLElement) notice.hidden = true;
      if (announce) announce.textContent = adminTr('dashboard.cards_saving');
      cards.forEach((item) => item.querySelectorAll('button').forEach((button) => { button.disabled = true; }));
      try {
        const body = new URLSearchParams({ csrf_token: csrf.value });
        next.forEach((key) => body.append('cards[]', key));
        const response = await fetch(endpoint, {
          method: 'POST', credentials: 'same-origin', redirect: 'error',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
          body: body.toString()
        });
        const payload = response.ok ? await response.json() : null;
        const allowedKeys = Array.from(cards[0].querySelectorAll('[data-dashboard-card-choice]'), (choice) => choice.dataset.dashboardCardChoice);
        if (!payload?.ok || !Array.isArray(payload.cards) || payload.cards.length !== 4 ||
            payload.cards.some((item) => !item || !allowedKeys.includes(item.key) ||
              typeof item.label !== 'string' || typeof item.count !== 'string' ||
              typeof item.url !== 'string' || !item.url.startsWith('/admin/')) ||
            new Set(payload.cards.map((item) => item.key)).size !== 4) throw new Error('Invalid dashboard card response');
        renderCards(payload.cards);
        if (announce) announce.textContent = adminTr('dashboard.cards_saved');
      } catch (error) {
        if (announce) announce.textContent = adminTr('dashboard.cards_save_failed');
        if (notice instanceof HTMLElement) notice.hidden = false;
      } finally {
        busy = false;
        cardsRoot.removeAttribute('aria-busy');
        cards.forEach((item) => item.querySelectorAll('button').forEach((button) => { button.disabled = false; }));
        closeMenu(card, true);
      }
    };
    cards.forEach((card) => {
      const trigger = card.querySelector('[data-dashboard-card-trigger]');
      if (!(trigger instanceof HTMLButtonElement)) return;
      trigger.addEventListener('click', () => {
        if (openCard === card) closeMenu(card, true); else openMenu(card);
      });
      card.querySelectorAll('[data-dashboard-card-choice]').forEach((choice) => {
        choice.addEventListener('click', () => { void saveChoice(card, choice.dataset.dashboardCardChoice || ''); });
      });
      card.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && openCard === card) {
          event.preventDefault(); closeMenu(card, true);
        }
      });
    });
    document.addEventListener('click', (event) => {
      if (openCard && !openCard.contains(event.target)) closeMenu(openCard);
    });
  }

  // Getting Started is shown automatically only until the user dismisses it.
  // Fresh installs have an instance-scoped preference, so old domain/browser
  // state cannot hide onboarding after a new installation on the same URL.
  // Existing installations without an instance ID retain their v3/v2 preference.
  // This is browser-local UI state, never an authorization or security control.
  const welcome = document.querySelector('[data-dashboard-welcome]');
  const welcomeRestore = document.querySelector('[data-dashboard-welcome-restore]');
  if (welcome instanceof HTMLElement && welcomeRestore instanceof HTMLButtonElement) {
    const userId = welcome.dataset.userId || '';
    const accountId = /^[1-9][0-9]*$/.test(userId) ? userId : null;
    const instanceId = /^[a-f0-9]{32}$/.test(welcome.dataset.siteInstanceId || '')
      ? welcome.dataset.siteInstanceId : null;
    const storageKey = accountId === null ? null : instanceId !== null
      ? 'boilerplate.admin.dashboard-welcome.v4.instance-' + instanceId + '.user-' + accountId
      : 'boilerplate.admin.dashboard-welcome.v3.user-' + accountId;
    const legacyKey = accountId === null || instanceId !== null ? null
      : 'boilerplate.admin.dashboard-welcome.v2.user-' + accountId;
    let dismissed = false;
    try {
      if (storageKey !== null) {
        const saved = localStorage.getItem(storageKey);
        if (saved === 'dismissed') {
          dismissed = true;
        } else if (saved === null && legacyKey !== null && localStorage.getItem(legacyKey) !== null) {
          // Existing users have already had a welcome experience. Opt in via Show welcome.
          dismissed = true;
          localStorage.setItem(storageKey, 'dismissed');
        }
      }
    } catch { /* Blocked browser storage: in-page close/restore still work. */ }

    const persistWelcome = () => {
      try { if (storageKey !== null) localStorage.setItem(storageKey, dismissed ? 'dismissed' : 'visible'); }
      catch { /* A blocked store must not prevent the controls working. */ }
    };
    welcome.hidden = dismissed;
    welcomeRestore.hidden = !dismissed;
    const close = welcome.querySelector('[data-dashboard-welcome-close]');
    if (close instanceof HTMLButtonElement) {
      close.addEventListener('click', () => {
        dismissed = true;
        persistWelcome();
        welcome.hidden = true;
        welcomeRestore.hidden = false;
        welcomeRestore.focus({ preventScroll: true });
      });
      welcomeRestore.addEventListener('click', () => {
        dismissed = false;
        persistWelcome();
        welcome.hidden = false;
        welcomeRestore.hidden = true;
        close.focus({ preventScroll: true });
      });
    }
  }

  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const controlId = button.getAttribute('aria-controls');
    const input = controlId ? document.getElementById(controlId) : null;
    if (!input) return;

    const text = button.querySelector('[data-password-toggle-text]');

    button.addEventListener('click', () => {
      const reveal = input.type === 'password';
      input.type = reveal ? 'text' : 'password';
      const label = reveal ? (button.dataset.labelHide || adminTr('Skjul adgangskode')) : (button.dataset.labelShow || adminTr('Vis adgangskode'));
      button.setAttribute('aria-label', label);
      button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
      button.title = label;
      if (text) text.textContent = label;
      input.focus({ preventScroll: true });
    });
  });

  // Core toggle status text is always on the same horizontal line, before the switch.
  // Delegation supports Page Builder / Forms controls inserted after initial render.
  document.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.matches('.admin-toggle__input')) return;
    const state = input.closest('.admin-toggle')?.querySelector('[data-admin-toggle-state]');
    if (state) state.textContent = input.checked ? state.dataset.stateOn : state.dataset.stateOff;
  });

  // The account menu is a native <details>; close it when focus moves outside,
  // the user clicks elsewhere, or Escape is pressed. Inner form controls remain usable.
  document.querySelectorAll('details.admin-user-menu').forEach((menu) => {
    document.addEventListener('pointerdown', (event) => {
      if (menu.open && !menu.contains(event.target)) menu.open = false;
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && menu.open) {
        event.preventDefault();
        menu.open = false;
        menu.querySelector('summary')?.focus();
      }
    });
  });

  // Core navigation badges: one lightweight authenticated snapshot per 90s,
  // paused in hidden tabs. All labels and DOM nodes are text-only, never HTML.
  const badgeLinks = Array.from(document.querySelectorAll('.admin-nav a[data-admin-nav-key]'));
  if (badgeLinks.length && typeof window.fetch === 'function') {
    let badgeRequestInFlight = false;
    const refreshNavBadges = async () => {
      if (document.visibilityState === 'hidden' || badgeRequestInFlight) return;
      badgeRequestInFlight = true;
      try {
        const response = await window.fetch('/admin/index.php?mode=nav-badges', {
          method: 'GET', credentials: 'same-origin', cache: 'no-store',
          headers: { Accept: 'application/json' },
        });
        if (!response.ok) return;
        const data = await response.json();
        if (!data || !data.badges || typeof data.badges !== 'object' || Array.isArray(data.badges)) return;
        badgeLinks.forEach((link) => {
          const key = link.dataset.adminNavKey;
          const count = data.badges[key];
          const safeCount = Number.isSafeInteger(count) && count > 0 ? count : 0;
          let badge = link.querySelector('[data-admin-nav-count]');
          if (!safeCount) {
            badge?.remove();
            return;
          }
          if (!badge) {
            badge = document.createElement('span');
            badge.className = 'admin-nav__badge';
            badge.dataset.adminNavCount = '';
            link.appendChild(badge);
          }
          badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
          badge.setAttribute('aria-label', `${safeCount} ${link.dataset.adminNavBadgeDescription || ''}`.trim());
        });
      } catch (_) {
        // Keep the last server-rendered count on a transient network error.
      } finally {
        badgeRequestInFlight = false;
      }
    };
    window.setInterval(refreshNavBadges, 90000);
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') refreshNavBadges();
    });
  }

  const sidebar = document.querySelector('#admin-sidebar');
  const openButton = document.querySelector('[data-admin-sidebar-open]');
  const closeButton = document.querySelector('[data-admin-sidebar-close]');
  const backdrop = document.querySelector('[data-admin-backdrop]');

  if (sidebar && openButton && backdrop) {
    const setOpen = (open) => {
      document.body.classList.toggle('admin-menu-open', open);
      sidebar.classList.toggle('is-open', open);
      backdrop.hidden = !open;
      openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open && closeButton) closeButton.focus();
    };

    openButton.addEventListener('click', () => setOpen(true));
    closeButton?.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
        setOpen(false);
        openButton.focus();
      }
    });

    const desktop = window.matchMedia('(min-width: 1020px)');
    const resetForDesktop = (event) => {
      if (event.matches) setOpen(false);
    };
    desktop.addEventListener?.('change', resetForDesktop);
  }

  const uploadDropzones = [...document.querySelectorAll('[data-admin-upload-dropzone]')];
  uploadDropzones.forEach((dropzone) => {
    const input = dropzone.querySelector('[data-admin-upload-input]');
    const selection = dropzone.querySelector('[data-admin-upload-selection]');
    const preview = dropzone.querySelector('[data-admin-upload-preview]');
    const fileName = dropzone.querySelector('[data-admin-upload-name]');
    const fileSize = dropzone.querySelector('[data-admin-upload-size]');
    const body = dropzone.querySelector('.admin-upload-dropzone__body');
    if (!input) return;

    let previewUrl = '';
    const humanSize = (bytes) => {
      if (!Number.isFinite(bytes) || bytes <= 0) return '';
      const units = ['B', 'KB', 'MB', 'GB'];
      const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
      const value = bytes / (1024 ** index);
      return `${value >= 10 || index === 0 ? value.toFixed(0) : value.toFixed(1)} ${units[index]}`;
    };

    const allowedTypes = (dropzone.dataset.uploadTypes || 'image/jpeg,image/png,image/webp')
      .split(',')
      .map((value) => value.trim().toLowerCase())
      .filter(Boolean);
    const allowedExtensions = (dropzone.dataset.uploadExtensions || '.jpg,.jpeg,.png,.webp')
      .split(',')
      .map((value) => value.trim().toLowerCase())
      .filter(Boolean);
    const maxBytes = Number.parseInt(dropzone.dataset.uploadMaxBytes || String(10 * 1024 * 1024), 10);
    const totalMaxBytes = Number.parseInt(dropzone.dataset.uploadTotalMaxBytes || '0', 10);

    const validateTotalSize = (files) => {
      if (!Number.isFinite(totalMaxBytes) || totalMaxBytes <= 0) return true;
      const total = files.reduce((sum, file) => sum + (Number(file?.size) || 0), 0);
      if (total > totalMaxBytes) {
        window.adminNotify(dropzone.dataset.uploadTotalSizeError || window.cmsTranslate('De valgte filer er samlet for store til serverens uploadgrænse.'));
        return false;
      }
      return true;
    };

    const validateFile = (file) => {
      if (!file) return true;
      const filename = String(file.name || '').toLowerCase();
      const typeAllowed = file.type ? allowedTypes.includes(file.type.toLowerCase()) : false;
      const extensionAllowed = allowedExtensions.some((extension) => filename.endsWith(extension));
      if (!typeAllowed && !extensionAllowed) {
        window.adminNotify(dropzone.dataset.uploadTypeError || window.cmsTranslate('Vælg et JPG-, PNG- eller WebP-billede.'));
        return false;
      }
      if (Number.isFinite(maxBytes) && maxBytes > 0 && file.size > maxBytes) {
        window.adminNotify(dropzone.dataset.uploadSizeError || window.cmsTranslate('Filen må højst fylde 10 MB.'));
        return false;
      }
      return true;
    };

    const showFile = (file) => {
      if (!file) {
        selection?.setAttribute('hidden', '');
        if (body) body.hidden = false;
        dropzone.classList.remove('has-file');
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = '';
        if (preview) preview.innerHTML = '';
        return;
      }
      dropzone.classList.add('has-file');
      if (body) body.hidden = true;
      selection?.removeAttribute('hidden');
      if (fileName) fileName.textContent = file.name;
      if (fileSize) fileSize.textContent = humanSize(file.size);
      if (preview) {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = '';
        preview.innerHTML = '';
        if (file.type.startsWith('image/')) {
          previewUrl = URL.createObjectURL(file);
          const image = document.createElement('img');
          image.src = previewUrl;
          image.alt = '';
          preview.appendChild(image);
        }
      }
    };

    input.addEventListener('change', () => {
      const files = [...(input.files || [])];
      if (files.some((file) => !validateFile(file)) || !validateTotalSize(files)) {
        input.value = '';
        showFile(null);
        return;
      }
      if (files.length > 1) {
        dropzone.classList.add('has-file');
        if (body) body.hidden = true;
        selection?.removeAttribute('hidden');
        if (fileName) fileName.textContent = `${files.length} filer valgt`;
        if (fileSize) fileSize.textContent = humanSize(files.reduce((sum, file) => sum + file.size, 0));
        if (preview) preview.innerHTML = '';
      } else {
        showFile(files[0] || null);
      }
    });

    ['dragenter', 'dragover'].forEach((type) => {
      dropzone.addEventListener(type, (event) => {
        event.preventDefault();
        event.stopPropagation();
        dropzone.classList.add('is-dragover');
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
      });
    });

    ['dragleave', 'dragend'].forEach((type) => {
      dropzone.addEventListener(type, (event) => {
        event.preventDefault();
        event.stopPropagation();
        dropzone.classList.remove('is-dragover');
      });
    });

    dropzone.addEventListener('drop', (event) => {
      event.preventDefault();
      event.stopPropagation();
      dropzone.classList.remove('is-dragover');
      const files = event.dataTransfer?.files;
      if (!files?.length) return;
      const droppedFiles = [...files];
      const selectedFiles = input.multiple ? droppedFiles : droppedFiles.slice(0, 1);
      if (selectedFiles.some((file) => !validateFile(file)) || !validateTotalSize(selectedFiles)) return;
      if (typeof DataTransfer === 'undefined') {
        input.click();
        return;
      }
      const transfer = new DataTransfer();
      selectedFiles.forEach((file) => transfer.items.add(file));
      input.files = transfer.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });


  const mediaUploadDialog = document.querySelector('[data-media-upload-dialog]');
  const mediaDetailDialog = document.querySelector('[data-media-detail-dialog]');
  const openDialog = (dialog) => {
    if (!dialog) return;
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  };
  const closeDialog = (dialog) => {
    if (!dialog) return;
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
  };

  document.querySelectorAll('[data-admin-modal]').forEach((dialog) => {
    dialog.querySelectorAll('[data-modal-close]').forEach((button) => {
      button.addEventListener('click', () => closeDialog(dialog));
    });
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) closeDialog(dialog);
    });
  });

  // One core-owned confirmation modal for destructive actions and builder changes.
  const confirmDialog = document.querySelector('[data-admin-confirm-dialog]');
  let pendingConfirmation = null;
  let confirmReturnFocus = null;
  const confirmTitle = confirmDialog?.querySelector('[data-admin-confirm-title]');
  const confirmMessage = confirmDialog?.querySelector('[data-admin-confirm-message]');
  const confirmAccept = confirmDialog?.querySelector('[data-admin-confirm-accept]');
  const confirmCancel = confirmDialog?.querySelector('[data-admin-confirm-cancel]');
  if (confirmDialog) {
    const settleConfirmation = (accepted) => {
      const resolve = pendingConfirmation;
      pendingConfirmation = null;
      closeDialog(confirmDialog);
      if (confirmReturnFocus?.isConnected) confirmReturnFocus.focus({ preventScroll: true });
      confirmReturnFocus = null;
      resolve?.(accepted);
    };
    confirmAccept?.addEventListener('click', () => settleConfirmation(true));
    confirmCancel?.addEventListener('click', () => settleConfirmation(false));
    confirmDialog.addEventListener('close', () => {
      if (pendingConfirmation) {
        const resolve = pendingConfirmation; pendingConfirmation = null;
        if (confirmReturnFocus?.isConnected) confirmReturnFocus.focus({ preventScroll: true });
        confirmReturnFocus = null;
        resolve(false);
      }
    });
    confirmDialog.addEventListener('click', (event) => {
      if (event.target === confirmDialog && pendingConfirmation) settleConfirmation(false);
    });
  }
  window.adminConfirm = (message, title = window.cmsTranslate('Bekræft handling'), acceptLabel = window.cmsTranslate('Bekræft')) => {
    if (!confirmDialog || pendingConfirmation) return Promise.resolve(false);
    if (confirmMessage) confirmMessage.textContent = message;
    if (confirmTitle) confirmTitle.textContent = title;
    if (confirmAccept) confirmAccept.textContent = acceptLabel;
    confirmReturnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    openDialog(confirmDialog);
    confirmCancel?.focus({ preventScroll: true });
    return new Promise((resolve) => { pendingConfirmation = resolve; });
  };
  // In-page feedback for non-destructive validation; no browser alert popups.
  window.adminNotify = (message) => {
    const main = document.querySelector('#admin-main');
    if (!main) return;
    main.querySelector('[data-admin-inline-error]')?.remove();
    const notice = document.createElement('div');
    notice.className = 'admin-notice admin-notice--error';
    notice.setAttribute('role', 'alert');
    notice.dataset.adminInlineError = '1';
    notice.textContent = message;
    main.prepend(notice);
    notice.scrollIntoView({ block: 'nearest' });
  };
  // Shared editor draft protection. Only opt-in editing forms participate.
  // Browser-managed unload prompt is unavoidable for refresh/close; internal CMS links use our modal.
  document.querySelectorAll('form[data-admin-track-changes]').forEach((form) => {
    const snapshot = () => {
      const values = [];
      form.querySelectorAll('input, select, textarea').forEach((field) => {
        if (!field.name || field.disabled || ['submit', 'button', 'reset'].includes(field.type)) return;
        if (field.type === 'checkbox' || field.type === 'radio') {
          values.push([field.name, field.value, field.checked]);
        } else if (field.type === 'file') {
          values.push([field.name, [...(field.files || [])].map(f => [f.name, f.size, f.lastModified])]);
        } else if (field.tagName === 'SELECT' && field.multiple) {
          values.push([field.name, [...field.selectedOptions].map(option => option.value)]);
        } else {
          values.push([field.name, field.value]);
        }
      });
      return JSON.stringify(values);
    };
    let initial = '';
    let active = false;
    let submitting = false;
    let dirty = false;
    let scheduled = false;
    const recalculate = () => {
      if (!active || submitting) return;
      dirty = snapshot() !== initial;
    };
    const queueRecalculate = () => {
      if (scheduled) return;
      scheduled = true;
      queueMicrotask(() => { scheduled = false; recalculate(); });
    };
    form.addEventListener('input', queueRecalculate);
    form.addEventListener('change', queueRecalculate);
    form.addEventListener('admin:changed', queueRecalculate);
    const observer = new MutationObserver((records) => {
      // Only changed structure matters. Ignore text-only preview updates.
      if (records.some(record => record.addedNodes.length || record.removedNodes.length)) queueRecalculate();
    });
    form.addEventListener('submit', () => { submitting = true; dirty = false; });
    form.addEventListener('admin:discard-draft', () => { submitting = true; dirty = false; });
    const unload = (event) => {
      recalculate();
      if (!dirty || submitting) return;
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', unload);
    document.addEventListener('click', async (event) => {
      const link = event.target.closest('a[href]');
      if (!link || !dirty || submitting || event.defaultPrevented || event.button !== 0 ||
          event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ||
          link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
      let destination;
      try { destination = new URL(link.href, window.location.href); } catch { return; }
      if (!['http:', 'https:'].includes(destination.protocol) || destination.origin !== window.location.origin) return;
      if (destination.pathname === window.location.pathname && destination.search === window.location.search) return;
      event.preventDefault();
      const confirmed = await window.adminConfirm(
        window.cmsTranslate('Du har ændringer, som ikke er gemt. Vil du forlade siden og kassere dem?'),
        window.cmsTranslate('Ikke-gemte ændringer'), window.cmsTranslate('Forlad siden')
      );
      if (!confirmed) return;
      submitting = true;
      window.location.assign(destination.href);
    }, true);
    // Initialize after all page/module builders have completed their initial setup.
    setTimeout(() => {
      initial = snapshot();
      active = true;
      observer.observe(form, { childList: true, subtree: true });
    }, 0);
  });

  // Forms can opt into the same modal without implementing their own dialogs.
  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-admin-confirm-form]');
    if (!form || form.dataset.adminConfirmed === '1') return;
    event.preventDefault();
    const submitter = event.submitter;
    const confirmed = await window.adminConfirm(
      form.dataset.adminConfirmMessage || window.cmsTranslate('Handlingen kan ikke fortrydes.'),
      form.dataset.adminConfirmTitle || window.cmsTranslate('Bekræft sletning'),
      form.dataset.adminConfirmLabel || window.cmsTranslate('Slet')
    );
    if (!confirmed) return;
    form.dataset.adminConfirmed = '1';
    if (form.requestSubmit) form.requestSubmit(submitter || undefined);
    else form.submit();
  });

  // Delegated to survive Core live-search table replacement.
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-admin-open-delete]');
    if (!button || button.disabled) return;
    const dialog = document.getElementById(button.dataset.adminOpenDelete || '');
    if (!dialog) return;
    if (dialog.id === 'user-list-delete-dialog') {
      const id = button.dataset.userDeleteId || '';
      if (!/^[1-9][0-9]*$/.test(id)) return;
      const idInput = dialog.querySelector('[data-user-list-delete-id]');
      const name = dialog.querySelector('[data-user-list-delete-name]');
      if (!idInput || !name) return;
      idInput.value = id;
      name.textContent = button.dataset.userDeleteName || '';
    }
    if (dialog.id === 'gallery-list-delete-dialog') {
      const id = button.dataset.galleryListDeleteId || '';
      if (!/^[1-9][0-9]*$/.test(id)) return;
      const input = dialog.querySelector('[data-gallery-list-delete-target]');
      const label = dialog.querySelector('[data-gallery-list-delete-subject]');
      if (!input || !label) return;
      input.value = id;
      label.textContent = button.dataset.galleryListDeleteName || '';
    }
    if (dialog.id === 'page-list-delete-dialog') {
      const id = button.dataset.pageDeleteId || '';
      if (!/^[1-9][0-9]*$/.test(id)) return;
      const idInput = dialog.querySelector('[data-page-list-delete-id]');
      const name = dialog.querySelector('[data-page-list-delete-title]');
      if (!idInput || !name) return;
      idInput.value = id;
      name.textContent = button.dataset.pageDeleteTitle || '';
    }
    if (dialog.id === 'media-delete-dialog') {
      // The media detail dialog owns selection, but never owns the delete form.
      // Read the current validated record ID when Delete is clicked, not when
      // the library card was first opened; stale/empty IDs cannot be submitted.
      const id = mediaDetailDialog?.querySelector('[data-media-detail-id]')?.value || '';
      if (!/^[1-9][0-9]*$/.test(id)) return;
      const idInput = dialog.querySelector('[data-media-delete-id]');
      const name = dialog.querySelector('[data-media-delete-name]');
      const selectedName = mediaDetailDialog?.querySelector('[data-media-detail-heading]');
      if (!idInput || !name || !selectedName) return;
      idInput.value = id;
      name.textContent = selectedName.textContent || '';
    }
    if (dialog.id === 'forms-list-delete-dialog') {
      const id = button.dataset.formsListDeleteId || '';
      if (!/^[1-9][0-9]*$/.test(id)) return;
      const idInput = dialog.querySelector('[data-forms-list-delete-target]');
      const name = dialog.querySelector('[data-forms-list-delete-subject]');
      if (!idInput || !name) return;
      idInput.value = id;
      name.textContent = button.dataset.formsListDeleteName || '';
    }
    if (dialog.id === 'redirect-list-delete-dialog') {
      const id = button.dataset.redirectDeleteId || '';
      if (!/^[1-9][0-9]*$/.test(id)) return;
      const idInput = dialog.querySelector('[data-redirect-list-delete-id]');
      const source = dialog.querySelector('[data-redirect-list-delete-source]');
      if (!idInput || !source) return;
      idInput.value = id;
      source.textContent = button.dataset.redirectDeleteSource || '';
    }
    if (dialog.id === 'nav-list-delete-dialog') {
      const id = button.dataset.navDeleteId || '';
      if (!/^[1-9][0-9]*$/.test(id)) return;
      const form = dialog.querySelector('[data-nav-list-delete-form]');
      const name = dialog.querySelector('[data-nav-list-delete-name]');
      if (!form || !name) return;
      // The Navigation controller intentionally identifies the menu by GET id,
      // but accepts deletion only through a CSRF-protected confirmed POST.
      form.action = '/admin/navigation?mode=delete&id=' + id;
      name.textContent = button.dataset.navDeleteName || '';
    }
    openDialog(dialog);
  });

  const moduleDeactivateDialog = document.querySelector('[data-module-deactivate-dialog]');
  const moduleDeactivateSlug = moduleDeactivateDialog?.querySelector('[data-module-deactivate-slug]');
  const moduleDeactivateName = moduleDeactivateDialog?.querySelector('[data-module-deactivate-name]');
  const moduleDeactivateConfirm = moduleDeactivateDialog?.querySelector('[data-module-deactivate-confirm]');

  document.querySelectorAll('[data-module-deactivate-open]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!moduleDeactivateDialog || !moduleDeactivateSlug) return;
      moduleDeactivateSlug.value = button.dataset.moduleSlug || '';
      if (moduleDeactivateName) moduleDeactivateName.textContent = button.dataset.moduleName || window.cmsTranslate('modulet');
      openDialog(moduleDeactivateDialog);
      requestAnimationFrame(() => moduleDeactivateConfirm?.focus());
    });
  });


  // Explicit plugin version acknowledgement; confirmation stays in a Core modal.
  const moduleUpdateDialog = document.querySelector('[data-module-update-dialog]');
  const moduleUpdateSlug = moduleUpdateDialog?.querySelector('[data-module-update-slug]');
  const moduleUpdateName = moduleUpdateDialog?.querySelector('[data-module-update-name]');
  const moduleUpdateConfirm = moduleUpdateDialog?.querySelector('[data-module-update-confirm]');
  document.querySelectorAll('[data-module-update-open]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!moduleUpdateDialog || !moduleUpdateSlug) return;
      moduleUpdateSlug.value = button.dataset.moduleSlug || '';
      if (moduleUpdateName) moduleUpdateName.textContent = button.dataset.moduleName || '';
      openDialog(moduleUpdateDialog);
      requestAnimationFrame(() => moduleUpdateConfirm?.focus());
    });
  });

  document.querySelector('[data-media-upload-open]')?.addEventListener('click', () => openDialog(mediaUploadDialog));
  document.addEventListener('click', (event) => {
      const card = event.target.closest('[data-media-item]');
      if (!card) return;
      if (!mediaDetailDialog) return;
      const get = (key) => card.dataset[key] || '';
      const id = get('mediaId');
      const kind = get('mediaKind');
      const url = get('mediaUrl');
      const title = get('mediaTitle');
      const filename = get('mediaFilename');
      const preview = mediaDetailDialog.querySelector('[data-media-detail-preview]');

      const detailId = mediaDetailDialog.querySelector('[data-media-detail-id]');
      if (detailId) detailId.value = id;
      const heading = mediaDetailDialog.querySelector('[data-media-detail-heading]');
      if (heading) heading.textContent = title || filename || window.cmsTranslate('Medie');
      const meta = mediaDetailDialog.querySelector('[data-media-detail-meta]');
      if (meta) meta.textContent = get('mediaMeta');
      const filenameInput = mediaDetailDialog.querySelector('[data-media-detail-filename]');
      if (filenameInput) filenameInput.value = filename;
      const titleInput = mediaDetailDialog.querySelector('[data-media-detail-title]');
      if (titleInput) titleInput.value = title;
      const altInput = mediaDetailDialog.querySelector('[data-media-detail-alt]');
      if (altInput) altInput.value = get('mediaAlt');
      const description = mediaDetailDialog.querySelector('[data-media-detail-description]');
      if (description) description.value = get('mediaDescription');
      const altField = mediaDetailDialog.querySelector('[data-media-alt-field]');
      if (altField) altField.hidden = kind !== 'image';
      const created = mediaDetailDialog.querySelector('[data-media-detail-created]');
      if (created) created.textContent = get('mediaCreated') || '—';
      const uploader = mediaDetailDialog.querySelector('[data-media-detail-uploader]');
      const uploaderRow = mediaDetailDialog.querySelector('[data-media-detail-uploader-row]');
      if (uploader) uploader.textContent = get('mediaUploader') || '—';
      if (uploaderRow) uploaderRow.hidden = !get('mediaUploader');

      if (preview) {
        preview.innerHTML = '';
        if (kind === 'image') {
          const image = document.createElement('img');
          image.src = url;
          image.alt = get('mediaAlt');
          preview.appendChild(image);
        } else {
          const video = document.createElement('video');
          video.src = url;
          video.controls = true;
          video.preload = 'metadata';
          preview.appendChild(video);
        }
      }
      openDialog(mediaDetailDialog);
  });


  const builderForm = document.querySelector('[data-page-builder-form]');
  if (builderForm) {
    const modeInputs = [...builderForm.querySelectorAll('[data-content-mode]')];
    const modePanels = [...builderForm.querySelectorAll('[data-builder-mode]')];
    const list = builderForm.querySelector('[data-builder-list]');
    const empty = builderForm.querySelector('[data-builder-empty]');
    const addButton = builderForm.querySelector('[data-builder-add]');
    const layoutDialog = document.querySelector('[data-page-layout-dialog]');
    const blockDialog = document.querySelector('[data-page-block-dialog]');
    const templatesDialog = document.querySelector('[data-page-templates-dialog]');
    let pendingColumn = null;
    let chooserReturnFocus = null;
    let blockChoiceCommitted = false;
    const translatePage = (key) => window.cmsTranslate(key);
    // Core SVG chevron indicates whether the inline block editor can expand or collapse.
    const syncBlockToggle = (button, expanded) => {
      if (!button) return;
      button.setAttribute('aria-expanded', String(expanded));
      const label = translatePage(expanded ? 'page_builder_v2.collapse_block' : 'page_builder_v2.expand_block');
      button.setAttribute('aria-label', label);
      button.title = label;
    };
    let draggedBlock = null;
    let draggedGridChild = null;

    const mediaPickerDialog = document.querySelector('[data-media-picker-dialog]');
    const mediaPickerGrid = mediaPickerDialog?.querySelector('[data-media-picker-grid]');
    const mediaPickerSearch = mediaPickerDialog?.querySelector('[data-media-picker-search]');
    const mediaPickerEmpty = mediaPickerDialog?.querySelector('[data-media-picker-empty]');
    const mediaPickerLoading = mediaPickerDialog?.querySelector('[data-media-picker-loading]');
    let activeMediaPickerField = null;
    let mediaPickerRequest = 0;
    let mediaPickerTimer = 0;

    const renderMediaPicker = (items) => {
      if (!mediaPickerGrid) return;
      mediaPickerGrid.innerHTML = '';
      items.forEach((item) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'admin-media-picker-item';
        button.dataset.mediaPickerSelect = '1';
        button.dataset.mediaId = String(item.id || '');
        button.dataset.mediaUrl = String(item.url || '');
        button.dataset.mediaAlt = String(item.alt || '');
        button.dataset.mediaTitle = String(item.title || '');
        const image = document.createElement('img');
        image.src = String(item.url || '');
        image.alt = '';
        image.loading = 'lazy';
        const label = document.createElement('span');
        label.textContent = String(item.title || item.filename || window.cmsTranslate('Billede'));
        button.append(image, label);
        mediaPickerGrid.appendChild(button);
      });
      if (mediaPickerEmpty) mediaPickerEmpty.hidden = items.length > 0;
    };

    const loadMediaPicker = async (query = '') => {
      if (!mediaPickerDialog || !mediaPickerGrid) return;
      const requestId = ++mediaPickerRequest;
      if (mediaPickerLoading) mediaPickerLoading.hidden = false;
      if (mediaPickerEmpty) mediaPickerEmpty.hidden = true;
      try {
        const response = await fetch(`/admin/medier?mode=picker&q=${encodeURIComponent(query)}`, {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Media picker request failed');
        const payload = await response.json();
        if (requestId !== mediaPickerRequest) return;
        renderMediaPicker(Array.isArray(payload.items) ? payload.items : []);
      } catch (error) {
        if (requestId !== mediaPickerRequest) return;
        mediaPickerGrid.innerHTML = '';
        if (mediaPickerEmpty) {
          mediaPickerEmpty.hidden = false;
          const text = mediaPickerEmpty.querySelector('span');
          if (text) text.textContent = window.cmsTranslate('Mediebiblioteket kunne ikke indlæses. Prøv igen.');
        }
      } finally {
        if (requestId === mediaPickerRequest && mediaPickerLoading) mediaPickerLoading.hidden = true;
      }
    };

    builderForm.addEventListener('click', async (event) => {
      const openButton = event.target.closest('[data-media-picker-open]');
      if (openButton) {
        activeMediaPickerField = openButton.closest('[data-media-picker-field]');
        if (!activeMediaPickerField || !mediaPickerDialog) return;
        if (mediaPickerSearch) mediaPickerSearch.value = '';
        openDialog(mediaPickerDialog);
        loadMediaPicker('');
        requestAnimationFrame(() => mediaPickerSearch?.focus());
        return;
      }

      const clearButton = event.target.closest('[data-media-picker-clear]');
      if (clearButton) {
        const field = clearButton.closest('[data-media-picker-field]');
        const pathInput = field?.querySelector('[data-media-picker-path]');
        const idInput = field?.querySelector('[data-media-picker-id]');
        if (pathInput) {
          pathInput.value = '';
          pathInput.dispatchEvent(new Event('input', { bubbles: true }));
          pathInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (idInput) idInput.value = '';
        const scope = clearButton.closest('[data-grid-child], [data-builder-block]');
        const altInput = scope?.querySelector('[data-media-picker-alt]');
        if (altInput) altInput.value = '';
        clearButton.hidden = true;
      }
    });

    mediaPickerDialog?.addEventListener('click', (event) => {
      const selected = event.target.closest('[data-media-picker-select]');
      if (!selected || !activeMediaPickerField) return;
      const pathInput = activeMediaPickerField.querySelector('[data-media-picker-path]');
      const idInput = activeMediaPickerField.querySelector('[data-media-picker-id]');
      if (pathInput) {
        pathInput.value = selected.dataset.mediaUrl || '';
        pathInput.dispatchEvent(new Event('input', { bubbles: true }));
        pathInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (idInput) idInput.value = selected.dataset.mediaId || '';
      const clearButton = activeMediaPickerField.querySelector('[data-media-picker-clear]');
      if (clearButton) clearButton.hidden = false;
      const scope = activeMediaPickerField.closest('[data-grid-child], [data-builder-block]');
      const altInput = scope?.querySelector('[data-media-picker-alt]');
      if (altInput && !altInput.value.trim()) altInput.value = selected.dataset.mediaAlt || '';
      closeDialog(mediaPickerDialog);
      activeMediaPickerField = null;
    });

    mediaPickerSearch?.addEventListener('input', () => {
      window.clearTimeout(mediaPickerTimer);
      mediaPickerTimer = window.setTimeout(() => loadMediaPicker(mediaPickerSearch.value.trim()), 220);
    });

    const GRID_PRESETS = {
      1: [['equal', '100 %']],
      2: [['equal', '50 / 50'], ['33-67', '33 / 67'], ['67-33', '67 / 33'], ['40-60', '40 / 60'], ['60-40', '60 / 40']],
      3: [['equal', '33 / 33 / 33'], ['25-50-25', '25 / 50 / 25'], ['50-25-25', '50 / 25 / 25'], ['25-25-50', '25 / 25 / 50']],
      4: [['equal', '25 / 25 / 25 / 25']],
      5: [['equal', '20 / 20 / 20 / 20 / 20']],
      6: [['equal', '16 / 16 / 16 / 16 / 16 / 16']],
    };

    const updateMode = () => {
      const selected = modeInputs.find((input) => input.checked)?.value || 'builder';
      modePanels.forEach((panel) => {
        panel.hidden = panel.dataset.builderMode !== selected;
      });
    };

    const blocks = () => list ? [...list.querySelectorAll(':scope > [data-builder-block]')] : [];
    const gridColumns = (gridBlock) => [...(gridBlock?.querySelectorAll('[data-grid-editor] > [data-grid-column]') || [])];
    const gridChildren = (column) => [...(column?.querySelectorAll('[data-grid-child-list] > [data-grid-child]') || [])];

    const updateGridColumnMeta = (column, index) => {
      if (!column) return;
      column.dataset.gridColumnIndex = String(index);
      const label = column.querySelector('[data-grid-column-label]');
      if (label) label.textContent = `Kolonne ${index + 1}`;
      const children = gridChildren(column);
      const count = column.querySelector('.admin-grid-column__header > span');
      if (count) count.textContent = `${children.length} ${children.length === 1 ? window.cmsTranslate('blok') : window.cmsTranslate('blokke')}`;
      const emptyState = column.querySelector('[data-grid-column-empty]');
      if (emptyState) emptyState.hidden = children.length > 0;
      const row = column.closest('[data-builder-block]');
      const chooser = column.querySelector('[data-grid-child-add]');
      if (chooser && row?.classList.contains('admin-page-row--v2-grid')) {
        chooser.hidden = children.length >= 1;
      }
    };

    const reindexGrid = (gridBlock, topIndex) => {
      gridColumns(gridBlock).forEach((column, columnIndex) => {
        updateGridColumnMeta(column, columnIndex);
        gridChildren(column).forEach((child, childIndex) => {
          // Reassign Core Toggle ids when cloning/reordering template blocks.
          // Every switch/label pair must be unique in the live builder DOM.
          const visibility = child.querySelector('[data-page-child-visibility]');
          if (visibility) {
            const id = `page-child-visibility-${topIndex}-${columnIndex}-${childIndex}`;
            visibility.id = id;
            const label = visibility.closest('label');
            if (label) label.htmlFor = id;
          }
          child.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name
              .replace(/blocks\[[^\]]+\]/, `blocks[${topIndex}]`)
              .replace(/\[data\]\[columns\]\[[^\]]+\]\[blocks\]\[[^\]]+\]/, `[data][columns][${columnIndex}][blocks][${childIndex}]`);
          });
          child.querySelectorAll('[data-gallery-new-field][data-gallery-name]').forEach(field => {
            field.dataset.galleryName = field.dataset.galleryName
              .replace(/blocks\[[^\]]+\]/, `blocks[${topIndex}]`)
              .replace(/\[data\]\[columns\]\[[^\]]+\]\[blocks\]\[[^\]]+\]/, `[data][columns][${columnIndex}][blocks][${childIndex}]`);
          });
        });
      });
    };

    const reindex = () => {
      blocks().forEach((block, index) => {
        block.querySelectorAll('[name]').forEach((field) => {
          field.name = field.name.replace(/blocks\[[^\]]+\]/, `blocks[${index}]`);
        });
        block.querySelectorAll('[data-gallery-new-field][data-gallery-name]').forEach(field => {
          field.dataset.galleryName = field.dataset.galleryName.replace(/blocks\[[^\]]+\]/, `blocks[${index}]`);
        });
        if (block.dataset.blockType === 'grid') reindexGrid(block, index);
      });
      if (empty) empty.hidden = blocks().length > 0;
    };

    const syncGalleryFields = (scope) => {
      if (!scope) return;
      const selector = scope.querySelector('[data-builder-component], [data-grid-child-component]');
      const settings = scope.querySelector('[data-builder-gallery-settings]');
      if (!settings || !selector) return;
      settings.hidden = !/^gallery:gallery-[1-9][0-9]*$/.test(selector.value);
      const mode = settings.querySelector('[data-gallery-mode]')?.value || 'lightbox';
      const grid = settings.querySelector('[data-gallery-grid-settings]');
      const slider = settings.querySelector('[data-gallery-slider-settings]');
      if (grid) grid.hidden = mode !== 'lightbox';
      if (slider) slider.hidden = mode !== 'slider';
      const interval = settings.querySelector('[data-gallery-interval-settings]');
      if (interval) interval.hidden = mode !== 'slider' || !settings.querySelector('[data-gallery-autoplay]')?.checked;
    };
    const activateGalleryFields = (scope) => {
      const settings = scope?.querySelector('[data-builder-gallery-settings]');
      if (!settings) return;
      settings.dataset.galleryLegacy = '0';
      settings.querySelectorAll('[data-gallery-new-field]').forEach(field => { if (field.dataset.galleryName) field.name = field.dataset.galleryName; });
      settings.querySelectorAll('[data-gallery-legacy-input]').forEach(field => { field.disabled = true; });
    };
    const newGalleryDefaults = (scope) => {
      const mode = scope?.querySelector('[data-gallery-mode]');
      if (mode) mode.value = 'lightbox';
      activateGalleryFields(scope);
      syncGalleryFields(scope);
    };

    const updateSummary = (block) => {
      const summary = block?.querySelector('[data-builder-summary]');
      if (!summary) return;
      if (block.dataset.blockType === 'grid') {
        const count = Number(block.querySelector('[data-grid-column-count]')?.value || 2);
        const presetSelect = block.querySelector('[data-grid-preset]');
        const presetLabel = presetSelect?.options[presetSelect.selectedIndex]?.textContent?.trim() || 'Lige brede kolonner';
        summary.textContent = `${count} kolonner · ${presetLabel}`;
        return;
      }
      const component = block.querySelector('[data-builder-component]');
      if (component) {
        summary.textContent = component.options[component.selectedIndex]?.textContent?.trim() || window.cmsTranslate('Vælg komponent');
        return;
      }
      const sources = [...block.querySelectorAll('[data-builder-summary-source]')];
      const values = sources.map((field) => field.value.trim()).filter(Boolean);
      summary.textContent = values[1] || values[0] || 'Ingen overskrift endnu';
    };

    const updateGridChildSummary = (child) => {
      const summary = child?.querySelector('[data-grid-child-summary]');
      if (!summary) return;
      const component = child.querySelector('[data-grid-child-component]');
      if (component) {
        summary.textContent = component.options[component.selectedIndex]?.textContent?.trim() || window.cmsTranslate('Vælg komponent');
        return;
      }
      const source = child.querySelector('[data-grid-child-summary-source]');
      const value = source?.value?.trim() || '';
      if (value) {
        summary.textContent = child.dataset.gridChildType === 'image' ? value.split('/').filter(Boolean).at(-1) : value;
        return;
      }
      summary.textContent = child.dataset.gridChildType === 'image' ? 'Intet billede valgt' : 'Ingen overskrift endnu';
    };

    const addBlock = (type) => {
      if (!list) return null;
      if (blocks().length >= 60) {
        window.adminNotify(translatePage('page_builder_v2.limit'));
        return null;
      }
      const template = builderForm.querySelector(`template[data-builder-template="${CSS.escape(type)}"]`);
      if (!template) return null;
      const index = blocks().length;
      list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
      const added = blocks().at(-1);
      newGalleryDefaults(added);
      reindex();
      if (!['grid', 'grid-1', 'grid-3', 'grid-4', 'grid-5', 'grid-6'].includes(type)) {
        const toggle = added?.querySelector('[data-page-row-toggle]');
        const fields = added?.querySelector('[data-page-row-fields]');
        if (fields) fields.hidden = false;
        syncBlockToggle(toggle, true);
        fields?.querySelector('input:not([type=hidden]), textarea, select')?.focus();
      } else {
        added?.querySelector('[data-grid-child-add]')?.focus();
      }
      added?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      return added;
    };

    const showBlockChoices = (column = null) => {
      pendingColumn = column;
      blockDialog?.querySelectorAll('[data-page-block-choices]').forEach((group) => {
        group.hidden = group.dataset.pageBlockChoices !== (column ? 'child' : 'top');
      });
      openDialog(blockDialog);
      blockDialog?.querySelector('[data-page-block-choices]:not([hidden]) [data-page-block-choice]')?.focus();
    };

    addButton?.addEventListener('click', () => {
      chooserReturnFocus = addButton;
      openDialog(layoutDialog);
      layoutDialog?.querySelector('[data-page-add-section], [data-page-add-layout]')?.focus();
    });
    layoutDialog?.addEventListener('click', (event) => {
      const section = event.target.closest('[data-page-add-section]');
      if (section) {
        const type = section.dataset.pageAddSection;
        closeDialog(layoutDialog);
        // Section types already use the persistent top-level PageBlocks model.
        addBlock(type);
        return;
      }
      const choice = event.target.closest('[data-page-add-layout]');
      if (!choice) return;
      closeDialog(layoutDialog);
      // All six layouts create persistable empty V2 rows immediately.
      const templates = { '100': 'grid-1', '50': 'grid', '33': 'grid-3', '25': 'grid-4', '20': 'grid-5', '16': 'grid-6' };
      const template = templates[choice.dataset.pageAddLayout];
      if (template) addBlock(template);
    });
    blockDialog?.addEventListener('click', (event) => {
      const choice = event.target.closest('[data-page-block-choice]');
      if (!choice) return;
      blockChoiceCommitted = true;
      const type = choice.dataset.pageBlockChoice;
      const column = pendingColumn;
      closeDialog(blockDialog);
      pendingColumn = null;
      if (column?.isConnected) {
        const added = addGridChild(column.querySelector('[data-grid-child-add]'), type);
        const body = added?.querySelector('[data-page-child-fields]');
        const toggle = added?.querySelector('[data-page-child-toggle]');
        if (body) body.hidden = false;
        syncBlockToggle(toggle, true);
        body?.querySelector('input:not([type=hidden]), textarea, select')?.focus();
      } else if (!column) {
        addBlock(type);
      }
    });
    blockDialog?.addEventListener('close', () => {
      pendingColumn = null;
      if (!blockChoiceCommitted && chooserReturnFocus?.isConnected) {
        chooserReturnFocus.focus({ preventScroll: true });
      }
      blockChoiceCommitted = false;
      chooserReturnFocus = null;
    });
    // V2.4.4z: read-only presets become normal editable Page Builder elements.
    // Close/Cancel/Escape never modifies the existing editor; confirmation comes first.
    let selectedPageTemplate = templatesDialog?.querySelector('[data-page-template-choice]')?.dataset.pageTemplateChoice || '';
    let templateIsApplying = false;
    const selectPageTemplate = (id) => {
      const choice = [...(templatesDialog?.querySelectorAll('[data-page-template-choice]') || [])]
        .find((entry) => entry.dataset.pageTemplateChoice === id);
      if (!choice) return;
      selectedPageTemplate = id;
      templatesDialog.querySelectorAll('[data-page-template-choice]').forEach((button) => {
        const active = button === choice;
        button.classList.toggle('is-selected', active);
        button.setAttribute('aria-pressed', String(active));
      });
      templatesDialog.querySelectorAll('[data-page-template-preview]').forEach((panel) => {
        panel.hidden = panel.dataset.pageTemplatePreview !== id;
      });
    };
    builderForm.querySelector('[data-page-templates-open]')?.addEventListener('click', () => {
      if (modeInputs.find((input) => input.checked)?.value !== 'builder') {
        window.adminNotify(translatePage('page_templates.switch_to_builder'));
        return;
      }
      openDialog(templatesDialog);
      templatesDialog?.querySelector('[data-page-template-choice][aria-pressed="true"]')?.focus();
    });
    templatesDialog?.addEventListener('click', async (event) => {
      const choice = event.target.closest('[data-page-template-choice]');
      if (choice) { selectPageTemplate(choice.dataset.pageTemplateChoice || ''); return; }
      const apply = event.target.closest('[data-page-template-apply]');
      if (!apply || !list || templateIsApplying || !selectedPageTemplate) return;
      const template = [...builderForm.querySelectorAll('template[data-page-template-content]')]
        .find((item) => item.dataset.pageTemplateContent === selectedPageTemplate);
      if (!template) return;
      const fragment = template.content.cloneNode(true);
      const incoming = [...fragment.children].filter((entry) => entry.matches('[data-builder-block]'));
      if (incoming.length < 1 || incoming.length > 60 || incoming.length !== fragment.children.length) {
        window.adminNotify(translatePage('page_templates.invalid'));
        return;
      }
      templateIsApplying = true;
      apply.disabled = true;
      try {
        if (blocks().length > 0 && !await window.adminConfirm(
          translatePage('page_templates.replace_confirm'),
          translatePage('page_templates.replace_title'),
          translatePage('page_templates.replace_action')
        )) return;
        // Only now is the existing Page Builder content allowed to change.
        // section-###### IDs are unique per inserted instance and validated again on save.
        const used = new Set(blocks().map((row) => row.querySelector('[data-builder-section-id]')?.value).filter(Boolean));
        incoming.forEach((row) => {
          const input = row.querySelector('[data-builder-section-id]');
          if (!input) return;
          let candidate = '';
          do {
            let value;
            if (window.crypto?.getRandomValues) {
              const bytes = new Uint32Array(1);
              window.crypto.getRandomValues(bytes);
              value = bytes[0];
            } else value = Math.floor(Math.random() * 0x100000000);
            candidate = `section-${String(100000 + (value % 900000))}`;
          } while (used.has(candidate));
          used.add(candidate);
          input.value = candidate;
        });
        list.replaceChildren(...incoming);
        reindex();
        blocks().forEach((row) => {
          updateSummary(row);
          if (row.dataset.blockType === 'grid') gridColumns(row).forEach(updateGridColumnMeta);
        });
        closeDialog(templatesDialog);
        builderForm.dispatchEvent(new Event('admin:changed', { bubbles: true }));
        list.querySelector('[data-builder-block]')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } finally {
        templateIsApplying = false;
        apply.disabled = false;
      }
    });
    // Arrow-key selection is an optional ergonomic shortcut. Native Tab/Enter also works.
    templatesDialog?.addEventListener('keydown', (event) => {
      const button = event.target.closest('[data-page-template-choice]');
      if (!button || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
      event.preventDefault();
      const choices = [...templatesDialog.querySelectorAll('[data-page-template-choice]')];
      const next = (choices.indexOf(button) + (event.key === 'ArrowDown' ? 1 : -1) + choices.length) % choices.length;
      selectPageTemplate(choices[next].dataset.pageTemplateChoice || '');
      choices[next].focus();
    });
    // V2.4.4y4: Page section settings are edited in one reusable Core modal.
    // No changes are made to the stored inputs until Save is pressed.
    const sectionDialog = document.querySelector('[data-page-section-settings-dialog]');
    const sectionWidth = sectionDialog?.querySelector('[data-page-section-width]');
    const sectionTheme = sectionDialog?.querySelector('[data-page-section-theme]');
    const sectionVisibility = sectionDialog?.querySelector('[data-page-section-visibility]');
    const sectionVisibilityState = sectionVisibility?.closest('.admin-toggle')?.querySelector('[data-admin-toggle-state]');
    const syncSectionVisibility = () => {
      if (sectionVisibilityState && sectionVisibility) {
        sectionVisibilityState.textContent = sectionVisibility.checked ? sectionVisibilityState.dataset.stateOn : sectionVisibilityState.dataset.stateOff;
      }
    };
    sectionVisibility?.addEventListener('change', syncSectionVisibility);
    const sectionSave = sectionDialog?.querySelector('[data-page-section-save]');
    const sectionGap = sectionDialog?.querySelector('[data-page-section-gap]');
    const sectionGapField = sectionDialog?.querySelector('[data-page-section-gap-field]');
    const sectionLayoutField = sectionDialog?.querySelector('[data-page-row-layout-field]');
    const sectionLayoutChoices = [...(sectionLayoutField?.querySelectorAll('[data-page-row-layout-choice]') || [])];
    const sectionLayoutWarning = sectionDialog?.querySelector('[data-page-layout-warning]');
    const rowLayoutLabel = (count) => `${count} ${count === 1 ? window.cmsTranslate('Kolonne') : translatePage('page_builder_v2.columns')}`;
    let settingsSection = null;
    let settingsReturnFocus = null;
    sectionDialog?.addEventListener('close', () => {
      settingsSection = null;
      if (settingsReturnFocus?.isConnected) settingsReturnFocus.focus({ preventScroll: true });
      settingsReturnFocus = null;
    });
    sectionSave?.addEventListener('click', async () => {
      if (!settingsSection) return;
      const widthInput = settingsSection.querySelector('[data-builder-section-width]');
      const themeInput = settingsSection.querySelector('[data-builder-section-theme]');
      const statusInput = settingsSection.querySelector('[data-builder-section-status]');
      if (!widthInput || !themeInput || !statusInput || !sectionVisibility) return;
      // Check layout safety BEFORE applying any other setting. A failed shrink must
      // leave the editor, section settings and all populated columns untouched.
      if (settingsSection.classList.contains('admin-page-row--v2-grid')) {
        const chosen = Number(sectionLayoutChoices.find((input) => input.checked)?.value || gridColumns(settingsSection).length);
        if (![1, 2, 3, 4, 5, 6].includes(chosen)) return;
        const applied = await syncGridColumns(settingsSection, chosen);
        if (!applied) {
          if (sectionLayoutWarning) {
            sectionLayoutWarning.textContent = translatePage('page_builder_v2.layout_change_blocked');
            sectionLayoutWarning.hidden = false;
          }
          return;
        }
      }
      const allowedWidths = ['narrow', 'standard', 'wide', 'full'];
      const allowedThemes = ['light', 'soft', 'dark'];
      const nextWidth = allowedWidths.includes(sectionWidth?.value) ? sectionWidth.value : 'standard';
      const nextTheme = allowedThemes.includes(sectionTheme?.value) ? sectionTheme.value : 'light';
      if (widthInput.value !== nextWidth) {
        widthInput.value = nextWidth;
        widthInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (themeInput.value !== nextTheme) {
        themeInput.value = nextTheme;
        themeInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const nextStatus = sectionVisibility.checked ? 'active' : 'hidden';
      if (statusInput.value !== nextStatus) {
        statusInput.value = nextStatus;
        statusInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const hiddenLabel = settingsSection.querySelector('[data-page-section-hidden-label]');
      if (hiddenLabel) hiddenLabel.hidden = nextStatus !== 'hidden';
      const gapInput = settingsSection.querySelector('[data-grid-gap]');
      if (gapInput && sectionGap && ['compact', 'normal', 'large'].includes(sectionGap.value) && gapInput.value !== sectionGap.value) {
        gapInput.value = sectionGap.value;
        gapInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      closeDialog(sectionDialog);
    });

    const rebuildGridPreset = (gridBlock) => {
      const count = Number(gridBlock.querySelector('[data-grid-column-count]')?.value || 2);
      const preset = gridBlock.querySelector('[data-grid-preset]');
      if (!preset) return;
      const previous = preset.value;
      const options = GRID_PRESETS[count] || GRID_PRESETS[2];
      preset.innerHTML = '';
      options.forEach(([value, label]) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        preset.appendChild(option);
      });
      preset.value = options.some(([value]) => value === previous) ? previous : 'equal';
      updateSummary(gridBlock);
      const layoutLabel = gridBlock.querySelector('[data-page-layout-label]');
      if (layoutLabel) layoutLabel.textContent = gridBlock.classList.contains('admin-page-row--v2-grid')
        ? rowLayoutLabel(count)
        : rowLayoutLabel(count);
    };

    const syncGridColumns = async (gridBlock, requestedCount) => {
      const editor = gridBlock.querySelector('[data-grid-editor]');
      if (!editor) return false;
      const isV2 = gridBlock.classList.contains('admin-page-row--v2-grid');
      const desired = Math.max(isV2 ? 1 : 2, Math.min(6, Number(requestedCount) || 2));
      const columns = gridColumns(gridBlock);

      if (desired < columns.length) {
        const removed = columns.slice(desired);
        const containsContent = removed.some((column) => gridChildren(column).length > 0);
        if (containsContent && isV2) {
          // Never offer to discard a populated V2 column. The editor must move
          // or remove its blocks explicitly before selecting fewer columns.
          return false;
        }
        if (containsContent && !await window.adminConfirm('De kolonner du fjerner indeholder indhold. Skal de og deres indhold fjernes?', 'Fjern kolonner', 'Fjern kolonner')) {
          const countSelect = gridBlock.querySelector('[data-grid-column-count]');
          if (countSelect) countSelect.value = String(columns.length);
          return false;
        }
        removed.forEach((column) => column.remove());
      } else if (desired > columns.length) {
        const source = columns.at(-1);
        while (gridColumns(gridBlock).length < desired && source) {
          const clone = source.cloneNode(true);
          const childList = clone.querySelector('[data-grid-child-list]');
          if (childList) childList.innerHTML = '';
          const emptyState = clone.querySelector('[data-grid-column-empty]');
          if (emptyState) emptyState.hidden = false;
          editor.appendChild(clone);
        }
      }

      editor.dataset.gridCount = String(desired);
      const countSelect = gridBlock.querySelector('[data-grid-column-count]');
      if (countSelect) countSelect.value = String(desired);
      reindex();
      rebuildGridPreset(gridBlock);
      return true;
    };

    const addGridChild = (button, selectedType = null) => {
      const column = button?.closest('[data-grid-column]');
      const gridBlock = button?.closest('[data-builder-block][data-block-type="grid"]');
      if (!column || !gridBlock) return null;
      if (gridBlock.classList.contains('admin-page-row--v2-grid') && gridChildren(column).length > 0) {
        window.adminNotify(translatePage('page_builder_v2.column_full'));
        return null;
      }
      const type = selectedType || column.querySelector('[data-grid-child-type]')?.value || 'text';
      const template = gridBlock.querySelector(`template[data-grid-child-template="${CSS.escape(type)}"]`);
      const childList = column.querySelector('[data-grid-child-list]');
      if (!template || !childList) return null;
      if (gridChildren(column).length >= 12) {
        window.adminNotify(window.cmsTranslate('En kolonne kan højst indeholde 12 indholdsblokke.'));
        return null;
      }
      const topIndex = blocks().indexOf(gridBlock);
      const columnIndex = gridColumns(gridBlock).indexOf(column);
      const childIndex = gridChildren(column).length;
      const html = template.innerHTML
        .replaceAll('__TOP_INDEX__', String(topIndex))
        .replaceAll('__COLUMN_INDEX__', String(columnIndex))
        .replaceAll('__CHILD_INDEX__', String(childIndex));
      childList.insertAdjacentHTML('beforeend', html);
      reindex();
      const added = gridChildren(column).at(-1);
      newGalleryDefaults(added);
      return added;
    };

    builderForm.addEventListener('click', async (event) => {
      const gridAdd = event.target.closest('[data-grid-child-add]');
      if (gridAdd) {
        const column = gridAdd.closest('[data-grid-column]');
        if (column?.closest('.admin-page-row--v2-grid') && gridChildren(column).length >= 1) {
          window.adminNotify(translatePage('page_builder_v2.column_full'));
          return;
        }
        chooserReturnFocus = gridAdd;
        showBlockChoices(column);
        return;
      }

      const rowToggle = event.target.closest('[data-page-row-toggle]');
      if (rowToggle) {
        const fields = rowToggle.closest('[data-page-row__component], .admin-page-row__component')?.querySelector('[data-page-row-fields]');
        if (fields) {
          fields.hidden = !fields.hidden;
          syncBlockToggle(rowToggle, !fields.hidden);
          if (!fields.hidden) fields.querySelector('input:not([type=hidden]), textarea, select')?.focus();
        }
        return;
      }

      const sectionSettingsButton = event.target.closest('[data-page-section-settings]');
      if (sectionSettingsButton) {
        const row = sectionSettingsButton.closest('[data-builder-block]');
        const widthInput = row?.querySelector('[data-builder-section-width]');
        const themeInput = row?.querySelector('[data-builder-section-theme]');
        const statusInput = row?.querySelector('[data-builder-section-status]');
        if (row && widthInput && themeInput && statusInput && sectionDialog && sectionWidth && sectionTheme && sectionVisibility) {
          settingsSection = row;
          settingsReturnFocus = sectionSettingsButton;
          sectionWidth.value = widthInput.value;
          sectionTheme.value = themeInput.value;
          sectionVisibility.checked = statusInput.value !== 'hidden';
          syncSectionVisibility();
          const gapInput = row.querySelector('[data-grid-gap]');
          const isV2Row = row.classList.contains('admin-page-row--v2-grid');
          if (sectionLayoutField) sectionLayoutField.hidden = !isV2Row;
          if (sectionLayoutWarning) {
            sectionLayoutWarning.textContent = '';
            sectionLayoutWarning.hidden = true;
          }
          if (isV2Row) {
            const current = gridColumns(row).length;
            sectionLayoutChoices.forEach((input) => { input.checked = Number(input.value) === current; });
          }
          if (sectionGap && sectionGapField) {
            sectionGapField.hidden = !gapInput;
            if (gapInput) sectionGap.value = gapInput.value;
          }
          openDialog(sectionDialog);
          sectionWidth.focus();
        }
        return;
      }

      const settingsToggle = event.target.closest('[data-page-grid-settings-toggle]');
      if (settingsToggle) {
        const panel = settingsToggle.closest('[data-builder-block]')?.querySelector('[data-page-grid-settings]');
        if (panel) {
          panel.hidden = !panel.hidden;
          settingsToggle.setAttribute('aria-expanded', String(!panel.hidden));
        }
        return;
      }

      const childToggle = event.target.closest('[data-page-child-toggle]');
      if (childToggle) {
        const fields = childToggle.closest('[data-grid-child]')?.querySelector('[data-page-child-fields]');
        if (fields) {
          fields.hidden = !fields.hidden;
          syncBlockToggle(childToggle, !fields.hidden);
          if (!fields.hidden) fields.querySelector('input:not([type=hidden]), textarea, select')?.focus();
        }
        return;
      }

      const gridRemove = event.target.closest('[data-grid-child-remove]');
      if (gridRemove) {
        const child = gridRemove.closest('[data-grid-child]');
        const column = gridRemove.closest('[data-grid-column]');
        if (child && await window.adminConfirm(window.cmsTranslate('Fjern denne blok fra kolonnen? Ændringen gemmes først, når du gemmer siden.'), window.cmsTranslate('Fjern blok'), window.cmsTranslate('Fjern blok'))) {
          child.remove();
          reindex();
          if (column) updateGridColumnMeta(column, Number(column.dataset.gridColumnIndex || 0));
        }
        return;
      }

      const gridUp = event.target.closest('[data-grid-child-up]');
      if (gridUp) {
        const child = gridUp.closest('[data-grid-child]');
        const previous = child?.previousElementSibling;
        if (child && previous?.matches('[data-grid-child]')) {
          child.parentElement.insertBefore(child, previous);
          reindex();
          gridUp.focus();
        }
        return;
      }

      const gridDown = event.target.closest('[data-grid-child-down]');
      if (gridDown) {
        const child = gridDown.closest('[data-grid-child]');
        const next = child?.nextElementSibling;
        if (child && next?.matches('[data-grid-child]')) {
          child.parentElement.insertBefore(next, child);
          reindex();
          gridDown.focus();
        }
        return;
      }

      const remove = event.target.closest('[data-builder-remove]');
      if (remove) {
        const block = remove.closest('[data-builder-block]');
        if (block && await window.adminConfirm(window.cmsTranslate('Fjern denne sektion fra siden? Ændringen gemmes først, når du gemmer siden.'), window.cmsTranslate('Fjern sektion'), window.cmsTranslate('Fjern sektion'))) {
          block.remove();
          reindex();
        }
        return;
      }

      const up = event.target.closest('[data-builder-up]');
      if (up) {
        const block = up.closest('[data-builder-block]');
        const previous = block?.previousElementSibling;
        if (block && previous?.matches('[data-builder-block]')) {
          list.insertBefore(block, previous);
          reindex();
          up.focus();
        }
        return;
      }

      const down = event.target.closest('[data-builder-down]');
      if (down) {
        const block = down.closest('[data-builder-block]');
        const next = block?.nextElementSibling;
        if (block && next?.matches('[data-builder-block]')) {
          list.insertBefore(next, block);
          reindex();
          down.focus();
        }
      }
    });

    builderForm.addEventListener('input', (event) => {
      const child = event.target.closest('[data-grid-child]');
      if (child && event.target.matches('[data-grid-child-summary-source]')) updateGridChildSummary(child);

      const block = event.target.closest('[data-builder-block]');
      if (block && (event.target.matches('[data-builder-summary-source]') || event.target.matches('[data-builder-component]'))) {
        updateSummary(block);
      }
    });

    builderForm.querySelectorAll('[data-builder-block], [data-grid-child]').forEach(scope => {
      syncGalleryFields(scope);
      const settings = scope.querySelector('[data-builder-gallery-settings]');
      if (settings?.dataset.galleryLegacy === '1') {
        settings.querySelectorAll('[data-gallery-new-field]').forEach(field => { field.dataset.galleryName = field.name; field.removeAttribute('name'); });
      }
    });

    builderForm.addEventListener('change', async (event) => {
      const child = event.target.closest('[data-grid-child]');
      if (child && event.target.matches('[data-page-child-visibility]')) {
        const status = child.querySelector('[data-page-child-status]');
        if (status) status.value = event.target.checked ? 'active' : 'hidden';
      }
      if (child && event.target.matches('[data-grid-child-component]')) {
        updateGridChildSummary(child);
        if (/^gallery:gallery-[1-9][0-9]*$/.test(event.target.value)) newGalleryDefaults(child);
        syncGalleryFields(child);
      }

      const block = event.target.closest('[data-builder-block]');
      if (block && event.target.matches('[data-builder-component]')) {
        updateSummary(block);
        if (/^gallery:gallery-[1-9][0-9]*$/.test(event.target.value)) newGalleryDefaults(block);
        syncGalleryFields(block);
      }

      if (event.target.closest('[data-builder-gallery-settings]') && event.target.matches('[data-gallery-new-field]')) {
        const scope = event.target.closest('[data-grid-child], [data-builder-block]');
        activateGalleryFields(scope);
        syncGalleryFields(scope);
      }

      const countSelect = event.target.closest('[data-grid-column-count]');
      if (countSelect && block?.dataset.blockType === 'grid') {
        await syncGridColumns(block, countSelect.value);
      }
      if (event.target.matches('[data-grid-preset]') && block?.dataset.blockType === 'grid') {
        updateSummary(block);
      }
    });

    // Accessible keyboard alternative for moving a V2 block between columns.
    builderForm.addEventListener('keydown', (event) => {
      const grip = event.target.closest('[data-grid-child-drag]');
      if (!grip || !event.altKey || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
      const child = grip.closest('[data-grid-child]');
      const row = child?.closest('.admin-page-row--v2-grid');
      if (!row) return;
      const current = child.closest('[data-grid-column]');
      const columns = gridColumns(row);
      const next = columns.indexOf(current) + (event.key === 'ArrowLeft' ? -1 : 1);
      event.preventDefault();
      if (next < 0 || next >= columns.length || gridChildren(columns[next]).length) return;
      columns[next].querySelector('[data-grid-child-list]')?.appendChild(child);
      reindex();
      grip.focus();
    });

    builderForm.addEventListener('dragstart', (event) => {
      const gridHandle = event.target.closest('[data-grid-child-drag]');
      if (gridHandle) {
        draggedGridChild = gridHandle.closest('[data-grid-child]');
        if (!draggedGridChild) return;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', 'page-builder-grid-child');
        // The grip initiates drag, but the browser must show the entire block.
        const rect = draggedGridChild.getBoundingClientRect();
        event.dataTransfer.setDragImage(draggedGridChild, Math.max(0, Math.round(event.clientX - rect.left)), Math.max(0, Math.round(event.clientY - rect.top)));
        draggedGridChild.classList.add('is-dragging');
        return;
      }

      const handle = event.target.closest('[data-builder-drag]');
      if (!handle) return;
      draggedBlock = handle.closest('[data-builder-block]');
      if (!draggedBlock) return;
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', 'page-builder-block');
      // Native preview defaults to the tiny draggable grip. Use the full section instead.
      const rect = draggedBlock.getBoundingClientRect();
      event.dataTransfer.setDragImage(draggedBlock, Math.max(0, Math.round(event.clientX - rect.left)), Math.max(0, Math.round(event.clientY - rect.top)));
      draggedBlock.classList.add('is-dragging');
    });

    builderForm.addEventListener('dragend', () => {
      draggedBlock?.classList.remove('is-dragging');
      draggedGridChild?.classList.remove('is-dragging');
      draggedBlock = null;
      draggedGridChild = null;
      reindex();
    });

    const dragTargetGridList = (target) => {
      const list = target.closest('[data-grid-child-list]');
      if (list) return list;
      const column = target.closest('[data-grid-column]');
      if (!column?.closest('.admin-page-row--v2-grid') || gridChildren(column).length) return null;
      return column.querySelector('[data-grid-child-list]');
    };

    builderForm.addEventListener('dragover', (event) => {
      if (!draggedGridChild) return;
      const targetList = dragTargetGridList(event.target);
      if (!targetList) return;
      const row = targetList.closest('[data-builder-block]');
      if (row?.classList.contains('admin-page-row--v2-grid') &&
          [...targetList.children].some((item) => item.matches('[data-grid-child]') && item !== draggedGridChild)) {
        event.dataTransfer.dropEffect = 'none';
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      event.dataTransfer.dropEffect = 'move';
      const siblings = [...targetList.querySelectorAll(':scope > [data-grid-child]')].filter((child) => child !== draggedGridChild);
      const next = siblings.find((child) => {
        const rect = child.getBoundingClientRect();
        return event.clientY < rect.top + rect.height / 2;
      });
      if (next) targetList.insertBefore(draggedGridChild, next);
      else targetList.appendChild(draggedGridChild);
    });

    builderForm.addEventListener('drop', (event) => {
      if (!draggedGridChild) return;
      const targetList = dragTargetGridList(event.target);
      if (!targetList) return;
      const row = targetList.closest('[data-builder-block]');
      if (row?.classList.contains('admin-page-row--v2-grid') &&
          [...targetList.children].some((item) => item.matches('[data-grid-child]') && item !== draggedGridChild)) return;
      event.preventDefault();
      event.stopPropagation();
      reindex();
    });

    list?.addEventListener('dragover', (event) => {
      if (!draggedBlock || draggedGridChild) return;
      event.preventDefault();
      event.dataTransfer.dropEffect = 'move';
      const siblings = blocks().filter((block) => block !== draggedBlock);
      const next = siblings.find((block) => {
        const rect = block.getBoundingClientRect();
        return event.clientY < rect.top + rect.height / 2;
      });
      if (next) list.insertBefore(draggedBlock, next);
      else list.appendChild(draggedBlock);
    });

    list?.addEventListener('drop', (event) => {
      if (!draggedBlock || draggedGridChild) return;
      event.preventDefault();
      reindex();
    });

    updateMode();
    reindex();
  }


  // Navigation V1.7.2: one edit form and one save for menu settings and all items.
  const navigationEditor = document.querySelector('[data-nav-editor]');
  if (navigationEditor) {
    const list = navigationEditor.querySelector('[data-nav-list]');
    const template = document.querySelector('[data-nav-template]');
    const addButton = navigationEditor.querySelector('[data-nav-add]');
    const empty = navigationEditor.querySelector('[data-nav-empty]');
    let nextId = 1;
    let dragSource = null;
    let dragStartX = 0;
    let dropPlan = null;
    const status = navigationEditor.querySelector('[data-nav-move-status]');
    const nodes = () => [...list.querySelectorAll(':scope > [data-nav-item]')].filter(node => !node.hidden);
    const getId = (node) => node.dataset.navItem;
    const parentOf = (node) => node.querySelector('[data-nav-parent]')?.value || '0';
    const titleOf = (node) => node.querySelector('[data-nav-preview]')?.textContent || 'Menupunkt';
    const hasChildren = (node) => nodes().some(other => parentOf(other) === getId(node));
    const markDirty = () => navigationEditor.dispatchEvent(new Event('admin:changed', { bubbles: true }));
    const announce = () => { if (status) status.textContent = window.cmsTranslate('nav.editor_moved'); };
    const updateEmpty = () => { if (empty) empty.hidden = nodes().length !== 0; };
    const updateFields = (node) => {
      const isPage = node.querySelector('[data-nav-type]')?.value === 'page';
      const pageField = node.querySelector('[data-nav-page-field]');
      const urlField = node.querySelector('[data-nav-url-field]');
      const page = node.querySelector('[data-nav-page]');
      const url = node.querySelector('[data-nav-url]');
      if (pageField) pageField.hidden = !isPage;
      if (urlField) urlField.hidden = isPage;
      if (page) { page.disabled = !isPage; page.required = isPage; }
      if (url) { url.disabled = isPage; url.required = !isPage; }
      const label = node.querySelector('[data-nav-label]');
      if (label) label.required = !isPage;
      const pageText = page?.selectedOptions?.[0]?.textContent?.replace(/ \(ikke publiceret\)$/, '') || '';
      const text = label?.value.trim() || (isPage ? pageText : '') || window.cmsTranslate('Nyt menupunkt');
      node.querySelector('[data-nav-preview]').textContent = text;
      node.querySelector('[data-nav-subtitle]').textContent = isPage ? window.cmsTranslate('Side · ') + (pageText || window.cmsTranslate('Vælg side')) : window.cmsTranslate('Link · ') + (url?.value || '');
      node.querySelector('[data-nav-hidden-label]').hidden = !!node.querySelector('[data-nav-visible]')?.checked;
    };
    // The DOM stays flat for compatibility with the existing items[ID] POST format.
    // Every top-level item and its consecutive children form one indivisible drag group.
    const group = (node) => {
      const all = nodes();
      const index = all.indexOf(node);
      if (index < 0) return [];
      if (parentOf(node) !== '0') return [node];
      const result = [node];
      for (let i = index + 1; i < all.length && parentOf(all[i]) !== '0'; i++) result.push(all[i]);
      return result;
    };
    const refreshPositions = () => {
      nodes().forEach(node => {
        node.dataset.parent = parentOf(node);
        node.classList.toggle('is-child', parentOf(node) !== '0');
      });
      updateEmpty();
    };
    const refreshParents = () => {
      const all = nodes();
      const roots = all.filter(node => parentOf(node) === '0');
      all.forEach(node => {
        const select = node.querySelector('[data-nav-parent]');
        if (!select) return;
        const current = select.value;
        select.replaceChildren(new Option(window.cmsTranslate('Hovedniveau'), '0'));
        roots.forEach(root => {
          if (root === node || hasChildren(node)) return;
          select.add(new Option(window.cmsTranslate('nav.under') + ' ' + titleOf(root), getId(root)));
        });
        select.value = [...select.options].some(option => option.value === current) ? current : '0';
      });
      refreshPositions();
    };
    /** Move a whole top-level subtree, or one leaf, without losing POST keys. */
    const moveNode = (source, target, mode) => {
      if (!source || !target || source === target || !nodes().includes(source) || !nodes().includes(target)) return false;
      const moving = group(source);
      if (moving.includes(target)) return false;
      const parent = mode === 'child' ? getId(target) : parentOf(target);
      if (parent !== '0' && (hasChildren(source) || !nodes().some(node => getId(node) === parent && parentOf(node) === '0'))) return false;
      if (mode === 'child' && parentOf(target) !== '0') return false;
      let end = mode === 'before' ? target : group(target).at(-1)?.nextElementSibling;
      // The reference may itself be the dragged node (adjacent destination).
      // Capture the next surviving sibling *before* detaching the group.
      if (moving.includes(end)) end = moving.at(-1)?.nextElementSibling;
      const fragment = document.createDocumentFragment();
      moving.forEach(node => fragment.appendChild(node));
      // The parent selector remains authoritative for the existing saveEditor validator.
      source.querySelector('[data-nav-parent]').value = parent;
      list.insertBefore(fragment, end || null);
      refreshParents();
      markDirty();
      announce();
      return true;
    };
    const moveUnderParent = (node, parent) => {
      if (parent !== '0' && hasChildren(node)) return false;
      const all = nodes();
      const target = all.find(other => getId(other) === parent);
      if (parent !== '0') {
        if (!target || parentOf(target) !== '0' || target === node) return false;
        return moveNode(node, target, 'child');
      }
      const moving = group(node);
      const fragment = document.createDocumentFragment();
      moving.forEach(child => fragment.appendChild(child));
      list.appendChild(fragment);
      refreshParents();
      markDirty();
      announce();
      return true;
    };
    const openNode = (node, open) => {
      node.querySelector('[data-nav-fields]').hidden = !open;
      node.querySelector('[data-nav-toggle]').setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) node.querySelector('[data-nav-label]')?.focus();
    };
    list.addEventListener('click', async (event) => {
      const button = event.target.closest('button');
      const node = button?.closest('[data-nav-item]');
      if (!button || !node) return;
      if (button.matches('[data-nav-toggle]')) {
        openNode(node, node.querySelector('[data-nav-fields]').hidden);
      } else if (button.matches('[data-nav-remove]')) {
        if (!await window.adminConfirm(window.cmsTranslate('Fjern menupunktet? Ændringen træder først i kraft, når du gemmer.'), window.cmsTranslate('Fjern menupunkt'), window.cmsTranslate('Fjern menupunkt'))) return;
        const id = getId(node);
        if (hasChildren(node)) {
          for (const child of nodes().filter(other => parentOf(other) === id)) {
            child.querySelector('[data-nav-parent]').value = '0';
            list.appendChild(child);
          }
        }
        node.querySelector('[data-nav-deleted]').value = '1';
        node.hidden = true;
        markDirty();
        refreshParents();
      }
    });
    list.addEventListener('change', (event) => {
      const target = event.target;
      const node = target.closest('[data-nav-item]');
      if (!node) return;
      if (target.matches('[data-nav-parent]')) {
        const selected = target.value;
        const original = node.dataset.parent || '0';
        target.value = original; // Keep the old group intact until the move completes.
        if (selected !== '0' && hasChildren(node)) {
          window.adminNotify(window.cmsTranslate('Flyt først undermenuerne, inden du placerer dette punkt under et andet.'));
          return;
        }
        // A manual parent change is also a supported, accessible alternative.
        if (selected !== '0') {
          if (!moveUnderParent(node, selected)) refreshParents();
        } else if (original !== '0') {
          const formerRoot = nodes().find(other => getId(other) === original);
          if (formerRoot) moveNode(node, formerRoot, 'after');
        }
      }
      updateFields(node);
      markDirty();
    });
    list.addEventListener('input', (event) => {
      const node = event.target.closest('[data-nav-item]');
      if (!node) return;
      updateFields(node);
      if (event.target.matches('[data-nav-label]')) refreshParents();
      markDirty();
    });
    // Keyboard: Alt + vertical arrows sorts peers, right nests a leaf beneath
    // the preceding top-level item, left promotes a child to the root.
    list.addEventListener('keydown', (event) => {
      const grip = event.target.closest('[data-nav-grip]');
      if (!grip || !event.altKey || !['ArrowUp', 'ArrowDown', 'ArrowRight', 'ArrowLeft'].includes(event.key)) return;
      event.preventDefault();
      const source = grip.closest('[data-nav-item]');
      const all = nodes();
      const parent = parentOf(source);
      let moved = false;
      if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        const siblings = all.filter(node => parentOf(node) === parent);
        const index = siblings.indexOf(source);
        const target = siblings[index + (event.key === 'ArrowUp' ? -1 : 1)];
        if (target) moved = moveNode(source, target, event.key === 'ArrowUp' ? 'before' : 'after');
      } else if (event.key === 'ArrowRight' && parent === '0' && !hasChildren(source)) {
        const previousRoots = all.slice(0, all.indexOf(source)).filter(node => parentOf(node) === '0');
        if (previousRoots.length) moved = moveNode(source, previousRoots.at(-1), 'child');
      } else if (event.key === 'ArrowLeft' && parent !== '0') {
        const root = all.find(node => getId(node) === parent);
        if (root) {
          // Move after the root group, not to the end of the entire menu.
          const reference = group(root).at(-1)?.nextElementSibling;
          const selector = source.querySelector('[data-nav-parent]');
          selector.value = '0';
          list.insertBefore(source, reference || null);
          refreshParents(); markDirty(); announce(); moved = true;
        }
      }
      if (moved) grip.focus();
    });
    // Native pointer DnD: stationary preview of the entire menu item, with
    // explicit before/after/nest target. Never mutate DOM in dragover.
    const clearDropTarget = () => {
      list.querySelectorAll('[data-nav-drop]').forEach(node => node.removeAttribute('data-nav-drop'));
      list.removeAttribute('data-nav-drop-end');
      dropPlan = null;
    };
    const planAt = (target, pointerX, pointerY) => {
      if (!target || !dragSource || target === dragSource || group(dragSource).includes(target)) return null;
      let anchor = target;
      let intent = 'before';
      if (parentOf(target) !== '0') {
        // Roots with descendants cannot be nested. They sort as intact groups.
        if (hasChildren(dragSource)) {
          anchor = nodes().find(node => getId(node) === parentOf(target));
        } else if (pointerX < target.getBoundingClientRect().left - 14) {
          anchor = nodes().find(node => getId(node) === parentOf(target));
        }
      } else {
        const row = target.querySelector('.admin-nav-editor__row');
        const rect = (row || target).getBoundingClientRect();
        const shiftedRight = pointerX - dragStartX > 28;
        if (!hasChildren(dragSource) && pointerX > rect.left + 42 && shiftedRight) {
          return { target, mode: 'child' };
        }
      }
      if (!anchor || group(dragSource).includes(anchor)) return null;
      const rect = anchor.querySelector('.admin-nav-editor__row')?.getBoundingClientRect() || anchor.getBoundingClientRect();
      intent = pointerY < rect.top + rect.height / 2 ? 'before' : 'after';
      const targetParent = parentOf(anchor);
      if (targetParent !== '0' && hasChildren(dragSource)) return null;
      return { target: anchor, mode: intent };
    };
    list.addEventListener('dragstart', (event) => {
      const grip = event.target.closest('[data-nav-grip]');
      if (!grip) return;
      dragSource = grip.closest('[data-nav-item]');
      if (!dragSource) return;
      dragStartX = event.clientX;
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', 'navigation-item');
      const rect = dragSource.getBoundingClientRect();
      event.dataTransfer.setDragImage(dragSource, Math.max(0, Math.round(event.clientX - rect.left)), Math.max(0, Math.round(event.clientY - rect.top)));
      dragSource.classList.add('is-dragging');
    });
    list.addEventListener('dragover', (event) => {
      if (!dragSource) return;
      const target = event.target.closest('[data-nav-item]');
      clearDropTarget();
      if (!target) {
        // Dropping below the last card promotes a leaf to a root item.
        if (event.target === list && nodes().length) {
          dropPlan = { end: true };
          list.dataset.navDropEnd = 'true';
          event.preventDefault();
        }
        return;
      }
      dropPlan = planAt(target, event.clientX, event.clientY);
      if (!dropPlan) return;
      dropPlan.target.dataset.navDrop = dropPlan.mode;
      event.preventDefault();
      event.dataTransfer.dropEffect = 'move';
    });
    list.addEventListener('drop', (event) => {
      if (!dragSource || !dropPlan) return;
      event.preventDefault();
      const plan = dropPlan;
      clearDropTarget();
      if (plan.end) {
        const moving = group(dragSource);
        const fragment = document.createDocumentFragment();
        moving.forEach(node => fragment.appendChild(node));
        dragSource.querySelector('[data-nav-parent]').value = '0';
        list.appendChild(fragment);
        refreshParents(); markDirty(); announce();
      } else {
        moveNode(dragSource, plan.target, plan.mode);
      }
      dragSource.classList.remove('is-dragging');
      dragSource = null;
    });
    list.addEventListener('dragleave', (event) => {
      if (!list.contains(event.relatedTarget)) clearDropTarget();
    });
    list.addEventListener('dragend', () => {
      dragSource?.classList.remove('is-dragging');
      dragSource = null;
      clearDropTarget();
    });
    if (addButton) {
      addButton.addEventListener('click', () => {
        if (!template) return;
        const key = 'new_' + nextId++;
        const fragment = template.content.cloneNode(true);
        const node = fragment.querySelector('[data-nav-item]');
        node.querySelectorAll('[name]').forEach(field => {
          field.name = field.name.replaceAll('__KEY__', key);
        });
        node.dataset.navItem = key;
        const editorFields = node.querySelector('[data-nav-fields]');
        if (editorFields) editorFields.id = 'nav-fields-' + key;
        node.querySelector('[data-nav-toggle]')?.setAttribute('aria-controls', 'nav-fields-' + key);
        list.appendChild(fragment);
        refreshParents();
        updateFields(node);
        openNode(node, true);
        markDirty();
      });
    }
    if (addButton) addButton.hidden = false;
    nodes().forEach(node => { updateFields(node); openNode(node, false); });
    refreshParents();
    // Opening a menu with a visible validation error lets the user correct it directly.
    if (navigationEditor.ownerDocument.querySelector('.admin-notice--error')) {
      nodes().forEach(node => openNode(node, true));
    }
  }
})();
