<?php
// New reuses the same form fields; only the mode changes.
$isEdit = false;
require __DIR__ . '/partials/form-context.php';
require __DIR__ . '/partials/form-fields.php';
$bodyHtml = ob_get_clean();
$documentTitle = $isEdit ? 'Rediger bruger' : 'Ny bruger';
require __DIR__ . '/../partials/layout.php';
