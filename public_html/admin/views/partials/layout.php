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
$documentTitle = $documentTitle ?? 'CMS';
$bodyHtml = $bodyHtml ?? '';
?>
<!doctype html>
<html lang="<?= admin_e(Translator::locale()); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <title><?= admin_e(t((string)$documentTitle)); ?> · Boilerplate</title>
  <link rel="icon" href="<?= admin_e(admin_asset('/admin/assets/brand/boilerplate-cms-favicon.svg')); ?>" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= admin_e(admin_asset('/admin/assets/brand/boilerplate-cms-favicon-180.png')); ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:ital,wght@0,300..700;1,300..700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= admin_e(admin_asset('/admin/assets/css/admin.css')); ?>">
  <?php
  $jsKeys = ['Nulstil', 'ui.live_search_error', 'ui.live_search_loading', 'Slet', 'Handlingen kan ikke fortrydes.', 'blok', 'Vis adgangskode', 'modulet', 'Vælg side', 'Hovedniveau', 'nav.under', 'nav.editor_moved', 'Fjern menupunkt', 'Medie', 'Bekræft handling', 'Bekræft', 'Nyt menupunkt', 'Billede', 'Skjul adgangskode', 'Fjern blok', 'Fjern sektion', 'Ikke-gemte ændringer', 'Forlad siden', 'Du har ændringer, som ikke er gemt. Vil du forlade siden og kassere dem?', 'Mediebiblioteket kunne ikke indlæses. Prøv igen.', 'Vælg komponent', 'En kolonne kan højst indeholde 12 indholdsblokke.', 'Fjern denne blok fra kolonnen? Ændringen gemmes først, når du gemmer siden.', 'Fjern denne sektion fra siden? Ændringen gemmes først, når du gemmer siden.', 'Side · ', 'Link · ', 'Fjern menupunktet? Ændringen træder først i kraft, når du gemmer.', 'Flyt først undermenuerne, inden du placerer dette punkt under et andet.', 'De valgte filer er samlet for store til serverens uploadgrænse.', 'Vælg et JPG-, PNG- eller WebP-billede.', 'Filen må højst fylde 10 MB.', 'Bekræft sletning', 'Ingen', 'Vælg e-mailfelt…', 'Række', 'Fjern række', 'Tilføj felt', 'Label *', 'Valgmuligheder', 'Én mulighed pr. linje.', 'blokke', 'Fjern rækken og alle felter i den? Ændringen gemmes først, når du gemmer formularen.', 'notice.dismiss', 'dashboard.cards_saving', 'dashboard.cards_saved', 'dashboard.cards_save_failed'];
  $jsMessages = [];
  foreach ($jsKeys as $key) $jsMessages[$key] = t($key);
  ?>
  <script>window.cmsTranslate = (key) => (window.CMS_ADMIN_MESSAGES && Object.prototype.hasOwnProperty.call(window.CMS_ADMIN_MESSAGES, key)) ? window.CMS_ADMIN_MESSAGES[key] : key;
  window.CMS_ADMIN_MESSAGES = <?= json_encode($jsMessages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); ?>;</script>
  <script src="<?= admin_e(admin_asset('/admin/assets/js/admin.js')); ?>" defer></script>
  <script src="<?= admin_e(admin_asset('/admin/assets/js/admin-live-search.js')); ?>" defer></script>
