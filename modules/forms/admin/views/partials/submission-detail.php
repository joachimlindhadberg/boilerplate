<?php
$submission=is_array($submission??null)?$submission:[];
$data=is_array($submission['data']??null)?$submission['data']:[];
$id=(int)($submission['id']??0);
$formId=(int)($submission['form_id']??0);
?>
<div class="admin-detail-back"><a class="admin-back-link" href="/admin/forms/submissions?form_id=<?= $formId; ?>"><?= admin_icon('arrow-left', 'admin-back-link__icon'); ?><?= admin_e(t('forms:Tilbage til indsendelser')); ?></a></div>
<?php if (!empty($notice)): ?><div class="admin-notice admin-notice--<?= admin_e((string)($notice['type'] ?? 'success')); ?>" role="status"><?= admin_e((string)($notice['message'] ?? '')); ?></div><?php endif; ?>
<header class="admin-detail-header">
  <div class="admin-detail-header__copy">
    <h2><?= admin_e((string)($submission['form_name'] ?? t('forms:Indsendelser'))); ?></h2>
    <p><?= admin_e(t('forms:Modtaget')); ?> <?= !empty($submission['submitted_at']) ? admin_e(date('d.m.Y H:i', strtotime((string)$submission['submitted_at']))) : '—'; ?></p>
  </div>
  <div class="admin-page-actions">
    <form method="post" action="/admin/forms/submission?id=<?= $id; ?>">
      <input type="hidden" name="csrf_token" value="<?= admin_e(admin_csrf_token()); ?>">
      <input type="hidden" name="id" value="<?= $id; ?>">
      <input type="hidden" name="submission_action" value="mark_unread">
      <button class="admin-button admin-button--secondary" type="submit"><?= admin_e(t('forms:Markér ulæst')); ?></button>
    </form>
    <button class="admin-button admin-button--danger-outline" type="button" data-forms-delete-submission><?= admin_e(t('Slet')); ?></button>
  </div>
</header>
<div class="admin-detail-grid">
  <div class="admin-detail-content">
    <section class="admin-panel">
      <div class="admin-panel__header"><h2><?= admin_e(t('forms:Data')); ?></h2></div>
      <dl class="admin-meta-list admin-meta-list--wide">
        <?php foreach ($data as $item): ?>
        <div><dt><?= admin_e((string)($item['label'] ?? $item['key'] ?? t('Felt'))); ?></dt><dd><?= nl2br(admin_e((string)($item['value'] ?? ''))); ?></dd></div>
        <?php endforeach; ?>
      </dl>
    </section>
  </div>
  <aside class="admin-detail-sidebar">
    <section class="admin-panel">
      <div class="admin-panel__header"><h2><?= admin_e(t('forms:Metadata')); ?></h2></div>
      <dl class="admin-meta-list">
        <div><dt><?= admin_e(t('Status')); ?></dt><dd><?= admin_e(t('forms:Læst')); ?></dd></div>
        <div><dt><?= admin_e(t('Kilde')); ?></dt><dd><code><?= admin_e((string)($submission['source_url'] ?? '')); ?></code></dd></div>
        <div><dt><?= admin_e(t('forms:ID')); ?></dt><dd><?= $id; ?></dd></div>
      </dl>
    </section>
  </aside>
</div>
<?= AdminDeleteDialog::render([
    'id' => 'forms-submission-delete-dialog',
    'titleId' => 'forms-delete-title',
    'dialogAttributes' => ['data-forms-delete-dialog' => true],
    'title' => t('forms:Slet indsendelse'),
    'warning' => t('forms:Denne handling kan ikke fortrydes.'),
    'action' => '/admin/forms/submission?id=' . $id,
    'csrfToken' => admin_csrf_token(),
    'fields' => ['id' => $id, 'submission_action' => 'delete'],
    'prefix' => t('forms:Er du sikker på, at du vil slette denne indsendelse permanent?'),
    'subject' => '',
    'suffix' => '',
    'confirmLabel' => t('forms:Slet indsendelse'),
    'closeLabel' => t('Luk'),
    'cancelLabel' => t('Annuller'),
]); ?>
<script>(()=>{const b=document.querySelector('[data-forms-delete-submission]'),d=document.querySelector('[data-forms-delete-dialog]');if(b&&d)b.addEventListener('click',()=>{if(typeof d.showModal==='function')d.showModal();});})();</script>
