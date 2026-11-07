'use strict';
/** Dynamic UI copy uses the same Core catalog as the PHP Page Builder. */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
const catalog = JSON.parse(fs.readFileSync(path.join(root, 'core/lang/translations.json'), 'utf8'));
const source = fs.readFileSync(path.join(root, 'public_html/admin/assets/js/admin.js'), 'utf8');
const start = source.indexOf('    const updateSummary = (block) => {');
const end = source.indexOf('    const addBlock = (type', start);
if (start < 0 || end <= start) throw new Error('Page Builder summary functions were not found');
let passed = 0;
const check = (condition, reason) => { if (!condition) throw new Error(reason); passed++; };
for (const locale of ['en','da','de','sv','nb']) {
  const tr = k => catalog[k]?.[locale] ?? k;
  const functions = vm.runInNewContext(
    `(function(){${source.slice(start,end)}return {updateSummary,updateGridChildSummary};})()`,
    { window: { cmsTranslate: tr } }, { timeout: 1000 }
  );
  const summary = { textContent: '' };
  const count = { value: '2' };
  const preset = { selectedIndex: 0, options: [{ textContent: '50 / 50' }] };
  const grid = {
    dataset: { blockType: 'grid' },
    querySelector(selector) { return {'[data-builder-summary]':summary,'[data-grid-column-count]':count,'[data-grid-preset]':preset}[selector] ?? null; }
  };
  functions.updateSummary(grid);
  check(summary.textContent === tr('pages_detail.row_columns').replace('{count}', '2') + ' · 50 / 50', `${locale}: grid count translated`);
  count.value = '1'; preset.options[0].textContent = '100 %';
  functions.updateSummary(grid);
  check(summary.textContent === tr('pages_detail.row_column_singular').replace('{count}', '1') + ' · 100 %', `${locale}: singular count translated`);

  const imageSummary = { textContent: '' };
  const image = {
    dataset: { gridChildType: 'image' },
    querySelector(selector) { return {'[data-grid-child-summary]':imageSummary,'[data-grid-child-summary-source]':{value:''}}[selector] ?? null; }
  };
  functions.updateGridChildSummary(image);
  check(imageSummary.textContent === tr('Intet billede valgt'), `${locale}: empty image translated`);
  const textSummary = { textContent: '' };
  const text = {
    dataset: { gridChildType: 'text' },
    querySelector(selector) {return {'[data-grid-child-summary]':textSummary,'[data-grid-child-summary-source]':{value:''}}[selector] ?? null; }
  };
  functions.updateGridChildSummary(text);
  check(textSummary.textContent === tr('pages.builder.no_heading'), `${locale}: empty heading translated`);
}
const layout=fs.readFileSync(path.join(root,'public_html/admin/views/partials/layout.php'),'utf8');
for (const key of ['pages_detail.row_column_singular','pages_detail.row_columns','pages.builder.no_heading',
                   'pages.builder.remove_columns','pages.builder.remove_columns_warning','Lige brede kolonner','Intet billede valgt']) {
  check(layout.includes("'"+key+"'"), `Translation ${key} is serialized into JS`);
}
check(source.includes("window.cmsTranslate('pages.builder.remove_columns_warning')"), 'Legacy-grid confirmation translated');
console.log(`Pages Full Text JS QA1: ${passed} PASS / 0 FAIL`);
