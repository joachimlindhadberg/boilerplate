#!/usr/bin/env python3
"""No-database regression for the successful install POST and one-request session cleanup.

Runs an isolated disposable server with only the DDL-capable install call and
preflight execution replaced in the disposable TEST copy, never in shipping files.
Checks that the real request logic renders completion without a PHP warning
once temporary install_db credentials are removed from the same session.
"""
from pathlib import Path
import os
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.parse
import urllib.request

SOURCE = Path(__file__).resolve().parents[1]
checks = 0

def check(ok, description):
    global checks
    if not ok:
        raise AssertionError('FAIL: ' + description)
    checks += 1
    print('PASS:', description)

def request(base, route, cookie=None, form=None):
    headers = {'Cookie': cookie} if cookie else {}
    data = urllib.parse.urlencode(form).encode() if form is not None else None
    req = urllib.request.Request(base + route, data=data, headers=headers)
    with urllib.request.urlopen(req, timeout=10) as result:
        return result.read().decode('utf-8'), result.headers

with tempfile.TemporaryDirectory(prefix='boilerplate-session-finish-') as td:
    tmp = Path(td)
    root = tmp / 'boilerplate'
    shutil.copytree(SOURCE, root, ignore=shutil.ignore_patterns('__pycache__', '*.zip'))
    installer = root / 'public_html/install.php'
    source = installer.read_text()
    real_install = "FirstInstall::install($root, $_SESSION['install_db'], $site);"
    preflight = "InstallerPreflight::requireReady($root, __DIR__, (string)($_SERVER['DOCUMENT_ROOT'] ?? ''));"
    check(source.count(real_install) == 1, 'One guarded installation operation found')
    check(source.count(preflight) == 2, 'Both hosting preflight gates found')
    # Deliberately NO actual database connection / schema change in this test.
    source = source.replace(real_install, "file_put_contents($root . '/.test-install-did-run', 'yes');")
    source = source.replace(preflight, '/* isolated test: hosting capability already checked */')
    installer.write_text(source)
    router = tmp / 'router.php'
    router.write_text('''<?php
$_SERVER['HTTPS'] = 'on'; $_SERVER['SERVER_PORT'] = '443';
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/__seed') {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
    if (($_GET['ready'] ?? '') === 'yes') {
        $_SESSION['install_db'] = ['host'=>'127.0.0.1','port'=>3306,'name'=>'NOT_A_REAL_DB',
          'user'=>'not_a_real_db_user','pass'=>'DO_NOT_CONNECT_TO_MYSQL'];
        $_SESSION['install_step'] = 'website';
    }
    header('Content-Type: text/plain'); echo $_SESSION['install_csrf']; return true;
}
return false;
''')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0)); port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    env = os.environ.copy()
    for name in list(env):
        if name.startswith('DB_') or name in ('APP_URL','CMS_INSTALL_KEY'):
            env.pop(name)
    proc = subprocess.Popen(['php','-d','display_errors=1','-d','display_startup_errors=1',
                             '-d','error_reporting=-1','-S',f'127.0.0.1:{port}',
                             '-t',str(root/'public_html'),str(router)],
                            cwd=tmp, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
    try:
        for _ in range(50):
            try:
                csrf, headers = request(base, '/__seed?ready=yes')
                break
            except Exception:
                time.sleep(0.1)
        else:
            raise RuntimeError('Isolated PHP server failed to start')
        cookie = headers.get('Set-Cookie','').split(';',1)[0]
        check(bool(cookie) and len(csrf) == 64, 'Test-only session seeded with valid CSRF')
        html, _ = request(base,'/install.php',cookie)
        check('Website and administrator' in html, 'Website step is accessible before installation')
        check('DO_NOT_CONNECT_TO_MYSQL' not in html, 'Private database password never appears in HTML')
        site = {'csrf':csrf, 'action':'finish','site_name':'QA Install Test',
                'site_url':f'https://127.0.0.1:{port}','first':'Test','last':'Account',
                'email':'qa-installer@example.test','password':'strong-testing-password-123456'}
        html, _ = request(base,'/install.php',cookie,site)
        check((root/'.test-install-did-run').read_text() == 'yes', 'Isolated no-DB install operation was reached')
        check('<h1 id="install-title">Installation complete</h1>' in html, 'Successful finish renders completion page')
        check('Warning' not in html and 'Notice' not in html and 'Undefined array key' not in html,
              'No undefined install_db warning after session cleanup')
        check('name="pass"' not in html and 'name="password"' not in html,
              'Completion does not render database or admin password inputs')
        check('DO_NOT_CONNECT_TO_MYSQL' not in html, 'Database secret not leaked on completion')
        check(not (root/'.env').exists(), 'Isolated test did not create a real CMS configuration')
        # Separate session with no verified DB must remain unable to finish installation.
        csrf2, headers2 = request(base,'/__seed?ready=no')
        cookie2 = headers2.get('Set-Cookie','').split(';',1)[0]
        site['csrf'] = csrf2
        html2, _ = request(base,'/install.php',cookie2,site)
        check('Test the database connection first.' in html2 and 'Installation complete' not in html2,
              'Install remains blocked without a verified database session')
    finally:
        proc.terminate()
        _out, errors = proc.communicate(timeout=5)
        stderr = errors.decode('utf-8', errors='replace')
        check('Undefined array key' not in stderr and 'PHP Warning' not in stderr and 'PHP Notice' not in stderr,
              'PHP server did not log warnings or notices during finish')
print(f'4A.6.9f2 isolated finish-session regression: {checks} PASS / 0 FAIL; no database used')
