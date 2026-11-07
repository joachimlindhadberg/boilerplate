'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const script = fs.readFileSync(path.join(root, 'public_html/admin/assets/js/admin-page-workflow.js'), 'utf8');
const label = '/** Pages Publishing Visibility QA1:';
const start = script.indexOf(label);
assert(start !== -1, 'Scope marker exists');
const code = script.slice(start);
let assertions = 0;
function check(condition, message) { assertions++; assert(condition, message); }
function run(initialStatus, initialPublish, initialUnpublish) {
  const handlers = {};
  const makeControl = (value) => ({ value, disabled: false, addEventListener(type, fn) { handlers[this.id + ':' + type] = fn; } });
  const status = Object.assign(makeControl(initialStatus), { id: 'page-status' });
  const publish = Object.assign(makeControl(initialPublish), { id: 'schedule-publish' });
  const unpublish = Object.assign(makeControl(initialUnpublish), { id: 'schedule-unpublish' });
  const pubField = { hidden: false };
  const unpubField = { hidden: false };
  const controls = {
    '#page-status': status,
    '#schedule-publish': publish,
    '#schedule-unpublish': unpublish,
    '[data-page-schedule-field="publish"]': pubField,
    '[data-page-schedule-field="unpublish"]': unpubField,
  };
  const form = {
    querySelector(selector) { return controls[selector] || null; },
    addEventListener(type, fn) { handlers['form:' + type] = fn; },
  };
  vm.runInNewContext(code, { document: { querySelector(selector) { return selector === 'form[data-page-builder-form]' ? form : null; } } });
  const changeStatus = v => { status.value = v; handlers['page-status:change'](); };
  const changePublish = v => { publish.value = v; handlers['schedule-publish:input'](); };
  const submitted = () => ({ publish: publish.disabled ? null : publish.value, unpublish: unpublish.disabled ? null : unpublish.value });
  return { status, publish, unpublish, pubField, unpubField, changeStatus, changePublish, submitted, handlers };
}
let f = run('draft', '', '');
check(!f.pubField.hidden && !f.publish.disabled, 'Draft: publish visible');
check(f.unpubField.hidden && f.unpublish.disabled, 'Draft without date: unpublish hidden and disabled');
f.changePublish('2026-12-10T12:00');
check(!f.unpubField.hidden && !f.unpublish.disabled, 'Draft with date: unpublish becomes visible');
f.unpublish.value = '2026-12-12T12:00';
check(f.submitted().publish === '2026-12-10T12:00' && f.submitted().unpublish === '2026-12-12T12:00', 'Draft interval can be submitted');
f.changePublish('');
check(f.unpubField.hidden && f.unpublish.disabled, 'Clearing publish hides unpublish');
check(f.unpublish.value === '2026-12-12T12:00' && f.submitted().unpublish === null, 'Hidden unpublish value retained but omitted from form');
f.changePublish('2026-12-10T12:00');
check(!f.unpubField.hidden && f.unpublish.value === '2026-12-12T12:00', 'Re-entering date restores unpublish value');
f.changeStatus('published');
check(f.pubField.hidden && f.publish.disabled, 'Published: publish hidden and disabled');
check(!f.unpubField.hidden && !f.unpublish.disabled, 'Published: unpublish available');
check(f.submitted().publish === null && f.submitted().unpublish === '2026-12-12T12:00', 'Published submits only unpublish');
f.changeStatus('protected');
check(f.pubField.hidden && f.unpubField.hidden && f.publish.disabled && f.unpublish.disabled, 'Protected: both controls disabled and hidden');
check(f.submitted().publish === null && f.submitted().unpublish === null, 'Protected submits neither date');
f.changeStatus('draft');
check(!f.pubField.hidden && !f.unpubField.hidden, 'Back to Draft restores both controls with prior publish date');
check(f.publish.value === '2026-12-10T12:00', 'Status toggle retains value until Save');
check(typeof f.handlers['form:submit'] === 'function', 'Submit guard installed');
f.changeStatus('protected'); f.handlers['form:submit']();
check(f.submitted().publish === null && f.submitted().unpublish === null, 'Submit guard protects against stale values');
f = run('published', '', '2026-12-15T23:30');
check(f.pubField.hidden && !f.unpubField.hidden, 'Existing Published schedule opens correctly');
f = run('draft', '2026-12-10T12:00', '2026-12-12T12:00');
check(!f.pubField.hidden && !f.unpubField.hidden, 'Existing Draft interval opens correctly');
f = run('protected', '', '');
check(f.pubField.hidden && f.unpubField.hidden, 'Existing Protected state opens correctly');
vm.runInNewContext(code, {document: { querySelector: () => null }});
check(true, 'Graceful no-op on non-editor pages');
console.log('Pages Publishing Visibility JS QA1: ' + assertions + ' PASS / 0 FAIL');
