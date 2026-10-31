<?php
$fullName = trim((string)($user['firstname'] ?? '') . ' ' . (string)($user['lastname'] ?? ''));
$role = (string)($user['role'] ?? '');
$roleLabel = admin_user_role_label($role);
$firstNameValue = (string)($user['firstname'] ?? '');
$lastNameValue = (string)($user['lastname'] ?? '');
$firstInitial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($firstNameValue, 0, 1)) : strtoupper(substr($firstNameValue, 0, 1));
$lastInitial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($lastNameValue, 0, 1)) : strtoupper(substr($lastNameValue, 0, 1));
$initials = trim($firstInitial . $lastInitial) ?: 'BP';
$navItems = admin_nav_items(admin_nav_badges());
$pageTitle = trim((string)($pageTitle ?? t('Slet permanent'))) ?: t('Slet permanent');
$entityLabel = trim((string)($entityLabel ?? 'element')) ?: 'element';
$entityName = trim((string)($entityName ?? '')) ?: ucfirst($entityLabel);
$entityContext = trim((string)($entityContext ?? ''));
$warningText = trim((string)($warningText ?? 'Denne handling kan ikke fortrydes.'));
$postAction = (string)($postAction ?? '/admin');
$cancelUrl = (string)($cancelUrl ?? '/admin');
$id = max(0, (int)($id ?? 0));
$blocked = !empty($blocked);
$blockedActionUrl = (string)($blockedActionUrl ?? '');
$blockedActionLabel = trim((string)($blockedActionLabel ?? t('Se relationer'))) ?: t('Se relationer');
?>
<!doctype html>
<html lang="<?= admin_e(Translator::locale()); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <title><?= admin_e($pageTitle); ?> · Boilerplate</title>
  <link rel="icon" href="<?= admin_e(admin_asset('/admin/assets/brand/boilerplate-cms-favicon.svg')); ?>" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= admin_e(admin_asset('/admin/assets/brand/boilerplate-cms-favicon-180.png')); ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:ital,wght@0,300..700;1,300..700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= admin_e(admin_asset('/admin/assets/css/admin.css')); ?>">
  <script src="<?= admin_e(admin_asset('/admin/assets/js/admin.js')); ?>" defer></script>
