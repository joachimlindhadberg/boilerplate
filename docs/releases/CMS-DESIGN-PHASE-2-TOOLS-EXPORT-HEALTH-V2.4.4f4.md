# V2.4.4f4 — Full-Width Backup Warning & Site Health Export Placement

Internal version: `2.4.4.11`. Built directly on V2.4.4f3 (`2.4.4.10`).

## Scope

- Tools → Export: within **Site data backup**, the title/description and site-backup button occupy their own row. The sensitive-backup warning is a separate row, spanning the entire width of this export option (not a third column and not a stand-alone export item).
- Tools → Export: **Detailed Site Health Report** is a third export option after partial JSON content export. The report download form retains `POST`, CSRF token, `health_report` action, and the existing read-only report generation behavior.
- Tools → Site Health: continues to show the health checks and the error/warning summary, but no longer offers the report-download form or its privacy help text. Report-generation errors now appear under Export.
- Core CSS/SCSS updated together; existing core visual components, 3px radius and select chevron at 11px unchanged.
- Localized export-option description added in Danish, English, German, Swedish and Norwegian.

## No change

- No database updates, module changes, public-theme changes, new handlers or report contents.
- Export/backup/health actions remain protected by `tools.manage` and CSRF as before.

## QA and suggested local checks

1. In Tools → Export, confirm sensitive backup warning fills the full width beneath site backup description and button, both on mobile and desktop.
2. Confirm Site Health displays only status/checks and Export contains the report option.
3. Download TXT report from Export and verify correct localization and safe metadata output.
4. Test backup TAR.GZ and partial JSON export buttons independently.
5. Confirm expected PHP/JS translation checks, ZIP integrity, and 0644 file / 0755 directory archive permissions.
