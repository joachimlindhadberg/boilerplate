<?php $siteName = trim((string)$settings->get('site_name', 'Boilerplate')) ?: 'Boilerplate'; ?>
</main>
<footer class="site-footer">
  <div class="container site-footer__row">
    <span>&copy; <?= date('Y'); ?> <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></span>
    <?php render_footer_navigation($pagesModel); ?>
  </div>
</footer>
<script src="<?= htmlspecialchars(asset_url('/assets/js/script.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php if (function_exists('cms_module_manager')): ?>
<?php foreach (cms_module_manager()->frontendScriptUrls() as $moduleScript): ?>
<script src="<?= htmlspecialchars($moduleScript, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
