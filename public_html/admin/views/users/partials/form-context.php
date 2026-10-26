<?php
$managedUser = is_array($managedUser ?? null) ? $managedUser : [];
$roles = is_array($roles ?? null) ? $roles : [];
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$isEdit = !empty($isEdit);
$id = (int)($managedUser['id'] ?? 0);
$isSelf = $isEdit && $id === (int)($actorId ?? 0);
$fieldError = static fn(string $key): string => t((string)($errors[$key] ?? ''));
$action = $isEdit ? '/admin/brugere?mode=edit&amp;id=' . $id : '/admin/brugere?mode=new';
ob_start();
?>
<div class="admin-detail-back"><a class="admin-back-link" href="<?= $isEdit?'/admin/brugere?mode=view&amp;id='.$id:'/admin/brugere'; ?>"><?= admin_icon('arrow-left', 'admin-back-link__icon'); ?><?= admin_e(t($isEdit ? 'Tilbage til bruger' : 'Tilbage til brugere')); ?></a></div>
<div class="admin-page-titlebar"><h1><?= $isEdit?t('Rediger bruger'):t('Ny bruger'); ?></h1><p><?= $isEdit?'Opdatér brugerens oplysninger, adgang og sikkerhed.':'Opret en ny konto og vælg hvilken adgang brugeren skal have.'; ?></p></div>
<?php if ($fieldError('form')): ?><div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$fieldError('form'))); ?></div><?php endif; ?>
