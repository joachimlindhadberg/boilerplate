# Fresh installation — Boilerplate CMS 2.5.0 candidate

**Use only on a NEW, isolated site. Do not point the wizard at any existing database or active website.** Public Stable release has not been approved.

## 1. Prepare a compatible webhost

Read [hosting requirements](HOSTING-REQUIREMENTS.md). Create a separate, empty MySQL database and a dedicated DB user with the privileges required for schema creation and triggers. Arrange **HTTPS** and restrict public access to the new, uninstalled website during setup so an unknown visitor cannot become the first administrator.

Use the hosting file manager or SFTP. The directory selected as the site's document root must be **exactly** `public_html/`, with `core/`, `database/`, `modules/` and private storage located alongside it **outside the webroot**. Preserve hidden `.htaccess` security files. Do not reuse an existing site's `.env`, database, uploads or session storage.

```
private-site-root/
  core/
  database/
  modules/
  public_html/        <-- set the domain's document root HERE
    .htaccess
    install.php
    uploads/
      .htaccess
  storage/            <-- private; may be created by application
  .env                <-- private; created by the installer
```

The package contains **one** active upload directory, `public_html/uploads/`. Older installations may separately retain `assets/uploads/` for legacy media; do not delete it from an existing site.

## 2. Run the browser wizard

Open `https://your-domain/install.php` only after setting up the isolated host. There is **no Installation Key or Activation Key**.

1. **Database:** enter the new database host/name/user/password; run **Test connection**. Hosting checks run silently on success; failures provide an explanation and optional **Technical details**.
2. **Website & Administrator:** enter website information and create the initial administrator (password minimum **12 characters** in this candidate).
3. **Complete:** sign in through `/admin` and verify the frontend, admin permissions, media upload and the correct URL.

The wizard needs to write the private `.env` after installing the schema. If the application cannot safely create it, do not try to force progress by placing `.env` under `public_html/`.

## 3. Verify and secure

- Remove the host-level installation access restriction *only after* successful administrator creation; maintain HTTPS.
- Verify **effective** `.htaccess` behavior on the real host: directory listing denied on uploads, script execution blocked in uploads, private Core files inaccessible, normal image requests available.
- Verify login → logout → login, roles, Gallery/Media and contact forms where enabled; test backup generation and restoring into another **isolated** environment before relying on it.
- Restrict write permissions that were needed only for `.env` creation. Keep upload and private storage directories writable where the application requires them.
- Do not publish credentials or upload PHP information pages to a public server to diagnose problems.

## When installation fails

- **Nonempty DB / partial install:** stop; MySQL DDL is not atomic. Preserve any partially created data for diagnosis. Retry only against a fresh **empty** test database; do not blindly delete tables.
- **Existing `.env`:** the wizard is intentionally blocked. This is not an upgrade route.
- **HTTPS / proxy / `.htaccess` / permission failure:** correct the hosting configuration instead of bypassing safety checks.
- **No trigger privileges:** ask the host to grant required privileges or move to a supported host; no workaround should omit data-integrity triggers.

See [UPDATING.md](UPDATING.md) for existing installations and [FIRST-TIME-INSTALL-RC2.md](FIRST-TIME-INSTALL-RC2.md) for detailed technical behavior and loopback-only testing policy.
