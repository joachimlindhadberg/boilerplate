#!/usr/bin/env python3
"""Read-only integrity preflight for SiteBackup TAR.GZ data archives.

Never extracts or runs SQL, uses no DB/network, and never prints private content.
This is NOT a restore rehearsal or an authenticity/signature verification.
"""
from __future__ import annotations

import argparse
import gzip
import hashlib
import json
from pathlib import Path
import re
import sys
import tarfile
import zlib

FORMAT = 'boilerplate-site-data-backup'
SHA256 = re.compile(r'^[a-f0-9]{64}$')
TABLE = re.compile(r'^[A-Za-z0-9_]+$')
MAX_ARCHIVE_BYTES = 32 * 1024**3
MAX_MEMBER_BYTES = 16 * 1024**3
MAX_TOTAL_BYTES = 64 * 1024**3
MAX_MEMBERS = 100_000
MAX_MANIFEST_BYTES = 16 * 1024**2
ALLOWED_UPLOADS = ('public_html/uploads/', 'public_html/assets/uploads/')
MAX_DEPTH = 32


class BackupFailure(ValueError):
    pass


def safe_path(name: str, directory: bool) -> str:
    if (not isinstance(name, str) or not name or name.startswith('/') or
            '\\' in name or '\x00' in name or any(ord(ch) < 32 for ch in name)):
        raise BackupFailure('Unsafe archive member path')
    if directory and name.endswith('//'):
        raise BackupFailure('Noncanonical archive directory path')
    clean = name.rstrip('/') if directory else name
    parts = clean.split('/')
    if (len(parts) > MAX_DEPTH or any(part in ('', '.', '..') for part in parts) or
            len(clean) > 1024 or (not directory and name.endswith('/'))):
        raise BackupFailure('Noncanonical archive member path')
    if directory:
        if not any(prefix.startswith(clean + '/') or clean == prefix.rstrip('/')
                   for prefix in ALLOWED_UPLOADS) and clean != 'public_html':
            raise BackupFailure('Unexpected archive directory')
        return clean
    # .htaccess is a versioned security policy, never restorable site media.
    if clean.startswith(ALLOWED_UPLOADS) and clean.rsplit('/', 1)[-1] == '.htaccess':
        raise BackupFailure('Upload security policy must not be present in a site-data backup')
    if name not in ('database.sql', 'manifest.json') and not any(
            name.startswith(prefix) and len(name) > len(prefix) for prefix in ALLOWED_UPLOADS):
        raise BackupFailure('Unexpected file outside backup data scope')
    return clean


def require_int(value: object, label: str) -> int:
    if type(value) is not int or value < 0:
        raise BackupFailure('Invalid backup manifest ' + label)
    return value


