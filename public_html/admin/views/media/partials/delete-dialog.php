<?php
/** z16: Media's permanent deletion is confirmed through the shared Core dialog.
 * The metadata editor stays separate; only the delete action submits this POST.
 */
echo AdminDeleteDialog::render([
    'id' => 'media-delete-dialog',
    'titleId' => 'media-delete-title',
    'title' => t('Slet medie'),
    'warning' => t('ui.media_delete_warning'),
    'action' => '/admin/medier?mode=delete',
    'csrfToken' => admin_csrf_token(),
    'fields' => [
        'id' => ['value' => '', 'attributes' => ['data-media-delete-id' => true]],
    ],
    'prefix' => t('ui.media_delete_prompt'),
    'subject' => '',
    'subjectAttributes' => ['data-media-delete-name' => true],
    'suffix' => '',
    'confirmLabel' => t('Slet medie'),
    'cancelLabel' => t('Annuller'),
    'closeLabel' => t('Luk'),
]);
