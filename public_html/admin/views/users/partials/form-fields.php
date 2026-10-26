<form method="post" action="<?= $action; ?>" class="admin-form" data-admin-track-changes>
  <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
  <?php if($isEdit): ?><input type="hidden" name="id" value="<?= $id; ?>"><?php endif; ?>
  <div class="admin-detail-grid">
    <div class="admin-detail-content">
      <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('Brugeroplysninger')); ?></h2></div><div class="admin-form-section">
        <div class="admin-form-row">
          <div class="admin-field"><label for="user-firstname"><?= admin_e(t('Fornavn *')); ?></label><input id="user-firstname" name="firstname" maxlength="100" value="<?= admin_e((string)($form['firstname']??'')); ?>" required><?php if($fieldError('firstname')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('firstname'))); ?></p><?php endif; ?></div>
          <div class="admin-field"><label for="user-lastname"><?= admin_e(t('Efternavn *')); ?></label><input id="user-lastname" name="lastname" maxlength="100" value="<?= admin_e((string)($form['lastname']??'')); ?>" required><?php if($fieldError('lastname')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('lastname'))); ?></p><?php endif; ?></div>
        </div>
        <div class="admin-form-row">
          <div class="admin-field"><label for="user-email"><?= admin_e(t('E-mail *')); ?></label><input id="user-email" type="email" name="email" maxlength="255" value="<?= admin_e((string)($form['email']??'')); ?>" autocomplete="off" required><?php if($fieldError('email')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('email'))); ?></p><?php endif; ?></div>
          <div class="admin-field"><label for="user-phone"><?= admin_e(t('Telefon')); ?></label><input id="user-phone" name="phone" maxlength="30" value="<?= admin_e((string)($form['phone']??'')); ?>" autocomplete="tel"><?php if($fieldError('phone')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('phone'))); ?></p><?php endif; ?></div>
        </div>
      </div></section>

      <section class="admin-panel"><div class="admin-panel__header"><h2><?= $isEdit?t('Adgangskode'):t('Adgangskode *'); ?></h2></div><div class="admin-form-section">
        <?php if($isEdit): ?><p class="admin-field__help"><?= admin_e(t('Lad felterne være tomme for at beholde den nuværende adgangskode.')); ?></p><?php endif; ?>
        <div class="admin-form-row">
          <div class="admin-field"><label for="user-password"><?= $isEdit?t('Ny adgangskode'):t('Adgangskode *'); ?></label><input id="user-password" type="password" name="password" minlength="12" autocomplete="new-password" <?= $isEdit?'':'required'; ?>><?php if($fieldError('password')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('password'))); ?></p><?php endif; ?><p class="admin-field__help"><?= admin_e(t('Mindst 12 tegn.')); ?></p></div>
          <div class="admin-field"><label for="user-password-confirm"><?= $isEdit?t('Gentag ny adgangskode'):t('Gentag adgangskode *'); ?></label><input id="user-password-confirm" type="password" name="password_confirm" minlength="12" autocomplete="new-password" <?= $isEdit?'':'required'; ?>><?php if($fieldError('password_confirm')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('password_confirm'))); ?></p><?php endif; ?></div>
        </div>
        <label class="admin-checkbox"><input type="checkbox" name="must_change_password" value="1" <?= ($form['must_change_password']??'0')==='1'?'checked':''; ?>><span><?= admin_e(t('Kræv at brugeren vælger en ny adgangskode ved næste login')); ?></span></label>
      </div></section>
    </div>

    <aside class="admin-detail-sidebar">
      <section class="admin-panel"><div class="admin-panel__header"><h2><?= admin_e(t('Adgang')); ?></h2></div><div class="admin-form-section">
        <div class="admin-field"><label for="user-role"><?= admin_e(t('Rolle')); ?></label>
          <?php if($isSelf): ?><input type="hidden" name="role_id" value="<?= (int)($form['role_id']??0); ?>"><?php endif; ?>
          <select id="user-role" name="<?= $isSelf?'role_display':'role_id'; ?>" <?= $isSelf?'disabled':''; ?>><?php foreach($roles as $role): $rid=(int)($role['id']??0); $roleName=(string)($role['role_name']??''); ?><option value="<?= $rid; ?>" title="<?= admin_e(admin_user_role_description($roleName)); ?>" <?= (int)($form['role_id']??0)===$rid?'selected':''; ?>><?= admin_e(admin_user_role_label($roleName)); ?></option><?php endforeach; ?></select>
          <div class="admin-role-help">
            <p><strong><?= admin_e(t('Administrator')); ?></strong> <?= admin_e(t('— fuld adgang til indhold, Brugere, Værktøjer og Indstillinger.')); ?></p>
            <p><strong><?= admin_e(t('Redaktør')); ?></strong> <?= admin_e(t('— adgang til alt dagligt indhold, men ikke Brugere, Værktøjer eller Indstillinger.')); ?></p>
            <p><strong><?= admin_e(t('Bruger')); ?></strong> <?= admin_e(t('— ingen adgang til CMS.')); ?></p>
          </div><?php if($isSelf):?><p class="admin-field__help"><?= admin_e(t('Din egen rolle kan ikke ændres her.')); ?></p><?php endif; ?><?php if($fieldError('role_id')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('role_id'))); ?></p><?php endif; ?>
        </div>
        <div class="admin-field"><label for="user-status"><?= admin_e(t('Status')); ?></label>
          <?php if($isSelf): ?><input type="hidden" name="status" value="active"><?php endif; ?>
          <select id="user-status" name="<?= $isSelf?'status_display':'status'; ?>" <?= $isSelf?'disabled':''; ?>><option value="active" <?= ($form['status']??'active')==='active'?'selected':''; ?>><?= admin_e(t('Aktiv')); ?></option><option value="inactive" <?= ($form['status']??'')==='inactive'?'selected':''; ?>><?= admin_e(t('Inaktiv')); ?></option><option value="banned" <?= ($form['status']??'')==='banned'?'selected':''; ?>><?= admin_e(t('Blokeret')); ?></option></select>
          <p class="admin-field__help"><?= admin_e(t('Kun aktive administratorer og redaktører kan logge ind i CMS’et.')); ?></p><?php if($isSelf):?><p class="admin-field__help"><?= admin_e(t('Din egen konto skal forblive aktiv.')); ?></p><?php endif; ?><?php if($fieldError('status')):?><p class="admin-field-error"><?= admin_e(t((string)$fieldError('status'))); ?></p><?php endif; ?>
        </div>
      </div></section>
      <div class="admin-form-actions"><a class="admin-button admin-button--secondary" href="<?= $isEdit?'/admin/brugere?mode=view&amp;id='.$id:'/admin/brugere'; ?>"><?= admin_e(t('Annuller')); ?></a><button class="admin-button" type="submit"><?= $isEdit ? '' : admin_icon('plus', 'admin-button__icon'); ?><?= admin_e($isEdit ? t('Gem ændringer') : t('Opret bruger')); ?></button></div>
    </aside>
  </div>
</form>
