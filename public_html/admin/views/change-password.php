<?php
$siteName = 'Boilerplate';
?>
<!doctype html>
<html lang="<?= admin_e(Translator::locale()); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <title><?= admin_e(t('Vælg ny adgangskode')); ?> · <?= admin_e($siteName); ?></title>
  <link rel="icon" href="<?= admin_e(admin_asset('/admin/assets/brand/boilerplate-cms-favicon.svg')); ?>" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= admin_e(admin_asset('/admin/assets/brand/boilerplate-cms-favicon-180.png')); ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= admin_e(admin_asset('/admin/assets/css/admin.css')); ?>">
</head>
<body class="nf-admin nf-admin--login">
  <main class="admin-login" id="main-content">
    <section class="admin-login__panel" aria-labelledby="password-title">
      <a class="admin-login__brand" href="/" aria-label="<?= admin_e(t('ui.brand_home')); ?>">
        <img class="admin-login__brand-image" src="<?= admin_e(admin_asset('/admin/assets/brand/boilerplate-cms-logo-light.png')); ?>" alt="" aria-hidden="true" width="1785" height="270">
      </a>

      <div class="admin-login__intro">
        <p class="admin-eyebrow"><?= admin_e(t('Administration')); ?></p>
        <h1 id="password-title"><?= admin_e(t('Vælg ny adgangskode')); ?></h1>
        <p><?= admin_e(t('Den midlertidige adgangskode skal udskiftes, før du kan fortsætte til CMS\'et.')); ?></p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="admin-alert admin-alert--error" role="alert"><?= admin_e(t((string)$error)); ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="/admin/index.php?mode=change-password" novalidate>
        <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">

        <div class="admin-field">
          <label for="new-password"><?= admin_e(t('Ny adgangskode')); ?></label>
          <input id="new-password" name="password" type="password" autocomplete="new-password" minlength="12" required autofocus>
          <p class="admin-field__help"><?= admin_e(t('Mindst 12 tegn. Brug gerne en unik sætning eller password-manager.')); ?></p>
        </div>

        <div class="admin-field">
          <label for="new-password-confirm"><?= admin_e(t('Gentag ny adgangskode')); ?></label>
          <input id="new-password-confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="12" required>
        </div>

        <button class="admin-button admin-button--primary" type="submit"><?= admin_e(t('Gem ny adgangskode')); ?></button>
      </form>

      <form class="admin-logout-form admin-password-logout" method="post" action="/admin/index.php?mode=logout">
        <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
        <button class="admin-login__back admin-login__back--button" type="submit"><?= admin_e(t('Log ud')); ?></button>
      </form>
    </section>
  </main>
</body>
</html>
