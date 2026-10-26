<?php
$forms = is_array($forms ?? null) ? $forms : [];
$deleteDependenciesById = is_array($deleteDependenciesById ?? null) ? $deleteDependenciesById : [];
$search = (string)($search ?? '');
$status = in_array((string)($status ?? ''), ['active', 'inactive'], true) ? (string)$status : '';
$filterCounts = is_array($filterCounts ?? null) ? $filterCounts : [
    '' => count($forms),
    'active' => count(array_filter($forms, static fn(array $form): bool => ($form['status'] ?? 'active') === 'active')),
    'inactive' => count(array_filter($forms, static fn(array $form): bool => ($form['status'] ?? 'active') !== 'active')),
];
$page = max(1, (int)($page ?? 1));
$pages = max(1, (int)($pages ?? 1));
$total = max(0, (int)($total ?? 0));
$from = max(0, (int)($from ?? 0));
$to = max(0, (int)($to ?? 0));
?>
<?php if (!empty($notice)): ?>
  <div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div>
<?php endif; ?>

<section class="admin-panel" aria-labelledby="forms-list-title" data-admin-live-list>
  <div class="admin-list-toolbar">
    <?= AdminListUI::filterTabs(array_map(
      static fn(string $value, string $label): array => [
        'value' => $value,
        'label' => t($label),
        'count' => (int)($filterCounts[$value] ?? 0),
        'url' => '/admin/forms?' . http_build_query(array_filter([
            'status' => $value, 'q' => $search,
        ], static fn($v) => $v !== '')),
      ],
      ['', 'active', 'inactive'], ['Alle', 'forms:Aktive', 'forms:Inaktive']
    ), $status, t('forms:Filtrer formularstatus')); ?>
    <form class="admin-list-search" method="get" action="/admin/forms" role="search" data-admin-live-search>
      <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= admin_e($status); ?>"><?php endif; ?>
      <label><span class="sr-only"><?= admin_e(t('forms:Søg formularer')); ?></span><?= admin_icon('search', 'admin-list-search__icon'); ?><input type="search" name="q" value="<?= admin_e($search); ?>" placeholder="<?= admin_e(t('forms:Søg formularer…')); ?>" autocomplete="off"></label>
      <noscript><button class="admin-button admin-button--compact" type="submit"><?= admin_e(t('Filtrer')); ?></button></noscript>
    </form>
  </div>
  <div class="admin-list-summary">
    <h2 id="forms-list-title"><?= admin_e(t('forms:Formularer')); ?></h2>
    <span><?= $total > 0 ? admin_e(t('common.showing', ['from'=>$from, 'to'=>$to, 'total'=>$total])) : '0'; ?></span>
  </div>

  <?php if ($forms): ?>
    <div class="admin-table-wrap">
      <table class="admin-list-table">
        <thead><tr><th><?= admin_e(t('forms:Formular')); ?></th><th><?= admin_e(t('Status')); ?></th><th><?= admin_e(t('forms:Felter')); ?></th><th><?= admin_e(t('forms:Indsendelser')); ?></th><th><?= admin_e(t('forms:Nye')); ?></th><th><?= admin_e(t('Opdateret')); ?></th><th class="admin-list-table__actions"><?= admin_e(t('Handlinger')); ?></th></tr></thead>
        <tbody>
        <?php foreach ($forms as $form):
          $id = (int)$form['id'];
          $active = ($form['status'] ?? 'active') === 'active';
          // Missing dependency information fails closed, never activates Delete.
          $dependencies = $deleteDependenciesById[$id] ?? null;
          $hasSubmissions = $dependencies === null || (int)($dependencies['submissions'] ?? 0) > 0;
          $usedOnPage = $dependencies === null || (int)($dependencies['pages'] ?? 0) > 0;
          $canDelete = $id > 0 && !$hasSubmissions && !$usedOnPage;
          $deleteHelp = $hasSubmissions
            ? t('forms:Formularen har gemte indsendelser. Slet dem først, eller deaktivér formularen.')
            : ($usedOnPage ? t('forms:Formularen bruges i Page Builder. Fjern den fra siderne først.') : '');
        ?>
          <tr>
            <td><a class="admin-table-primary" href="/admin/forms?screen=edit&amp;id=<?= $id; ?>"><?= admin_e((string)$form['name']); ?></a><span class="admin-table-secondary"><?= admin_e(t('forms:forms_page_builder_component')); ?> · <?= $id; ?></span></td>
            <td><span class="admin-status-label <?= $active ? 'admin-status-label--module-active' : ''; ?>"><?= $active ? t('Aktiv') : t('Inaktiv'); ?></span></td>
            <td><?= (int)($form['field_count'] ?? 0); ?></td>
            <td><?= (int)($form['submission_count'] ?? 0); ?></td>
            <td><?= (int)($form['new_submission_count'] ?? 0); ?></td>
            <td><?= !empty($form['updated_at']) ? admin_e(date('d.m.Y H:i', strtotime((string)$form['updated_at']))) : '—'; ?></td>
            <td class="admin-list-table__actions"><?= AdminListUI::actionGroup([
              ['icon' => 'edit', 'label' => t('Rediger') . ': ' . (string)$form['name'], 'url' => '/admin/forms?screen=edit&id=' . $id],
              ['type' => 'button', 'icon' => 'delete', 'label' => t('Slet') . ': ' . (string)$form['name'], 'tone' => 'danger', 'attributes' => [
                'data-admin-open-delete' => 'forms-list-delete-dialog',
                'data-forms-list-delete-id' => (string)$id,
                'data-forms-list-delete-name' => (string)$form['name'],
                'disabled' => !$canDelete,
                'title' => $canDelete ? t('Slet') . ': ' . (string)$form['name'] : $deleteHelp,
              ]],
            ], t('Handlinger') . ': ' . (string)$form['name']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php admin_render_pagination($page, $pages, static function (int $targetPage) use ($search, $status): string { return '/admin/forms?' . http_build_query(array_filter(['status' => $status, 'q' => $search, 'page' => $targetPage], static fn($value) => $value !== '')); }, t('forms:Formularer')); ?>
  <?php else: ?>
    <div class="admin-empty-state admin-empty-state--large">
      <?= admin_icon('mail', 'admin-empty-state__icon'); ?>
      <strong><?= admin_e(t($search !== '' ? 'forms:Ingen formularer fundet' : 'forms:Ingen formularer endnu')); ?></strong>
      <span><?= admin_e(t($search !== '' ? 'forms:Prøv en anden søgning.' : 'forms:Opret den første formular og indsæt den derefter via Page Builderens dynamiske komponent.')); ?></span>
    </div>
  <?php endif; ?>
</section>
<p class="admin-list-utilities"><a href="/admin/forms/settings"><?= admin_e(t('forms:Globale Forms-indstillinger')); ?></a></p>

<?php // The shared modal is intentionally outside the live-search replacement region. ?>
<?= AdminDeleteDialog::render([
    'id' => 'forms-list-delete-dialog',
    'titleId' => 'forms-list-delete-dialog-title',
    'title' => t('forms:Slet formular'),
    'warning' => t('forms:Denne handling kan ikke fortrydes.'),
    // The existing editor controller also accepts ID in POST when GET omits it.
    'action' => '/admin/forms?screen=edit',
    'csrfToken' => admin_csrf_token(),
    'fields' => [
        'id' => ['value' => '', 'attributes' => ['data-forms-list-delete-target' => true]],
        'form_action' => 'delete',
        'confirm_delete' => 'permanent',
    ],
    'prefix' => t('forms:Er du sikker på, at du vil slette formularen'),
    'subject' => '',
    'subjectAttributes' => ['data-forms-list-delete-subject' => true],
    'suffix' => '?',
    'confirmLabel' => t('forms:Slet formular'),
    'closeLabel' => t('Luk'),
    'cancelLabel' => t('Annuller'),
]); ?>
