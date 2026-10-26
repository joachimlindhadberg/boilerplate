from pathlib import Path
from playwright.sync_api import sync_playwright
import os
import shutil
import subprocess
project=Path(__file__).resolve().parents[2]
html=subprocess.check_output([os.environ.get("PHP_BIN", "php"), "tests/fixtures/dashboard-cards-rc5b-browser.php"], cwd=project).decode("utf-8")
js=(project/'public_html/admin/assets/js/admin.js').read_text()
css=(project/'public_html/admin/assets/css/admin.css').read_text()
with sync_playwright() as p:
  browser=p.chromium.launch(headless=True,executable_path=os.environ.get('CHROMIUM_BIN', shutil.which('chromium') or shutil.which('google-chrome')),args=['--no-sandbox'])
  total=0
  def check(value,label):
    global total
    assert value,label
    total+=1;print('PASS: '+label)
  for width in (320,390,768,1280):
    page=browser.new_page(viewport={'width':width,'height':800})
    errors=[];page.on('pageerror',lambda err:errors.append(str(err)))
    # Render actual PHP template output. Inject actual compiled CSS/JS and a synthetic
    # AJAX transport; no DB/server/filesystem operations, no external network.
    page.route('**/*',lambda route:route.abort())
    page.set_content(html,wait_until='domcontentloaded')
    page.add_style_tag(content=css)
    page.evaluate('''() => {
      window.__posted=[]; window.__failSave=false;
      window.fetch = async (endpoint, request) => {
        window.__posted.push({endpoint,body:request.body,method:request.method});
        if(window.__failSave) return new Response(JSON.stringify({ok:false}),{status:503,headers:{'Content-Type':'application/json'}});
        const args=new URLSearchParams(request.body);
        const keys=args.getAll('cards[]');
        if(keys.length!==4||new Set(keys).size!==4||args.get('csrf_token')!=='fixture-token-no-real-session')
          return new Response(JSON.stringify({ok:false}),{status:422,headers:{'Content-Type':'application/json'}});
        const titles={published:'Publicerede sider',drafts:'Kladder',scheduled:'Planlagt',media:'Medier',pages_total:'Alle sider',forms:'Formularer',submissions:'Indsendelser',galleries:'Gallerier'};
        const urls={published:'/admin/sider',drafts:'/admin/sider',scheduled:'/admin/scheduled-content',media:'/admin/medier',pages_total:'/admin/sider',forms:'/admin/forms',submissions:'/admin/forms/submissions',galleries:'/admin/gallerier'};
        return new Response(JSON.stringify({ok:true,cards:keys.map((key,i)=>({key,label:titles[key],count:String((i+1)*100),url:urls[key]}))}),{status:200,headers:{'Content-Type':'application/json'}});
      }
    }''')
    page.add_script_tag(content=js)
    check(page.locator('[data-dashboard-card]').count()==4,f'{width}px: four cards')
    check(page.locator('[data-dashboard-cards-toggle]').count()==0,f'{width}px: no large customize action')
    check(not page.locator('[data-dashboard-cards-notice]').is_visible(),f'{width}px: no error notice before save')
    check(not page.locator('[data-dashboard-card-menu]').first.is_visible(),f'{width}px: per-card menu initially hidden')
    check(page.locator('[data-dashboard-welcome-restore]').count()==1,f'{width}px: welcome restore remains')
    check(page.locator('body').evaluate('(el)=>el.scrollWidth<=document.documentElement.clientWidth+2'),f'{width}px: no viewport overflow')
    if width == 320:
      page.locator('[data-dashboard-card-trigger]').first.click()
      check(page.locator('[data-dashboard-card-menu]').first.is_visible(),'Menu opens on first card')
      check(page.locator('[data-dashboard-card-trigger]').first.get_attribute('aria-expanded')=='true','Expanded state announced')
      page.keyboard.press('Escape')
      check(not page.locator('[data-dashboard-card-menu]').first.is_visible(),'Escape closes menu')
      check(page.locator('[data-dashboard-card-trigger]').first.evaluate('(node)=>node===document.activeElement'),'Escape restores focus')
      page.locator('[data-dashboard-card-trigger]').first.click()
      page.locator('[data-dashboard-card]').first.locator('[data-dashboard-card-choice="galleries"]').click()
      page.wait_for_function("document.querySelector('[data-dashboard-card]').dataset.metric==='galleries'")
      check(page.locator('[data-dashboard-card]').first.get_attribute('data-metric')=='galleries','First card updates live')
      check(page.locator('[data-dashboard-card-label]').first.inner_text()=='Gallerier','Label updated live')
      check(page.locator('[data-dashboard-card-count]').first.inner_text()=='100','Number updated live')
      check(page.evaluate('window.__posted.length')==1 and 'cards%5B%5D' in page.evaluate('window.__posted[0].body'),'One AJAX POST with full selection')
      check(page.locator('[data-dashboard-card]').first.locator('[data-dashboard-card-link]').get_attribute('href')=='/admin/gallerier','Link updated live')
      page.locator('[data-dashboard-card-trigger]').nth(1).click()
      page.locator('[data-dashboard-card]').nth(1).locator('[data-dashboard-card-choice="galleries"]').click()
      page.wait_for_function("document.querySelectorAll('[data-dashboard-card]')[1].dataset.metric==='galleries'")
      check([el.get_attribute('data-metric') for el in page.locator('[data-dashboard-card]').all()][:2] == ['drafts','galleries'], 'Selecting occupied metric swaps two cards')
      page.evaluate('window.__failSave=true')
      page.locator('[data-dashboard-card-trigger]').first.click()
      page.locator('[data-dashboard-card]').first.locator('[data-dashboard-card-choice="forms"]').click()
      page.wait_for_function("!document.querySelector('[data-dashboard-cards-notice]').hidden")
      check(page.locator('[data-dashboard-card]').first.get_attribute('data-metric')=='drafts','Failed save retains old metric')
      check(page.locator('[data-dashboard-cards-notice]').is_visible(),'Failed save shows error notice')
      check(page.locator('[data-dashboard-card-trigger]').first.is_enabled(),'Failed save re-enables trigger')
    check(not errors,f'{width}px: no JavaScript exceptions: {errors}')
    page.close()
  browser.close()
  print(f'Chromium synthetic RC5b: {total} PASS / 0 FAIL (simulated API, no live MySQL)')
