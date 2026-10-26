  <section class="admin-panel admin-tools-section" aria-labelledby="tools-redirects-title">
    <div class="admin-panel__header admin-tools-section__header">
      <div><h2 id="tools-redirects-title"><?= admin_e(t('Redirects')); ?></h2><p><?= admin_e(t('Administrér 301/302-viderestillinger og se automatiske redirects fra ændrede side-URL’er.')); ?></p></div>
    </div>
    <div class="admin-tools-body">
      <div class="admin-tools-feature">
        <div><strong><?= admin_e(t('URL-viderestillinger')); ?></strong><span><?= admin_e(t('Undgå døde links, når sider flyttes eller får nye URL’er. Publicerede sider opretter automatisk 301-redirect ved URL-ændringer.')); ?></span></div>
        <a class="admin-button admin-button--secondary" href="/admin/vaerktoejer/redirects"><?= admin_e(t('Administrér redirects')); ?></a>
      </div>
    </div>
  </section>
