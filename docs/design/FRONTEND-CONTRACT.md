# Boilerplate Frontend Contract

This contract is the fixed boundary between CMS/core/modules and the public frontend.

## 1. Ownership

- The frontend/theme owns **100% of CSS and presentation**.
- Core and modules output semantic markup, content and behaviour only.
- Modules must never output inline `<style>` or ship/load frontend CSS.
- Modules may provide frontend JavaScript only when behaviour genuinely requires it.
- Page Builder/admin/module implementation names must not leak into frontend classes.

## 2. Naming

Use generic BEM-like class names:

- `component`
- `component__element`
- `component--variant`
- `component__element--state`

Do not use implementation/project prefixes such as `nf-`, `cms-`, `builder-`, `module-`, brand names or admin class names on the public frontend.

## 3. Layout contract

```text
section
└── container
    └── grid
        └── grid__column
            └── block / semantic component
```

Layout primitives describe layout only. Components describe actual UI/content.

## 4. Markup rules

- Keep markup minimal; every wrapper/class must have a semantic, layout, state or styling purpose.
- Dynamic values such as image URLs belong in attributes (`src`, `href`), not inline CSS.
- Component states should be component-specific (for example `form__field--error`).
- Frontend markup must remain usable even if the theme styling is completely replaced.

## 5. SCSS structure

- `settings/`: design tokens
- `tools/`: Sass helpers only when native CSS is insufficient
- `base/`: reset, primitives, typography, forms, buttons and utilities
- `layout/`: section, container and grid
- `components/`: semantic frontend components
- `themes/`: frontend theme variants
- `style.scss`: imports only

Whenever styling changes, update the SCSS source and compiled `assets/css/style.css` together.

## 6. Media

Static theme assets live under `public_html/assets/`.

User-uploaded CMS media lives under `public_html/uploads/`, outside theme assets. New media URLs use `/uploads/...`. Legacy `/assets/uploads/...` paths from Boilerplate 1.5.x remain readable for backwards compatibility.

## 7. Module frontend contract

Modules may register semantic Page Builder components and optional functional JavaScript. They may not control frontend presentation. Examples:

- Forms: `.form`, `.form__row`, `.form__field`
- Gallery: `.gallery`, `.gallery__item`

Their appearance is defined only by the site's frontend theme.
