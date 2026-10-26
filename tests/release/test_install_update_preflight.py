#!/usr/bin/env python3
"""Synthetic adversarial ZIP tests; only temporary data, never user's site/DB."""
from __future__ import annotations

from pathlib import Path
import importlib.util
import stat
import tempfile
import zipfile

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('preflight', HERE / 'install_update_preflight.py')
assert spec and spec.loader
p = importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)


def fixture(path: Path, version='2.4.4.108', overrides=None, remove=None, extra=None):
    source = {name: b'code content' for name in p.REQUIRED}
    source['public_html/incl/init.php'] = f"<?php define('BOILERPLATE_VERSION', '{version}');".encode()
    source['database/updates/20261013_000_core_schema.php'] = b'<?php return static function(){};'
    source['database/migrations/20261012_activity_log_v2_2_1.sql'] = b'-- historical schema'
    for name in (remove or []):
        source.pop(name, None)
    source.update(overrides or {})
    source.update(extra or {})
    with zipfile.ZipFile(path, 'w') as archive:
        members = {'boilerplate/': None}
        for folder in p.EMPTY_RUNTIME:
            members[p.TOP + folder] = None
        for name, content in source.items():
            members[p.TOP + name] = content
        for name, content in members.items():
            is_dir = content is None
            info = zipfile.ZipInfo(name)
            info.create_system = 3
            info.external_attr = (((stat.S_IFDIR | 0o755) if is_dir else (stat.S_IFREG | 0o644)) << 16)
            archive.writestr(info, b'' if is_dir else content)


def reject(action, expected):
    try:
        action()
    except p.PreflightFailure as e:
        assert expected in str(e), (expected, str(e))
    else:
        raise AssertionError(f'Expected rejection: {expected}')


with tempfile.TemporaryDirectory(prefix='cms-install-preflight-') as folder:
    base = Path(folder)
    old = base / 'old.zip'
    new = base / 'new.zip'
    fixture(old)
    fixture(new, '2.4.4.109')
    a, b = p.Archive(old), p.Archive(new)
    assert a.version == '2.4.4.108'
    assert b.version == '2.4.4.109'
    assert len(p.compare(a, b)) >= 3
    print('PASS: valid full candidate and forward upgrade accepted')

    fixture(new, '2.4.4.108')
    reject(lambda: p.compare(p.Archive(old), p.Archive(new)), 'Version must increase')
    print('PASS: unchanged or downgraded version rejected')

    fixture(new, '2.4.4.109', overrides={'database/updates/20261013_000_core_schema.php': b'edited'})
    reject(lambda: p.compare(p.Archive(old), p.Archive(new)), 'Historical migrations changed or removed')
    print('PASS: historical PHP migration modification rejected')

    fixture(new, '2.4.4.109', remove={'database/updates/20261013_000_core_schema.php'})
    reject(lambda: p.compare(p.Archive(old), p.Archive(new)), 'Historical migrations changed or removed')
    print('PASS: historical migration removal rejected')

    fixture(new, '2.4.4.109', overrides={'database/migrations/20261012_activity_log_v2_2_1.sql': b'changed'})
    reject(lambda: p.compare(p.Archive(old), p.Archive(new)), 'Historical migrations changed or removed')
    print('PASS: historical manual SQL alteration rejected')

    fixture(new, '2.4.4.109', extra={'database/updates/20261020_000_new.php': b'<?php return fn()=>null;'})
    assert any('REVIEW REQUIRED' in line for line in p.compare(p.Archive(old), p.Archive(new)))
    print('PASS: new migration flagged for explicit manual review')

    for bad in ('.env', '.env.local', 'public_html/uploads/secret.jpg', 'public_html/assets/uploads/secret.jpg',
                'public_html/storage/private.json', 'user-backup.zip', 'public_html/.env.production',
                'random.sql', 'public_html/logs/cms.log', 'core/.DS_Store'):
        fixture(new, '2.4.4.109', extra={bad: b'SECRET'})
        reject(lambda: p.Archive(new), 'Private/runtime data or unsafe file')
    print('PASS: private config, runtime uploads, backups and dumps rejected')

    fixture(new, '2.4.4.109', extra={k: b'Options -Indexes\n' for k in p.UPLOAD_POLICY})
    assert p.UPLOAD_POLICY <= set(p.Archive(new).data)
    print('PASS: historical ZIP archives retain known upload security policies')
    fixture(new, '2.4.4.109', extra={'public_html/assets/uploads/.htaccess': b'legacy policy'})
    assert 'public_html/assets/uploads/.htaccess' in p.Archive(new).data
    reject(lambda: p.Archive(new, current=True), 'New release must not ship legacy upload directory')
    print('PASS: new ZIPs cannot contain legacy upload policy')

    for bad in ('boilerplate/../outside', 'boilerplate/core/../../outside', '/tmp/evil',
                'boilerplate/core\\evil.php', 'boilerplate//bad', 'boilerplate/./bad'):
        fixture(new, '2.4.4.109')
        with zipfile.ZipFile(new, 'a') as z:
            z.writestr(bad, b'bad')
        reject(lambda: p.Archive(new), 'ZIP path' if bad.startswith('/tmp') or '\\' in bad else 'path')
    print('PASS: ZIP traversal, absolute paths and noncanonical names rejected')

    fixture(new, '2.4.4.109', remove={'core/cli/migrate.php'})
    reject(lambda: p.Archive(new), 'Incomplete application release')
    print('PASS: incomplete full CMS release rejected')

    fixture(new, '2.4.4.109')
    with zipfile.ZipFile(new, 'a') as z:
        z.writestr('boilerplate/public_html/index.php', b'repeated')
    reject(lambda: p.Archive(new), 'Duplicate ZIP member')
    print('PASS: duplicate archive member rejected')

    fixture(new, '2.4.4.109')
    with zipfile.ZipFile(new, 'a') as z:
        link = zipfile.ZipInfo('boilerplate/evil-link')
        link.create_system = 3
        link.external_attr = (stat.S_IFLNK | 0o777) << 16
        z.writestr(link, b'../../secret')
    reject(lambda: p.Archive(new), 'Unexpected file type or permissions')
    print('PASS: symlink member rejected')

    fixture(new, '2.4.4.109', extra={'pages/random.php': b'x' * (p.MAX_FILE_BYTES + 1)})
    reject(lambda: p.Archive(new), 'Oversized member')
    print('PASS: oversized archive member rejected before decompression')

print('PASS: synthetic install/update preflight adversarial test suite')
