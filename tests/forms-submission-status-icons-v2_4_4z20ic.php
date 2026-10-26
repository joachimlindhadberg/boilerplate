<?php

declare(strict_types=1);
/** z20i-c: list status icons represent CURRENT read state; mutation remains the next action. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
$passes = 0;
$fails = 0;
$check = static function (bool $ok, string $label) use (&$passes, &$fails): void {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    $ok ? ++$passes : ++$fails;
};
function t(string $key, array $params = []): string { return str_starts_with($key, 'forms:') ? substr($key, 6) : $key; }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_icon(string $name, string $class = ''): string { return AdminIcons::render($name, $class); }
function admin_csrf_token(): string { return 'test-status-icon-csrf'; }
function admin_crud_pagination(int $total): array { return ['page'=>1, 'pages'=>1, 'from'=>$total ? 1 : 0, 'to'=>$total, 'offset'=>0]; }
function admin_render_pagination(int $page, int $pages, callable $url, string $label): void {}
$view = $root . '/modules/forms/admin/views/partials/submissions-list.php';
$source = (string)file_get_contents($view);
$check(str_contains($source, "'icon' => \$isNew ? 'mail' : 'check'"), 'Current-state icon contract: unread envelope, read checkmark');
$check(str_contains($source, "\$isNew?'mark_read':'mark_unread'"), 'Original status action form still uses mark_read/mark_unread');
$check(str_contains($source, "t(\$isNew ? 'forms:Markér læst' : 'forms:Markér ulæst')"), 'Tooltips and accessible labels describe next action');
$check(str_contains($source, "'data-forms-mark-submission' => '1'"), 'Core status action preserves delegated click hook');
$check(str_contains($source, 'AdminListUI::actionGroup('), 'Core still renders all list actions');
$check(str_contains($source, 'AdminDeleteDialog::render(['), 'Core deletion modal remains intact');

$submissions = [
    ['id'=>21,'status'=>'new','form_name'=>'Kontakt <unsafe>','source_url'=>'/kontakt','submitted_at'=>'2026-10-17 16:00:00'],
    ['id'=>22,'status'=>'read','form_name'=>'Tilmelding & Test','source_url'=>'/tilmelding','submitted_at'=>'2026-10-17 16:05:00'],
];
$forms=[]; $selectedFormId=0; $selectedStatus=''; $search=''; $total=count($submissions); $notice=null;
$filterCounts=[''=>2, 'new'=>1, 'read'=>1];
ob_start(); require $view; $markup = (string)ob_get_clean();
$rows=[];
if (preg_match_all('~<tr>(.*?)</tr>~s', $markup, $all)) {
    foreach ($all[1] as $row) {
        if (str_contains($row, 'name="id" value="21"')) $rows['unread']=$row;
        if (str_contains($row, 'name="id" value="22"')) $rows['read']=$row;
    }
}
$check(isset($rows['unread'], $rows['read']), 'Unread and read fixture rows rendered');
$unread=$rows['unread']??'';
$read=$rows['read']??'';
$mailSvg=AdminIcons::render('mail', 'admin-icon-action__icon');
$checkSvg=AdminIcons::render('check', 'admin-icon-action__icon');
$check(str_contains($unread, $mailSvg) && !str_contains($unread, $checkSvg), 'Unread row shows closed mail, not checkmark');
$check(str_contains($read, $checkSvg) && !str_contains($read, $mailSvg), 'Read row shows checkmark, not mail');
$check(str_contains($unread, 'aria-label="Markér læst: Kontakt &lt;unsafe&gt;"'), 'Unread tooltip/accessibility communicates Markér læst');
$check(str_contains($read, 'aria-label="Markér ulæst: Tilmelding &amp; Test"'), 'Read tooltip/accessibility communicates Markér ulæst');
$check(str_contains($unread, 'name="submission_action" value="mark_read"'), 'Unread click preserves mark_read POST');
$check(str_contains($read, 'name="submission_action" value="mark_unread"'), 'Read click preserves mark_unread POST');
$check(str_contains($unread, 'name="csrf_token" value="test-status-icon-csrf"')
    && str_contains($read, 'name="csrf_token" value="test-status-icon-csrf"'), 'Both status actions remain CSRF protected');
$check(str_contains($unread, 'data-forms-mark-submission="1"')
    && str_contains($read, 'data-forms-mark-submission="1"'), 'Both buttons keep same row-local delegation');
$check(str_contains($unread, 'Ulæst') && str_contains($read, 'Læst'), 'Explicit text in status column still matches stored state');
$check(str_contains($unread, 'Kontakt &lt;unsafe&gt;') && !str_contains($unread, 'Kontakt <unsafe>'), 'Untrusted form name is escaped');
$check(substr_count($markup, 'id="forms-submissions-delete-dialog"') === 1, 'Only one shared delete modal');
$check(str_contains($markup, 'data-admin-live-list') && str_contains($markup,'data-admin-live-search'), 'Existing live search stays enabled');
$check(file_get_contents($root.'/public_html/admin/assets/scss/admin.scss') === file_get_contents($root.'/public_html/admin/assets/css/admin.css'), 'Core SCSS/CSS still byte-identical');
$check((bool)preg_match("/BOILERPLATE_VERSION', '([0-9.]+)'/", (string)file_get_contents($root.'/public_html/incl/init.php'), $version)
    && version_compare($version[1], '2.4.4.89', '>='), 'Internal version at least 2.4.4.89');
printf("Forms Submission Status Icons V2.4.4z20i-c: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails ? 1 : 0);
