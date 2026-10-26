#!/usr/bin/env python3
"""Read-only ZIP and upgrade preflight for Boilerplate CMS.

Inspects release archives in memory: NEVER extracts, deploys, modifies a project,
connects to MySQL, sends HTTP, or executes a migration. Code-only comparator.
"""
from __future__ import annotations

import argparse
import hashlib
from pathlib import Path, PurePosixPath
import re
import stat
import sys
import zipfile

TOP = 'boilerplate/'
REQUIRED = {
    'public_html/index.php', 'public_html/admin/index.php',
    'public_html/incl/init.php', 'core/classes/MigrationRunner.php',
    'core/cli/migrate.php', 'tests/integration/run.sh',
    'tests/release/release_zip.py',
}
EMPTY_RUNTIME = {'public_html/uploads/'}
UPLOAD_POLICY = {'public_html/uploads/.htaccess', 'public_html/assets/uploads/.htaccess'}
MAX_ENTRIES = 20_000
MAX_FILE_BYTES = 64 * 1024 * 1024
MAX_TOTAL_BYTES = 256 * 1024 * 1024
VERSION_RX = re.compile(rb"define\('BOILERPLATE_VERSION', '([0-9]+(?:\.[0-9]+){2,3})'\)")
MIGRATION_RX = re.compile(r'(?:database/updates|modules/[^/]+/migrations)/\d{8}_[a-z0-9_-]+\.php$')


class PreflightFailure(ValueError):
    pass


def check_path(raw: str, is_dir: bool) -> str:
    if '\\' in raw or '\x00' in raw or raw.startswith('/') or not raw.startswith(TOP):
        raise PreflightFailure(f'Unsafe ZIP path: {raw!r}')
    rel = raw[len(TOP):]
    if rel == '' and is_dir:
        return ''
    if not rel or rel.startswith('/') or rel.endswith('//'):
        raise PreflightFailure(f'Invalid ZIP path: {raw!r}')
    if raw.endswith('/') != is_dir:
        raise PreflightFailure(f'Invalid directory marker: {raw!r}')
    parts = rel.rstrip('/').split('/')
    if any(p in ('', '.', '..') or '\x00' in p for p in parts):
        raise PreflightFailure(f'Unsafe path components: {raw!r}')
    if PurePosixPath(rel).as_posix() != rel.rstrip('/'):
        raise PreflightFailure(f'Noncanonical ZIP path: {raw!r}')
    return rel


def prohibited(rel: str, is_dir: bool) -> bool:
    if not rel:
        return False
    path = rel.rstrip('/').split('/')
    low = [p.lower() for p in path]
    if any(p in ('.git', '__macosx', '__pycache__', 'node_modules', 'vendor',
                 'cache', 'storage', 'tmp', 'temp', '.pytest_cache') for p in low):
        return True
    base = low[-1]
    if base in ('.ds_store', 'thumbs.db', '.env', '.env.local', '.env.production',
                '.env.testing') or base.startswith('.env.') or base.startswith('._'):
        return True
    if base.endswith(('.log', '.pyc', '.swp', '.bak', '.backup', '.dump')):
        return True
    if base.endswith(('.zip', '.tar', '.tar.gz', '.gz')):
        return True
    # SQL sources under database/ are a known part of the project; ad-hoc
    # exports elsewhere are not acceptable release contents.
    if base.endswith('.sql') and low[0] != 'database' and low[:3] != ['tests', 'integration', 'fixtures']:
        return True
    if low[0] == 'public_html' and len(low) >= 2 and low[1] == 'uploads' and len(low) > 2 and rel not in UPLOAD_POLICY:
        return True
    if low[:3] == ['public_html', 'assets', 'uploads'] and len(low) > 3 and rel not in UPLOAD_POLICY:
        return True
    return False


