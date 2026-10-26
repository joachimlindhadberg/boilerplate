#!/usr/bin/env python3
"""Production *candidate* ZIP builder and verifier (source/ZIP only).

Development-only tools, fixtures, tests and historical release/audit notes are
omitted; operations/upgrade tools, runtime code, schema history and deployment
instructions are retained.  Building an archive does NOT authorize go-live.

Usage:
    python3 tests/release/production_zip.py build /safe/output.zip
    python3 tests/release/production_zip.py verify /safe/output.zip

Both modes read versioned source only; no database, HTTP, CMS bootstrap,
archive extraction or installation.  `build` writes only the requested ZIP.
"""
from __future__ import annotations

from pathlib import Path
import os
import re
import stat
import sys
import zipfile

import release_zip
import install_update_preflight as preflight

ROOT = release_zip.ROOT
TOP = release_zip.TOP
# These CLI tools are exclusively for development/audits; operational CLI
# migration/status, initial admin bootstrap, media and environment QA remain.
DEV_CLI = {
    'code-audit.php', 'security-audit.php', 'install-update-audit.php',
    'backup-rollback-audit.php', 'final-production-audit.php',
    'translations.php', 'module.php',
}
OMIT_PREFIXES = ('tests/', 'module-starter/', 'docs/releases/', 'docs/audits/')
KEEP_CLI = {'migrate.php', 'create-admin.php', 'qa.php', 'media-index.php'}
REQUIRED = {
    'public_html/index.php', 'public_html/admin/index.php',
    'public_html/incl/init.php', 'public_html/.htaccess',
    'public_html/uploads/.htaccess',
    'public_html/admin/.htaccess', 'core/classes/SiteBackup.php',
    'core/classes/MigrationRunner.php', 'core/cli/migrate.php',
    'core/cli/create-admin.php', 'public_html/install.php',
    'database/install/empty-schema.sql', 'core/classes/FirstInstall.php', 'core/cli/qa.php',
    'core/cron/scheduled-content.php', 'database/updates/20261013_000_core_schema.php',
    'modules/forms/module.json', 'modules/gallery/module.json',
    'docs/deployment/V2.5.0-GO-LIVE-GATE.md',
    'README.md',
    'docs/deployment/INSTALLATION.md',
    'docs/deployment/HOSTING-REQUIREMENTS.md',
    'docs/deployment/UPDATING.md',
    'docs/deployment/UPDATE-COMPATIBILITY-PREFLIGHT.md',
    'docs/deployment/SAFE-UPDATE-WORKFLOW.md',
}
EMPTY_RUNTIME = {p + '/' for p in release_zip.EMPTY_RUNTIME_DIRS}
VERSION_RX = re.compile(rb"define\('BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'\)")
MAX_FILES = 20_000
MAX_TOTAL_UNCOMPRESSED = 256 * 1024 * 1024


def omitted(rel: str) -> bool:
    rel = rel.rstrip('/')
    if not rel:
        return False
    if any(rel == prefix[:-1] or rel.startswith(prefix) for prefix in OMIT_PREFIXES):
        return True
    if rel.startswith('core/cli/') and rel.split('/')[-1] in DEV_CLI:
        return True
    return False


def source_entries():
    entries = []
    for name, path, is_dir in release_zip.paths():
        rel = name[len(TOP):]
        if omitted(rel):
            continue
        preflight.check_path(name, is_dir)
        if preflight.prohibited(rel, is_dir):
            raise ValueError(f'Unsafe release source member: {rel}')
        entries.append((name, path, is_dir))
    assert_consistency({name[len(TOP):] for name, _, is_dir in entries if not is_dir},
                       {name[len(TOP):] for name, _, is_dir in entries if is_dir})
    return entries


