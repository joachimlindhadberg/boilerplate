<?php
$data = is_array($redirectsData ?? null) ? $redirectsData : [];
$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$search = (string)($data['search'] ?? '');
$status = (string)($data['status'] ?? '');
$page = max(1, (int)($data['page'] ?? 1));
$pages = max(1, (int)($data['pages'] ?? 1));
$total = max(0, (int)($data['total'] ?? 0));
$from = max(0, (int)($data['from'] ?? 0));
$to = max(0, (int)($data['to'] ?? 0));
$counts = is_array($data['counts'] ?? null) ? $data['counts'] : ['all' => 0, 'active' => 0, 'inactive' => 0, 'automatic' => 0];
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$isEdit = !empty($form['id']);
ob_start();
?>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div>
    <h1><?= admin_e(t('Redirects')); ?></h1>
    <p><?= admin_e(t('Administrér permanente og midlertidige URL-viderestillinger i CMS-kernen.')); ?></p>
  </div>
  <a class="admin-button admin-button--secondary" href="/admin/vaerktoejer"><?= admin_icon('arrow-left', 'admin-button__icon'); ?><?= admin_e(t('Tilbage til Værktøjer')); ?></a>
</div>

<?php if (!empty($notice)): ?>
  <div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div>
<?php endif; ?>
<?php if (!empty($errors['form'])): ?>
  <div class="admin-notice admin-notice--error" role="alert"><?= admin_e(t((string)$errors['form'])); ?></div>
<?php endif; ?>

