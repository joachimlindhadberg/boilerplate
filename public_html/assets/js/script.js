/**
 * Global frontend behaviour only.
 * Modules may supply their own functional JavaScript when required.
 * Do not put module-specific styling or business logic here.
 */

document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('.site-header');
  const toggle = document.querySelector('[data-navigation-toggle]');
  const navigation = document.querySelector('[data-navigation]');

  // BP-HERO-H1: optional decorative scroll depth, never moves real content.
  const heroLayers = [...document.querySelectorAll('.site-hero--parallax .site-hero__media')];
  if (heroLayers.length) {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    const mobileHero = window.matchMedia('(max-width: 767.98px)');
    let scheduled = false;
    const updateHero = () => {
      scheduled = false;
      const enabled = !reduce.matches && !mobileHero.matches;
      heroLayers.forEach((layer) => {
        if (!enabled) { layer.style.transform = ''; return; }
        const bounds = layer.parentElement.getBoundingClientRect();
        const center = bounds.top + bounds.height / 2 - window.innerHeight / 2;
        const offset = Math.max(-56, Math.min(56, -center * 0.12));
        layer.style.transform = `translate3d(0, ${offset.toFixed(1)}px, 0)`;
      });
    };
    const requestUpdate = () => {
      if (scheduled) return;
      scheduled = true;
      window.requestAnimationFrame(updateHero);
    };
    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate, { passive: true });
    [reduce, mobileHero].forEach((media) => {
      if (media.addEventListener) media.addEventListener('change', requestUpdate);
      else if (media.addListener) media.addListener(requestUpdate);
    });
    requestUpdate();
  }

  if (!header || !toggle || !navigation) return;

  const mobile = window.matchMedia('(max-width: 767.98px)');

  const closeNavigation = () => {
    header.classList.remove('is-menu-open');
    document.body.classList.remove('navigation-open');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Åbn navigation');
  };

  toggle.addEventListener('click', () => {
    const open = !header.classList.contains('is-menu-open');
    header.classList.toggle('is-menu-open', open);
    document.body.classList.toggle('navigation-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Luk navigation' : 'Åbn navigation');
  });

  navigation.addEventListener('click', (event) => {
    if (mobile.matches && event.target.closest('a[href]')) closeNavigation();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && header.classList.contains('is-menu-open')) {
      closeNavigation();
      toggle.focus({ preventScroll: true });
    }
  });

  const onViewportChange = (event) => {
    if (!event.matches) closeNavigation();
  };

  if (typeof mobile.addEventListener === 'function') {
    mobile.addEventListener('change', onViewportChange);
  } else if (typeof mobile.addListener === 'function') {
    mobile.addListener(onViewportChange);
  }
});
