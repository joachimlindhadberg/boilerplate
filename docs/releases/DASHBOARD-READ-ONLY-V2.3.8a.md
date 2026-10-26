# V2.3.8a — Read-only dashboard lists

The visible patch release is **V2.3.8a**; the internal `BOILERPLATE_VERSION` is `2.3.8.1` to preserve PHP version-comparison ordering.

## Scope

- Removed the Actions column and Edit button from Dashboard → Scheduled Content.
- Changed scheduled page names to plain text rather than clickable row links.
- Kept the existing panel-header **View pages** link and the read-only Recent Activity table.
- Preserved the dashboard queries, schedule data, permissions, and styling.
- Clarified the core lists/tables design contract: the dashboard is an overview, never an inline CRUD workspace.

## Verification

- Verify Scheduled Content with at least one scheduled page at narrow and desktop widths.
- Confirm the panel has exactly three columns (Page / Action / Date and time), no Edit button, and that View pages still navigates to Pages.
- Confirm Recent Activity remains read-only and both dashboard lists stay inside their panels.

## Installation

Upgrade from V2.3.8; no SQL migration, frontend, JavaScript, or stylesheet changes. Preserve local `.env`, database, and uploaded files.
