#!/usr/bin/env python3
"""No-DB HTTP checks of silent successful preflight and actionable failure UI.

The test overrides ONLY the two computed display-state variables in a disposable
copy, never the distributed source or the actual server-side requireReady guard.
"""
from pathlib import Path
import os, shutil, socket, subprocess, tempfile, time, urllib.request

SOURCE = Path(__file__).resolve().parents[1]
ANCHOR = '$hostingReady = InstallerPreflight::ready($hostingChecks);'
COUNT = 0

def check(ok, label):
    global COUNT
    if not ok:
        raise AssertionError(label)
    COUNT += 1
    print('PASS:', label)

def render(tmp, succeed):
    root = Path(tmp) / ('pass' if succeed else 'fail') / 'boilerplate'
    shutil.copytree(SOURCE, root, ignore=shutil.ignore_patterns('__pycache__','*.zip'))
    file = root / 'public_html/install.php'
    content = file.read_text()
    check(content.count(ANCHOR) == 1, 'Found unique preflight display-state anchor')
    fixture = "\n$hostingChecks = [['id'=>'synthetic','label'=>'Synthetic test check', 'ok'=>%s, 'detail'=>'Repair required for this isolated test.', 'required'=>true]];\n$hostingReady = %s; // UI fixture only; not in shipping code\n" % ('true' if succeed else 'false', 'true' if succeed else 'false')
    file.write_text(content.replace(ANCHOR,ANCHOR+fixture))
    router = root.parent / 'https-router.php'
    router.write_text("<?php $_SERVER['HTTPS']='on'; $_SERVER['SERVER_PORT']='443'; return false;\n")
    with socket.socket() as s:
        s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
    env=os.environ.copy()
    for key in list(env):
        if key.startswith('DB_') or key in ('APP_URL','CMS_INSTALL_KEY'):
            del env[key]
    proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(root/'public_html'),str(router)],cwd=root.parent,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)
    try:
        deadline=time.monotonic()+5
        while True:
            try:
                with urllib.request.urlopen(f'http://127.0.0.1:{port}/install.php',timeout=2) as req:
                    return req.read().decode('utf-8')
            except Exception:
                if time.monotonic()>deadline:
                    raise
                time.sleep(0.1)
    finally:
        proc.terminate()
        proc.communicate(timeout=5)

with tempfile.TemporaryDirectory(prefix='boilerplate-quiet-hosting-') as tmp:
    success=render(tmp,True)
    check('Database connection' in success and 'install-db-form' in success, 'Database form still renders after successful checks')
    check('Hosting setup needs attention' not in success, 'Successful hosting checks show no banner')
    check('Technical details' not in success and 'install__preflight' not in success, 'Successful hosting checks show no expandable status')
    check('Hosting requirements checked' not in success, 'Legacy success label is absent')
    check('form="install-db-form" disabled' not in success, 'Passing preflight enables Test connection')
    failing=render(tmp,False)
    check('Hosting setup needs attention' in failing, 'Mandatory failure produces clear error banner')
    check('<summary id="install-preflight-title">Technical details</summary>' in failing, 'Failure provides expandable technical details')
    check('<details class="install__preflight">' in failing and '<details class="install__preflight" open>' not in failing, 'Technical detail panel is closed by default')
    check('Synthetic test check' in failing and 'Repair required for this isolated test.' in failing, 'Only failing requirements are explained')
    check('form="install-db-form" disabled aria-disabled="true"' in failing, 'Mandatory preflight failure disables Test connection')
print('4A.6.9f1 quiet hosting UI QA:',COUNT,'PASS / 0 FAIL; no MySQL used')
