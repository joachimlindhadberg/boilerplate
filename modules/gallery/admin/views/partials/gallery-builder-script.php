<script>
(() => {
  const root = document.querySelector('[data-gallery-builder]');
  if (!root) return;
  const list = root.querySelector('[data-gallery-items]');
  const empty = root.querySelector('[data-gallery-empty]');
  const dialog = document.querySelector('[data-gallery-media-dialog]');
  const grid = dialog?.querySelector('[data-gallery-media-grid]');
  const search = dialog?.querySelector('[data-gallery-media-search]');
  const loading = dialog?.querySelector('[data-gallery-media-loading]');
  const emptyPicker = dialog?.querySelector('[data-gallery-media-empty]');
  const addButton = dialog?.querySelector('[data-gallery-media-add]');
  const countLabel = dialog?.querySelector('[data-gallery-media-count]');
  const deleteDialog = document.querySelector('[data-gallery-delete-dialog]');
  let pickerItems = [];
  let selected = new Set();
  let requestId = 0;
  let timer = 0;
  let dragged = null;
  let dropTarget = null;
  let dropSide = null;
  const template = root.querySelector('[data-gallery-item-template]');
  const sortStatus = root.querySelector('[data-gallery-sort-status]');
  const announce = (message) => { if (sortStatus) sortStatus.textContent = message; };
  const labels = {
    moved: <?= json_encode(t('gallery:gallery_moved_feedback'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    removed: <?= json_encode(t('gallery:gallery_removed_feedback'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    confirmBody: <?= json_encode(t('gallery:gallery_remove_confirm'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    confirmTitle: <?= json_encode(t('gallery:gallery_remove_image'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
  };

  const openDialog = (target) => { if (!target) return; if (typeof target.showModal === 'function') target.showModal(); else target.setAttribute('open',''); };
  const closeDialog = (target) => { if (!target) return; if (typeof target.close === 'function') target.close(); else target.removeAttribute('open'); };
  const existingIds = () => new Set([...list.querySelectorAll('[data-gallery-item]')].map((row) => row.dataset.mediaId || '').filter(Boolean));
  const updateEmpty = () => { if (empty) empty.hidden = list.querySelector('[data-gallery-item]') !== null; };
  const updateCount = () => { if (countLabel) countLabel.textContent = selected.size ? `(${selected.size})` : ''; if (addButton) addButton.disabled = selected.size === 0; };

  const renderPicker = (items) => {
    if (!grid) return;
    grid.innerHTML = '';
    const used = existingIds();
    items.forEach((item) => {
      const id = String(item.id || '');
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'admin-media-picker-item';
      button.dataset.galleryPickerId = id;
      if (selected.has(id)) button.classList.add('is-selected');
      if (used.has(id)) { button.disabled = true; button.title = 'Billedet er allerede i galleriet'; }
      const image = document.createElement('img'); image.src = String(item.url || ''); image.alt = ''; image.loading = 'lazy';
      const label = document.createElement('span'); label.textContent = String(item.title || item.filename || (window.cmsTranslate?.('Billede') ?? 'Billede'));
      button.append(image, label); grid.appendChild(button);
    });
    if (emptyPicker) emptyPicker.hidden = items.length > 0;
  };

  const loadPicker = async (query='') => {
    if (!grid) return;
    const current = ++requestId;
    if (loading) loading.hidden = false;
    if (emptyPicker) emptyPicker.hidden = true;
    try {
      const response = await fetch(`/admin/medier?mode=picker&q=${encodeURIComponent(query)}`, {headers:{Accept:'application/json'},credentials:'same-origin'});
      if (!response.ok) throw new Error('picker');
      const payload = await response.json();
      if (current !== requestId) return;
      pickerItems = Array.isArray(payload.items) ? payload.items : [];
      renderPicker(pickerItems);
    } catch (error) {
      if (current !== requestId) return;
      grid.innerHTML = '';
      if (emptyPicker) { emptyPicker.hidden = false; const text=emptyPicker.querySelector('span'); if(text) text.textContent='Mediebiblioteket kunne ikke indlæses. Prøv igen.'; }
    } finally { if (current === requestId && loading) loading.hidden = true; }
  };

  const appendItem = (item) => {
    const id = String(item.id || '');
    if (!/^\d+$/.test(id) || Number(id) < 1 || existingIds().has(id) || !template?.content) return;
    const article = template.content.querySelector('[data-gallery-item]')?.cloneNode(true);
    if (!article) return;
    article.dataset.mediaId = id;
    article.querySelector('input[name="media_ids[]"]').value = id;
    const thumb = article.querySelector('.admin-builder-block__preview img');
    if (thumb) thumb.src = String(item.url || '');
    const title = article.querySelector('.admin-builder-block__title strong');
    if (title) title.textContent = String(item.title || item.filename || (window.cmsTranslate?.('Billede') ?? 'Billede'));
    const filename = article.querySelector('.admin-builder-block__title span');
    if (filename) filename.textContent = String(item.filename || '');
    const remove = article.querySelector('[data-gallery-remove]');
    if (remove) { const description = `${labels.confirmTitle}: ${title?.textContent || ''}`; remove.setAttribute('aria-label', description); remove.title = description; }
    list.appendChild(article);
  };

  root.addEventListener('click', async (event) => {
    if (event.target.closest('[data-gallery-media-open]')) { selected = new Set(); updateCount(); if(search)search.value=''; openDialog(dialog); loadPicker(''); requestAnimationFrame(()=>search?.focus()); return; }
    const row = event.target.closest('[data-gallery-item]'); if (!row) return;
    if (event.target.closest('[data-gallery-remove]')) {
      if (typeof window.adminConfirm === 'function' && await window.adminConfirm(labels.confirmBody, labels.confirmTitle, labels.confirmTitle)) {
        const nextFocus = row.nextElementSibling?.querySelector('[data-gallery-drag]') || row.previousElementSibling?.querySelector('[data-gallery-drag]') || root.querySelector('[data-gallery-media-open]');
        row.remove(); updateEmpty(); announce(labels.removed); nextFocus?.focus();
      }
      return;
    }
  });

  dialog?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-gallery-picker-id]'); if (!button || button.disabled) return;
    const id = button.dataset.galleryPickerId || ''; if (!id) return;
    if (selected.has(id)) selected.delete(id); else selected.add(id);
    button.classList.toggle('is-selected', selected.has(id)); updateCount();
  });
  addButton?.addEventListener('click', () => { pickerItems.forEach((item) => { if(selected.has(String(item.id||''))) appendItem(item); }); selected.clear(); updateCount(); updateEmpty(); closeDialog(dialog); });
  search?.addEventListener('input', () => { clearTimeout(timer); timer=setTimeout(()=>loadPicker(search.value.trim()),220); });
  document.querySelector('[data-gallery-delete-open]')?.addEventListener('click',()=>openDialog(deleteDialog));

  // The DOM stays stationary during dragover. Only commit the new order on drop.
  // This prevents jitter, disappearing previews, and the dragged row targeting itself.
  const clearDrop = () => {
    list.querySelectorAll('[data-gallery-drop]').forEach((row) => row.removeAttribute('data-gallery-drop'));
    list.removeAttribute('data-gallery-drop-end');
    dropTarget = null; dropSide = null;
  };
  const resetDrag = () => {
    clearDrop();
    if (dragged) dragged.classList.remove('is-dragging');
    dragged = null;
  };
  const setDrop = (target, side) => {
    clearDrop();
    if (!target || target === dragged) return;
    dropTarget = target; dropSide = side;
    target.dataset.galleryDrop = side;
  };
  list?.addEventListener('dragstart', (event) => {
    const grip = event.target.closest('[data-gallery-drag]');
    if (!grip || !list.contains(grip)) return;
    dragged = grip.closest('[data-gallery-item]');
    if (!dragged || !event.dataTransfer) { resetDrag(); return; }
    const rect = dragged.getBoundingClientRect();
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', 'gallery-item');
    event.dataTransfer.setDragImage(dragged, Math.max(0, Math.round(event.clientX - rect.left)), Math.max(0, Math.round(event.clientY - rect.top)));
    dragged.classList.add('is-dragging');
  });
  list?.addEventListener('dragover', (event) => {
    if (!dragged) return;
    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
    const otherRows = [...list.querySelectorAll('[data-gallery-item]')].filter((row) => row !== dragged);
    if (!otherRows.length) { clearDrop(); return; }
    const first = otherRows[0]; const last = otherRows[otherRows.length - 1];
    if (event.clientY < first.getBoundingClientRect().top) { setDrop(first, 'before'); return; }
    if (event.clientY > last.getBoundingClientRect().bottom) { setDrop(last, 'after'); return; }
    const hovered = event.target.closest('[data-gallery-item]');
    const target = hovered && hovered !== dragged ? hovered : otherRows.reduce((closest, row) => {
      const r = row.getBoundingClientRect(); const c = closest.getBoundingClientRect();
      const mid = r.top + r.height / 2; const closestMid = c.top + c.height / 2;
      return Math.abs(event.clientY - mid) < Math.abs(event.clientY - closestMid) ? row : closest;
    });
    const rect = target.getBoundingClientRect();
    setDrop(target, event.clientY >= rect.top + rect.height / 2 ? 'after' : 'before');
  });
  list?.addEventListener('drop', (event) => {
    if (!dragged) return;
    event.preventDefault();
    if (dropTarget && dropTarget !== dragged) {
      const reference = dropSide === 'after' ? dropTarget.nextElementSibling : dropTarget;
      if (reference !== dragged) list.insertBefore(dragged, reference);
      announce(labels.moved);
    }
    resetDrag();
  });
  list?.addEventListener('dragend', resetDrag);
  list?.addEventListener('dragleave', (event) => {
    if (!list.contains(event.relatedTarget)) clearDrop();
  });
  // Accessible alternative: focus a grip and use Alt + ArrowUp / ArrowDown.
  list?.addEventListener('keydown', (event) => {
    const grip = event.target.closest('[data-gallery-drag]');
    if (!grip || !event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
    event.preventDefault();
    const row = grip.closest('[data-gallery-item]');
    if (!row) return;
    const sibling = event.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;
    if (!sibling?.matches('[data-gallery-item]')) return;
    if (event.key === 'ArrowUp') list.insertBefore(row, sibling);
    else list.insertBefore(sibling, row);
    grip.focus(); announce(labels.moved);
  });
  updateEmpty(); updateCount();
})();
</script>
