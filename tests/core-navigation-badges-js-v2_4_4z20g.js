'use strict';
const fs = require('node:fs');
const assert = require('node:assert/strict');
const source = fs.readFileSync(__dirname + '/../public_html/admin/assets/js/admin.js','utf8');
const start = source.indexOf('  // Core navigation badges:');
const end = source.indexOf('  const sidebar =', start);
assert.ok(start > 0 && end > start, 'badge implementation is in Core admin.js');
const code = source.slice(start,end);
let passed = 0;
function check(cond, description) { assert.ok(cond, description); console.log('PASS: ' + description); passed++; }

class FakeElement {
  constructor(key) { this.dataset={adminNavKey:key,adminNavBadgeDescription:key==='modules'?'available updates':'unread submissions'}; this.badge=null; }
  querySelector(selector) { return selector==='[data-admin-nav-count]' ? this.badge : null; }
  appendChild(child) { child.parent=this; this.badge=child; return child; }
}
const links=[new FakeElement('modules'),new FakeElement('module:forms:submissions')];
const document={
  visibilityState:'visible',
  listeners:{},
  querySelectorAll(sel){ assert.equal(sel,'.admin-nav a[data-admin-nav-key]'); return links; },
  createElement(tag){ assert.equal(tag,'span');return { className:'',dataset:{},textContent:'',attributes:{},setAttribute(k,v){this.attributes[k]=v;},remove(){this.parent.badge=null;} }; },
  addEventListener(name,fn){this.listeners[name]=fn;},
};
let timer;
let fetchCalls=0;
let next={badges:{modules:2,'module:forms:submissions':3}};
const window={
  setInterval(fn,ms){ assert.equal(ms,90000);timer=fn; },
  async fetch(url,opts){
    fetchCalls++;
    assert.equal(url,'/admin/index.php?mode=nav-badges');
    assert.equal(opts.method,'GET');
    assert.equal(opts.credentials,'same-origin');
    assert.equal(opts.cache,'no-store');
    return {ok:true,async json(){return next;}};
  }
};
new Function('document','window',code)(document,window);
check(typeof timer==='function' && typeof document.listeners.visibilitychange==='function','Core registers periodic and visible-tab refresh');
const tick=async()=>{timer();await new Promise(resolve=>setImmediate(resolve));};
(async()=>{
  await tick();
  check(fetchCalls===1,'One authenticated read-only request per refresh');
  check(links[0].badge.textContent==='2' && links[1].badge.textContent==='3','Two independent top-level badges populated');
  check(links[1].badge.attributes['aria-label']==='3 unread submissions','Badge exposes descriptive accessible label');
  next={badges:{'module:forms:submissions':102}};
  await tick();
  check(links[0].badge===null,'Absent Modules update is removed');
  check(links[1].badge.textContent==='99+' && links[1].badge.attributes['aria-label']==='102 unread submissions','Large values clamp visually, full count in accessible label');
  next={badges:{'module:forms:submissions':0,modules:-3}};
  await tick();
  check(links.every(x=>x.badge===null),'Zero and negative counts never render');
  next={badges:{'module:forms:submissions':'<svg onload=alert(1)>'}};
  await tick();
  check(links[1].badge===null,'Non-numeric/malicious provider result is ignored');
  next={badges:{modules:1}};
  document.visibilityState='hidden';
  const prior=fetchCalls;
  await tick();
  check(fetchCalls===prior,'Hidden tab does not poll');
  document.visibilityState='visible';
  document.listeners.visibilitychange();
  await new Promise(resolve=>setImmediate(resolve));
  check(fetchCalls===prior+1 && links[0].badge.textContent==='1','Returning to visible tab immediately refreshes badges');
  next={badges:{}};
  await tick();
  check(links.every(x=>x.badge===null),'Read-all and no-updates snapshot clears the UI');
  console.log(`Core Navigation Badges JS z20g: ${passed} PASS / 0 FAIL`);
})().catch(err=>{console.error('FAIL:',err);process.exitCode=1;});
