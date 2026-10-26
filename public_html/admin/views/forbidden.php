<!doctype html>
<html lang="<?= admin_e(Translator::locale()); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= admin_e(t('Ingen adgang · Boilerplate')); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= admin_e(admin_asset('/admin/assets/css/admin.css')); ?>">
</head>
<body class="nf-admin nf-admin--login">
  <main class="admin-login">
    <section class="admin-login__panel">
      <p class="admin-eyebrow">403</p>
      <h1><?= admin_e(t('Ingen adgang')); ?></h1>
      <p><?= admin_e(t('Din bruger har ikke adgang til administrationen.')); ?></p>
      <form method="post" action="/admin/index.php?mode=logout"><input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>"><button class="admin-button admin-button--primary" type="submit"><?= admin_e(t('Log ud')); ?></button></form>
    </section>
  </main>
</body>
</html>
