# Boilerplate V2.3.7 — Security, Login & Forms Rate Limiting

## Summary
- Replaced the session-based login attempt guard with shared MySQL counters: 40 attempts/IP per 15 minutes and 12 attempts/account per 15 minutes. All attempts with a valid CSRF token count, including valid credentials. Counters persist across browser sessions and PHP processes.
- Replaced Forms session-based rate limiting with a shared per-IP/per-form counter using Forms Settings (default six submissions/10 minutes) plus a site-wide per-IP ceiling (at least 30 requests per period). Counters are consumed only after the anti-replay token and field validation, before storing data or sending email.
- Both entry points **fail closed** when the rate limiter database is unavailable. No exception detail is displayed to site visitors; details go to server logs.
- Hardened login/Forms request parsing so malformed array values for CSRF token, e-mail, password and form token are rejected safely instead of triggering PHP type errors or warnings.
- Fixed the login password visibility toggle, which previously called `window.cmsTranslate()` even on the stand-alone login page where that function is not registered. Accessible show/hide labels are now supplied by the PHP translation strings.
- Localized login document title and retry-after feedback in all five admin languages. Added sensible autocomplete and explicit labels while preserving login page styling.
- Authentication retains password hashing, session regeneration, role checks, CSRF tokens, HttpOnly/SameSite cookie policy and generic invalid-credentials feedback.

## Installation (existing site)
1. Back up your database and files.
2. Import **`database/migrations/20261013_security_rate_limits_v2_3_7.sql`** into the existing database using phpMyAdmin **before replacing the PHP files**. Do not re-import `boilerplate.sql`.
3. Copy the V2.3.7 code while keeping your existing `.env`, database, and uploaded media.
4. Set `RATE_LIMIT_SECRET` to a strong random value (recommended: at least 32 random bytes encoded as hex) in the root `.env`. Use the same value on all web servers, and keep it stable. An installation-specific deterministic fallback derived from database credentials and site URL is used when it is omitted.
5. Restart PHP if OPcache is enabled. Perform a hard reload.

For a new installation, the `boilerplate.sql` schema also includes the new throttle table.

## Important operational notes
- Counter keys are keyed SHA-256 hashes, not plaintext IPs/emails. IPs come from `REMOTE_ADDR`; forwarded headers are never trusted implicitly. A reverse proxy may require a separately reviewed trusted-proxy configuration, otherwise users behind the proxy share its source IP.
- Fixed-window throttling is a starting point, not bot protection or a DDoS mitigation service. Shared IPs/NAT users may share a quota. Tune limits after production monitoring.
- Requests during database outages are denied for safety, including Forms submissions. Import the migration before deploying PHP.
- Counters are opportunistically pruned, using an indexed expiration field. Do not alter or reset active production counters during an incident.
- On login, the input stays enabled even while limited so that an authorized user can retry once the window expires. Password-manager autofill remains enabled.
- The current login page still uses the global core CSS. No separate login theme or module CSS was introduced.
- No new JavaScript/CSS bundles and no changes to public frontend styles or CMS routes.

## Manual acceptance checklist
1. Verify normal login, logout, invalid password feedback, Show/Hide Password button, and Remember Me.
2. Verify 12+ attempts for the same email with fresh sessions result in a rate limit; switch browsers to confirm shared throttling. Verify other email accounts aren't blocked unless the 40/IP threshold is also reached.
3. Confirm a valid login still works after the window expires.
4. Submit a form up to its configured limit; check the next submission is blocked even with a fresh session from the same IP, and that notifications are not sent for blocked submissions.
5. Verify Forms validation, anti-replay protection, honeypot and notifications still work.
6. Test all five admin languages on login and check compact desktop/mobile layout in your environment.
7. Inspect server logs rather than trying to log in if the migration failed; table missing should result in an unavailable message rather than bypassing security.

## Limitations of QA
Automated source checks and isolated rate-limit tests are run before packaging; production MySQL, reverse proxies, real email delivery, and browser end-to-end behavior require local/infrastructure validation. This release is **not** a comprehensive penetration test.
