<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/core/classes/CapabilityRegistry.php';
require_once dirname(__DIR__) . '/core/classes/LegacyHtmlPolicy.php';

$passes = 0;
$assert = static function (bool $condition, string $label) use (&$passes): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    ++$passes;
    echo "PASS: {$label}\n";
};
$throws = static function (callable $f) use ($assert): void {
    try {
        $f();
    } catch (InvalidArgumentException $e) {
        $assert(true, 'Unauthorized save rejected with validation error');
        return;
    }
    $assert(false, 'Unauthorized save rejected with validation error');
};

$builder = ['content_mode'=>'builder','page_content'=>'<script>historical()</script>'];
$legacy = ['content_mode'=>'legacy','page_content'=>'<script>trustedAdmin()</script>'];
$assert(CapabilityRegistry::allows('admin','content.html.unfiltered'), 'Admin owns raw HTML privilege');
$assert(!CapabilityRegistry::allows('editor','content.html.unfiltered'), 'Editor cannot use raw HTML');
$assert(!CapabilityRegistry::allows('user','content.html.unfiltered'), 'Ordinary user cannot use raw HTML');
$assert(LegacyHtmlPolicy::canEditPage($legacy,'admin'), 'Admin can edit existing legacy pages');
$assert(!LegacyHtmlPolicy::canEditPage($legacy,'editor'), 'Editor cannot edit existing legacy pages');
$assert(LegacyHtmlPolicy::canEditPage($builder,'editor'), 'Editor can edit builder pages');
$assert(!LegacyHtmlPolicy::canEditPage($builder,'user'), 'Unprivileged account cannot edit builder pages');
$assert(LegacyHtmlPolicy::prepareSave($legacy,null,'admin') === $legacy, 'Admin retains raw HTML on create');
$assert(LegacyHtmlPolicy::prepareSave($legacy,$builder,'admin') === $legacy, 'Admin can switch to legacy mode');
$throws(fn()=>LegacyHtmlPolicy::prepareSave($legacy,null,'editor'));
$throws(fn()=>LegacyHtmlPolicy::prepareSave($legacy,$builder,'editor'));
$throws(fn()=>LegacyHtmlPolicy::prepareSave(['content_mode'=>'builder','page_content'=>'injected'],$legacy,'editor'));
$throws(fn()=>LegacyHtmlPolicy::prepareSave($builder,null,'user'));
$created = LegacyHtmlPolicy::prepareSave(['content_mode'=>'builder','page_content'=>'<script>x()</script>'],null,'editor');
$assert($created['page_content'] === '', 'Editor cannot smuggle raw HTML in new builder page');
$edited = LegacyHtmlPolicy::prepareSave(['content_mode'=>'builder','page_content'=>'<script>new()</script>'],$builder,'editor');
$assert($edited['page_content'] === $builder['page_content'], 'Editor does not overwrite preserved legacy text');
$assert(LegacyHtmlPolicy::canRestore($builder,$builder,'editor'), 'Editor can restore unchanged builder HTML metadata');
$assert(!LegacyHtmlPolicy::canRestore($builder,$legacy,'editor'), 'Editor cannot restore legacy content');
$assert(!LegacyHtmlPolicy::canRestore($legacy,$builder,'editor'), 'Editor cannot restore a legacy page into builder');
$assert(!LegacyHtmlPolicy::canRestore($builder,['content_mode'=>'builder','page_content'=>'<script>other()</script>'],'editor'), 'Editor cannot restore altered hidden HTML');
$assert(LegacyHtmlPolicy::canRestore($builder,$legacy,'admin'), 'Admin retains unrestricted legacy restore');
$assert(!LegacyHtmlPolicy::canRestore($builder,$builder,'user'), 'Unprivileged account cannot restore');

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/public_html/admin/pages.php');
$revisions = file_get_contents($root . '/core/classes/PageRevisions.php');
$form = file_get_contents($root . '/public_html/admin/views/pages/partials/form-fields.php');
$assert(substr_count($controller, 'LegacyHtmlPolicy::prepareSave(') === 2, 'Both create/edit controllers enforce server policy');
$assert(str_contains($controller, 'LegacyHtmlPolicy::canEditPage('), 'Edit endpoint enforces server authorization');
$assert(str_contains($revisions, 'LegacyHtmlPolicy::canRestore('), 'Restore model checks policy inside transaction');
$assert(str_contains($form, 'if ($canUnfilteredHtml)'), 'Legacy HTML editor is hidden in role-restricted UI');

printf("RESULT: %d PASS / 0 FAIL (policy unit + source guards, no HTTP or MySQL)\n", $passes);
