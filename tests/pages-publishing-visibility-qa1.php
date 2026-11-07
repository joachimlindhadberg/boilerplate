<?php
declare(strict_types=1);
/** Read-only Pages Publishing Visibility QA1: zero SQL, writes or network. */
$root = dirname(__DIR__);
$view = (string)file_get_contents($root . '/public_html/admin/views/pages/partials/form-fields.php');
$js = (string)file_get_contents($root . '/public_html/admin/assets/js/admin-page-workflow.js');
$scheduler = (string)file_get_contents($root . '/core/classes/PageScheduler.php');
$processor = (string)file_get_contents($root . '/public_html/admin/incl/functions.php');
$count = 0;
$test = static function(bool $condition, string $name) use (&$count): void {
    if (!$condition) {fwrite(STDERR, "FAIL: {$name}\n"); exit(1);}
    $count++;
};
$test(substr_count($view, 'data-page-schedule-field="publish"') === 1, 'Publish has one scoped field');
$test(substr_count($view, 'data-page-schedule-field="unpublish"') === 1, 'Unpublish has one scoped field');
$test(str_contains($view, 'id="page-status" name="page_status"'), 'Original status selector retained');
foreach (['draft', 'published', 'protected'] as $state) {
    $test(str_contains($view, "'{$state}'"), "State {$state} retained");
}
$test(str_contains($view, "=== 'published' ||"), 'Published unpublish state');
$test(str_contains($view, "'schedule_publish'"), 'Draft exception depends on publish date');
$test(str_contains($view, 'id="schedule-publish" type="datetime-local" name="schedule_publish"'), 'Publish input retains name');
$test(str_contains($view, 'id="schedule-unpublish" type="datetime-local" name="schedule_unpublish"'), 'Unpublish input retains name');
$test(str_contains($view, "t('Publicér automatisk')") && str_contains($view, "t('Afpublicér automatisk')"), 'Five-language keys unchanged');
$test(str_contains($view, "t('pages.schedule_timezone'"), 'Timezone indicator unchanged');
$test(str_contains($js, 'Pages Publishing Visibility QA1'), 'Script scoping marker');
$test(str_contains($js, "document.querySelector('form[data-page-builder-form]')"), 'Only Pages editor is enhanced');
$test(str_contains($js, "status.addEventListener('change', sync)"), 'Status changes update fields');
$test(str_contains($js, "publish.addEventListener('input', sync)"), 'Date input updates fields');
$test(str_contains($js, "form.addEventListener('submit', sync)"), 'Submit refreshes visibility');
$test(str_contains($js, 'input.disabled = !visible'), 'Hidden values are not submitted');
$test(!str_contains($js, "unpublish.value = ''") && !str_contains($js, "publish.value = ''"), 'Hidden dates are not erased while editing');
$test(str_contains($scheduler, "if (\$publish !== null && \$status !== 'draft')"), 'Server rejects non-Draft scheduled publish');
$test(str_contains($scheduler, "if (\$unpublish !== null && \$status !== 'published' && \$publish === null)"), 'Server accepts interval on Draft only');
$test(str_contains($processor, "'schedule_publish' => trim((string)(\$source['schedule_publish'] ?? ''))"), 'Absent publish input normalizes to empty');
$test(str_contains($processor, "'schedule_unpublish' => trim((string)(\$source['schedule_unpublish'] ?? ''))"), 'Absent unpublish input normalizes to empty');
require_once $root . '/core/classes/PageScheduler.php';
$oldTz = date_default_timezone_get();
date_default_timezone_set('Europe/Copenhagen');
try {
    $pub = (new DateTimeImmutable('+10 days'))->setTime(10, 0)->format('Y-m-d\TH:i');
    $end = (new DateTimeImmutable('+12 days'))->setTime(10, 0)->format('Y-m-d\TH:i');
    $draftPublish = PageScheduler::validate('draft', $pub, '', 'legacy', []);
    $test($draftPublish[0] !== null && $draftPublish[1] === null, 'Draft may schedule a publish');
    $interval = PageScheduler::validate('draft', $pub, $end, 'legacy', []);
    $test($interval[0] !== null && $interval[1] !== null && $interval[1] > $interval[0], 'Draft may schedule an interval');
    $published = PageScheduler::validate('published', '', $end, 'legacy', []);
    $test($published[0] === null && $published[1] !== null, 'Published may schedule only unpublish');
    $protected = PageScheduler::validate('protected', '', '', 'legacy', []);
    $test($protected === [null, null], 'Protected permits no schedule');
    foreach ([['published', $pub, ''], ['protected', $pub, ''], ['draft', '', $end], ['protected', '', $end]] as [$status, $publish, $unpublish]) {
        try {PageScheduler::validate($status, $publish, $unpublish, 'legacy', []); $rejected = false;}
        catch (InvalidArgumentException) {$rejected = true;}
        $test($rejected, "Reject illegal schedule for {$status}");
    }
} finally {date_default_timezone_set($oldTz);}
echo "Pages Publishing Visibility QA1: {$count} PASS / 0 FAIL (read-only)\n";
