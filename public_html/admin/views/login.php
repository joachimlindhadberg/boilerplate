<?php
$siteName = 'Boilerplate';
?>
<!doctype html>
<html lang="<?= admin_e(Translator::locale()); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <title><?= admin_e(t('Log ind')); ?> · <?= admin_e($siteName); ?></title>
  <link rel="icon" href="<?= admin_e(admin_asset('/admin/assets/brand/boilerplate-cms-favicon.svg')); ?>" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= admin_e(admin_asset('/admin/assets/brand/boilerplate-cms-favicon-180.png')); ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= admin_e(admin_asset('/admin/assets/css/admin.css')); ?>">
</head>
<body class="nf-admin nf-admin--login">
  <main class="admin-login admin-login--auth" id="main-content">
    <section class="admin-login__panel" aria-labelledby="login-title">
      <a class="admin-login__brand" href="/" aria-label="Boilerplate CMS">
        <img class="admin-login__brand-image" src="<?= admin_e(admin_asset('/admin/assets/brand/boilerplate-cms-logo-light.png')); ?>" alt="" aria-hidden="true" width="1785" height="270">
      </a>
      <div class="admin-login__intro">
        <p class="admin-eyebrow"><?= admin_e(t('Administration')); ?></p>
        <h1 id="login-title"><?= admin_e(t('Log ind')); ?></h1>
        <p><?= admin_e(t('Log ind for at administrere indholdet på Boilerplate.')); ?></p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="admin-alert admin-alert--error" role="alert">
          <?= admin_e(t((string)$error)); ?>
          <?php if (!empty($retryAfter)): ?>
            <span class="admin-alert__meta"><?= admin_e(t('login.retry_after', ['seconds' => (int)$retryAfter])); ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="/admin/index.php?mode=authenticate" novalidate aria-label="<?= admin_e(t('Log ind')); ?>">
        <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">

        <div class="admin-field">
          <label for="admin-email"><?= admin_e(t('E-mail')); ?></label>
          <input
            id="admin-email"
            name="email"
            type="email"
            autocomplete="username"
            inputmode="email"
            value="<?= admin_e($email ?? ''); ?>"
            required
            maxlength="254"
            autofocus
          >
        </div>

        <div class="admin-field">
          <label for="admin-password"><?= admin_e(t('Adgangskode')); ?></label>
          <div class="admin-password-control">
            <input
              id="admin-password"
              name="password"
              type="password"
              autocomplete="current-password"
              required
              maxlength="1024"
            >
            <button
              class="admin-password-toggle"
              type="button"
              data-password-toggle
              data-label-show="<?= admin_e(t('Vis adgangskode')); ?>"
              data-label-hide="<?= admin_e(t('Skjul adgangskode')); ?>"
              aria-controls="admin-password"
              aria-label="<?= admin_e(t('Vis adgangskode')); ?>"
              aria-pressed="false"
              title="<?= admin_e(t('Vis adgangskode')); ?>"
            >
              <svg class="admin-password-toggle__icon" data-password-icon-show viewBox="0 0 24 24" aria-hidden="true">
                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
                <circle cx="12" cy="12" r="2.75"/>
              </svg>
              <svg class="admin-password-toggle__icon" data-password-icon-hide viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 3l18 18"/>
                <path d="M10.6 6.15A9.4 9.4 0 0 1 12 6c6 0 9.5 6 9.5 6a16.5 16.5 0 0 1-2.45 3.18M6.2 7.25C3.8 9.08 2.5 12 2.5 12s3.5 6 9.5 6a9.7 9.7 0 0 0 3.1-.5M9.95 9.95A2.9 2.9 0 0 0 14.05 14.05"/>
              </svg>
              <span class="sr-only" data-password-toggle-text><?= admin_e(t('Vis adgangskode')); ?></span>
            </button>
          </div>
        </div>

        <div class="admin-login__actions">
          <label class="admin-checkbox">
            <input type="checkbox" name="remember" value="1" <?= !empty($remember) ? 'checked' : ''; ?>>
            <span><?= admin_e(t('Husk mig på denne enhed')); ?></span>
          </label>

          <button class="admin-button admin-button--primary" type="submit"><?= admin_e(t('Log ind')); ?></button>
        </div>
      </form>

      <a class="admin-login__back admin-back-link" href="/"><?= admin_icon('arrow-left', 'admin-back-link__icon'); ?><?= admin_e(t('Tilbage til hjemmesiden')); ?></a>
    </section>
  </main>
  <script src="<?= admin_e(admin_asset('/admin/assets/js/admin.js')); ?>" defer></script>
</body>
</html>
