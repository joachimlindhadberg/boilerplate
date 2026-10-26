# Authorization & Request Contract — Boilerplate V2.4.1

## Scope / security boundaries

- Core owns authorization, authenticated admin routing, CSRF, request validation and output escaping. A plugin must **not** assume that an admin menu item grants access to its handler.
- A plugin's `adminRoutes()` **must specify** a known `capability`. Missing, empty or unknown rights fail closed; there is no `admin.access` fallback. Menu items without a known capability are hidden, but hiding a menu is **not** an authorization check.
- Admin routes declare supported `methods` (`GET`, `POST`); other methods are not accepted by the module dispatcher. POST to module-admin routes **always** requires the current session's `csrf_token`, verified centrally **before** the handler. Existing module-specific CSRF checks remain as defense in depth.
- Rights are checked per request against the signed-in user's current role. A CSRF token is **not** a permission check. A validated input value is **not** a permission check.
- PHP plugins are trusted server-side code, **not sandboxed**. Manifest validation cannot protect against malicious plugin PHP; only install code from trusted providers.

## Core capabilities and backwards compatibility

`CapabilityRegistry` retains the existing permissions:

| Role | Core capabilities |
|---|---|
| admin | `admin.access`, `content.manage`, `media.manage`, `modules.manage`, `users.manage`, `tools.manage`, `settings.manage` |
| editor | `admin.access`, `content.manage`, `media.manage` |
| user | none |

Forms, Gallery and Example continue to use their explicit existing capabilities. Existing roles/users and database data are unchanged. Unknown capability strings (including unknown strings requested by admin accounts) are denied.

## Declare module-specific permissions

New plugins declare their capabilities in `module.json`:

```json
{
  "name": "Announcements", "slug": "announcements", "version": "1.0.0",
  "entry": "AnnouncementsModule.php", "class": "AnnouncementsModule",
  "capabilities": {
    "announcements.manage": ["admin", "editor"],
    "announcements.delete": ["admin"]
  }
}
```

Each name must be namespaced as `<slug>.<action>` and its role list may contain only `admin` and/or `editor`. A module cannot grant core permissions or another module's permissions. Roles are explicit; admin does not automatically get every undeclared permission. Modules may also use an existing Core capability when appropriate. `ModuleManifestValidator` checks this static metadata without running module PHP and `CapabilityRegistry` reads only valid manifests. Manifest-based permission grants are request-scoped and **not** an end-user role editor.

```php
public function adminRoutes(): array {
    return [[
        'route' => 'announcements',
        'methods' => ['GET', 'POST'],
        'capability' => 'announcements.manage',
        'handler' => 'adminIndex',
    ]];
}
```

Treat destructive/sensitive actions (refunds, user-data exports, deletion) as separate capabilities and check them **inside the handler** if multiple actions share one route. Core checks the route-level capability; fine-grained *action-level* enforcement remains the module author's responsibility.

## Typed input validation for new controllers

`AdminRequest::validate($source, $schema)`, `AdminRequest::query($schema)` and `AdminRequest::form($schema)` accept explicit field schemas. Supported types: `string`, `int`, `id`, `bool`, `enum`, `email`, `url`, `date` (`YYYY-MM-DD`). `required`, `default`, `min`, `max`, `values`, `trim` are available where applicable. Only declared fields are returned; arrays/objects and invalid scalar values are rejected by `InvalidArgumentException`.

```php
try {
    $input = AdminRequest::form([
        'id' => ['type' => 'id', 'required' => true],
        'action' => ['type' => 'enum', 'required' => true, 'values' => ['save', 'delete']],
        'title' => ['type' => 'string', 'required' => true, 'max' => 191],
    ]);
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    // Render a generic, translated validation error; do not log sensitive input.
    return;
}

// Authorization for delete must still be checked separately here.
```

`max` for text is in **bytes**, so it corresponds to MySQL VARCHAR storage limits only when your schema accounts for UTF-8. Use stricter, field-specific constraints for business data.

## Output contexts

- `AdminEscaper::text($text)` — HTML text node; used by existing `admin_e()`.
- `AdminEscaper::attribute($text)` — quoted HTML attribute.
- `AdminEscaper::url($url)` — quoted `href`/`src` values; permits root-relative URLs and absolute `http` / `https` URLs, rejects dangerous schemes and returns `#` for invalid URLs.

**Do not** pass user-controlled HTML directly to a view. Rich HTML requires a separate allowed-tag sanitizer and/or a trusted-role policy. Do not apply HTML escaping to SQL: use prepared statements. Do not treat escaping as input validation.

## Release guarantees and limits

- No new SQL migration; no role table changes; no admin CSS/SCSS changes; no frontend changes.
- No immediate rework of Forms or Gallery CRUD. Their existing route capabilities and form validation are retained. For new code, use the shared request contract.
- No REST API or per-object ACL system yet. Those require explicit future product decisions and threat modelling.
- The developer's PHP/CLI can lint/test without MySQL, but real browser, MAMP sessions and MySQL must be checked by the user before marking the release stable.