def assert_consistency(files: set[str], dirs: set[str]) -> None:
    missing = REQUIRED - files
    if missing:
        raise ValueError(f'Required runtime files missing: {sorted(missing)}')
    if not EMPTY_RUNTIME <= dirs:
        raise ValueError('Current upload root missing')
    if any(rel == 'public_html/assets/uploads/' or rel.startswith('public_html/assets/uploads/')
           for rel in files | dirs):
        raise ValueError('New production ZIP must not ship legacy upload storage')
    if any(omitted(rel) for rel in files | dirs):
        raise ValueError('Development-only content leaked into production ZIP')
    if any(rel.startswith('core/cli/') and rel.split('/')[-1] not in KEEP_CLI for rel in files):
        raise ValueError('Unexpected development-only CLI tool in production ZIP')
    if not any(rel.startswith('database/migrations/') and rel.endswith('.sql') for rel in files):
        raise ValueError('Historical manual migrations missing')
    if not any(rel.startswith('modules/forms/migrations/') and rel.endswith('.php') for rel in files):
        raise ValueError('Forms migration missing')
    if not any(rel.startswith('modules/gallery/migrations/') and rel.endswith('.php') for rel in files):
        raise ValueError('Gallery migration missing')


def build(destination: Path) -> None:
    destination = destination.resolve()
    if destination.is_relative_to(ROOT):
        raise ValueError('ZIP destination cannot be inside CMS source')
    entries = source_entries()
    destination.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(destination, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=8) as archive:
        for name, path, is_dir in entries:
            mode = release_zip.DIR_MODE if is_dir else release_zip.FILE_MODE
            info = zipfile.ZipInfo(name)
            info.create_system = 3
            info.external_attr = (mode << 16) | (0x10 if is_dir else 0)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.flag_bits |= 0x800
            archive.writestr(info, b'' if is_dir else path.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=8)
    verify(destination)


def verify(destination: Path) -> None:
    source = {name: (path, is_dir) for name, path, is_dir in source_entries()}
    with zipfile.ZipFile(destination, 'r') as archive:
        infos = archive.infolist()
        if len(infos) > MAX_FILES or len(infos) != len(set(info.filename for info in infos)):
            raise ValueError('Excessive or duplicate archive members')
        if set(source) != {info.filename for info in infos}:
            raise ValueError('Production archive does not exactly match approved source file inventory')
        if archive.testzip() is not None:
            raise ValueError('CRC corruption in production archive')
        total = 0
        for info in infos:
            rel = preflight.check_path(info.filename, info.is_dir())
            if preflight.prohibited(rel, info.is_dir()) or omitted(rel) or (info.flag_bits & 1):
                raise ValueError(f'Unsafe archive member: {rel}')
            path, is_dir = source[info.filename]
            required_mode = release_zip.DIR_MODE if is_dir else release_zip.FILE_MODE
            if info.create_system != 3 or (info.external_attr >> 16) != required_mode or info.is_dir() != is_dir:
                raise ValueError(f'Unsafe file type or permission: {rel}')
            if not is_dir:
                total += info.file_size
                if total > MAX_TOTAL_UNCOMPRESSED:
                    raise ValueError('Oversized production archive')
                if archive.read(info.filename) != path.read_bytes():
                    raise ValueError(f'Byte mismatch against source: {rel}')
            elif info.file_size != 0:
                raise ValueError(f'Nonempty directory entry: {rel}')
        version = archive.read(TOP + 'public_html/incl/init.php')
        match = VERSION_RX.search(version)
        if not match or match.group(1).decode() != '2.5.0':
            raise ValueError('Not the expected V2.5.0 release candidate')
        files = sum(not info.is_dir() for info in infos)
        print(f'PASS: production candidate ZIP 2.5.0 ({files} source-matched files, {len(infos)-files} directories)')
        print('PASS: no tests/fixtures/developer CLIs, no private uploads or credentials, migration history retained')
        print('READINESS: PRODUCTION ZIP CANDIDATE ONLY — NO-GO pending staging and environment sign-off')


def main() -> int:
    if len(sys.argv) != 3 or sys.argv[1] not in {'build', 'verify'}:
        print('Usage: python3 tests/release/production_zip.py {build|verify} path/to/production-candidate.zip', file=sys.stderr)
        return 2
    try:
        {'build': build, 'verify': verify}[sys.argv[1]](Path(sys.argv[2]))
    except (OSError, ValueError, zipfile.BadZipFile, preflight.PreflightFailure) as exc:
        print(f'FAIL: {exc}', file=sys.stderr)
        return 1
    return 0

if __name__ == '__main__':
    raise SystemExit(main())
