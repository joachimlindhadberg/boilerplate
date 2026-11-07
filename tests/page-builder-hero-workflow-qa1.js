'use strict';
/** Read-only, synthetic JS event QA for dedicated Hero; no browser, SQL, network or writes. */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'public_html/admin/assets/js/admin.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'public_html/admin/assets/css/admin.css'), 'utf8');
const form = fs.readFileSync(path.join(root, 'public_html/admin/views/pages/partials/form-fields.php'), 'utf8');
let count = 0;
const check = (actual, expected, description) => {
  count++;
  assert.deepEqual(actual, expected, description);
};
const section = (start, end) => {
  const i = js.indexOf(start);
  const j = js.indexOf(end, i + start.length);
  assert(i >= 0 && j > i, 'Missing expected Core JS function: ' + start);
  return js.slice(i, j);
};

// Execute the real, unmodified synchronization function with fake DOM objects.
const syncSource = section('    const syncDedicatedHeroMedia = (field) => {', '\n    const mediaPickerDialog =');
const ctx = { Event: class SyntheticEvent { constructor(type, options) { this.type = type; this.bubbles = !!options?.bubbles; } } };
vm.runInNewContext(syncSource + '\nthis.sync = syncDedicatedHeroMedia;', ctx);
const named = {};
for (const label of ['[data-builder-hero-image-id]', '[data-builder-hero-image-path]']) {
  named[label] = {value:label.endsWith('id]')?'68':'/uploads/old.webp', changes:[], dispatchEvent(e) {this.changes.push({type:e.type,bubbles:e.bubbles});}};
}
const hero = {
  classList: {contains: cls=>cls==='admin-page-row--dedicated-hero'},
  querySelector: selector=>named[selector]||null,
};
const content = {closest: selector=>selector==='[data-builder-block]'?hero:null};
const id = {value:'99'};
const url = {value:'/uploads/new.webp'};
const field = {closest: selector=>selector==='[data-page-hero-content]'?content:null,
  querySelector:selector=> selector==='[data-media-picker-id]'?id:(selector==='[data-media-picker-path]'?url:null)};
ctx.sync(field);
check(named['[data-builder-hero-image-id]'].value,'99','Chosen media ID reaches the original named input');
check(named['[data-builder-hero-image-path]'].value,'/uploads/new.webp','Chosen media path reaches the original named input');
check(named['[data-builder-hero-image-id]'].changes.length,1,'ID change tracked exactly once');
check(named['[data-builder-hero-image-path]'].changes.length,1,'URL change tracked exactly once');
check(named['[data-builder-hero-image-path]'].changes[0].bubbles,true,'Change bubbles for dirty-state tracking');
ctx.sync(field);
check(named['[data-builder-hero-image-id]'].changes.length,1,'Identical image selection does not generate redundant events');
check(named['[data-builder-hero-image-path]'].changes.length,1,'Identical image URL does not generate redundant events');
id.value='';url.value='';ctx.sync(field);
check(named['[data-builder-hero-image-id]'].value,'','Clear removes media ID');
check(named['[data-builder-hero-image-path]'].value,'','Clear removes media URL');
check(named['[data-builder-hero-image-path]'].changes.length,2,'Clearing image notifies Core');
const unrelated = {closest:()=>null};ctx.sync(unrelated);
check(named['[data-builder-hero-image-path]'].changes.length,2,'Ordinary image blocks cannot overwrite Hero media');

// Existing legacy Hero rows must not be changed by the new synchronizer.
const legacy = {classList:{contains:()=>false},querySelector:()=>{throw new Error('Legacy must not be queried');}};
const legacyField = {closest:()=>({closest:()=>legacy})};
ctx.sync(legacyField);check(true,true,'Legacy media handled only by its original modal flow');

const toggleSrc = section('    const syncBlockToggle = (button, expanded) => {','\n    let draggedBlock =');
const toggleCtx={translatePage: key=>key};
vm.runInNewContext(toggleSrc+'\nthis.toggle=syncBlockToggle;',toggleCtx);
const attrs={};const button={setAttribute:(k,v)=>attrs[k]=v};
toggleCtx.toggle(button,true);
check(attrs['aria-expanded'],'true','Expanded Hero reports accessible state');
check(attrs['aria-label'],'page_builder_v2.collapse_block','Expanded Hero action becomes Collapse');
check(attrs.title,undefined,'Title is assigned via property, not aria attribute');
check(button.title,'page_builder_v2.collapse_block','Hover title updates on expand');
toggleCtx.toggle(button,false);
check(attrs['aria-expanded'],'false','Collapsed Hero reports accessible state');
check(button.title,'page_builder_v2.expand_block','Hover title updates on collapse');
check(css.includes('body.nf-admin [data-page-hero-toggle][aria-expanded="true"] .admin-icon-action__icon'),true,'Core CSS rotates expanded Hero chevron');
check(css.includes('body.nf-admin [data-page-child-toggle][aria-expanded="true"] .admin-icon-action__icon'),true,'Other block chevrons remain supported');
check(js.includes("if (!settingsSection.classList.contains('admin-page-row--dedicated-hero'))"),true,'Dedicated Hero settings do not overwrite changed content media');
check(js.includes('if (heroBackgroundSettings) heroBackgroundSettings.hidden = dedicatedHero;'),true,'Dedicated Hero settings hide legacy image selector');
check(form.includes('data-page-hero-background-settings'),true,'Legacy modal image selector still available');
console.log('Page Builder Hero Workflow JS QA1: '+count+' PASS / 0 FAIL (synthetic DOM, read-only)');
