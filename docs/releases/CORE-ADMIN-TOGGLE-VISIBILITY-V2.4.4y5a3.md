# Boilerplate CMS V2.4.4y5a3 — Core Admin Toggle & Page Builder Visibility

**Base:** V2.4.4y5a2 (`2.4.4.45`) — not yet locally approved.  
**Candidate:** V2.4.4y5a3 (`2.4.4.46`).

## Scope

- New reusable Core component `AdminToggle::render()` exposed via `admin_toggle_switch()`. Uses a native checkbox with `role="switch"`, associated label, safe attribute handling, keyboard accessibility and visible focus.
- Core-only `.admin-toggle` design: accent is `var(--admin-accent)`, neutral inactive state, compact 44×26 px switch track, reduced-motion support. SCSS and compiled CSS updated together. No frontend CSS.
- Page Builder Section Settings: **Synlighed** is the first control, above row layouts, width, gap and color theme. Active and hidden descriptions use the existing translation system (da/en/de/sv/nb).
- Page Builder remains source-of-truth: the existing `blocks[i][status]` hidden input keeps `active`/`hidden`. The switch only changes the modal's draft state; **Save settings** commits, **Cancel/Escape** do not. Hidden badges and existing frontend visibility rules remain intact.
- No SQL or changes to Forms, Gallery, Page Builder row serialization, frontend theme or publishing state.

## QA

- `php tests/core-toggle-visibility-v2_4_4y5a3.php`
- All PHP regression tests in `tests/*.php` (including historical status and modal behavior checks).
- `php core/cli/code-audit.php --release`
- `php core/cli/qa.php` (may show only known CLI `mysqli`/`mbstring` warnings).
- Syntax check PHP files and `node --check public_html/admin/assets/js/admin.js`.
- Chromium tests: 375, 390, 768 and 1280 px; keyboard toggle, active/hidden, modal Cancel/Escape, applying settings, Core accent, no overflow or JS errors.
- ZIP completeness, 0644/0755, updated ZIP timestamps, no `.env`, uploads, credentials or DB export. Re-extract archive before final QA.

## Manual acceptance

1. Open a **hidden** section's Settings; toggle must be OFF and labeled *Skjult*.
2. Toggle ON and click **Annuller** (or press Escape); original section must remain hidden after reopening.
3. Toggle ON, click **Gem indstillinger**, then save the page; section must become active after reopening.
4. Reverse to hidden and ensure frontend visibility follows the existing `active`/`hidden` semantics.
5. Confirm 1–4-column Rows, width/theme settings, drag/drop, editor, Forms and Media still work.

Only proceed to Page Templates after local approval.
