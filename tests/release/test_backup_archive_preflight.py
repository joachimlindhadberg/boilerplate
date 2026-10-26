#!/usr/bin/env python3
"""Synthetic-only, locally temporary archives; NEVER imports SQL or reads site data."""
from __future__ import annotations

import io
import hashlib
import json
from pathlib import Path
import sys
import tarfile
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parent))
from backup_archive_preflight import BackupFailure, safe_path, verify

SQL = b'-- synthetic database.sql\nCREATE TABLE `example` (`id` int);\n'
MEDIA = b'fake-image-bytes'
MEDIA_PATH = 'public_html/uploads/example.jpg'


def manifest(sql=SQL, uploads=None):
    if uploads is None:
        uploads = {MEDIA_PATH: hashlib.sha256(MEDIA).hexdigest()}
    return {
        'format': 'boilerplate-site-data-backup', 'version': 1,
        'generated_utc': '2026-10-18T00:00:00+00:00', 'cms_version': '2.4.4.109',
        'database_tables': {'pages': 2, 'media': 1}, 'database_rows': 3,
        'database_triggers': 9, 'database_sha256': hashlib.sha256(sql).hexdigest(),
        'upload_files': len(uploads), 'upload_sha256': uploads,
        'contains_sensitive_data': True,
    }


def make_archive(path, entries=None, mf=None):
    if mf is None:
        mf = manifest()
    if entries is None:
        entries = [('database.sql', SQL, 'file'), ('manifest.json', json.dumps(mf).encode(), 'file'),
                   ('public_html/uploads/', b'', 'dir'), (MEDIA_PATH, MEDIA, 'file')]
    with tarfile.open(path, 'w:gz') as z:
        for name, data, kind in entries:
            info = tarfile.TarInfo(name)
            if kind == 'dir':
                info.type = tarfile.DIRTYPE
            elif kind == 'link':
                info.type = tarfile.SYMTYPE
                info.linkname = 'database.sql'
            elif kind == 'hardlink':
                info.type = tarfile.LNKTYPE
                info.linkname = 'database.sql'
            else:
                info.type = tarfile.REGTYPE
                info.size = len(data)
            z.addfile(info, io.BytesIO(data) if kind == 'file' else None)


class BackupPreflightTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='boilerplate-synthetic-backup-')
        self.addCleanup(self.temp.cleanup)
        self.archive = Path(self.temp.name) / 'sample.tar.gz'

    def invalid(self, entries=None, mf=None):
        make_archive(self.archive, entries, mf)
        with self.assertRaises(BackupFailure):
            verify(self.archive)

    def entries(self, manifest_override=None):
        return [('database.sql', SQL, 'file'),
                ('manifest.json', json.dumps(manifest_override if manifest_override is not None else manifest()).encode(), 'file'),
                (MEDIA_PATH, MEDIA, 'file')]

    def test_upload_policy_is_not_backup_data(self):
        from backup_archive_preflight import safe_path
        with self.assertRaises(BackupFailure):
            safe_path('public_html/uploads/.htaccess', False)
        with self.assertRaises(BackupFailure):
            safe_path('public_html/assets/uploads/.htaccess', False)

    def test_valid_archive(self):
        make_archive(self.archive)
        self.assertEqual(verify(self.archive), (2, 1, 4))

    def test_empty_upload_set(self):
        m = manifest(uploads={})
        self.invalid(self.entries(m))

    def test_valid_zero_uploads(self):
        m = manifest(uploads={})
        make_archive(self.archive, [('database.sql', SQL, 'file'), ('manifest.json', json.dumps(m).encode(), 'file')])
        self.assertEqual(verify(self.archive), (2, 0, 2))

    def test_php_empty_list_for_zero_uploads(self):
        # The real SiteBackup PHP generator uses an empty PHP array, which
        # json_encode serializes to [], not the synthetic test's {}.
        m = manifest(uploads={})
        m['upload_sha256'] = []
        make_archive(self.archive, [('database.sql', SQL, 'file'), ('manifest.json', json.dumps(m).encode(), 'file')])
        self.assertEqual(verify(self.archive), (2, 0, 2))

    def test_php_empty_list_rejected_when_count_nonzero(self):
        m = manifest(uploads={})
        m['upload_sha256'] = []
        m['upload_files'] = 1
        self.invalid([('database.sql', SQL, 'file'), ('manifest.json', json.dumps(m).encode(), 'file')])

    def test_php_empty_list_rejected_when_upload_exists(self):
        m = manifest(uploads={})
        m['upload_sha256'] = []
        self.invalid([('database.sql', SQL, 'file'), ('manifest.json', json.dumps(m).encode(), 'file'),
                      (MEDIA_PATH, MEDIA, 'file')])

    def test_nonempty_checksum_list_rejected(self):
        m = manifest(uploads={})
        m['upload_sha256'] = [hashlib.sha256(MEDIA).hexdigest()]
        self.invalid([('database.sql', SQL, 'file'), ('manifest.json', json.dumps(m).encode(), 'file')])

    def test_missing_checksum_field_rejected(self):
        m = manifest(uploads={})
        del m['upload_sha256']
        self.invalid([('database.sql', SQL, 'file'), ('manifest.json', json.dumps(m).encode(), 'file')])

    def test_tampered_sql(self):
        e = self.entries()
        e[0] = ('database.sql', b'ALTER TABLE fake;', 'file')
        self.invalid(e)

    def test_tampered_upload(self):
        e = self.entries()
        e[2] = (MEDIA_PATH, b'modified-media', 'file')
        self.invalid(e)

    def test_missing_sql(self):
        self.invalid(self.entries()[1:])

    def test_missing_manifest(self):
        self.invalid([self.entries()[0], self.entries()[2]])

    def test_missing_upload(self):
        self.invalid(self.entries()[:2])

    def test_unregistered_upload(self):
        self.invalid(self.entries() + [('public_html/uploads/unexpected.png', b'x', 'file')])

    def test_duplicate_member(self):
        self.invalid(self.entries() + [('database.sql', SQL, 'file')])

    def test_absolute_path(self):
        self.invalid(self.entries() + [('/tmp/outside', b'x', 'file')])

    def test_parent_path(self):
        self.invalid(self.entries() + [('public_html/uploads/../evil', b'x', 'file')])

    def test_credential_path(self):
        self.invalid(self.entries() + [('.env', b'not-real-secrets', 'file')])

    def test_symbolic_link(self):
        self.invalid(self.entries() + [('public_html/uploads/link', b'', 'link')])

    def test_hardlink(self):
        self.invalid(self.entries() + [('public_html/uploads/link', b'', 'hardlink')])

    def test_invalid_manifest_format(self):
        m = manifest(); m['format'] = 'incorrect'; self.invalid(self.entries(m))

    def test_invalid_manifest_version(self):
        m = manifest(); m['version'] = 2; self.invalid(self.entries(m))

    def test_mismatched_upload_count(self):
        m = manifest(); m['upload_files'] = 5; self.invalid(self.entries(m))

    def test_mismatched_row_count(self):
        m = manifest(); m['database_rows'] = 50; self.invalid(self.entries(m))

    def test_mismatched_sql_digest(self):
        m = manifest(); m['database_sha256'] = 'a' * 64; self.invalid(self.entries(m))

    def test_missing_privacy_flag(self):
        m = manifest(); m['contains_sensitive_data'] = False; self.invalid(self.entries(m))

    def test_invalid_table_name(self):
        m = manifest(); m['database_tables'] = {'pages;DROP TABLE': 1}; m['database_rows'] = 1; self.invalid(self.entries(m))

    def test_invalid_json(self):
        e = self.entries(); e[1] = ('manifest.json', b'{', 'file'); self.invalid(e)

    def test_truncated_archive(self):
        make_archive(self.archive)
        data = self.archive.read_bytes(); self.archive.write_bytes(data[:max(4, len(data)//2)])
        with self.assertRaises(BackupFailure):
            verify(self.archive)

    def test_corrupt_gzip_crc(self):
        make_archive(self.archive)
        data = bytearray(self.archive.read_bytes())
        data[-8] ^= 0x80  # gzip footer CRC32 is not a TAR header
        self.archive.write_bytes(data)
        with self.assertRaises(BackupFailure):
            verify(self.archive)

    def test_double_slash_directory(self):
        with self.assertRaises(BackupFailure):
            safe_path('public_html/uploads//', True)

    def test_archive_symlink(self):
        make_archive(self.archive)
        link = self.archive.with_suffix('.link.tar.gz')
        link.symlink_to(self.archive)
        with self.assertRaises(BackupFailure):
            verify(link)

    def test_invalid_paths_directly(self):
        for path in ['../escape', '/absolute', 'public_html/uploads/../escape', 'a\\b', '']:
            with self.subTest(path=path):
                with self.assertRaises(BackupFailure):
                    safe_path(path, False)


if __name__ == '__main__':
    unittest.main(verbosity=2)
