# 4A.6.9g — One active uploads directory

**Release status: QA1 test candidate, not approved for public production.** No SQL migration, file moves or deletion of user data is performed.

## New installations

- The only upload directory in the new development/runtime ZIP is `public_html/uploads/`, protected by its versioned `.htaccess` policy. Media writes already use `/uploads/YYYY/MM/`.
- The installer requires only this directory to be writable and this `.htaccess` (plus the public/admin policies) to be present.
- No `public_html/assets/uploads/` directory or policy is created on new installations.
- A successful site-data backup uses the current upload directory and includes its SHA256 file hashes; the manifest does not falsely advertise the absent legacy root.

## Existing installations / historical site-data archives

- **Do not delete or rename** an existing `public_html/assets/uploads/` directory. New ZIPs do not delete anything at that path, and updating the code will not automatically relocate or rewrite any media.
- MediaLibrary, Page Builder, MediaReferenceIndex, and SiteBackup continue to resolve, protect, and back up old media paths (`/assets/uploads/...`), even when DB entries or old HTML still refer to them.
- Existing sites should keep the original `assets/uploads/.htaccess` alongside their older media files. It is an application security rule, not ordinary user media, and is intentionally *not* in the site-data backup. For restoring a historical archive on a new installation: create `assets/uploads/` only when such media is present, place a **copy of the shipped `public_html/uploads/.htaccess`** there before exposing the site, then restore its media. Verify Apache enforcement on the target host. Never restore without the policy or expose the folder publicly without equivalent server-side protection.
- An existing legacy directory (if present during fresh setup) makes installer preflight require its security policy; it does **not** need write permission because it is read-only legacy storage.
- Site Health shows legacy storage only when present; absent legacy storage is normal, not a warning.
- SiteBackup still archives old files under their original paths and the backup verifier still accepts historical archives. This is intentionally **not** a physical migration of existing sites into a single directory; that requires a separate explicit, collision-safe, backup-first operation to avoid broken URLs and lost data. The one-directory invariant holds for **new installations and new uploads**.

## Local checks

1. Extract the development candidate in an **isolated new folder** and check that only `public_html/uploads/.htaccess` exists, not `public_html/assets/uploads/`.
2. Deploy on an isolated loopback virtual host with a clean, empty database. Verify preflight does not require a legacy directory; finish installation and upload an image. Check its URL is `/uploads/YYYY/MM/...`.
3. Restore a disposable *copy* of a legacy image under `assets/uploads/` with a copy of `.htaccess` and verify reading/backup/restore plus the original old URL. Keep the original approved CMS installation, data and backup unchanged.
4. Check `/uploads/` directory index denial, script execution denial, and that HTTPS/proxy requirements remain met.
5. Do not merge onto an existing public site or remove old uploads until a separate, verified migration is available.

**Not verified in source-only QA:** database-backed end-to-end upload/restore, Apache enforcement on shared hosting, MySQL 8.4/MariaDB runtime, public production deployment.
