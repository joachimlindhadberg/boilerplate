'use strict';
// Headless test of the exact deployed Core delegated Media branch.
// Does not require a browser, external packages, filesystems or user data.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const js = fs.readFileSync(path.resolve(__dirname, '../public_html/admin/assets/js/admin.js'), 'utf8');
const startToken = "if (dialog.id === 'media-delete-dialog') {";
const start = js.indexOf(startToken);
const end = js.indexOf("    if (dialog.id === 'nav-list-delete-dialog')", start);
assert.ok(start >= 0 && end > start, 'Live Core handler contains Media branch');
const code = js.slice(start, end);
const execute = new Function('dialog','mediaDetailDialog', code + '\nreturn true;');
let checks = 0;
function check(label, cb) { cb(); checks++; console.log('PASS: ' + label); }
function fixture(initialId, initialName) {
  const idInput = {value: ''};
  const name = {textContent: '', set innerHTML(_) { throw new Error('Unsafe injection'); }};
  const currentId = {value: initialId};
  const currentName = {textContent: initialName};
  const dialog = {
    id: 'media-delete-dialog',
    querySelector(selector) {
      return {'[data-media-delete-id]': idInput, '[data-media-delete-name]': name}[selector] || null;
    },
  };
  const mediaDetailDialog = {
    querySelector(selector) {
      return {'[data-media-detail-id]': currentId, '[data-media-detail-heading]': currentName}[selector] || null;
    },
  };
  return {dialog, mediaDetailDialog, idInput, name, currentId, currentName};
}
const f = fixture('42', '<img src=x onerror=alert(1)>');
check('Valid numeric media ID is copied into the Core POST field', () => {
  assert.equal(execute(f.dialog, f.mediaDetailDialog), true);
  assert.equal(f.idInput.value, '42');
});
check('Untrusted media filename becomes inert text, never HTML', () => {
  assert.equal(f.name.textContent, '<img src=x onerror=alert(1)>');
});
check('Changing selection updates BOTH POST ID and selected name', () => {
  f.currentId.value = '84'; f.currentName.textContent = 'Video test';
  assert.equal(execute(f.dialog, f.mediaDetailDialog), true);
  assert.equal(f.idInput.value, '84'); assert.equal(f.name.textContent, 'Video test');
});
for (const invalidId of ['', '0', '-1', '12x', '1&2', ' 23', '<script>']) {
  check('Reject invalid media ID: ' + JSON.stringify(invalidId), () => {
    f.currentId.value = invalidId; f.idInput.value = '';
    assert.equal(execute(f.dialog, f.mediaDetailDialog), undefined);
    assert.equal(f.idInput.value, '');
  });
}
check('Missing selected detail dialog cannot mutate delete target', () => {
  f.idInput.value='';
  assert.equal(execute(f.dialog, null), undefined); assert.equal(f.idInput.value, '');
});
check('Missing dynamic Core subject aborts the deletion', () => {
  f.currentId.value = '51';
  assert.equal(execute({id:'media-delete-dialog',querySelector:s=>s==='[data-media-delete-id]' ? f.idInput : null}, f.mediaDetailDialog), undefined);
});
check('Exact app handler never injects dynamic HTML', () => {
  assert.ok(code.includes('name.textContent'));
  assert.ok(!code.includes('innerHTML'));
});
console.log(`Media Delete modal JS V2.4.4z16: ${checks} PASS / 0 FAIL`);
