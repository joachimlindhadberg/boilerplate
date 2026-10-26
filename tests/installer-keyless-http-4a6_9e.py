#!/usr/bin/env python3
"""Isolated PHP built-in-server smoke test. Simulated HTTPS; no database connects/writes."""
import contextlib, http.cookiejar, os, pathlib, re, shutil, socket, subprocess, tempfile, time, urllib.error, urllib.request
source = pathlib.Path(__file__).resolve().parents[1]
checks = 0

def check(cond, msg):
    global checks
    if not cond: raise AssertionError(msg)
    checks += 1
    print('PASS:', msg)

def req(opener, base, path='/install.php', data=None, headers=None):
    p=urllib.request.Request(base+path, data=data.encode() if data is not None else None, headers=headers or {})
    try:
        with opener.open(p, timeout=5) as res:
            return res.status, res.headers, res.read().decode('utf-8')
    except urllib.error.HTTPError as e:
        return e.code, e.headers, e.read().decode('utf-8')

with tempfile.TemporaryDirectory(prefix='boilerplate-keyless-http-') as temporary:
    base_path=pathlib.Path(temporary)/'boilerplate'
    shutil.copytree(source,base_path,ignore=shutil.ignore_patterns('*.zip','.DS_Store'))
    # Simulate a securely terminated HTTPS request for local-only negative UI tests.
    router=pathlib.Path(temporary)/'https-router.php'
    router.write_text("<?php $_SERVER['HTTPS']='on'; $_SERVER['SERVER_PORT']='443'; return false;\n")
    socket_obj=socket.socket(); socket_obj.bind(('127.0.0.1',0)); port=socket_obj.getsockname()[1]; socket_obj.close()
    env=os.environ.copy()
    # Do not inherit a developer's DB_* environment, which would lock the wizard.
    for key in list(env):
        if key.startswith('DB_') or key in ('CMS_INSTALL_KEY', 'APP_URL'):
            del env[key]
    proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(base_path/'public_html'),str(router)],cwd=temporary,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)
    try:
        time.sleep(.5)
        cookie= http.cookiejar.CookieJar()
        opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie),urllib.request.HTTPRedirectHandler())
        base=f'http://127.0.0.1:{port}'
        status,head,page=req(opener,base)
        check(status==200 and 'Database connection' in page and 'Step 1 of 3' in page, 'GET offers database screen without activation key')
        check('name="key"' not in page and 'Installation key' not in page, 'No activation key input or challenge')
        check('Hosting setup needs attention' in page and 'Technical details' in page, 'Failed mandatory hosting checks show actionable technical details')
        session_cookie=head.get('Set-Cookie','').split(';',1)[0]
        check(session_cookie.startswith('PHPSESSID=') and 'secure' in head.get('Set-Cookie','').lower() and 'httponly' in head.get('Set-Cookie','').lower() and 'samesite=strict' in head.get('Set-Cookie','').lower(), 'Session cookie is Secure, HttpOnly and SameSite=Strict')
        headers={'Cookie':session_cookie}
        csrf=re.search(r'name="csrf" value="([0-9a-f]{64})"',page)
        check(bool(csrf),'Session receives unpredictable CSRF token')
        form='csrf='+csrf.group(1)+'&action=finish'
        status,_,page=req(opener,base,data=form,headers=headers)
        check('Test the database connection first.' in page and status==200, 'Direct finish POST without verified DB is refused')
        status,_,page=req(opener,base,data='csrf=incorrect&action=db-test',headers=headers)
        check(status==403 and 'Your session has expired' in page, 'Bad CSRF POST denied with 403')
        status,_,page=req(opener,base,data='csrf='+csrf.group(1)+'&action=db-test&host=&name=&user=&pass=',headers=headers)
        check('empty or invalid' in page or 'Action required' in page or 'Hosting' in page or 'missing' in page.lower(), 'DB submission does not bypass mandatory preflight and validation')
        check(not (base_path/'.env').exists(),'Malicious POSTs cannot create configuration')
        check(not (base_path/'storage/install.key').exists(),'GET and POST never generate installation key')
        (base_path/'.env').write_text("DB_NAME='configured'\n")
        # Avoid auto-follow for the redirect assertion.
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self,req,fp,code,msg,headers,newurl): return None
        direct=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie),NoRedirect())
        status,headers,_=req(direct,base)
        check(status==303 and headers.get('Location')=='/admin','Existing configuration hard-locks installer to admin route')
    finally:
        proc.terminate()
        try: proc.communicate(timeout=6)
        except subprocess.TimeoutExpired: proc.kill(); proc.communicate()
print(f'4A.6.9e keyless HTTP smoke: {checks} PASS / 0 FAIL; no DB created or contacted')
