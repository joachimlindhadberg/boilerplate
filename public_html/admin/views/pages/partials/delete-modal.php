<?php
/** Core-owned permanent delete dialog, only for deletable existing pages. */
if (!$isSystem && (int)($childCount ?? 0) === 0):
    echo AdminDeleteDialog::render([
        'id' => 'page-delete-dialog',
        'titleId' => 'page-delete-title',
        'title' => t('Slet side'),
        'warning' => t('Handlingen kan ikke fortrydes.'),
        'action' => '/admin/sider?mode=delete&id=' . (int)$id,
        'csrfToken' => admin_csrf_token(),
        'fields' => [
            'id' => (int)$id,
            'confirm_delete' => 'permanent',
            'return_to' => $deleteReturnMode ?? 'view',
        ],
        'prefix' => t('Vil du slette siden'),
        'subject' => (string)($pageRecord['page_title'] ?? ''),
        'suffix' => t('permanent? Sidens Page Builder-indhold og versionshistorik bliver også slettet.'),
        'confirmLabel' => t('Slet side'),
        'cancelLabel' => t('Annuller'),
        'closeLabel' => t('Luk'),
    ]);
endif;
