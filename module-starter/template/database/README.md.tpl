# Database scripts

Database changes must be supplied as standalone `.sql` files for phpMyAdmin, not PHP migrations.

- Include an explicit backup and import plan before changing data or schema.
- Use the selected CMS database; do not hard-code unexpected databases or modify unrelated tables.
- Use unique, dated SQL filenames and idempotent statements where safe.
- Confirm collation and character set compatibility with the installation.
- Test imports in a local copy before production.
- Never execute DDL from `boot()`, admin routes or frontend requests.

This generated module does not need database changes.
