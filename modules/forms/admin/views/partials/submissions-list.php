<?php
$submissions=is_array($submissions??null)?$submissions:[];
$forms=is_array($forms??null)?$forms:[];
$selectedFormId=max(0,(int)($selectedFormId??0));
$selectedStatus=in_array((string)($selectedStatus??''),['new','read'],true)?(string)$selectedStatus:'';
$search=trim((string)($search??''));
$filterCounts=is_array($filterCounts??null)?$filterCounts:[
  ''=>(int)($total??count($submissions)),
  'new'=>count(array_filter($submissions,static fn(array $item):bool=>($item['status']??'new')==='new')),
  'read'=>count(array_filter($submissions,static fn(array $item):bool=>($item['status']??'new')==='read')),
];
$query=[];
if($selectedFormId>0)$query['form_id']=$selectedFormId;
if($selectedStatus!=='')$query['status']=$selectedStatus;
if($search!=='')$query['q']=$search;
$paging=is_array($paging??null)?$paging:admin_crud_pagination((int)($total??0));
$query['page']=$paging['page'];
$returnUrl='/admin/forms/submissions'.($query?'?'.http_build_query($query):'');
?>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<section class="admin-panel" data-admin-live-list>
  <div class="admin-list-toolbar">
    <?= AdminListUI::filterTabs(array_map(
      static fn(string $value, string $label): array => [
        'value' => $value,
        'label' => t($label),
        'count' => (int)($filterCounts[$value] ?? 0),
        'url' => '/admin/forms/submissions?' . http_build_query(array_filter([
          'form_id' => $selectedFormId > 0 ? $selectedFormId : '',
          'status' => $value,
          'q' => $search,
        ], static fn($v) => $v !== '')),
      ],
      ['', 'new', 'read'], ['Alle', 'forms:Ulæste', 'forms:Læste']
    ), $selectedStatus, t('forms:Filtrer indsendelsesstatus')); ?>
    <form class="admin-list-search" method="get" action="/admin/forms/submissions" role="search" data-admin-live-search>
      <?php if ($selectedFormId > 0): ?><input type="hidden" name="form_id" value="<?= $selectedFormId; ?>"><?php endif; ?>
      <?php if ($selectedStatus !== ''): ?><input type="hidden" name="status" value="<?= admin_e($selectedStatus); ?>"><?php endif; ?>
      <label><span class="sr-only"><?= admin_e(t('forms:Søg i indsendelser')); ?></span><?= admin_icon('search', 'admin-list-search__icon'); ?><input type="search" name="q" value="<?= admin_e($search); ?>" placeholder="<?= admin_e(t('Søg i indsendelser…')); ?>" autocomplete="off"></label>
      <noscript><button class="admin-button admin-button--compact" type="submit"><?= admin_e(t('Filtrer')); ?></button></noscript>
    </form>
  </div>
  <div class="admin-list-summary"><h2><?= admin_e(t('forms:Indsendelser')); ?></h2><span><?= (int)($total??0)>0 ? admin_e(t('common.showing', ['from'=>$paging['from'], 'to'=>$paging['to'], 'total'=>(int)$total])) : '0'; ?></span></div>
  <?php if($submissions): ?><div class="admin-table-wrap"><table class="admin-list-table"><thead><tr><th><?= admin_e(t('forms:Formular')); ?></th><th><?= admin_e(t('Status')); ?></th><th><?= admin_e(t('Kilde')); ?></th><th><?= admin_e(t('forms:Modtaget')); ?></th><th class="admin-list-table__actions"><?= admin_e(t('Handlinger')); ?></th></tr></thead><tbody>
  <?php foreach($submissions as $submission): $isNew=($submission['status']??'new')==='new'; ?><tr>
    <td><a class="admin-table-primary" href="/admin/forms/submission?id=<?= (int)$submission['id']; ?>"><?= admin_e((string)$submission['form_name']); ?></a></td>
    <td><span class="admin-status-label <?= $isNew?'admin-status-label--module-active':''; ?>"><?= admin_e(t($isNew ? 'forms:Ulæst' : 'forms:Læst')); ?></span></td>
    <td><code><?= admin_e((string)($submission['source_url']??'')); ?></code></td>
    <td><?= admin_e(date('d.m.Y H:i',strtotime((string)$submission['submitted_at']))); ?></td>
    <td class="admin-list-table__actions">
      <?= AdminListUI::actionGroup([
        ['icon' => 'eye', 'label' => t('forms:Åbn') . ': ' . (string)$submission['form_name'], 'url' => '/admin/forms/submission?id=' . (int)$submission['id']],
        ['type' => 'button', 'icon' => $isNew ? 'mail' : 'check', 'label' => t($isNew ? 'forms:Markér læst' : 'forms:Markér ulæst') . ': ' . (string)$submission['form_name'], 'attributes' => [
          'data-forms-mark-submission' => '1',
        ]],
        ['type' => 'button', 'icon' => 'delete', 'label' => t('Slet') . ': ' . (string)$submission['form_name'], 'tone' => 'danger', 'attributes' => [
          'data-forms-delete-submission' => '1',
          'data-submission-id' => (string)(int)$submission['id'],
          'data-submission-name' => (string)$submission['form_name'],
        ]],
      ], t('Handlinger') . ': ' . (string)$submission['form_name']); ?>
      <?php // Keep the original server-owned POST request; the Core status icon triggers it below. ?>
      <form method="post" action="/admin/forms/submissions" data-forms-status-post hidden>
        <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
        <input type="hidden" name="id" value="<?= (int)$submission['id']; ?>">
        <input type="hidden" name="return_url" value="<?= admin_e($returnUrl); ?>">
        <input type="hidden" name="submission_action" value="<?= $isNew?'mark_read':'mark_unread'; ?>">
      </form>
    </td>
  </tr><?php endforeach; ?>
  </tbody></table></div><?php else: ?><div class="admin-empty-state admin-empty-state--large"><?= admin_icon('mail','admin-empty-state__icon'); ?><strong><?= admin_e(t('forms:Ingen indsendelser fundet')); ?></strong><span><?= admin_e(t('forms:Prøv at ændre filtrene, eller send en formular fra frontend.')); ?></span></div><?php endif; ?>
  <?php admin_render_pagination((int)$paging['page'], (int)$paging['pages'], static function (int $p) use ($query): string { $params = $query; $params['page'] = $p; return '/admin/forms/submissions?' . http_build_query($params); }, t('forms:Indsendelser')); ?>
