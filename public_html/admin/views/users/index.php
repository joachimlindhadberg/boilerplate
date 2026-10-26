<?php
$data = is_array($usersData ?? null) ? $usersData : [];
$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$counts = is_array($data['counts'] ?? null) ? $data['counts'] : ['all'=>0,'active'=>0,'inactive'=>0,'banned'=>0];
$roles = is_array($data['roles'] ?? null) ? $data['roles'] : [];
$search = (string)($data['search'] ?? '');
$status = (string)($data['status'] ?? '');
$roleId = max(0, (int)($data['roleId'] ?? 0));
$page = max(1, (int)($data['page'] ?? 1));
$pages = max(1, (int)($data['pages'] ?? 1));
$total = max(0, (int)($data['total'] ?? 0));
$from = max(0, (int)($data['from'] ?? 0));
$to = max(0, (int)($data['to'] ?? 0));
$currentUserId = (int)($user['id'] ?? 0);
$activeAdminCount = max(0, (int)($data['activeAdminCount'] ?? 0));
$filters = [
    '' => ['Alle', (int)($counts['all'] ?? 0)],
    'active' => ['Aktive', (int)($counts['active'] ?? 0)],
    'inactive' => ['Inaktive', (int)($counts['inactive'] ?? 0)],
    'banned' => ['Blokerede', (int)($counts['banned'] ?? 0)],
];
$formatDate = static function (?string $value): string {
    if (!$value) return 'Aldrig';
    $t = strtotime($value);
    return $t === false ? '—' : date('d.m.Y H:i', $t);
};
ob_start();
?>
<div class="admin-page-titlebar admin-page-titlebar--split">
  <div><h1><?= admin_e(t('Brugere')); ?></h1><p><?= admin_e(t('Administrér CMS-adgang, roller og brugerkonti.')); ?></p></div>
  <a class="admin-button" href="/admin/brugere?mode=new"><?= admin_icon('plus', 'admin-button__icon'); ?><?= admin_e(t('Ny bruger')); ?></a>
