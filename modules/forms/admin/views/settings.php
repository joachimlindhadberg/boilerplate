<?php
// Forms module view. CMS Core owns the admin layout; the shared workspace
// adapter renders this screen and its related content partial.
(require dirname(__DIR__) . '/workspace.php')('settings', get_defined_vars());
