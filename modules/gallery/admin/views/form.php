<?php

declare(strict_types=1);

/**
 * Compatibility entry point for a GalleryModule.php still running the previous
 * adminGalleries() implementation during/after a ZIP deployment. Both paths
 * use the same presentation partial; no duplicate form or frontend styles.
 *
 * Keep this adapter until the V2.4.4s controller migration is confirmed on all
 * deployed installations (including PHP OPcache reset).
 */
require __DIR__ . '/partials/gallery-editor.php';