</div>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<section class="admin-panel" aria-labelledby="users-list-title" data-admin-live-list>
  <div class="admin-list-toolbar">
    <?= AdminListUI::filterTabs(array_map(
      static fn(string $filterStatus, array $filter): array => [
        'value' => $filterStatus,
        'label' => t((string)$filter[0]),
        'count' => (int)$filter[1],
        'url' => admin_users_url(['status'=>$filterStatus,'page'=>null]),
      ],
      array_keys($filters), array_values($filters)
    ), $status, t('ui.filter_users_status')); ?>
    <form class="admin-list-search admin-list-search--filters" method="get" action="/admin/brugere" role="search" data-admin-live-search>
      <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= admin_e($status); ?>"><?php endif; ?>
      <select name="role" aria-label="<?= admin_e(t('Rolle')); ?>">
        <option value=""><?= admin_e(t('Alle roller')); ?></option>
        <?php foreach ($roles as $role): $rid=(int)($role['id']??0); ?><option value="<?= $rid; ?>" <?= $roleId===$rid?'selected':''; ?>><?= admin_e(admin_user_role_label((string)($role['role_name']??''))); ?></option><?php endforeach; ?>
      </select>
      <label><span class="sr-only"><?= admin_e(t('Søg brugere')); ?></span><?= admin_icon('search','admin-list-search__icon'); ?><input type="search" name="q" value="<?= admin_e($search); ?>" placeholder="<?= admin_e(t('Søg navn, e-mail, telefon…')); ?>" autocomplete="off"></label>
      <noscript><button class="admin-button admin-button--compact" type="submit"><?= admin_e(t('Filtrer')); ?></button></noscript>
    </form>
  </div>
  <div class="admin-list-summary"><h2 id="users-list-title"><?= admin_e(t('Brugerkonti')); ?></h2><span><?= $total > 0 ? admin_e(t('common.showing', ['from' => $from, 'to' => $to, 'total' => $total])) : t('common.zero_users'); ?></span></div>
  <?php if ($items): ?>
    <div class="admin-table-wrap"><table class="admin-list-table"><thead><tr><th><?= admin_e(t('Bruger')); ?></th><th><?= admin_e(t('Rolle')); ?></th><th><?= admin_e(t('Status')); ?></th><th><?= admin_e(t('Seneste login')); ?></th><th class="admin-list-table__actions"><?= admin_e(t('Handlinger')); ?></th></tr></thead><tbody>
      <?php foreach ($items as $item): $uid=(int)($item['id']??0); $name=trim((string)($item['firstname']??'').' '.(string)($item['lastname']??'')); $st=(string)($item['status']??''); $rowLabel=$name !== '' ? $name : 'Bruger #' . $uid; $deleteBlocked=$uid===$currentUserId || (($item['role_name']??'')==='admin' && $st==='active' && $activeAdminCount < 2); $deleteTitle=$uid===$currentUserId ? t('users.cannot_delete_self') : ((($item['role_name']??'')==='admin' && $st==='active' && $activeAdminCount<2) ? t('users.cannot_delete_last_admin') : t('Slet bruger') . ': ' . $rowLabel); ?>
      <tr>
        <td><a class="admin-table-primary" href="/admin/brugere?mode=view&amp;id=<?= $uid; ?>"><?= admin_e($name !== '' ? $name : 'Bruger #' . $uid); ?><?= $uid===$currentUserId?' · Dig':''; ?></a><span class="admin-table-secondary"><?= admin_e((string)($item['email']??'—')); ?></span></td>
        <td><?= admin_e(admin_user_role_label((string)($item['role_name']??''))); ?></td>
        <td><span class="admin-status-label admin-status-label--<?= admin_e(admin_user_status_class($st)); ?>"><?= admin_e(admin_user_status_label($st)); ?></span></td>
        <td><?= admin_e($formatDate($item['last_login']??null)); ?></td>
        <td class="admin-list-table__actions"><?= AdminListUI::actionGroup([
          ['icon' => 'eye', 'label' => t('Vis') . ': ' . $rowLabel, 'url' => '/admin/brugere?mode=view&id=' . $uid],
          ['icon' => 'edit', 'label' => t('Rediger') . ': ' . $rowLabel, 'url' => '/admin/brugere?mode=edit&id=' . $uid],
          ['type' => 'button', 'icon' => 'delete', 'label' => $deleteTitle, 'tone' => 'danger', 'attributes' => [
            'data-admin-open-delete' => 'user-list-delete-dialog',
            'data-user-delete-id' => (string)$uid,
            'data-user-delete-name' => $rowLabel,
            'disabled' => $deleteBlocked,
          ]],
        ], t('Handlinger') . ': ' . $rowLabel); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php admin_render_pagination($page, $pages, static fn(int $targetPage): string => admin_users_url(['page'=>$targetPage]), 'Brugere'); ?>
  <?php else: ?>
    <div class="admin-empty-state admin-empty-state--large"><?= admin_icon('user','admin-empty-state__icon'); ?><strong><?= admin_e(t('Ingen brugere fundet')); ?></strong><span><?= $search !== '' || $status !== '' || $roleId > 0 ? 'Prøv at ændre søgningen eller filtrene.' : 'Opret den første ekstra bruger for at komme i gang.'; ?></span></div>
  <?php endif; ?>
</section>
<?php // One Core-owned delete dialog, outside live-search replacement. ?>
<?= AdminDeleteDialog::render([
    'id' => 'user-list-delete-dialog',
    'titleId' => 'user-list-delete-title',
    'title' => t('Slet bruger'),
    'warning' => t('Handlingen kan ikke fortrydes.'),
    'action' => '/admin/brugere?mode=delete',
    'csrfToken' => admin_csrf_token(),
    'fields' => [
        'id' => ['value' => '', 'attributes' => ['data-user-list-delete-id' => true]],
        'confirm_delete' => 'permanent',
    ],
    'prefix' => t('Vil du slette brugeren'),
    'subject' => '',
    'subjectAttributes' => ['data-user-list-delete-name' => true],
    'suffix' => t('permanent?'),
    'confirmLabel' => t('Slet bruger'),
    'cancelLabel' => t('Annuller'),
    'closeLabel' => t('Luk'),
]); ?>
<?php
$bodyHtml = ob_get_clean();
$documentTitle = 'Brugere';
require __DIR__ . '/../partials/layout.php';
