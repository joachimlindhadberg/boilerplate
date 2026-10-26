# Media References & Data Integrity Contract (V2.4.4)

## Ownership

- **Core** owns `MediaLibrary`, the Page Builder reference index, deletion checks, migration and CLI diagnostic tools.
- **Module authors** register SQL reference columns in `module.json` (`media_references`) and enforce `FOREIGN KEY (...) REFERENCES media(id) ON DELETE RESTRICT` wherever the relationship allows. References are checked even if the module is inactive. Unknown or failing checks must **never** authorize a deletion.
- **Frontend Theme** continues to own all public styling. Modules output semantic markup; no frontend CSS is introduced by this release.

## Page Builder index

- `media_reference_index(source_id, media_id)` stores unique Page Builder page/media pairs, including hidden and nested blocks. `media_id` is protected by an InnoDB restrictive foreign key.
- `media_reference_state(id=1, dirty, updated_at, rebuilt_at)` records whether the index needs rebuilding. It starts **dirty** after migration so an empty snapshot cannot be mistaken for a complete one.
- Database triggers mark it dirty **before** writes to `page_blocks`, relevant writes to `pages`, and media uploads/path changes. This catches imports, restores, direct SQL and editor changes, including non-PHP writes.
- The trigger guarding direct media DELETE refuses removal while the index is dirty. Missing required triggers also cause Core's deletion code to fail closed.
- A media deletion opens a transaction, obtains an exclusive state-row lock, rebuilds the index *only if dirty*, performs indexed lookup, checks legacy HTML and all module declarations, then deletes the DB record or rolls back. The state lock is held until commit. If source JSON is invalid or index repair fails, deletion is refused.
- Ordinary page views and media listing do **not** rebuild the index. It can be explicitly rebuilt in the CLI, or lazily on the first subsequent safe deletion.
- The index is a **materialized reference snapshot**, not a permanent per-request cache. It does not depend on PHP process memory or HTTP sessions.

## Intentional limits

- Arbitrary legacy HTML remains checked by an exact-path SQL LIKE lookup at deletion time, including `/uploads/` and `/assets/uploads/` aliases. Unstructured HTML cannot be indexed reliably without migrating it to structured content.
- Module references continue to use parameterized SQL COUNT queries rather than being duplicated into the Page Builder index. A plugin lacking a real foreign key can still have a concurrency race on concurrent writes; all plugins handling media ownership must add restrictive foreign keys.
- References are extracted from `image_id` and `image_path` keys in Page Builder JSON. A new plugin/component using different field names must declare its own reference ownership rather than assuming the Page Builder index detects it.
- If database schemas/triggers are edited manually, the index must be reverified before media deletion. `--audit` can detect missing IDs/files and malformed JSON, but is not a replacement for monitoring or restoring full backups.
- This release does not introduce automatic orphan cleanup, change the media upload path, or delete user files during reindexing.

## Operations

```
php core/cli/migrate.php --status
php core/cli/migrate.php --apply
php core/cli/media-index.php --rebuild
php core/cli/media-index.php --status
php core/cli/media-index.php --audit
php core/cli/qa.php --database
```

The commands must use the PHP executable with `mysqli` enabled. `--status` and `--audit` are read-only; `--rebuild` writes only to the derived index. Treat the audit's missing IDs and files as integrity warnings, and **do not** delete source records to silence them without investigating. `--audit` can flag legitimate external image paths as unresolved if they are not managed media records.

## Backups and restore

Full site backups include all database tables **and all database triggers**, plus registered upload folders. Trigger definitions are written after data inserts in `database.sql` using SQL-client `DELIMITER` directives. Restoring a backup that strips triggers invalidates safety guarantees and is rejected by the guarded media deletion workflow. Test restoring the backup to an isolated, empty database and verify the trigger count and media-index status. Do not restore over the active installation.

## Testing requirements

- A used medium cannot be deleted; an unused medium can.
- Hidden and deeply nested Page Builder references block deletion.
- Changes to Page Builder and media uploads dirty the snapshot. A guarded deletion refreshes it before proceeding.
- An inactive module with stored media ownership still blocks deletion.
- Missing migration/triggers, malformed block JSON and unexpected SQL errors always fail closed.
- Data integrity audit reports nonexistent media IDs, missing files and invalid blocks without modifying records.
- ZIP releases exclude test fixtures, runtime upload data, credentials and temporary databases.
