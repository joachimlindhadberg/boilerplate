<div class="admin-page-titlebar">
  <h1><?= admin_e(t('example:Eksempel')); ?></h1>
  <p><?= admin_e(t('example:Denne side kommer fra Example Module og er ikke en del af CMS-kernen.')); ?></p>
</div>

<section class="admin-panel">
  <div class="admin-panel__header"><h2><?= admin_e(t('example:Module-systemet fungerer')); ?></h2></div>
  <div class="admin-module-example">
    <p><strong><?= admin_e(t('example:Example Module er aktivt.')); ?></strong></p>
    <p><?= admin_e(t('example:Boilerplate har indlæst modulets manifest, startet det aktive module, registreret menupunktet og sendt denne admin-route til modulets egen handler.')); ?></p>
    <dl class="admin-meta-list admin-meta-list--module-example">
      <div><dt><?= admin_e(t('Module')); ?></dt><dd>Example Module</dd></div>
      <div><dt><?= admin_e(t('Version')); ?></dt><dd><?= admin_e((string)($moduleVersion ?? '1.0.0')); ?></dd></div>
      <div><dt><?= admin_e(t('Placering')); ?></dt><dd><code>/modules/example/</code></dd></div>
    </dl>
  </div>
</section>
