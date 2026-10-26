#!/bin/sh
# GET smoke against local macOS Apache. Defaults to ADMIN-ONLY endpoints:
# visiting frontend URLs can run ScheduledContent::runOnTraffic() and commit
# scheduled state changes. Never imply that an ordinary GET cannot write DB.
# Explicitly allow frontend GET only on an isolated/staging site where those
# expected scheduler effects are acceptable.
set -eu
# The existing macOS Apache VirtualHost is HTTP :80 (not HTTPS :443).
# Only the local development hostname and loopback endpoint are accepted.
base=${CMS_TEST_URL:-http://boilerplate.test}
case "$base" in
    http://boilerplate.test)  pinned_endpoint='boilerplate.test:80:127.0.0.1' ;;
    https://boilerplate.test) pinned_endpoint='boilerplate.test:443:127.0.0.1' ;;
    *) echo "FAIL: refusing an unknown origin: $base" >&2; exit 2 ;;
esac
command -v curl >/dev/null 2>&1 || { echo 'FAIL: curl is required' >&2; exit 2; }
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT HUP INT TERM
insecure=''
if [ "${CMS_TEST_TRUST_LOCAL_CERT:-0}" = 1 ]; then
    [ "$base" = 'https://boilerplate.test' ] || {
        echo 'FAIL: CMS_TEST_TRUST_LOCAL_CERT only applies to local HTTPS, not HTTP' >&2
        exit 2
    }
    insecure='--insecure'
    echo 'WARN: certificate validation disabled by explicit opt-in for boilerplate.test only'
fi
check() {
    route=$1 expected=$2 label=$3
    # Never follow redirects, send cookies or submit POST forms.
    # -q prevents ~/.curlrc from silently changing routing or TLS options.
    # --noproxy and --resolve keep traffic pinned to this Mac, regardless of DNS/proxy.
    code=$(curl -q $insecure --noproxy '*' --resolve "$pinned_endpoint" --silent --show-error --connect-timeout 5 --max-time 15 \
        --max-redirs 0 --request GET --output "$tmp/body" --dump-header "$tmp/headers" \
        --write-out '%{http_code}' "$base$route") || { echo "FAIL: connection/TLS for $label" >&2; exit 1; }
    if [ "$code" != "$expected" ]; then
        echo "FAIL: $label: HTTP $code (expected $expected)" >&2
        echo 'Response headers (first lines):' >&2
        sed -n '1,8p' "$tmp/headers" >&2
        exit 1
    fi
    echo "PASS: $label — HTTP $code"
}
# No public bootstrap in default HTTP smoke, because it may publish due content.
if [ "${CMS_TEST_PUBLIC_GET:-}" = 'ISOLATED_SITE_SCHEDULER_WRITES_OK' ]; then
    echo 'WARN: explicit isolated-site opt-in: public GET can execute due schedules and write to database'
    check '/' 200 'CMS homepage (may trigger scheduler)'
    grep -Eiq 'content-type:.*text/html' "$tmp/headers" || { echo 'FAIL: expected HTML homepage' >&2; exit 1; }
else
    echo 'SKIP: public homepage/404 GETs (normal frontend bootstrap can write scheduled content)'
fi
# Apache's DirectorySlash canonically redirects the real /admin directory to /admin/.
# Verify that the redirect stays on this exact local host; never auto-follow it.
check '/admin' 301 'Canonical admin directory redirect'
location=$(sed -n '/^[Ll]ocation: */{s/^[Ll]ocation: *//;p;}' "$tmp/headers" | tr -d '\015')
case "$location" in
    "$base/admin/"|/admin/) echo 'PASS: admin redirect stays on the local CMS origin' ;;
    *) echo "FAIL: unsafe or unexpected admin redirect: $location" >&2; exit 1 ;;
esac
# Request the canonical URL explicitly; no redirect-following or cookies are used.
check '/admin/' 200 'Anonymous admin login'
grep -Eiq '^x-content-type-options: *nosniff' "$tmp/headers" || { echo 'FAIL: missing nosniff HTTP header' >&2; exit 1; }
grep -Eiq 'type=["\x27]password|adgangskode|password' "$tmp/body" || { echo 'FAIL: login form missing' >&2; exit 1; }
check '/incl/init.php' 403 'Frontend include protected'
# A nested Tools route must resolve to the authorized module controller, not
# the generic admin 404/login fallback. Anonymous callers are redirected by
# module-bootstrap before Update Compatibility can inspect the database.
check '/admin/vaerktoejer/update' 302 'Anonymous update preflight route requires authentication'
location=$(sed -n '/^[Ll]ocation: */{s/^[Ll]ocation: *//;p;}' "$tmp/headers" | tr -d '\015')
case "$location" in
    /admin|/admin/|"$base/admin"|"$base/admin/") echo 'PASS: update preflight redirects to login' ;;
    *) echo "FAIL: update preflight redirect target: $location" >&2; exit 1 ;;
esac
check '/admin/incl/functions.php' 403 'Admin include protected'
check '/admin/assets/scss/admin.scss' 403 'SCSS source protected'
if [ "${CMS_TEST_PUBLIC_GET:-}" = 'ISOLATED_SITE_SCHEDULER_WRITES_OK' ]; then
    check '/definitely-not-a-real-z8-page' 404 'Unknown public page (may trigger scheduler)'
fi
echo "PASS: local Apache admin routing and security smoke ($base); public GET opt-in only"
