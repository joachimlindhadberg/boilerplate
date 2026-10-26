'use strict';
// Exercise the exact delegated Core Redirects branch with synthetic row data.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const js = fs.readFileSync(path.resolve(__dirname, '../public_html/admin/assets/js/admin.js'), 'utf8');
const start = js.indexOf("if (dialog.id === 'redirect-list-delete-dialog') {");
const end = js.indexOf("    if (dialog.id === 'nav-list-delete-dialog')", start);
assert.ok(start !== -1 && end > start, 'Core delegated Redirects branch exists');
const code = js.slice(start, end);
const execute = new Function('dialog', 'button', code + '\nreturn true;');
let checks = 0;
function check(label, fn) { fn(); checks++; console.log('PASS: ' + label); }
const input = {value: ''};
const source = {textContent: '', set innerHTML(_) { throw new Error('Untrusted source injected as HTML'); }};
const dialog = {id: 'redirect-list-delete-dialog', querySelector: (q) => ({'[data-redirect-list-delete-id]': input, '[data-redirect-list-delete-source]': source})[q] || null};
const button = {dataset:{redirectDeleteId: '21', redirectDeleteSource: '/from'}};
check('First selected row sets original POST ID', () => { assert.equal(execute(dialog, button), true); assert.equal(input.value, '21'); });
check('First selected row sets the path as text', () => { assert.equal(source.textContent, '/from'); });
check('Switching to a second row refreshes BOTH ID and path', () => {button.dataset.redirectDeleteId='42'; button.dataset.redirectDeleteSource='/other'; assert.equal(execute(dialog, button), true); assert.equal(input.value,'42'); assert.equal(source.textContent,'/other');});
check('Malicious URL path is inert text', () => { button.dataset.redirectDeleteSource='/<img src=x onerror=alert(1)>'; assert.equal(execute(dialog,button),true); assert.equal(source.textContent,button.dataset.redirectDeleteSource); });
for (const invalid of ['', '0', '-1', '2x', ' 3', '1&2', '1.3', '<svg>']) {
  check('Reject invalid id ' + JSON.stringify(invalid), () => {
    input.value=''; source.textContent=''; button.dataset.redirectDeleteId=invalid;
    assert.equal(execute(dialog,button),undefined); assert.equal(input.value,''); assert.equal(source.textContent,'');
  });
}
check('Missing Core ID field cannot submit deletion', () => { button.dataset.redirectDeleteId='7'; assert.equal(execute({id:dialog.id, querySelector:()=>null},button),undefined); });
check('No direct innerHTML writes in handler', () => {assert.ok(code.includes('source.textContent')); assert.ok(!code.includes('innerHTML')); });
console.log(`Redirects Delete JS V2.4.4z19: ${checks} PASS / 0 FAIL`);
