<!doctype html>
<html lang="<?= admin_e(Translator::locale()); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= admin_e(t('Ikke fundet · Boilerplate')); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= admin_e(admin_asset('/admin/assets/css/admin.css')); ?>">
</head>
<body class="nf-admin nf-admin--login">
  <main class="admin-login">
    <section class="admin-login__panel">
      <p class="admin-eyebrow">404</p>
      <h1><?= admin_e(t('Siden findes ikke')); ?></h1>
      <p><?= admin_e(t('Den ønskede admin-side findes ikke endnu.')); ?></p>
      <a class="admin-button admin-button--primary" href="/admin"><?= admin_e(t('Til dashboard')); ?></a>
    </section>
  </main>
</body>
</html>
