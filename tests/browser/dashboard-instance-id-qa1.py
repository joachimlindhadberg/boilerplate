"""Browser regression for onboarding across separate sites sharing same URL and user IDs.
Renders real dashboard PHP fragment and real admin.js; no HTTP or DB writes.
"""
from pathlib import Path
from playwright.sync_api import sync_playwright
import subprocess, os, shutil
root = Path(__file__).resolve().parents[2]
fixture = root/'tests/fixtures/dashboard-welcome-browser-v2_4_4z20l.php'
js = (root/'public_html/admin/assets/js/admin.js').read_text()
css = (root/'public_html/admin/assets/css/admin.css').read_text()
id_a = 'a'*32
id_b = 'b'*32
store = {'boilerplate.admin.dashboard-welcome.v3.user-91':'dismissed',
         'boilerplate.admin.dashboard-welcome.v2.user-91':'{"dismissed":true}'}
passed = 0

def check(ok, msg):
    global passed
    assert ok, msg
    passed += 1
    print('PASS: '+msg)

def render(id):
    return subprocess.check_output(['php',str(fixture),'en','91','Test','1','1','unused','1', id], cwd=root).decode()

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True,executable_path=os.environ.get('CHROMIUM_BIN',shutil.which('chromium')),args=['--no-sandbox'])
    def visit(id):
        page = browser.new_page(viewport={'width':1280,'height':720})
        page.set_content(render(id))
        page.add_style_tag(content=css)
        page.evaluate('''(state)=>{
          const memory = new Map(Object.entries(state));
          Object.defineProperty(window,'localStorage',{configurable:true,value:{
            getItem:k=>memory.has(k)?memory.get(k):null,
            setItem:(k,v)=>memory.set(String(k),String(v)),
            removeItem:k=>memory.delete(k)
          }});
          window.__state=memory;
        }''',store)
        page.add_script_tag(content=js)
        return page
    def persist(page):
        store.update(page.evaluate('Object.fromEntries(window.__state.entries())'))
    page=visit(id_a)
    check(page.locator('[data-dashboard-welcome]').is_visible(), 'Fresh instance opens despite old v3/v2 dismissal on same URL')
    check(page.locator('[data-site-instance-id]').get_attribute('data-site-instance-id')==id_a, 'Dashboard has current installation namespace')
    page.locator('[data-dashboard-welcome-close]').click();persist(page);page.close()
    key_a='boilerplate.admin.dashboard-welcome.v4.instance-'+id_a+'.user-91'
    check(store.get(key_a)=='dismissed', 'Dismissal stored for first installation only')
    page=visit(id_a)
    check(not page.locator('[data-dashboard-welcome]').is_visible(), 'Later login to same instance stays closed')
    check(page.locator('[data-dashboard-welcome-restore]').is_visible(), 'Show welcome visible on same instance')
    page.close()
    page=visit(id_b)
    check(page.locator('[data-dashboard-welcome]').is_visible(), 'Fresh second instance on same URL/user ID opens')
    page.locator('[data-dashboard-welcome-close]').click();persist(page);page.close()
    key_b='boilerplate.admin.dashboard-welcome.v4.instance-'+id_b+'.user-91'
    check(store.get(key_b)=='dismissed', 'Second installation gets a different storage entry')
    page=visit(id_a)
    check(not page.locator('[data-dashboard-welcome]').is_visible(), 'Revisiting first instance still honors its dismissal')
    page.locator('[data-dashboard-welcome-restore]').click()
    check(page.locator('[data-dashboard-welcome]').is_visible(), 'Manual reopen works')
    page.close()
    page=visit('')
    check(not page.locator('[data-dashboard-welcome]').is_visible(), 'Legacy sites without instance ID honor v3 preference')
    page.close()
    page=visit('not-a-valid-id')
    check(not page.locator('[data-dashboard-welcome]').is_visible(), 'Invalid ID falls back to legacy safely')
    page.close()
    browser.close()
print(f'Dashboard Instance Browser: {passed} PASS / 0 FAIL')
