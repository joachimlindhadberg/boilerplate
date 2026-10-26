# Admin Module Styling Contract

**Gældende fra V2.4.4z7:** `CORE-UI-STANDARD-V1.0.md` er overordnet for delt admin-UI; denne fil supplerer kun, hvor den ikke er i modstrid. Historiske releaseeksempler har ikke højere prioritet.

The CMS core owns the backend design system. Modules extend that system; they do not redefine it.

## Core owns shared UI

`public_html/admin/assets/scss/admin.scss` and compiled `admin.css` are the source of truth for shared admin UI:

- buttons and text actions
- inputs, textareas, selects, checkboxes and radios
- panels and panel headers
- modals
- tabs
- tables, filters and toolbars
- status badges
- empty states
- media picker controls
- Page Builder/admin builder blocks
- typography, spacing, borders, radii and interaction states

Modules must use those global classes and variants.

## Module CSS is structural only

Module CSS may exist only where the module needs a unique visual structure to function, for example a Forms row/column editor or a Gallery thumbnail arrangement. It may use the global admin design tokens, but must not restyle a shared control.

Good:

```css
.forms-row__columns--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.gallery-builder-item__preview { width: 48px; height: 36px; }
```

Not allowed:

```css
.forms-module .admin-button { ... }
.gallery-module select { ... }
.some-module .admin-modal__header { ... }
.some-module input { ... }
```

## Prefer global variants

If a module needs a compact select, simple builder card, destructive button, modal footer layout or another reusable pattern, add a generic variant to the global admin design system and use that class from the module. Do not duplicate the visual rules in module CSS.

## Specificity

Module selectors should primarily target module-owned classes. A selector combining module and core classes is allowed only for structural layout that the core cannot know about, never to change shared colors, typography, borders, radii or interaction states.

## Promotion rule

When the same pattern appears in two places, promote it to core/global styling. SCSS and compiled CSS must always be kept synchronized.

## Component ownership and cascade

Core components own their own complete visual contract. Do not add blanket catch-all selectors that repeat values already defined by the component itself, for example a global selector that reapplies the same radius to every button, input, select and panel.

Shared variants should be explicit and reusable. Module markup must choose the correct global component class (`admin-button`, `admin-builder-drag`, `admin-text-action`, etc.) instead of relying on browser defaults or module CSS to make an unclassified control look correct.

## Core variants before module overrides

If a module needs a recurring variation of a shared CMS component, add or reuse an official core modifier instead of targeting the shared component from module CSS. Examples: `admin-panel__header--split` and `admin-builder-block--media`. Module CSS must not contain selectors such as `.module-x .admin-button`, `.module-x .admin-panel`, `.module-x .admin-builder-block__header`, or equivalent cascade overrides.
