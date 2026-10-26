# Persistent security rate limiting (V2.3.7)

- Rate limiting of security-critical entry points must be persisted centrally (MySQL) and must not rely solely on the PHP session, an untrusted cookie, or JavaScript.
- All rate-limit keys must be pseudonymized via keyed hashing and scoped to the particular operation.
- Use atomic database mutations for request counting. Deny requests when rate-limit storage is unavailable.
- Do not infer client IP from arbitrary X-Forwarded-For headers. Configure explicit trusted proxies before introducing proxy awareness.
- Keep forms, buttons, error messages and responsive behaviors consistent with the global admin styling and localization system.
- Do not record submitted form contents or passwords in rate-limit storage or logs.
- Rotate or prune counters safely and document migrations before rollout.