class Archive:
    def __init__(self, filename: Path, *, current: bool = False):
        self.filename = filename
        self.data: dict[str, bytes] = {}
        self.folders: set[str] = set()
        total = 0
        try:
            with zipfile.ZipFile(filename) as archive:
                infos = archive.infolist()
                if not infos or len(infos) > MAX_ENTRIES:
                    raise PreflightFailure('Empty or oversized ZIP member list')
                seen: set[str] = set()
                for info in infos:
                    name = info.filename
                    if name in seen:
                        raise PreflightFailure(f'Duplicate ZIP member: {name}')
                    seen.add(name)
                    rel = check_path(name, info.is_dir())
                    if prohibited(rel, info.is_dir()):
                        raise PreflightFailure(f'Private/runtime data or unsafe file in ZIP: {rel}')
                    if info.flag_bits & 1:
                        raise PreflightFailure(f'Encrypted ZIP member: {rel}')
                    if info.create_system != 3:
                        raise PreflightFailure(f'Archive entry lacks Unix permission metadata: {rel}')
                    expected = (stat.S_IFDIR | 0o755) if info.is_dir() else (stat.S_IFREG | 0o644)
                    if info.external_attr >> 16 != expected:
                        raise PreflightFailure(f'Unexpected file type or permissions: {rel}')
                    if info.is_dir():
                        if info.file_size != 0:
                            raise PreflightFailure(f'Nonempty directory record: {rel}')
                        self.folders.add(rel)
                    else:
                        if info.file_size > MAX_FILE_BYTES:
                            raise PreflightFailure(f'Oversized member: {rel}')
                        total += info.file_size
                        if total > MAX_TOTAL_BYTES:
                            raise PreflightFailure('Oversized uncompressed archive')
                        content = archive.read(info)
                        if len(content) != info.file_size:
                            raise PreflightFailure(f'Invalid size or corrupt data: {rel}')
                        self.data[rel] = content
                if archive.testzip() is not None:
                    raise PreflightFailure('CRC failure')
        except (OSError, zipfile.BadZipFile, RuntimeError, zipfile.LargeZipFile) as exc:
            raise PreflightFailure(f'Cannot inspect ZIP: {exc}') from exc
        missing = REQUIRED.difference(self.data)
        if missing:
            raise PreflightFailure(f'Incomplete application release: {sorted(missing)}')
        if not EMPTY_RUNTIME.issubset(self.folders):
            raise PreflightFailure('Release must contain the current uploads directory')
        if current and any(rel == 'public_html/assets/uploads/' or rel.startswith('public_html/assets/uploads/')
                           for rel in self.folders | set(self.data)):
            raise PreflightFailure('New release must not ship legacy upload directory')
        match = VERSION_RX.search(self.data['public_html/incl/init.php'])
        if match is None:
            raise PreflightFailure('Internal CMS version not found')
        self.version = match.group(1).decode('ascii')

    def migration_hashes(self) -> dict[str, str]:
        return {rel: hashlib.sha256(content).hexdigest() for rel, content in self.data.items()
                if MIGRATION_RX.fullmatch(rel) or rel.startswith('database/migrations/') and rel.endswith('.sql')}


def version_tuple(version: str) -> tuple[int, ...]:
    return tuple(map(int, version.split('.')))


def compare(previous: Archive, current: Archive) -> list[str]:
    if version_tuple(current.version) <= version_tuple(previous.version):
        raise PreflightFailure(f'Version must increase: {previous.version} -> {current.version}')
    old = previous.migration_hashes()
    new = current.migration_hashes()
    changed = [k for k, digest in old.items() if k not in new or new[k] != digest]
    if changed:
        raise PreflightFailure(f'Historical migrations changed or removed: {changed}')
    new_migrations = sorted(set(new) - set(old))
    dropped = sorted(set(previous.data) - set(current.data))
    changed_code = sum(previous.data[p] != value for p, value in current.data.items() if p in previous.data)
    new_code = len(set(current.data) - set(previous.data))
    results = [f'PASS: upgrade order {previous.version} -> {current.version}',
               f'PASS: {len(old)} existing migration files preserved byte-identically',
               f'INFO: code files changed={changed_code}, added={new_code}, absent in new ZIP={len(dropped)}',
               'INFO: Overlay installation never removes old files; review absent paths manually.']
    if new_migrations:
        results.append(f'REVIEW REQUIRED: new migration files {new_migrations}; do not auto-run or import.')
    return results


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--release', type=Path, required=True, help='Proposed full CMS ZIP (read-only)')
    parser.add_argument('--from-zip', type=Path, help='Previously approved full CMS ZIP for upgrade comparison')
    args = parser.parse_args(argv)
    try:
        release = Archive(args.release, current=True)
        print(f'PASS: safe complete candidate ZIP {release.version} '
              f'({len(release.data)} files; {len(release.folders)} directories)')
        if args.from_zip:
            previous = Archive(args.from_zip)
            for line in compare(previous, release):
                print(line)
        else:
            print('INFO: No earlier ZIP compared; upgrade/migration compatibility not established.')
        print('PASS: read-only ZIP preflight (no extraction, DB, HTTP, migrations or local site changes)')
        return 0
    except PreflightFailure as exc:
        print(f'FAIL: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