</head>
<body class="nf-admin">
  <a class="admin-skip-link" href="#admin-main"><?= admin_e(t('Gå til indhold')); ?></a>

  <div class="admin-layout">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="<?= admin_e(t('ui.admin_navigation')); ?>">
      <div class="admin-sidebar__header">
        <a class="admin-brand" href="/admin" aria-label="Boilerplate CMS">
          <img class="admin-brand__logo" src="<?= admin_e(admin_asset('/admin/assets/brand/boilerplate-cms-logo-light.png')); ?>" alt="" aria-hidden="true" width="1785" height="270">
        </a>
        <button class="admin-sidebar__close" type="button" data-admin-sidebar-close aria-label="<?= admin_e(t('ui.close_menu')); ?>">×</button>
      </div>

      <nav class="admin-nav" aria-label="<?= admin_e(t('ui.backend')); ?>">
        <ul>
          <?php foreach ($navItems as $item): ?>
            <li>
              <?php if ($item['enabled']): ?>
                <a data-admin-nav-key="<?= admin_e((string)$item['key']); ?>" data-admin-nav-badge-description="<?= admin_e(t(trim((string)($item['badge_label'] ?? '')) ?: 'ui.nav_pending_items')); ?>" href="<?= admin_e($item['href']); ?>" <?= ($activeNav ?? '') === $item['key'] ? 'aria-current="page" class="is-active"' : ''; ?>>
                  <?= admin_icon((string)($item['icon'] ?? 'file'), 'admin-nav__icon'); ?>
                  <span><?= admin_e($item['label']); ?></span>
                  <?= admin_nav_badge_markup($item); ?>
                </a>
              <?php else: ?>
                <span class="admin-nav__disabled" aria-disabled="true" title="<?= admin_e(t('Modulet er ikke aktiveret endnu')); ?>">
                  <?= admin_icon((string)($item['icon'] ?? 'file'), 'admin-nav__icon'); ?>
                  <span><?= admin_e($item['label']); ?></span>
                </span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
    </aside>

    <div class="admin-backdrop" data-admin-backdrop hidden></div>

    <div class="admin-workspace">
      <header class="admin-topbar">
        <div class="admin-topbar__left">
          <button class="admin-menu-toggle" type="button" data-admin-sidebar-open aria-controls="admin-sidebar" aria-expanded="false">
            <span aria-hidden="true"></span>
            <span class="sr-only"><?= admin_e(t('Åbn menu')); ?></span>
          </button>
          <div class="admin-search admin-search--desktop" role="search">
            <label class="admin-search__field">
              <?= admin_icon('search', 'admin-search__icon'); ?>
              <span class="sr-only"><?= admin_e(t('Søg i CMS')); ?></span>
              <input type="search" placeholder="<?= admin_e(t('Søg i CMS…')); ?>" autocomplete="off" aria-label="<?= admin_e(t('Søg i CMS')); ?>">
            </label>
          </div>
        </div>

        <div class="admin-topbar__actions">
          <button class="admin-search-toggle" type="button" data-admin-search-toggle aria-controls="admin-mobile-search" aria-expanded="false" aria-label="<?= admin_e(t('Åbn søgning')); ?>">
            <?= admin_icon('search', 'admin-search-toggle__icon'); ?>
          </button>
          <a class="admin-view-site" href="/" target="_blank" rel="noopener">
            <?= admin_icon('external', 'admin-view-site__icon'); ?>
            <span><?= admin_e(t('Se hjemmeside')); ?></span>
          </a>
          <span class="admin-topbar__separator" aria-hidden="true"></span>
          <details class="admin-user-menu">
            <summary>
              <span class="admin-avatar" aria-hidden="true"><?= admin_e($initials); ?></span>
              <span class="admin-user-menu__text">
                <strong><?= admin_e((string)($user['firstname'] ?? $fullName)); ?></strong>
                <small><?= admin_e($roleLabel); ?></small>
              </span>
              <?= admin_icon('chevron-down', 'admin-user-menu__chevron'); ?>
            </summary>
            <div class="admin-user-menu__dropdown">
              <div class="admin-user-menu__identity">
                <strong><?= admin_e($fullName !== '' ? $fullName : ($user['email'] ?? '')); ?></strong>
                <span><?= admin_e((string)($user['email'] ?? '')); ?></span>
              </div>
              <form method="post" action="/admin/index.php?mode=logout">
                <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
                <button type="submit"><?= admin_e(t('Log ud')); ?></button>
              </form>
            </div>
          </details>
        </div>
      </header>

      <div class="admin-mobile-search" id="admin-mobile-search" data-admin-mobile-search hidden>
        <div class="admin-search admin-search--mobile" role="search">
          <label class="admin-search__field">
            <?= admin_icon('search', 'admin-search__icon'); ?>
            <span class="sr-only"><?= admin_e(t('Søg i CMS')); ?></span>
            <input type="search" placeholder="<?= admin_e(t('Søg i CMS…')); ?>" autocomplete="off" aria-label="<?= admin_e(t('Søg i CMS')); ?>" data-admin-mobile-search-input>
          </label>
        </div>
      </div>

      <main class="admin-main" id="admin-main">
        <div class="admin-detail-back"><a class="admin-back-link" href="<?= admin_e($cancelUrl); ?>"><?= admin_icon('arrow-left', 'admin-back-link__icon'); ?><?= admin_e(t('Tilbage')); ?></a></div>

        <div class="admin-page-titlebar">
          <div>
            <h1><?= admin_e($pageTitle); ?></h1>
            <p><?= admin_e(t('Permanent sletning kræver en eksplicit bekræftelse.')); ?></p>
          </div>
        </div>

        <section class="admin-panel admin-delete-confirm" aria-labelledby="delete-confirm-title">
          <div class="admin-panel__header">
            <h2 id="delete-confirm-title"><?= admin_e(t('Er du sikker?')); ?></h2>
          </div>
          <div class="admin-delete-confirm__body">
            <p><?= admin_e(t('delete.entity_permanent', ['entity' => $entityLabel])); ?></p>
            <div class="admin-delete-confirm__entity">
              <strong><?= admin_e($entityName); ?></strong>
              <?php if ($entityContext !== ''): ?><span><?= admin_e($entityContext); ?></span><?php endif; ?>
            </div>
            <p class="admin-delete-confirm__warning"><?= admin_e(t($warningText)); ?></p>
          </div>
          <div class="admin-delete-confirm__actions">
            <a class="admin-button admin-button--secondary" href="<?= admin_e($cancelUrl); ?>"><?= admin_e(t('Annuller')); ?></a>
            <?php if ($blocked): ?>
              <?php if ($blockedActionUrl !== ''): ?><a class="admin-button" href="<?= admin_e($blockedActionUrl); ?>"><?= admin_e($blockedActionLabel); ?></a><?php endif; ?>
            <?php else: ?>
              <form method="post" action="<?= admin_e($postAction); ?>">
                <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
                <input type="hidden" name="id" value="<?= $id; ?>">
                <input type="hidden" name="confirm_delete" value="permanent">
                <button class="admin-button admin-button--danger" type="submit"><?= admin_e(t(t('Slet permanent'))); ?></button>
              </form>
            <?php endif; ?>
          </div>
        </section>
      </main>
    </div>
  </div>
</body>
</html>
