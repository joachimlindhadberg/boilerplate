#!/usr/bin/env python3
"""Offline V2.5.0 production package regression; only temporary synthetic ZIPs."""
from __future__ import annotations
from pathlib import Path
import os
import sys
import tempfile
import zipfile

sys.path.insert(0, str(Path(__file__).resolve().parent))
import production_zip as p
import install_update_preflight as u

checks = 0
def ok(value: bool, message: str):
    global checks
    if not value:
        raise AssertionError(message)
    checks += 1
    print('PASS:', message)

def rejects(callable_, message: str):
    try:
        callable_()
    except (ValueError, OSError, zipfile.BadZipFile):
        ok(True, message)
    else:
        ok(False, message)

with tempfile.TemporaryDirectory(prefix='cms-v250-prod-test-') as tmp:
    archive = Path(tmp) / 'production.zip'
    p.build(archive)
    p.verify(archive)
    with zipfile.ZipFile(archive) as z:
        files = {i.filename for i in z.infolist() if not i.is_dir()}
        dirs = {i.filename for i in z.infolist() if i.is_dir()}
        ok('boilerplate/public_html/incl/init.php' in files, 'Production retains application version')
        ok('boilerplate/core/cli/qa.php' in files, 'Production retains read-only operational QA')
        ok('boilerplate/core/cli/migrate.php' in files, 'Production retains migration CLI')
        ok('boilerplate/core/classes/SiteBackup.php' in files, 'Production retains backup hotfix')
        ok('boilerplate/docs/deployment/V2.5.0-GO-LIVE-GATE.md' in files, 'Production ships go-live gate')
        ok(all('boilerplate/' + name in files for name in ('README.md', 'docs/deployment/INSTALLATION.md', 'docs/deployment/HOSTING-REQUIREMENTS.md', 'docs/deployment/UPDATING.md')), 'Production includes current customer-facing release documents')
        ok(not any(k.startswith('boilerplate/docs/releases/') for k in files), 'Production omits historical developer release notes')
        ok('boilerplate/public_html/uploads/' in dirs and 'boilerplate/public_html/assets/uploads/' not in dirs, 'Production ships one current uploads root')
        ok('boilerplate/public_html/uploads/.htaccess' in files and 'boilerplate/public_html/assets/uploads/.htaccess' not in files, 'Production retains only current upload security policy')
        ok(not any(i.startswith('boilerplate/tests/') for i in files), 'Production contains no test files')
        ok(not any(i.startswith('boilerplate/module-starter/') for i in files), 'Production contains no starter fixtures')
        ok(not any(i.startswith('boilerplate/docs/audits/') for i in files), 'Production excludes historical audits')
        ok(not any(i.startswith('boilerplate/core/cli/') and i.rsplit('/',1)[-1] in p.DEV_CLI for i in files), 'Production excludes developer CLI tools')
        ok('boilerplate/.env' not in files and not any(i.startswith('boilerplate/public_html/uploads/') and i != 'boilerplate/public_html/uploads/.htaccess' for i in files) and not any(i.startswith('boilerplate/public_html/assets/uploads/') and i != 'boilerplate/public_html/assets/uploads/.htaccess' for i in files), 'Production excludes private credentials and uploads')
        ok('boilerplate/modules/forms/FormsModule.php' in files and 'boilerplate/modules/gallery/GalleryModule.php' in files, 'Forms/Gallery runtime retained')
        ok('boilerplate/public_html/admin/assets/scss/admin.scss' in files and 'boilerplate/public_html/admin/assets/css/admin.css' in files, 'Core compiled+SCSS source retained')
        src=z.read('boilerplate/public_html/incl/init.php')
        ok(p.VERSION_RX.search(src).group(1).decode() == '2.5.0', 'Three-part 2.5.0 version supported')
        migrations={k[len('boilerplate/'):] for k in files if k.startswith('boilerplate/database/updates/') or k.startswith('boilerplate/database/migrations/') or '/migrations/' in k and k.startswith('boilerplate/modules/')}
        ok(len(migrations) == 14, 'All historical migrations plus Gallery Lightbox, RC5 dashboard and Session Manager migration retained')
    # Offline member inventory must reject undeclared payloads and tampered copies.
    with zipfile.ZipFile(archive, 'a') as z:
        z.writestr('boilerplate/test-dump.sql', b'INSERT INTO secrets VALUES (1);')
    rejects(lambda: p.verify(archive), 'Extra SQL payload rejected, before any extraction')
    p.build(archive)
    with zipfile.ZipFile(archive, 'a') as z:
        z.writestr('boilerplate/public_html/incl/init.php', b'hacked')
    rejects(lambda: p.verify(archive), 'Duplicate ZIP member rejected')
    ok(p.omitted('tests/integration/fixtures/schema.sql'), 'Fixture subtree excluded')
    ok(p.omitted('core/cli/security-audit.php'), 'Audit CLI excluded')
    ok(not p.omitted('core/cli/qa.php'), 'Read-only QA retained')
    ok(u.VERSION_RX.search(b"define('BOILERPLATE_VERSION', '2.5.0');").group(1).decode()=='2.5.0', 'Update preflight recognizes semver')
    ok(u.version_tuple('2.5.0') > u.version_tuple('2.4.4.113'), 'Version comparator advances to 2.5.0')
    rejects(lambda: p.assert_consistency({'public_html/index.php'}, set()), 'Incomplete runtime inventory rejected')
print(f'V2.5.0 Production Candidate ZIP: {checks} PASS / 0 FAIL (synthetic temporary ZIPs, no DB/HTTP/CMS writes)')
