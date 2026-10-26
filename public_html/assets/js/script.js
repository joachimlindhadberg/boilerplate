/**
 * Global frontend behaviour only.
 * Modules may supply their own functional JavaScript when required.
 * Do not put module-specific styling or business logic here.
 */

document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('.site-header');
  const toggle = document.querySelector('[data-navigation-toggle]');
  const navigation = document.querySelector('[data-navigation]');

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
