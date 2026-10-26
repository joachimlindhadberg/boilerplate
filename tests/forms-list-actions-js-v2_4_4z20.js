'use strict';
/** z20 optional browser-logic regression; run with node, no DOM packages needed. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'modules/forms/admin/views/partials/submissions-list.php'), 'utf8');
const all = [...source.matchAll(/<script>([\s\S]*?)<\/script>/g)];
assert.equal(all.length, 1, 'Only one delegated Forms list event script');
let click;
let opened = 0;
let deleteId = '';
let deleteName = '';
let returnUrl = '';
let csrfSubmissions = 0;
const dialog = {
  querySelector(selector) {
    if (selector === '[data-forms-delete-id]') return {set value(v) { deleteId = v; }};
    if (selector === '[data-forms-delete-name]') return {set textContent(v) { deleteName = v; }};
    if (selector === '[name=return_url]') return {set value(v) { returnUrl = v; }};
    throw new Error('Unexpected dialog selector: ' + selector);
  },
  showModal() { opened++; },
};
const document = {
  querySelector(selector) { assert.equal(selector,'[data-forms-delete-dialog]'); return dialog; },
  addEventListener(type, fn) { assert.equal(type,'click'); click=fn; },
};
vm.runInNewContext(all[0][1], {document, location:{pathname:'/admin/forms/submissions',search:'?form_id=7&status=new&q=x'}}, {timeout:2000});
let pass=0;
function check(cond, label) { assert.ok(cond,label); console.log('PASS: '+label); pass++; }
check(typeof click === 'function','Delegated click listener installed once');
const rows = [0,0].map((_,i)=>{
  let count = 0;
  const form = {requestSubmit(){count++;}};
  const td = {querySelector(q){assert.equal(q,'form[data-forms-status-post]');return form;}};
  const button = {closest(q){if(q==='td')return td;throw new Error(q);}};
  return {button, get calls(){return count;}};
});
click({target:{closest(q){return q==='[data-forms-mark-submission]'?rows[0].button:null;}}});
check(rows[0].calls===1 && rows[1].calls===0,'First state button submits only first row');
click({target:{closest(q){return q==='[data-forms-mark-submission]'?rows[1].button:null;}}});
check(rows[0].calls===1 && rows[1].calls===1,'Second status button submits its own row');
check(opened===0,'Status updates do not open delete modal');
click({target:{closest(q){if(q==='[data-forms-mark-submission]')return {closest(){return {querySelector(){return null;}};}};return null;}}});
check(rows[0].calls===1 && rows[1].calls===1,'Missing status form cannot trigger unrelated submission');
const deleteButton={dataset:{submissionId:'43',submissionName:'A <script>example</script>'}};
click({target:{closest(q){if(q==='[data-forms-mark-submission]')return null;if(q==='[data-forms-delete-submission]')return deleteButton;return null;}}});
check(opened===1 && deleteId==='43','Delete action fills current ID and opens original Core modal');
check(deleteName==='A <script>example</script>','Delete label is assigned as inert textContent');
check(returnUrl==='/admin/forms/submissions?form_id=7&status=new&q=x','Delete preserves current filtered return URL');
click({target:{closest(q){if(q==='[data-forms-mark-submission]')return null;if(q==='[data-forms-delete-submission]')return {dataset:{submissionId:'44',submissionName:'Second'}};return null;}}});
check(opened===2 && deleteId==='44' && deleteName==='Second','Second delete target replaces previous ID and name');
check(rows[0].calls===1 && rows[1].calls===1,'Delete actions never submit mark-read forms');
console.log(`Forms List Actions JS z20: ${pass} PASS / 0 FAIL`);
