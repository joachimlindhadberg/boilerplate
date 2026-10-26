'use strict';
// Execute exactly the deployed Forms tab-controller code in a minimal isolated DOM.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const src=fs.readFileSync(path.resolve(__dirname,'../modules/forms/admin/views/partials/form-editor.php'),'utf8');
const a=src.indexOf('  // Core editor tabs only change the workspace view;');
const b=src.indexOf('  const rows = root.querySelector(',a);
assert.ok(a>=0&&b>a,'Deployed tabs code located');
const code=src.slice(a,b);
let checks=0;
function check(label,fn){fn();checks++;console.log('PASS: '+label);}
function makeEl(id,kind){const attrs={};const classes=new Set();return {
 dataset: kind==='tab'?{adminEditorTab:id}:{adminEditorPanel:id},
 hidden:id!=='fields', tabIndex:id==='fields'?0:-1, focused:false,
 classList:{toggle(k,enabled){if(enabled)classes.add(k);else classes.delete(k);},contains(k){return classes.has(k);}},
 setAttribute(k,v){attrs[k]=v;}, getAttribute(k){return attrs[k];},
 focus(){this.focused=true;}, closest(sel){return sel===`[data-admin-editor-${kind}]`?this:null},
 };}
const tabs=['fields','settings','emails'].map(i=>makeEl(i,'tab'));
const panels=['fields','settings','emails'].map(i=>makeEl(i,'panel'));
const events={};
const root={querySelectorAll(sel){return sel==='[data-admin-editor-tab]'?tabs:sel==='[data-admin-editor-panel]'?panels:[];}, addEventListener(name,fn){(events[name]||=[]).push(fn);},contains(el){return tabs.includes(el)||panels.includes(el);}};
const evaluate=new Function('root',code+'\nreturn {activateEditorTab,editorTabs,editorPanels};');
const ui=evaluate(root);
check('Three real named tabs',()=>assert.deepEqual(ui.editorTabs.map(t=>t.dataset.adminEditorTab),['fields','settings','emails']));
check('Three real corresponding panels',()=>assert.deepEqual(ui.editorPanels.map(p=>p.dataset.adminEditorPanel),['fields','settings','emails']));
check('Each supported event registered once',()=>{for(const name of ['click','keydown','invalid'])assert.equal(events[name].length,1);});
check('Click Emails selects pane',()=>{events.click[0]({target:tabs[2]});assert.equal(panels[2].hidden,false);assert.equal(panels[0].hidden,true);});
check('Selected tab gets ARIA and roving focus',()=>{assert.equal(tabs[2].getAttribute('aria-selected'),'true');assert.equal(tabs[0].getAttribute('aria-selected'),'false');assert.equal(tabs[2].tabIndex,0);assert.equal(tabs[0].tabIndex,-1);});
check('Only selected tab has active Core style',()=>assert.deepEqual(tabs.map(t=>t.classList.contains('is-active')),[false,false,true]));
check('Return to builder without clearing model',()=>{ui.activateEditorTab('fields');assert.equal(panels[0].hidden,false);});
check('Left arrow wraps around to Emails and focuses tab',()=>{let prevented=false;events.keydown[0]({target:tabs[0],key:'ArrowLeft',preventDefault(){prevented=true;}});assert.equal(panels[2].hidden,false);assert.equal(tabs[2].focused,true);assert.equal(prevented,true);});
check('Home jumps to Fields',()=>{events.keydown[0]({target:tabs[2],key:'Home',preventDefault(){}});assert.equal(panels[0].hidden,false);});
check('End jumps to Emails',()=>{events.keydown[0]({target:tabs[0],key:'End',preventDefault(){}});assert.equal(panels[2].hidden,false);});
check('ArrowRight from Emails wraps to Fields',()=>{events.keydown[0]({target:tabs[2],key:'ArrowRight',preventDefault(){}});assert.equal(panels[0].hidden,false);});
check('Unknown tab cannot hide all panels',()=>{ui.activateEditorTab('__unknown__');assert.equal(panels[0].hidden,false);});
check('Invalid field reveals its panel',()=>{events.invalid[0]({target:{closest(sel){return sel==='[data-admin-editor-panel]'?panels[1]:null;}}});assert.equal(panels[1].hidden,false);});
check('Later invalid events in same validation pass do not override first',()=>{events.invalid[0]({target:{closest(){return panels[2];}}});assert.equal(panels[1].hidden,false);});
async function finish(){
 await Promise.resolve();
 check('New validation pass opens next invalid panel',()=>{events.invalid[0]({target:{closest(){return panels[2];}}});assert.equal(panels[2].hidden,false);});
 check('No HTML injection or form submit in tab controller',()=>{assert.ok(!code.includes('innerHTML')&&!code.includes('.submit(')&&!code.includes('location.'));});
 console.log('Forms Editor Tab JS V2.4.4z20b: '+checks+' PASS / 0 FAIL');
}
finish().catch(e=>{console.error(e);process.exitCode=1;});