<div class="admin-tools-grid">
  <section class="admin-panel" aria-labelledby="redirect-form-title">
    <div class="admin-panel__header">
      <h2 id="redirect-form-title"><?= $isEdit ? t('redirects.edit') : t('redirects.new'); ?></h2>
      <?php if ($isEdit): ?><a class="admin-button" href="/admin/vaerktoejer/redirects"><?= admin_icon('plus', 'admin-button__icon'); ?><?= admin_e(t('Opret nyt')); ?></a><?php endif; ?>
    </div>
    <form method="post" action="/admin/vaerktoejer/redirects<?= $isEdit ? '?edit=' . (int)$form['id'] : ''; ?>" class="admin-form-section" data-admin-track-changes>
      <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
      <input type="hidden" name="action" value="save_redirect">
      <input type="hidden" name="id" value="<?= (int)($form['id'] ?? 0); ?>">

      <div class="admin-form-row">
        <div class="admin-field">
          <label for="redirect-source"><?= admin_e(t('Fra URL')); ?></label>
          <input id="redirect-source" type="text" name="source_path" value="<?= admin_e((string)($form['source_path'] ?? '')); ?>" placeholder="/old-page" maxlength="512" required autocomplete="off">
          <p class="admin-field__help"><?= admin_e(t('Intern sti uden domæne, query-parametre eller #fragment.')); ?></p>
        </div>
        <div class="admin-field">
          <label for="redirect-target"><?= admin_e(t('Til URL')); ?></label>
          <input id="redirect-target" type="text" name="target_url" value="<?= admin_e((string)($form['target_url'] ?? '')); ?>" placeholder="/ny-side" maxlength="2048" required autocomplete="off">
          <p class="admin-field__help"><?= admin_e(t('Kan være en intern sti eller en fuld http(s)-URL.')); ?></p>
        </div>
      </div>

      <div class="admin-form-row">
        <div class="admin-field">
          <label for="redirect-type"><?= admin_e(t('Type')); ?></label>
          <select id="redirect-type" name="redirect_type">
            <option value="301" <?= (int)($form['redirect_type'] ?? 301) === 301 ? 'selected' : ''; ?>><?= admin_e(t('301 · Permanent')); ?></option>
            <option value="302" <?= (int)($form['redirect_type'] ?? 301) === 302 ? 'selected' : ''; ?>><?= admin_e(t('302 · Midlertidig')); ?></option>
          </select>
          <p class="admin-field__help"><?= admin_e(t('Brug normalt 301 ved permanente URL-ændringer.')); ?></p>
        </div>
        <div class="admin-field admin-redirect-active-field">
          <span class="admin-field__label"><?= admin_e(t('Status')); ?></span>
          <label class="admin-checkbox"><input type="checkbox" name="is_active" value="1" <?= !empty($form['is_active']) ? 'checked' : ''; ?>><span><?= admin_e(t('Redirectet er aktivt')); ?></span></label>
          <?php if ($isEdit && !empty($editRecord['is_automatic'])): ?><p class="admin-field__help"><?= admin_e(t('Hvis du gemmer ændringer her, bliver redirectet fremover behandlet som manuelt.')); ?></p><?php endif; ?>
        </div>
      </div>

      <div class="admin-form-actions admin-form-actions--left">
        <button class="admin-button" type="submit"><?= $isEdit ? '' : admin_icon('plus', 'admin-button__icon'); ?><?= admin_e(t($isEdit ? 'redirects.save' : 'redirects.create')); ?></button>
        <?php if ($isEdit): ?><a class="admin-button admin-button--secondary" href="/admin/vaerktoejer/redirects"><?= admin_e(t('Annuller')); ?></a><?php endif; ?>
      </div>
    </form>
  </section>

  <section class="admin-panel" aria-labelledby="redirect-list-title" data-admin-live-list>
    <div class="admin-list-toolbar">
      <?= AdminListUI::filterTabs([
        ['value' => '', 'label' => t('Alle'), 'count' => (int)($counts['all'] ?? 0), 'url' => admin_redirects_url(['status' => '', 'page' => null])],
        ['value' => 'active', 'label' => t('redirects.filter_active'), 'count' => (int)($counts['active'] ?? 0), 'url' => admin_redirects_url(['status' => 'active', 'page' => null])],
        ['value' => 'inactive', 'label' => t('redirects.filter_inactive'), 'count' => (int)($counts['inactive'] ?? 0), 'url' => admin_redirects_url(['status' => 'inactive', 'page' => null])],
        ['value' => 'automatic', 'label' => t('redirects.filter_automatic'), 'count' => (int)($counts['automatic'] ?? 0), 'url' => admin_redirects_url(['status' => 'automatic', 'page' => null])],
      ], $status, t('ui.filter_redirects')); ?>
      <form class="admin-list-search" method="get" action="/admin/vaerktoejer/redirects" role="search" data-admin-live-search>
        <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= admin_e($status); ?>"><?php endif; ?>
        <label><span class="sr-only"><?= admin_e(t('Søg redirects')); ?></span><?= admin_icon('search','admin-list-search__icon'); ?><input type="search" name="q" value="<?= admin_e($search); ?>" placeholder="<?= admin_e(t('Søg URL eller side…')); ?>" autocomplete="off"></label>
      
      <noscript><button class="admin-button admin-button--compact" type="submit"><?= admin_e(t('Filtrer')); ?></button></noscript>
    </form>
    </div>
    <div class="admin-list-summary">
      <h2 id="redirect-list-title"><?= admin_e(t('Redirects')); ?></h2>
      <span><?= $total > 0 ? admin_e(t('common.showing', ['from' => $from, 'to' => $to, 'total' => $total])) : t('common.zero_redirects'); ?></span>
    </div>

    <?php if ($items): ?>
      <div class="admin-table-wrap">
        <table class="admin-list-table admin-list-table--redirects">
          <thead><tr><th><?= admin_e(t('Fra')); ?></th><th><?= admin_e(t('Til')); ?></th><th><?= admin_e(t('Type')); ?></th><th><?= admin_e(t('Status')); ?></th><th><?= admin_e(t('Kilde')); ?></th><th><?= admin_e(t('Hits')); ?></th><th><?= admin_e(t('Senest brugt')); ?></th><th class="admin-list-table__actions"><?= admin_e(t('Handlinger')); ?></th></tr></thead>
          <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><code class="admin-redirect-code"><?= admin_e((string)$item['source_path']); ?></code></td>
              <td><code class="admin-redirect-code"><?= admin_e((string)$item['target_url']); ?></code></td>
              <td><?= admin_e(admin_redirect_type_label((int)$item['redirect_type'])); ?></td>
              <td><span class="admin-status-label <?= !empty($item['is_active']) ? 'admin-status-label--module-active' : ''; ?>"><?= !empty($item['is_active']) ? t('Aktiv') : t('Inaktiv'); ?></span></td>
              <td>
                <?= !empty($item['is_automatic']) ? 'Automatisk' : 'Manuel'; ?>
                <?php if (!empty($item['page_title'])): ?><span class="admin-table-secondary"><?= admin_e((string)$item['page_title']); ?></span><?php endif; ?>
              </td>
              <td><?= number_format((int)($item['hit_count'] ?? 0), 0, ',', '.'); ?></td>
              <td><?= !empty($item['last_hit_at']) ? admin_e(date('d.m.Y H:i', strtotime((string)$item['last_hit_at']))) : '—'; ?></td>
              <td class="admin-list-table__actions">
                <div class="admin-table-actions">
                  <a class="admin-button admin-button--secondary admin-button--compact" href="<?= admin_e(admin_redirects_url(['edit'=>(int)$item['id']])); ?>"><?= admin_e(t('Rediger')); ?></a>
                  <button class="admin-button admin-button--danger-outline admin-button--compact" type="button"
                          data-admin-open-delete="redirect-list-delete-dialog"
                          data-redirect-delete-id="<?= (int)$item['id']; ?>"
                          data-redirect-delete-source="<?= admin_e((string)$item['source_path']); ?>"
                          aria-label="<?= admin_e(t('redirects.delete') . ': ' . (string)$item['source_path']); ?>"><?= admin_e(t('Slet')); ?></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php admin_render_pagination($page, $pages, static fn(int $targetPage): string => admin_redirects_url(['page'=>$targetPage]), 'Redirects'); ?>
    <?php else: ?>
      <div class="admin-empty-state admin-empty-state--large">
        <?= admin_icon('external', 'admin-empty-state__icon'); ?>
        <strong><?= admin_e(t('Ingen redirects fundet')); ?></strong>
        <span><?= $search !== '' || $status !== '' ? t('redirects.empty_filtered') : t('redirects.empty_initial'); ?></span>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php // One Core confirmation dialog outside the live-search list; row controls survive DOM replacement. ?>
<?= AdminDeleteDialog::render([
    'id' => 'redirect-list-delete-dialog',
    'titleId' => 'redirect-list-delete-title',
    'title' => t('redirects.delete'),
    'warning' => t('Handlingen kan ikke fortrydes.'),
    'action' => '/admin/vaerktoejer/redirects',
    'csrfToken' => admin_csrf_token(),
    'fields' => [
        'action' => 'delete_redirect',
        'id' => ['value' => '', 'attributes' => ['data-redirect-list-delete-id' => true]],
    ],
    'prefix' => t('redirects.delete_prompt'),
    'subject' => '',
    'subjectAttributes' => ['data-redirect-list-delete-source' => true],
    'suffix' => t('redirects.delete_suffix'),
    'confirmLabel' => t('redirects.delete'),
    'cancelLabel' => t('Annuller'),
    'closeLabel' => t('Luk'),
]); ?>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'Redirects';
require __DIR__ . '/../partials/layout.php';
