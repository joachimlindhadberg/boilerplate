#!/usr/bin/env python3
"""Synthetic ZIP fixture: upload directory entries must not ship user data."""

from pathlib import Path
import importlib.util
import os
import stat
import tempfile
import zipfile

SCRIPT = Path(__file__).with_name('release_zip.py')
spec = importlib.util.spec_from_file_location('cms_release_zip_under_test', SCRIPT)
assert spec and spec.loader
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

with tempfile.TemporaryDirectory(prefix='cms-zip-upload-dirs-') as tmp:
    base = Path(tmp)
    module.ROOT = base / 'boilerplate'
    module.PARENT = base
    module.ROOT.mkdir()
    (module.ROOT / 'public_html' / 'assets').mkdir(parents=True)
    (module.ROOT / 'public_html' / 'uploads').mkdir(parents=True)
    (module.ROOT / 'public_html' / 'assets' / 'uploads').mkdir()
    for folder in ('uploads',):
        (module.ROOT / 'public_html' / folder / '.htaccess').write_bytes(b'Options -Indexes\n')
    (module.ROOT / 'public_html' / 'uploads' / 'private-photo.jpg').write_bytes(b'SECRET_IMAGE_BYTES')
    (module.ROOT / 'public_html' / 'assets' / 'uploads' / 'legacy.png').write_bytes(b'SECRET_LEGACY_BYTES')
    (module.ROOT / '.env').write_text('DB_PASS=SECRET_TEST_VALUE\n')
    (module.ROOT / '.env.production').write_text('DB_PASS=SECOND_SECRET_TEST_VALUE\n')
    (module.ROOT / 'public_html' / 'index.php').write_text('<?php echo 1;\n')
    archive = base / 'fixture.zip'
    module.build(archive)
    module.verify(archive)
    with zipfile.ZipFile(archive) as z:
        names = set(z.namelist())
        for folder in ('boilerplate/public_html/uploads/',):
            assert folder in names, folder
            info = z.getinfo(folder)
            assert info.is_dir()
            assert info.external_attr >> 16 == stat.S_IFDIR | 0o755
            assert z.read(folder) == b''
        assert not any('private-photo' in n or 'legacy.png' in n or n.endswith('.env') or n.endswith('.env.production') for n in names)
        assert {n for n in names if n.startswith('boilerplate/public_html/uploads/')} == {'boilerplate/public_html/uploads/', 'boilerplate/public_html/uploads/.htaccess'}
        assert not any(n.startswith('boilerplate/public_html/assets/uploads/') for n in names)
    # Verify that repair never modifies permissions of user-owned upload directories.
    up = module.ROOT / 'public_html' / 'uploads'
    up.chmod(0o700)
    module.repair(archive)
    assert stat.S_IMODE(up.stat().st_mode) == 0o700
    # Source checkout with no physical upload dirs should still package them.
    (module.ROOT / 'public_html' / 'uploads' / 'private-photo.jpg').unlink()
    (module.ROOT / 'public_html' / 'assets' / 'uploads' / 'legacy.png').unlink()
    # Remove only runtime media; the current .htaccess policy must stay in place.
    # A missing policy is a packaging failure, not a condition to recover from.
    (module.ROOT / 'public_html' / 'uploads' / '.htaccess').unlink()
    try:
        module.build(archive)
    except ValueError as e:
        assert 'Missing secure upload directory policy' in str(e)
    else:
        raise AssertionError('Missing upload policy must fail closed')
    (module.ROOT / 'public_html' / 'uploads' / '.htaccess').write_bytes(b'Options -Indexes\n')
    module.build(archive)
    module.verify(archive)
    print('PASS: versioned upload policies retained, private media excluded, safe repair')
