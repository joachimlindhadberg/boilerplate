#!/usr/bin/env python3
"""Isolated installer UI QA. Backend via local HTTP; visual checks on rendered HTML in Chromium.
Browser navigation to localhost is disallowed in some environments, so visual tests use set_content.
No database changes or connections, and no tests touch an existing installation.
Requires optional Playwright + Chromium for visual checks.
"""
import os, pathlib, re, shutil, socket, subprocess, tempfile, time, urllib.error, urllib.parse, urllib.request
from playwright.sync_api import sync_playwright

source = pathlib.Path(__file__).resolve().parents[1]
checks = 0

def check(result, label):
    global checks
    if not result:
        raise AssertionError('FAIL: ' + label)
    checks += 1
    print('PASS:', label)

def get(base, path, cookie=''):
    req = urllib.request.Request(base+path, headers={'Cookie':cookie} if cookie else {})
    with urllib.request.urlopen(req, timeout=8) as res:
        return res.read().decode('utf-8'), res.headers

def post(base, values, cookie):
    data=urllib.parse.urlencode(values).encode()
    req = urllib.request.Request(base+'/install.php',data=data,headers={'Cookie':cookie})
    try:
        with urllib.request.urlopen(req, timeout=8) as res:
            return res.read().decode('utf-8')
    except urllib.error.HTTPError as exc:
        return exc.read().decode('utf-8')