def verify(filename: Path) -> tuple[int, int, int]:
    if not filename.is_file() or filename.is_symlink():
        raise BackupFailure('Backup must be a regular non-symlink file')
    if filename.stat().st_size > MAX_ARCHIVE_BYTES:
        raise BackupFailure('Compressed backup exceeds offline review size limit')
    hashes: dict[str, str] = {}
    manifest_data = None
    member_names: set[str] = set()
    size_total = 0
    num_members = 0
    try:
        # Stream-only: never writes a temporary file or extracts any member.
        with tarfile.open(filename, mode='r|gz') as archive:
            for member in archive:
                num_members += 1
                if num_members > MAX_MEMBERS:
                    raise BackupFailure('Too many members in backup archive')
                isdir = member.isdir()
                if not (isdir or member.isfile()) or member.issym() or member.islnk():
                    raise BackupFailure('Links and special TAR member types are forbidden')
                clean = safe_path(member.name, isdir)
                if clean in member_names:
                    raise BackupFailure('Duplicate archive member')
                member_names.add(clean)
                if isdir:
                    continue
                if member.size < 0 or member.size > MAX_MEMBER_BYTES:
                    raise BackupFailure('Oversized TAR member')
                size_total += member.size
                if size_total > MAX_TOTAL_BYTES:
                    raise BackupFailure('Uncompressed backup exceeds review limit')
                if clean == 'manifest.json' and member.size > MAX_MANIFEST_BYTES:
                    raise BackupFailure('Manifest exceeds review limit')
                reader = archive.extractfile(member)
                if reader is None:
                    raise BackupFailure('Cannot read archive member')
                digest = hashlib.sha256()
                read_bytes = 0
                chunks = [] if clean == 'manifest.json' else None
                while True:
                    data = reader.read(1024 * 1024)
                    if not data:
                        break
                    read_bytes += len(data)
                    digest.update(data)
                    if chunks is not None:
                        chunks.append(data)
                if read_bytes != member.size:
                    raise BackupFailure('Truncated TAR member')
                hashes[clean] = digest.hexdigest()
                if chunks is not None:
                    manifest_data = b''.join(chunks)
    except (OSError, tarfile.TarError, EOFError, OverflowError, zlib.error) as exc:
        raise BackupFailure('Unreadable, truncated or corrupt backup archive') from exc

    # Consume the entire gzip stream to verify its trailer CRC and truncation,
    # including data after the TAR reader's logical end-of-archive marker.
    # This second streaming pass never extracts or writes any content.
    try:
        expanded_bytes = 0
        with gzip.open(filename, 'rb') as gz:
            while True:
                chunk = gz.read(1024 * 1024)
                if not chunk:
                    break
                expanded_bytes += len(chunk)
                if expanded_bytes > MAX_TOTAL_BYTES + MAX_MEMBERS * 2048:
                    raise BackupFailure('Expanded gzip stream exceeds review limit')
    except (OSError, EOFError, zlib.error) as exc:
        raise BackupFailure('Gzip checksum error or truncated compressed stream') from exc

    if 'database.sql' not in hashes or manifest_data is None:
        raise BackupFailure('Backup must include database.sql and manifest.json')
    try:
        manifest = json.loads(manifest_data.decode('utf-8'))
    except (ValueError, UnicodeError) as exc:
        raise BackupFailure('Invalid UTF-8 JSON manifest') from exc
    if not isinstance(manifest, dict) or manifest.get('format') != FORMAT or type(manifest.get('version')) is not int or manifest['version'] != 1:
        raise BackupFailure('Unsupported backup format/version')
    expected_sql = manifest.get('database_sha256')
    if not isinstance(expected_sql, str) or not SHA256.fullmatch(expected_sql) or hashes['database.sql'] != expected_sql:
        raise BackupFailure('Database SQL checksum mismatch')
    expected_uploads = manifest.get('upload_sha256')
    # PHP json_encode([]) produces [] rather than {} when SiteBackup has no
    # uploaded files. Accept this *only* with an explicit, integer zero count;
    # a nonempty or otherwise malformed list must never bypass hash checks.
    if (type(expected_uploads) is list and not expected_uploads and
            type(manifest.get('upload_files')) is int and manifest['upload_files'] == 0):
        expected_uploads = {}
    if not isinstance(expected_uploads, dict):
        raise BackupFailure('Missing or invalid upload checksum manifest')
    uploaded = {name: digest for name, digest in hashes.items() if name not in ('database.sql', 'manifest.json')}
    if (set(uploaded) != set(expected_uploads) or
            any(not isinstance(value, str) or not SHA256.fullmatch(value) for value in expected_uploads.values()) or
            any(uploaded[name] != expected_uploads[name] for name in uploaded)):
        raise BackupFailure('Upload set or checksum mismatch')
    if require_int(manifest.get('upload_files'), 'upload count') != len(uploaded):
        raise BackupFailure('Upload count mismatch')
    tables = manifest.get('database_tables')
    if not isinstance(tables, dict) or not tables or any(
            not isinstance(table, str) or not TABLE.fullmatch(table) or type(count) is not int or count < 0
            for table, count in tables.items()):
        raise BackupFailure('Invalid database table counts')
    if require_int(manifest.get('database_rows'), 'row count') != sum(tables.values()):
        raise BackupFailure('Database row count mismatch')
    require_int(manifest.get('database_triggers'), 'trigger count')
    if manifest.get('contains_sensitive_data') is not True:
        raise BackupFailure('Missing sensitive-data warning flag')
    return len(tables), len(uploaded), num_members


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--archive', type=Path, required=True, help='Locally stored SiteBackup TAR.GZ (read-only)')
    args = parser.parse_args(argv)
    try:
        tables, uploads, members = verify(args.archive)
    except BackupFailure as exc:
        print(f'FAIL: backup archive preflight: {exc}', file=sys.stderr)
        return 1
    print(f'PASS: backup structure, manifest, SQL/upload SHA-256 ({tables} tables, {uploads} uploads, {members} TAR members)')
    print('PASS: offline archive preflight (no extraction, SQL import, DB, HTTP or writes)')
    print('NOTE: matching checksums do not prove authenticity, point-in-time consistency or restorability.')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
