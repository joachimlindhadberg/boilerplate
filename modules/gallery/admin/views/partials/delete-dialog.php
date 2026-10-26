<?php
/**
 * Gallery permanent-delete confirmation, rendered by the shared Core component.
 * The Gallery controller/repository remain responsible for CSRF and page usage checks.
 */
if ($isEdit && $galleryId > 0):
    echo AdminDeleteDialog::render([
        'id' => 'gallery-delete-dialog',
        'titleId' => 'gallery-delete-title',
        'dialogAttributes' => ['data-gallery-delete-dialog' => true],
        'title' => t('gallery:Slet galleri?'),
        'warning' => t('gallery:Denne handling kan ikke fortrydes.'),
        // Core escapes the raw ampersand; do not pass HTML-encoded &amp; here.
        'action' => '/admin/gallerier?screen=edit&id=' . $galleryId,
        'csrfToken' => admin_csrf_token(),
        // Previously gallery_action=delete came from the clicked submit button.
        // Core's submit button has no name, so send the action as a hidden field.
        'fields' => ['id' => $galleryId, 'gallery_action' => 'delete'],
        'prefix' => t('gallery:Galleri'),
        'subject' => (string)($galleryData['name'] ?? ''),
        'suffix' => '– ' . t('gallery:Galleriet kan kun slettes, hvis det ikke længere bruges på en side. Billederne i Medier bliver ikke slettet.'),
        'confirmLabel' => t('gallery:Slet galleri'),
        'cancelLabel' => t('Annuller'),
        'closeLabel' => t('Luk'),
    ]);
endif;