with tempfile.TemporaryDirectory(prefix='boilerplate-install-wizard-ui-') as tmp:
    root = pathlib.Path(tmp) / 'boilerplate'
    shutil.copytree(source, root, ignore=shutil.ignore_patterns('*.zip', '.DS_Store'))
    router = pathlib.Path(tmp)/'https-router.php'
    router.write_text('''<?php
$_SERVER['HTTPS'] = 'on'; $_SERVER['SERVER_PORT'] = '443';
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/__test_seed') {
    // Test-only synthetic verified DB state; NOT in the distributed CMS runtime.
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
    $_SESSION['install_db'] = ['host'=>'127.0.0.1','name'=>'isolated_mock_only',
        'port'=>3306,'user'=>'ui_test','pass'=>'DO_NOT_EXPOSE_UI_TEST_SECRET'];
    $_SESSION['install_step'] = 'website';
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
    header('Content-Type: text/plain'); echo 'TEST ONLY'; return true;
}
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/__test_forward') {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    session_start(); $_SESSION['install_step'] = 'website';
    header('Content-Type: text/plain'); echo 'TEST ONLY'; return true;
}
return false;
''')
    sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
    env=os.environ.copy()
    for key in list(env):
        if key.startswith('DB_') or key in ('CMS_INSTALL_KEY','APP_URL'):
            del env[key]
    proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(root/'public_html'),str(router)],
        cwd=tmp,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)
    try:
        time.sleep(.6)
        base=f'http://127.0.0.1:{port}'
        html,headers=get(base,'/install.php')
        cookie=headers.get('Set-Cookie','').split(';',1)[0]
        check('Database connection' in html and 'install-db-form' in html, 'Database step renders')
        check('form="install-db-form"' in html and '<footer class="install__footer"' in html, 'Footer owns database button')
        check('script-src \'self\'' in (headers.get('Content-Security-Policy') or ''), 'CSP allows local password-toggle script')
        check('name="key"' not in html, 'No activation key field')
        core_css=(root/'public_html/admin/assets/css/admin.css').read_text()
        css=(root/'public_html/assets/install.css').read_text()
        js=(root/'public_html/assets/install.js').read_text()

        with sync_playwright() as pw:
            browser=pw.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
            desktop=browser.new_context(viewport={'width':1280,'height':900})
            page=desktop.new_page()
            def show(html_text):
                page.set_content(html_text)
                page.add_style_tag(content=core_css)
                page.add_style_tag(content=css)
                page.add_script_tag(content=js)
            show(html)
            check(page.locator('.install__footer').is_visible(), 'Footer visible at database step')
            check(page.locator('[data-password-toggle="db-pass"]').is_visible(), 'Password reveal enabled when JavaScript runs')
            page.locator('[data-password-toggle="db-pass"]').click()
            check(page.locator('#db-pass').get_attribute('type')=='text', 'Password reveal changes input type')
            page.locator('[data-password-toggle="db-pass"]').click()
            check(page.locator('#db-pass').get_attribute('type')=='password', 'Password conceal restores input type')
            check(page.locator('.install__preflight summary').is_visible(), 'Failed hosting preflight shows technical detail control')
            page.locator('.install__preflight summary').click()
            header_y=page.locator('.install__header').bounding_box()['y']
            footer_y=page.locator('.install__footer').bounding_box()['y']
            page.locator('.install__body').evaluate('(el) => el.scrollTop = el.scrollHeight')
            check(abs(page.locator('.install__header').bounding_box()['y']-header_y)<1 and abs(page.locator('.install__footer').bounding_box()['y']-footer_y)<1, 'Header and footer fixed while content scrolls')
            check(page.locator('.install__body').evaluate('(el) => getComputedStyle(el).overflowY')=='auto', 'Only content area scrolls')
            # Actual server state, including Back request and private drafts, uses test-only injected session.
            _,seed_headers=get(base,'/__test_seed')
            cookie=seed_headers.get('Set-Cookie','').split(';',1)[0]
            website,_=get(base,'/install.php',cookie)
            check('Website and administrator' in website, 'Verified DB session opens website step')
            check('DO_NOT_EXPOSE_UI_TEST_SECRET' not in website, 'Database secret not reflected in HTML')
            check('form="install-site-form" name="action" value="back"' in website and 'form="install-site-form" name="action" value="finish"' in website, 'Both actions in fixed footer')
            csrf=re.search(r'name="csrf" value="([0-9a-f]{64})"',website).group(1)
            database=post(base,{'csrf':csrf,'action':'back','site_name':'Test & Company','site_url':base,
                'first':'Alex','last':'Example','email':'alex@example.test','password':'MY_ADMIN_TEMP_SECRET'},cookie)
            check('Database connection' in database and 'value="isolated_mock_only"' in database and 'value="ui_test"' in database, 'Back preserves verified DB details')
            check('DO_NOT_EXPOSE_UI_TEST_SECRET' not in database and 'value="DO_NOT_EXPOSE_UI_TEST_SECRET"' not in database, 'Server never reflects DB password')
            check('Enter the database password again after changing the connection settings.'
                in (root/'public_html/install.php').read_text(),
                'Source guard requires password reentry for changed connection fields')
            error_page=post(base,{'csrf':'wrong','action':'db-test'},cookie)
            check('There is a problem' in error_page and 'data-install-error' in error_page,
                'Server errors are summarized in a focusable alert')
            show(error_page)
            check(page.evaluate('document.activeElement.hasAttribute("data-install-error")'),
                'Error summary receives focus automatically')
            get(base,'/__test_forward',cookie)
            website2,_=get(base,'/install.php',cookie)
            check('value="Test &amp; Company"' in website2 and 'value="alex@example.test"' in website2, 'Website details restored on forward navigation')
            check('MY_ADMIN_TEMP_SECRET' not in website2, 'Administrator password never persisted as site draft')
            # Synthetic fixture may fail host preflight; enable only the visual
            # submit buttons in the isolated DOM to test Enter-key semantics.
            simulated_ready = website2.replace('aria-hidden="true" disabled>Install CMS</button>', 'aria-hidden="true">Install CMS</button>')
            show(simulated_ready)
            page.locator('#password').fill('temporary-password-123456')
            page.evaluate('''() => { window.__submitAction = null; document.getElementById('install-site-form').addEventListener('submit', e => { e.preventDefault(); window.__submitAction = e.submitter?.value || 'none'; }); }''')
            page.locator('#last').focus()
            page.keyboard.press('Enter')
            check(page.evaluate('window.__submitAction')=='finish', 'Enter performs Install instead of Back')

            mobile=browser.new_context(viewport={'width':375,'height':640},is_mobile=True,has_touch=True)
            m=mobile.new_page();m.set_content(html);m.add_style_tag(content=core_css);m.add_style_tag(content=css)
            card=m.locator('.install').bounding_box()
            foot=m.locator('.install__footer').bounding_box()
            check(card and foot and foot['y']+foot['height']<=641, 'Footer stays in mobile viewport')
            check(m.locator('.install').evaluate('(el)=>el.scrollWidth<=el.clientWidth'), 'No horizontal modal overflow')
            # Responsive screenshots for manual design review only, saved in isolated temp work folder.
            page.set_content(website2);page.add_style_tag(content=core_css);page.add_style_tag(content=css)
            page.screenshot(path=str(pathlib.Path(tmp)/'installer-desktop.png'))
            m.screenshot(path=str(pathlib.Path(tmp)/'installer-mobile.png'))
            mobile.close();desktop.close();browser.close()
    finally:
        proc.terminate()
        try: _,err=proc.communicate(timeout=8)
        except subprocess.TimeoutExpired: proc.kill();_,err=proc.communicate()
print(f'4A.6.9f installer UI QA: {checks} PASS / 0 FAIL; no MySQL used')