</head>
<body class="nf-admin">
  <a class="admin-skip-link" href="#admin-main"><?= admin_e(t('Gå til indhold')); ?></a>
  <div class="admin-layout">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="<?= admin_e(t('ui.admin_navigation')); ?>">
      <div class="admin-sidebar__header">
        <a class="admin-brand" href="/admin" aria-label="Boilerplate CMS"><img class="admin-brand__logo" src="<?= admin_e(admin_asset('/admin/assets/brand/boilerplate-cms-logo-light.png')); ?>" alt="" aria-hidden="true" width="1785" height="270"></a>
        <button class="admin-sidebar__close" type="button" data-admin-sidebar-close aria-label="<?= admin_e(t('ui.close_menu')); ?>">×</button>
      </div>
      <nav class="admin-nav" aria-label="<?= admin_e(t('ui.backend')); ?>"><ul>
        <?php foreach ($navItems as $item): ?><li>
          <?php if ($item['enabled']): ?>
            <a data-admin-nav-key="<?= admin_e((string)$item['key']); ?>" data-admin-nav-badge-description="<?= admin_e(t(trim((string)($item['badge_label'] ?? '')) ?: 'ui.nav_pending_items')); ?>" href="<?= admin_e($item['href']); ?>" <?= ($activeNav ?? '') === $item['key'] ? 'aria-current="page" class="is-active"' : ''; ?>>
              <?= admin_icon((string)($item['icon'] ?? 'file'), 'admin-nav__icon'); ?><span><?= admin_e(t((string)$item['label'])); ?></span>
              <?= admin_nav_badge_markup($item); ?>
            </a>
          <?php else: ?>
            <span class="admin-nav__disabled" aria-disabled="true" title="<?= admin_e(t((string)($item['disabled_title'] ?? 'Modulet er ikke aktiveret endnu'))); ?>"><?= admin_icon((string)($item['icon'] ?? 'file'), 'admin-nav__icon'); ?><span><?= admin_e(t((string)$item['label'])); ?></span></span>
          <?php endif; ?>
        </li><?php endforeach; ?>
      </ul></nav>
    </aside>
    <div class="admin-backdrop" data-admin-backdrop hidden></div>
    <div class="admin-workspace">
      <header class="admin-topbar">
        <div class="admin-topbar__left">
          <button class="admin-menu-toggle" type="button" data-admin-sidebar-open aria-controls="admin-sidebar" aria-expanded="false"><span aria-hidden="true"></span><span class="sr-only"><?= admin_e(t('Åbn menu')); ?></span></button>
        </div>
        <div class="admin-topbar__actions">
          <a class="admin-view-site" href="/" target="_blank" rel="noopener"><?= admin_icon('external', 'admin-view-site__icon'); ?><span><?= admin_e(t('Se hjemmeside')); ?></span></a>
          <span class="admin-topbar__separator" aria-hidden="true"></span>
          <details class="admin-user-menu"><summary><span class="admin-avatar" aria-hidden="true"><?= admin_e($initials); ?></span><span class="admin-user-menu__text"><strong><?= admin_e((string)($user['firstname'] ?? $fullName)); ?></strong><small><?= admin_e($roleLabel); ?></small></span><?= admin_icon('chevron-down', 'admin-user-menu__chevron'); ?></summary>
            <div class="admin-user-menu__dropdown"><div class="admin-user-menu__identity"><strong><?= admin_e($fullName !== '' ? $fullName : ($user['email'] ?? '')); ?></strong><span><?= admin_e((string)($user['email'] ?? '')); ?></span></div>
            <form method="post" action="/admin/index.php?mode=set-language" class="admin-user-menu__language">
              <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
              <input type="hidden" name="return_to" value="<?= admin_e((string)($_SERVER['REQUEST_URI'] ?? '/admin')); ?>">
              <label for="user-admin-language"><?= admin_e(t('profile.admin_language')); ?></label>
              <select id="user-admin-language" name="admin_language">
                <?php
                $systemLanguage = Translator::defaultLocale();
                $personalLanguage = (string)($user['admin_language'] ?? '');
                $languageNames = [
                  'en' => t('locale.english'),
                  'da' => t('locale.danish'),
                  'de' => t('locale.german'),
                  'sv' => t('locale.swedish'),
                  'nb' => t('locale.norwegian_bokmal'),
                ];
                ?>
                <option value="" <?= $personalLanguage === '' || $personalLanguage === $systemLanguage ? 'selected' : ''; ?>><?= admin_e(t('profile.system_default_option', ['language' => $languageNames[$systemLanguage] ?? 'English'])); ?></option>
                <?php foreach ($languageNames as $localeCode => $localeName): ?>
                  <?php if ($localeCode === $systemLanguage) continue; ?>
                  <option value="<?= admin_e($localeCode); ?>" <?= $personalLanguage === $localeCode ? 'selected' : ''; ?>><?= admin_e($localeName); ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit"><?= admin_e(t('profile.apply_language')); ?></button>
            </form>
            <form method="post" action="/admin/index.php?mode=logout"><input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>"><button type="submit"><?= admin_e(t('Log ud')); ?></button></form></div>
          </details>
        </div>
      </header>
      <main class="admin-main" id="admin-main"><?= $bodyHtml; ?></main>
      <dialog class="admin-modal admin-modal--sm" data-admin-modal data-admin-confirm-dialog aria-labelledby="admin-confirm-title" aria-describedby="admin-confirm-message">
        <div class="admin-modal__surface">
          <div class="admin-modal__header"><div><h2 id="admin-confirm-title" data-admin-confirm-title><?= admin_e(t('Bekræft handling')); ?></h2><p><?= admin_e(t('Kontrollér handlingen, før du fortsætter.')); ?></p></div><button class="admin-modal__close" type="button" data-modal-close aria-label="<?= admin_e(t('Luk')); ?>">×</button></div>
          <div class="admin-modal__body"><p class="admin-modal__message" id="admin-confirm-message" data-admin-confirm-message></p></div>
          <div class="admin-modal__footer"><button class="admin-button admin-button--secondary" type="button" data-admin-confirm-cancel><?= admin_e(t('Annuller')); ?></button><button class="admin-button admin-button--danger" type="button" data-admin-confirm-accept><?= admin_e(t('Bekræft')); ?></button></div>
        </div>
      </dialog>
    </div>
  </div>
</body>
</html>
