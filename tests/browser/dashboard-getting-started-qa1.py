"""Chromium with real dashboard PHP fragment/admin.js and isolated storage fixture (no DB/network)."""
from pathlib import Path
from playwright.sync_api import sync_playwright
import subprocess, json, os, shutil
root = Path(__file__).resolve().parents[2]
fixture = root/'tests/fixtures/dashboard-welcome-browser-v2_4_4z20l.php'
css = (root/'public_html/admin/assets/css/admin.css').read_text()
js = (root/'public_html/admin/assets/js/admin.js').read_text()
def fragment(user=91, locale='en', content=True, settings=True):
    return subprocess.check_output(['php',str(fixture),locale,str(user),'New User','1' if content else '0','1','unused','1' if settings else '0'],cwd=root).decode()
checks=[0]
def check(ok,detail):
    assert ok,detail
    checks[0]+=1;print('PASS: '+detail)
# The sandbox browser blocks file:// and HTTP navigation. Feed the real PHP-rendered HTML
# via set_content and use a controlled localStorage stand-in. Each visit is a fresh page.
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path=os.environ.get('CHROMIUM_BIN',shutil.which('chromium')),args=['--no-sandbox'])
    store={}
    errors=[]
    def visit(user, width=1280):
        page=browser.new_page(viewport={'width':width,'height':720 if width==1280 else 640})
        page.on('pageerror',lambda error: errors.append(str(error)))
        page.set_content(fragment(user))
        page.add_style_tag(content=css)
        page.evaluate('''(state)=>{
          const cache=new Map(Object.entries(state));
          Object.defineProperty(window,'localStorage',{configurable:true,value:{
            getItem:(key)=>cache.has(key)?cache.get(key):null,
            setItem:(key,value)=>cache.set(String(key),String(value)),
            removeItem:(key)=>cache.delete(key),
            clear:()=>cache.clear(),
            key:(index)=>Array.from(cache.keys())[index]??null,
            get length(){return cache.size}
          }});
          window.__testStorage=cache;
        }''',store)
        page.add_script_tag(content=js)
        return page
    def save(page):
        store.update(page.evaluate('Object.fromEntries(window.__testStorage.entries())'))
    page=visit(91);welcome=page.locator('[data-dashboard-welcome]');restore=page.locator('[data-dashboard-welcome-restore]')
    check(welcome.is_visible(),'New user sees onboarding on first dashboard visit')
    check('Welcome to Boilerplate CMS!' in welcome.inner_text(),'English onboarding title displayed')
    check('Your website is set up' in welcome.inner_text(),'Intro description displayed')
    check(welcome.locator('a').count()==3,'Administrator has three onboarding shortcuts')
    check(welcome.locator('a').nth(0).get_attribute('href')=='/admin/sider?mode=edit&id=9','Homepage points to actual edit URL')
    check(welcome.locator('a').nth(1).get_attribute('href')=='/admin/indstillinger','Settings points to admin settings')
    check(welcome.locator('a').nth(2).get_attribute('href')=='/','Website points to homepage')
    welcome.locator('[data-dashboard-welcome-close]').click()
    check(not welcome.is_visible() and restore.is_visible(),'Dismiss hides onboarding and reveals Show welcome')
    save(page)
    check(store.get('boilerplate.admin.dashboard-welcome.v3.user-91')=='dismissed','Dismissal persisted per user')
    page.close()
    page=visit(91);welcome=page.locator('[data-dashboard-welcome]');restore=page.locator('[data-dashboard-welcome-restore]')
    check(not welcome.is_visible() and restore.is_visible(),'Later dashboard visit does not reopen dismissed onboarding')
    restore.click()
    check(welcome.is_visible() and not restore.is_visible(),'Manual Show welcome restores onboarding')
    check(page.locator('[data-dashboard-welcome-close]').evaluate('(el)=>el===document.activeElement'),'Restore sets focus to close button')
    save(page);page.close()
    page=visit(91);welcome=page.locator('[data-dashboard-welcome]')
    check(welcome.is_visible(),'Manually reopened onboarding stays visible until dismissed')
    welcome.locator('[data-dashboard-welcome-close]').click();save(page);page.close()
    page=visit(92);check(page.locator('[data-dashboard-welcome]').is_visible(),'Separate user has independent onboarding state');page.close()
    store['boilerplate.admin.dashboard-welcome.v2.user-93']='{"dismissed":true,"lastShownAt":123,"loginMarker":"abc"}'
    page=visit(93)
    check(not page.locator('[data-dashboard-welcome]').is_visible(),'Old v2 setting stops surprising reappearance')
    save(page)
    check(store.get('boilerplate.admin.dashboard-welcome.v3.user-93')=='dismissed','Old setting migrated to v3')
    page.close()
    for width in (375,768):
        page=visit(94,width)
        check(page.locator('[data-dashboard-welcome]').is_visible(),f'{width}px onboarding visible')
        check(page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2'),f'{width}px no horizontal overflow')
        if width==375:
            page.screenshot(path=str(root.parents[1]/'getting-started-mobile-preview.png'),full_page=True)
        page.close()
    h=fragment(user=95,content=False,settings=False)
    check('Edit homepage' not in h and 'Site settings' not in h and 'View website' in h,'Unauthorized links absent from PHP rendering')
    check(not errors,f'No JS exceptions: {errors}')
    browser.close()
print(f'Getting Started Chromium: {checks[0]} PASS / 0 FAIL (synthetic storage, no live database)')
