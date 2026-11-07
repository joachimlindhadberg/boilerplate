#!/usr/bin/env python3
"""Package/verify local CMS releases with explicit safe Unix permissions.

Usage:
  python3 tests/release/release_zip.py build /path/to/release.zip
  python3 tests/release/release_zip.py verify /path/to/release.zip
  python3 tests/release/release_zip.py repair /path/to/release.zip

Repair only touches paths shipped by the archive whose current content matches it;
it deliberately does not touch .env, uploads or other local-only data.
"""

from __future__ import annotations

import os
from pathlib import Path
import stat
import sys
import time
import zipfile

ROOT = Path(__file__).resolve().parents[2]
PARENT = ROOT.parent
TOP = 'boilerplate/'
FILE_MODE = stat.S_IFREG | 0o644
DIR_MODE = stat.S_IFDIR | 0o755
EXCLUDED_DIRS = {'.git', '__MACOSX', '__pycache__', '.pytest_cache', 'node_modules', 'vendor', 'uploads', 'storage', 'cache', 'tmp', 'temp'}
EXCLUDED_FILES = {'.env', '.DS_Store', 'Thumbs.db'}
# New installs ship a single media directory and its policy. Legacy storage
# is excluded completely, even if a local development tree contains old files.
EMPTY_RUNTIME_DIRS = ('public_html/uploads',)


def paths():
    entries = [(TOP, ROOT, True)]
    for current, dirs, files in os.walk(ROOT, followlinks=False):
        current_path = Path(current)
        dirs[:] = sorted(d for d in dirs if d not in EXCLUDED_DIRS and not d.startswith('.'))
        for dirname in dirs:
            p = current_path / dirname
            if p.is_symlink():
                raise ValueError(f'Symlink in release tree: {p}')
            entries.append((f'{TOP}{p.relative_to(ROOT).as_posix()}/', p, True))
        for filename in sorted(files):
            if filename in EXCLUDED_FILES or filename.startswith(('.env.', '._')) or filename.endswith(('.log', '.pyc', '.swp')):
                continue
            p = current_path / filename
            if p.is_symlink() or not p.is_file():
                raise ValueError(f'Non-regular file in release tree: {p}')
            entries.append((f'{TOP}{p.relative_to(ROOT).as_posix()}', p, False))
    # Runtime media entries remain excluded by EXCLUDED_DIRS; only the
    # current upload security policy is added explicitly and validated.
    for relative in EMPTY_RUNTIME_DIRS:
        p = ROOT / relative
        if p.is_symlink() or (p.exists() and not p.is_dir()):
            raise ValueError(f'Unsafe upload directory: {relative}')
        entries.append((f'{TOP}{relative}/', p, True))
        policy = p / '.htaccess'
        if policy.is_symlink() or not policy.is_file():
            raise ValueError(f'Missing secure upload directory policy: {relative}/.htaccess')
        entries.append((f"{TOP}{relative}/.htaccess", policy, False))
    return sorted(entries, key=lambda e: e[0])


def build(dest: Path):
    dest = dest.resolve()
    if dest.is_relative_to(ROOT):
        raise ValueError('ZIP output must be outside the source project')
    dest.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(dest, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=8, allowZip64=True) as z:
        for name, p, is_dir in paths():
            # Empty runtime directory entries may be virtual on a clean checkout.
            mtime = time.localtime((p if p.exists() else ROOT).stat().st_mtime)
            year = max(1980, min(2107, mtime.tm_year))
            info = zipfile.ZipInfo(name, (year, *mtime[1:6]))
            info.create_system = 3  # Unix attrs recognized by macOS unzip/Archive Utility
            info.external_attr = ((DIR_MODE if is_dir else FILE_MODE) << 16) | (0x10 if is_dir else 0)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.flag_bits |= 0x800
            z.writestr(info, b'' if is_dir else p.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=8)
    verify(dest)


def verify(src: Path):
    with zipfile.ZipFile(src) as z:
        all_names = z.namelist()
        if len(all_names) != len(set(all_names)):
            raise ValueError('Duplicate paths in archive')
        if z.testzip() is not None:
            raise ValueError('ZIP CRC check failed')
        expected = {name: (p, is_dir) for name, p, is_dir in paths()}
        if set(all_names) != set(expected):
            diff = set(all_names) ^ set(expected)
            raise ValueError(f'Unexpected/missing archive paths: {sorted(diff)[:8]}')
        count = 0
        for info in z.infolist():
            p, is_dir = expected[info.filename]
            if info.create_system != 3 or (info.external_attr >> 16) != (DIR_MODE if is_dir else FILE_MODE):
                raise ValueError(f'Unsafe archive permissions: {info.filename} = {oct(info.external_attr >> 16)}')
            if info.is_dir() != is_dir:
                raise ValueError(f'Unexpected archive file type: {info.filename}')
            if not is_dir:
                if z.read(info.filename) != p.read_bytes():
                    raise ValueError(f'Archive content differs from tested source: {info.filename}')
                count += 1
        print(f'PASS: ZIP permissions, CRC and byte-identical source ({count} files, {len(all_names)-count} directories)')


def repair(src: Path):
    # Applied only to matching code files, never to user data/credentials.
    fixed_files, fixed_dirs, skipped = 0, 0, 0
    with zipfile.ZipFile(src) as z:
        for info in z.infolist():
            name = info.filename
            if not name.startswith(TOP) or '..' in Path(name).parts:
                raise ValueError(f'Unsafe path: {name}')
            rel = name[len(TOP):].rstrip('/')
            p = ROOT / rel
            if p.is_symlink():
                raise ValueError(f'Symlink at installed path: {p}')
            mode = info.external_attr >> 16
            if info.is_dir():
                if mode != DIR_MODE: raise ValueError(f'Unsafe directory mode: {name}')
                # Never modify local upload ownership/permissions in repair mode.
                if rel in EMPTY_RUNTIME_DIRS:
                    skipped += 1
                    continue
                if p.is_dir():
                    p.chmod(0o755); fixed_dirs += 1
            elif mode == FILE_MODE and p.is_file() and z.read(info.filename) == p.read_bytes():
                p.chmod(0o644); fixed_files += 1
            else:
                skipped += 1
    print(f'PASS: normalized {fixed_files} matching release files and {fixed_dirs} directories; {skipped} absent/modified files untouched')


if __name__ == '__main__':
    if len(sys.argv) != 3 or sys.argv[1] not in {'build', 'verify', 'repair'}:
        sys.exit('Usage: python3 tests/release/release_zip.py {build|verify|repair} /path/to/release.zip')
    try:
        {'build': build, 'verify': verify, 'repair': repair}[sys.argv[1]](Path(sys.argv[2]))
    except (OSError, ValueError, zipfile.BadZipFile) as exc:
        sys.exit(f'FAIL: {exc}')
