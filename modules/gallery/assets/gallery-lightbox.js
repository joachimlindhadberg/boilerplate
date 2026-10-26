/** Gallery lightbox behavior. Presentation belongs to frontend/theme. */
(() => {
  'use strict';
  if (!('HTMLDialogElement' in window) || typeof HTMLDialogElement.prototype.showModal !== 'function') return;

  let dialog = null;
  let display = null;
  let caption = null;
  let counter = null;
  let previous = null;
  let next = null;
  let activeLinks = [];
  let current = 0;
  let returnFocus = null;

  const createViewer = () => {
    if (dialog) return;
    dialog = document.createElement('dialog');
    dialog.className = 'gallery-lightbox';
    dialog.setAttribute('aria-label', 'Billedvisning');
    dialog.innerHTML = `
      <div class="gallery-lightbox__frame">
        <div class="gallery-lightbox__toolbar">
          <span class="gallery-lightbox__counter" aria-live="polite"></span>
          <button class="gallery-lightbox__close" type="button" aria-label="Luk billedvisning"></button>
        </div>
        <div class="gallery-lightbox__content">
          <button class="gallery-lightbox__prev" type="button" aria-label="Forrige billede"></button>
          <img class="gallery-lightbox__image" alt="">
          <button class="gallery-lightbox__next" type="button" aria-label="Næste billede"></button>
        </div>
        <p class="gallery-lightbox__caption"></p>
      </div>`;
    document.body.appendChild(dialog);
    display = dialog.querySelector('.gallery-lightbox__image');
    caption = dialog.querySelector('.gallery-lightbox__caption');
    counter = dialog.querySelector('.gallery-lightbox__counter');
    previous = dialog.querySelector('.gallery-lightbox__prev');
    next = dialog.querySelector('.gallery-lightbox__next');
    dialog.querySelector('.gallery-lightbox__close').addEventListener('click', () => dialog.close());
    previous.addEventListener('click', () => show(current - 1));
    next.addEventListener('click', () => show(current + 1));
    dialog.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowLeft') { event.preventDefault(); show(current - 1); }
      if (event.key === 'ArrowRight') { event.preventDefault(); show(current + 1); }
    });
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => {
      display.removeAttribute('src');
      activeLinks = [];
      if (returnFocus?.isConnected) returnFocus.focus({ preventScroll: true });
      returnFocus = null;
    });
  };

  const show = (index) => {
    if (!activeLinks.length) return;
    current = (index + activeLinks.length) % activeLinks.length;
    const link = activeLinks[current];
    // href is rendered server-side by MediaLibrary; never use arbitrary HTML or srcdoc.
    display.src = link.href;
    const description = link.querySelector('img')?.getAttribute('alt') || '';
    display.alt = description;
    caption.textContent = description;
    caption.hidden = description.trim() === '';
    counter.textContent = `${current + 1} / ${activeLinks.length}`;
    previous.hidden = next.hidden = activeLinks.length < 2;
  };

  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-gallery-lightbox-image]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const gallery = link.closest('.gallery--lightbox');
    if (!gallery) return;
    const links = [...gallery.querySelectorAll('a[data-gallery-lightbox-image]')];
    if (!links.includes(link)) return;
    createViewer();
    const iconTemplate = gallery.querySelector('[data-gallery-viewer-icons]');
    if (iconTemplate?.content) {
      for (const [kind, selector] of [
        ['close', '.gallery-lightbox__close'],
        ['previous', '.gallery-lightbox__prev'],
        ['next', '.gallery-lightbox__next']
      ]) {
        const button = dialog.querySelector(selector);
        const icon = iconTemplate.content.querySelector(`[data-gallery-icon="${kind}"] svg`);
        if (button && icon) button.replaceChildren(icon.cloneNode(true));
      }
    }
    dialog.setAttribute('aria-label', gallery.dataset.galleryLabelTitle || 'Billedvisning');
    dialog.dataset.viewer = gallery.dataset.galleryViewer === 'contained' ? 'contained' : 'fullscreen';
    dialog.querySelector('.gallery-lightbox__close').setAttribute('aria-label', gallery.dataset.galleryLabelClose || 'Luk billedvisning');
    previous.setAttribute('aria-label', gallery.dataset.galleryLabelPrevious || 'Forrige billede');
    next.setAttribute('aria-label', gallery.dataset.galleryLabelNext || 'Næste billede');
    if (dialog.open) return;
    activeLinks = links;
    returnFocus = link;
    show(links.indexOf(link));
    try {
      dialog.showModal();
      event.preventDefault();
    } catch {
      // Fall back to the normal anchor if the browser cannot open a dialog.
      activeLinks = [];
      returnFocus = null;
    }
  });
})();
