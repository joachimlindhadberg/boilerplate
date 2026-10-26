#!/bin/sh
# Native macOS/Homebrew test runner. No Docker, no server configuration changes.
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
PHP_BIN=${PHP_BIN:-$(command -v php || true)}
[ -n "$PHP_BIN" ] && [ -x "$PHP_BIN" ] || { echo 'FAIL: PHP CLI not found.' >&2; exit 2; }
cd "$ROOT"
fail() { echo "FAIL: $*" >&2; exit 2; }
info() { echo "=== $* ==="; }
code_tests() {
    info 'Local PHP, Apache and MySQL tools'
    "$PHP_BIN" -v | head -n 2
    if command -v apachectl >/dev/null 2>&1; then apachectl -v | head -n 2; else echo 'WARN: apachectl not found'; fi
    if command -v mysql >/dev/null 2>&1; then mysql --version; else echo 'WARN: mysql client not found'; fi
    for ext in mysqli mbstring; do
        if "$PHP_BIN" -r "exit(extension_loaded('$ext') ? 0 : 1);"; then
            echo "PASS: PHP CLI extension $ext"
        else
            echo "WARN: PHP CLI extension $ext not loaded in this shell; required for full DB/server checks"
        fi
    done
    info 'Code Audit, read-only Core QA and module manifests'
    "$PHP_BIN" core/cli/code-audit.php
    "$PHP_BIN" core/cli/qa.php
    "$PHP_BIN" core/cli/module.php validate all
    info 'PHP regression tests'
    total=0
    for file in tests/*.php; do
        case "$file" in *generate-forms-svg-fixture*) continue ;; esac
        "$PHP_BIN" "$file" || exit 1
        total=$((total + 1))
    done
    echo "PASS: $total PHP regression test files"
}
normal_db() {
    # Only read-only commands. No test fixtures, --apply, or SQL imports.
    "$PHP_BIN" -r 'exit(extension_loaded("mysqli") ? 0 : 1);' || fail 'mysqli missing from PHP CLI; cannot check database'
    [ -f "$ROOT/.env" ] || { [ -n "${DB_NAME:-}" ] && [ -n "${DB_USER:-}" ]; } || fail 'No local .env or DB_* environment configuration; configure installed site first, or run verify-code.'
    [ "${APP_ENV:-}" != testing ] || fail 'verify-site must use normal local app configuration, not APP_ENV=testing'
    info 'Existing CMS DB: read-only status, no migrations or fixtures'
    "$PHP_BIN" core/cli/qa.php --database
    "$PHP_BIN" core/cli/migrate.php --status
}
fixture_db() {
    # Check dedicated database, marker and effective DB account *before* any writes.
    "$PHP_BIN" -r 'exit(extension_loaded("mysqli") ? 0 : 1);' || fail 'mysqli missing from PHP CLI; cannot check fixture database'
    [ "${APP_ENV:-}" = testing ] || fail 'fixture tests require APP_ENV=testing exported in this Terminal session'
    [ "${DB_NAME:-}" = boilerplate_integration_only ] || fail 'fixture tests require DB_NAME=boilerplate_integration_only'
    [ "${DB_USER:-}" = cms_test_only ] || fail 'fixture tests require DB_USER=cms_test_only, never root or the normal CMS user'
    [ -n "${DB_PASS:-}" ] || fail 'fixture tests require DB_PASS set locally; do not share credentials'
    case "${DB_HOST:-}" in localhost|127.0.0.1) ;; *) fail 'fixture DB_HOST must be localhost or 127.0.0.1';; esac
    info 'Explicit dedicated-database guard (no writes)'
    "$PHP_BIN" tests/integration/fixture-guard.php
    info 'Apply existing historical PHP migrations ONLY to fixture DB'
    "$PHP_BIN" core/cli/migrate.php --apply
    "$PHP_BIN" core/cli/migrate.php --status
    info 'Refresh synthetic fixture reference index ONLY'
    "$PHP_BIN" core/cli/media-index.php --rebuild
    "$PHP_BIN" core/cli/qa.php --database
    info 'Schema and rolled-back synthetic transaction'
    "$PHP_BIN" tests/integration/db-smoke.php
    info 'Cross-module rollback-only integration workflows (synthetic database ONLY)'
    "$PHP_BIN" tests/integration/workflows.php
    echo 'PASS: fixture database integration (real CMS database not accessed)'
}
case "${1:-}" in
    verify)
        code_tests
        normal_db
        info 'Existing Apache VirtualHost: admin-only safe GET checks; frontend GET requires explicit isolated-site opt-in'
        sh tests/integration/http-smoke.sh
        echo 'PASS: native local environment verification (no migrations or fixture writes)'
        ;;
    verify-code)
        code_tests
        echo 'PASS: source-level checks (site and database not checked)'
        ;;
    verify-site)
        normal_db
        sh tests/integration/http-smoke.sh
        echo 'PASS: local VirtualHost safe admin-only GET checks (public frontend optional and may write schedules)'
        ;;
    verify-security)
        info 'Security and data-integrity SOURCE audit (no database, HTTP, fixture or filesystem changes)'
        "$PHP_BIN" core/cli/security-audit.php
        echo 'PASS: safe security audit (source-only)'
        ;;
    verify-install)
        info 'Installation & Update SOURCE preflight (no database, HTTP, migrations or writes)'
        "$PHP_BIN" core/cli/install-update-audit.php
        echo 'PASS: installation/update source preflight (no DB / HTTP / writes)'
        ;;
    verify-backup)
        info 'Backup & Rollback SOURCE preflight (no database, HTTP, restore or writes)'
        "$PHP_BIN" core/cli/backup-rollback-audit.php
        echo 'PASS: backup/rollback source preflight (no DB / HTTP / writes)'
        ;;
    verify-production)
        info 'Final Production QA SOURCE gate (no database, HTTP, fixtures, restore or writes)'
        "$PHP_BIN" core/cli/security-audit.php
        "$PHP_BIN" core/cli/install-update-audit.php
        "$PHP_BIN" core/cli/backup-rollback-audit.php
        "$PHP_BIN" core/cli/final-production-audit.php
        echo 'PASS: final production source QA (no DB / HTTP / writes)'
        echo 'NOTE: source QA does NOT grant production go-live approval; staging and restore are unverified'
        ;;
    verify-fixture)
        fixture_db
        ;;
    *)
        echo 'Usage: sh tests/integration/run.sh {verify|verify-code|verify-site|verify-security|verify-install|verify-backup|verify-production|verify-fixture}' >&2
        exit 2
        ;;
esac
