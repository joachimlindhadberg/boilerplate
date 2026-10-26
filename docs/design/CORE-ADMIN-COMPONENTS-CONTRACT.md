# Core Admin Components — Design Contract (V2.4.4f2)

**Gældende fra V2.4.4z7:** `CORE-UI-STANDARD-V1.0.md` er overordnet for delt admin-UI; denne fil supplerer kun, hvor den ikke er i modstrid. Historiske releaseeksempler har ikke højere prioritet.

Applies to Core and all installed/future modules. **Core alone owns shared admin appearance and interaction states** in `public_html/admin/assets/scss/admin.scss`, served byte-for-byte as `public_html/admin/assets/css/admin.css` in this build. Modules must use the shared classes; module CSS may only provide structural layout where needed. This contract supplements `ADMIN-MODULE-STYLE-CONTRACT.md`, `CMS-UI-UX-DESIGN-CONTRACT.md`, and `ADMIN-LISTS-TABLES-CONTRACT.md`.

## Shared tokens and defaults

| Token | Default | Usage |
| --- | --- | --- |
| `--admin-radius` | `3px` | Shared controls, panels, labels and modals |
| `--admin-control-height` | `43px` | Standard field input and select |
| `--admin-control-height-compact` | `36px` | Compact select, search and compact action button |
| `--admin-button-height` | `40px` | Standard button minimum height |
| `--admin-control-padding-x` | `11px` | Standard field horizontal padding and select text start |
| `--admin-control-hover-border` | `#aab8b6` | Hover border for standard controls |
| `--admin-control-focus-border` | `#829fa0` | Muted 1px pointer-focus border for text fields and selects |
| `--admin-control-focus-keyboard-border` | `#66898b` | Slightly more visible 1px keyboard-focus border (no inset or halo) |
| `--admin-control-focus-shadow` | `none` | No external or internal extra focus ring |

**Preserve:** Standard select background chevron `right var(--admin-select-chevron-offset, 11px) center`. Compact selects intentionally retain their local `--admin-select-chevron-offset: 13px`, padding and `36px` height. Do not change their geometry when touching standard selects.

## Official shared classes

- **Actions:** `admin-button`, `admin-button--compact`, `admin-button--secondary`, `admin-button--danger`, `admin-button--danger-outline`, `admin-text-action`, `admin-form-actions`. Only one highlighted primary action per context. Disabled buttons keep their variant's resting colors even on hover.
- **Forms:** `admin-field`, `admin-field__label`, `admin-field__help`, `admin-field-error`, `admin-form-row`, `admin-form-section`, `admin-checkbox`, `admin-list-search`, `admin-select--compact`. Disabled text fields, textareas and selects use the shared muted treatment; checkbox/radio mechanics remain untouched.
- **List views:** `admin-panel`, `admin-panel__header`, `admin-list-toolbar`, `admin-filter-tabs`, `admin-list-summary`, `admin-table-wrap`, `admin-list-table`, `admin-table-actions`, `admin-pagination`, `admin-empty-state`.
- **Status/feedback:** `admin-status-label` and its existing modifiers, `admin-notice`, and `admin-notice__close`.
- **Modal:** `admin-modal` with width modifiers `--sm`, `--md`, `--lg`, `--xl`, and `admin-modal__surface`, `__header`, `__body`, `__footer`. Continue using Core's shared modal JS workflow for destructive actions; do not introduce native `confirm` dialogs.

## Interaction & responsive requirements

1. Focused text fields and selects have a single 1px, muted teal border (#829fa0); keyboard-focused fields get a slightly stronger #66898b 1px border with no inset or outer halo. Forced-colors/high-contrast keyboard focus continues to use a clear 2px outline. Buttons, links and non-text inputs keep their native/Core keyboard outlines. Preserve labels and `:focus-visible` behavior when adding fields.
2. Use semantic native `disabled` on buttons and fields. Disabled button variants must not unexpectedly turn into a primary (teal) button on hover.
3. Standard status labels use the global 3px radius, not an independent hard-coded value. Other status colors remain Core-owned.
4. Modal max-height uses `dvh` with `vh` fallback. Long translated headings may wrap; footers may wrap actions rather than overflow. At widths <=480px, header/body/footer padding is compact.
5. Tables keep left-aligned Actions columns and horizontal scrolling within `.admin-table-wrap`; page contents must not gain horizontal overflow.
6. Do not redefine global control colors/radii inside Forms, Gallery, Example Module, or any future module. Promote common variants to Core first.
7. CSS and SCSS must be byte-identical in this project. For any change to CSS, patch both and run `php core/cli/qa.php`.

## Acceptance test on the user's local CMS

- Open core forms, select fields, list-search controls, status labels and table actions; check 3px corners and unchanged control heights.
- Tab between inputs/selects to see a muted 1px focus border (without an inset or external halo); verify mouse clicks show an even softer 1px border.
- Check native disabled action buttons and disabled inputs/selects; ensure no hover recoloring or accidental click.
- Open a confirmation modal on narrow and desktop viewports, including long German/Swedish labels.
- Verify Forms/Gallery list and edit flows still use Core controls, without markup/JS changes.

No database changes, new modules, frontend styles or admin workflow changes were introduced in V2.4.4f.
