# Forms 1.2.6

Forms is an optional Boilerplate module for reusable frontend forms.

## Included in 1.2

- Row/column form builder
  - 100% row = 1 column
  - 50 / 50 row = 2 columns
  - one field per column
  - 50 / 50 stacks automatically on mobile
- Field type is selected before a field is added
- Simple field cards without type dropdown or up/down arrows
- Drag-and-drop reordering of rows
- Existing Forms 1.1 field lists migrate automatically to individual 100% rows
- Field types: text, email, telephone, textarea, select, radio and checkbox
- Stable field keys so mail-field references survive label changes
- Required-field and server-side validation
- Page Builder integration through Dynamic Component
- Optional saved submissions with search and form/status filters
- Read/unread submission workflow and permanent deletion with confirmation modal
- Optional notification email using CMS Settings → Notifikationer
- Custom notification subject
- Configurable Reply-To using any email field in the form
- Optional confirmation email to the submitter
- Success action: show a message or redirect to a published CMS page
- Module settings for defaults and session rate limiting
- One-time frontend form token, honeypot and server-side validation
- Deactivation preserves module data
- Frontend renderer emits semantic `.form*` markup only; all visual styling is owned by the site frontend theme

## Deliberately not included yet

- File uploads
- Conditional fields
- CAPTCHA providers
- SMTP/provider configuration
- Webhooks / CRM / newsletter integrations
- CSV export or bulk submission actions

The module requires Boilerplate 1.6.3 or newer. The module supplies functionality and semantic markup only; frontend presentation is defined in the site theme.

## 1.2.6 – fælles CMS UI/UX-kontrakt

- Bekræftelse ved fjernelse af elementer via CMS Core-modal.
- Ingen ændringer af frontend-rendering eller database.
- Globale knapper, formularfelter og øvrige UI-komponenter ejes af CMS Core.
