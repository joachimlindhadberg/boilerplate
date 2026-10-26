'use strict';
/** z20a: Core delegated Forms list Delete action, no DOM packages needed. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public_html/admin/assets/js/admin.js'),'utf8');
const start = source.indexOf('  // Delegated to survive Core live-search table replacement.');
const end = source.indexOf('  const moduleDeactivateDialog', start);
assert.ok(start > 0 && end > start, 'Locate unchanged Core event listener');
const snippet = source.slice(start,end);
let callback, opens=0, selectedId='', selectedName='';
let hasIdField=true, hasNameField=true;
const dialog = {
  id: 'forms-list-delete-dialog',
  querySelector(selector) {
    if (selector === '[data-forms-list-delete-target]') return hasIdField ? {set value(v) { selectedId=v; }} : null;
    if (selector === '[data-forms-list-delete-subject]') return hasNameField ? {set textContent(v) { selectedName=v; }} : null;
    throw new Error('Unexpected selector '+selector);
  },
};
let present=true;
const doc={
  addEventListener(type,fn) { assert.equal(type,'click');callback=fn; },
  getElementById(id) { assert.equal(id,'forms-list-delete-dialog');return present ? dialog:null; },
};
vm.runInNewContext(snippet,{document:doc,mediaDetailDialog:null,openDialog(d){assert.equal(d,dialog);opens++;}},{timeout:2000});
let pass=0;
function check(expr,label){assert.ok(expr,label);console.log('PASS: '+label);pass++;}
check(typeof callback==='function','Core installs one delegated click handler');
function click(id,name,disabled=false,active=true){
 const b={disabled,dataset:{adminOpenDelete:'forms-list-delete-dialog',formsListDeleteId:id,formsListDeleteName:name}};
 callback({target:{closest(q){assert.equal(q,'[data-admin-open-delete]');return active?b:null;}}});
}
click('21','First');
check(opens===1 && selectedId==='21' && selectedName==='First','First valid form opens modal with current ID and name');
click('22','Second');
check(opens===2 && selectedId==='22' && selectedName==='Second','Second form replaces previous selection');
click('23','<img onerror=alert(1)>');
check(opens===3 && selectedId==='23','New selected ID updated');
check(selectedName==='<img onerror=alert(1)>','Untrusted title assigned using textContent only');
for (const id of ['','0','-4','4.5',' 7','08x','1&2','1<script>']) {
 const before=opens;click(id,'Danger');check(opens===before && selectedId==='23',`Invalid ID ${JSON.stringify(id)} rejected without overwriting selection`);
}
click('24','Disabled',true);
check(opens===3 && selectedId==='23','Disabled Core action is inert');
click('25','Missing button',false,false);
check(opens===3,'Unrelated click does not open dialog');
let saved = [opens,selectedId];present=false;click('26','No dialog');
check(opens===saved[0] && selectedId===saved[1],'Missing modal ignored');present=true;
hasIdField=false;click('26','No ID input');
check(opens===saved[0] && selectedId===saved[1],'Missing hidden POST target blocks modal');hasIdField=true;
hasNameField=false;click('26','No subject');
check(opens===saved[0] && selectedId===saved[1],'Missing inert subject blocks modal');hasNameField=true;
click('27','Fresh search result');
check(opens===4 && selectedId==='27' && selectedName==='Fresh search result','Delegation works with newly replaced live-search row');
console.log(`Forms list Delete JS z20a: ${pass} PASS / 0 FAIL`);