</section>

<?= AdminDeleteDialog::render([
    'id' => 'forms-submissions-delete-dialog',
    'titleId' => 'forms-delete-title',
    'dialogAttributes' => ['data-forms-delete-dialog' => true],
    'title' => t('forms:Slet indsendelse'),
    'warning' => t('forms:Denne handling kan ikke fortrydes.'),
    'action' => '/admin/forms/submissions',
    'csrfToken' => admin_csrf_token(),
    'fields' => [
        'id' => ['value' => '', 'attributes' => ['data-forms-delete-id' => true]],
        'submission_action' => 'delete',
        'return_url' => $returnUrl,
    ],
    'prefix' => t('forms:Er du sikker på, at du vil slette indsendelsen fra'),
    'subject' => t('forms:formularen'),
    'subjectAttributes' => ['data-forms-delete-name' => true],
    'suffix' => '?',
    'confirmLabel' => t('forms:Slet indsendelse'),
    'closeLabel' => t('Luk'),
    'cancelLabel' => t('Annuller'),
]); ?>
<script>(()=>{const d=document.querySelector('[data-forms-delete-dialog]');if(!d)return;document.addEventListener('click',(event)=>{const mark=event.target.closest('[data-forms-mark-submission]');if(mark){const cell=mark.closest('td');const form=cell&&cell.querySelector('form[data-forms-status-post]');if(form&&typeof form.requestSubmit==='function')form.requestSubmit();return;}const b=event.target.closest('[data-forms-delete-submission]');if(!b)return;d.querySelector('[data-forms-delete-id]').value=b.dataset.submissionId||'';d.querySelector('[data-forms-delete-name]').textContent=b.dataset.submissionName||'formularen';const returnField=d.querySelector('[name=return_url]');if(returnField)returnField.value=location.pathname+location.search;if(typeof d.showModal==='function')d.showModal();});})();</script>
