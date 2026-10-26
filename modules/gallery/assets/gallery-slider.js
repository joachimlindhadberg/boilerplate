/** Gallery-only behavior; layout remains frontend-theme owned. */
(() => {
  'use strict';
  const boot = () => {
    document.querySelectorAll('[data-gallery-slider]').forEach((gallery) => {
      if (gallery.dataset.galleryInitialized === '1') return;
      const slides = [...gallery.querySelectorAll(':scope > .gallery__item')];
      const controls = gallery.querySelector('[data-gallery-controls]');
      const previous = gallery.querySelector('[data-gallery-previous]');
      const next = gallery.querySelector('[data-gallery-next]');
      const counter = gallery.querySelector('[data-gallery-counter]');
      const play = gallery.querySelector('[data-gallery-play]');
      if (!slides.length || !controls || !previous || !next || !counter) return;
      const interval = Math.max(3000, Math.min(10000, (Number(gallery.dataset.galleryInterval) || 5) * 1000));
      let active = 0;
      let playing = gallery.dataset.galleryAutoplay === '1' && slides.length > 1;
      let hovered = false;
      let focused = false;
      let timer = null;
      const clear = () => { if (timer !== null) { clearTimeout(timer); timer = null; } };
      const show = (index) => {
        active = (index + slides.length) % slides.length;
        slides.forEach((slide, position) => {
          slide.hidden = position !== active;
          // Hidden slides must not retain a keyboard tab stop.
          if ('inert' in slide) slide.inert = position !== active;
        });
        counter.textContent = `${active + 1} / ${slides.length}`;
      };
      const pauseTicker = () => {
        clear();
        if (playing && !hovered && !focused && !document.hidden && slides.length > 1) {
          timer = window.setTimeout(() => { show(active + 1); pauseTicker(); }, interval);
        }
      };
      const setPlaying = (value) => {
        playing = value;
        if (play) {
          play.setAttribute('aria-pressed', playing ? 'true' : 'false');
          const pauseIcon = play.querySelector?.('[data-gallery-pause-icon]');
          const resumeIcon = play.querySelector?.('[data-gallery-resume-icon]');
          if (pauseIcon) pauseIcon.hidden = !playing;
          if (resumeIcon) resumeIcon.hidden = playing;
          play.setAttribute('aria-label', playing ? (play.dataset.galleryPauseLabel || 'Pause slideshow') : (play.dataset.galleryPlayLabel || 'Play slideshow'));
        }
        pauseTicker();
      };
      previous.addEventListener('click', () => { show(active - 1); pauseTicker(); });
      next.addEventListener('click', () => { show(active + 1); pauseTicker(); });
      if (play) play.addEventListener('click', () => setPlaying(!playing));
      gallery.addEventListener('mouseenter', () => { hovered = true; pauseTicker(); });
      gallery.addEventListener('mouseleave', () => { hovered = false; pauseTicker(); });
      gallery.addEventListener('focusin', () => { focused = true; pauseTicker(); });
      gallery.addEventListener('focusout', () => {
        focused = gallery.contains(document.activeElement);
        pauseTicker();
      });
      document.addEventListener('visibilitychange', pauseTicker);
      gallery.addEventListener('keydown', (event) => {
        if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
        if (event.key === 'ArrowLeft') { event.preventDefault(); show(active - 1); pauseTicker(); }
        if (event.key === 'ArrowRight') { event.preventDefault(); show(active + 1); pauseTicker(); }
      });
      // Touch swipe without intercepting vertical scrolling.
      let touchStart = null;
      gallery.addEventListener('touchstart', event => {
        if (event.touches.length === 1) touchStart = {x: event.touches[0].clientX, y: event.touches[0].clientY};
      }, {passive:true});
      gallery.addEventListener('touchend', event => {
        if (!touchStart || !event.changedTouches.length) return;
        const dx = event.changedTouches[0].clientX - touchStart.x;
        const dy = event.changedTouches[0].clientY - touchStart.y;
        touchStart = null;
        if (Math.abs(dx) > 55 && Math.abs(dx) > Math.abs(dy) * 1.5) { show(active + (dx < 0 ? 1 : -1)); pauseTicker(); }
      }, {passive:true});
      gallery.dataset.galleryInitialized = '1';
      gallery.classList.add('gallery--enhanced');
      controls.hidden = slides.length <= 1;
      show(0);
      pauseTicker();
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, {once:true});
  else boot();
})();
