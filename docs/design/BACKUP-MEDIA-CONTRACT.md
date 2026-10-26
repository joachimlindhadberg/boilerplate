# CMS Backup & Media Safety Contract

1. A feature labelled **complete backup** must declare its precise scope and limitations. A partial JSON export must never masquerade as a full site backup.
2. Admin-only backups use CSRF-protected POST endpoints, restrictive no-cache headers, secure temporary files and automatic cleanup. They are treated as highly sensitive personal data.
3. Backups must enumerate module data dynamically rather than maintain a list of only core tables. If expected referenced media cannot be read, fail visibly instead of silently claiming completeness.
4. Never include `.env`, runtime server credentials or code bundles in a downloadable data backup. Database-stored secrets may still be present; warn the administrator.
5. Manual restore into a clean environment is mandatory until a verified recovery engine exists. Never restore destructively from an untrusted uploaded backup.
6. Media references are checked before deletion. Modules must enforce referential integrity with database foreign keys wherever practical, particularly for persistence across module deactivation.
7. Errors other than a genuinely absent optional module table must cause media deletion to fail safely, not bypass protections.
8. For future modules, introduce a registered Media Usage Provider interface in a dedicated integration release. Do not bypass referential checks by manually deleting media rows.
9. Keep independent server/offsite backup routines and regularly test restoring them. A browser download is not a substitute for recovery planning.
