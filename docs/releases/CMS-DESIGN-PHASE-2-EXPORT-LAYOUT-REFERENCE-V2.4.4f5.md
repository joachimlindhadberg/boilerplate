# V2.4.4f5 — Tools Export Layout: Screenshot-Matched Revision

Internal version `2.4.4.12`. Base: V2.4.4f4.

## User-approved target layout (pending test)

- **Export panel**: site backup with title/description left, download button right in a single top row.
- **Sensitive backup warning**: separate child below the top row, spanning 100% of backup option's content width; it is not a separate export item or restricted to the description column.
- **Partial JSON export**: next separate row.
- **Site Health Report**: final export row with concise single-line description and the download button aligned to right. There is no extra explanatory paragraph in this row. The TXT report contents and security controls are unchanged.
- Restore note stays at the panel bottom.

## Scope and safeguards

Core admin view, SCSS, compiled CSS, locale JSON and version only. New `health_report.export_title` is scoped to this row so the TXT document header stays unchanged. The report download remains protected by POST/CSRF and `tools.manage`. No database changes, frontend changes, module changes or report generator changes.

Browser fixture should be tested at 390, 620, 760, 1024, 1440 and 1920px. Test backup TAR.GZ, partial JSON and Site Health TXT on the local installation. PHP lint, translation QA and file permissions are release gates.
