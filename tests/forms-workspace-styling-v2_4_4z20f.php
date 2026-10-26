<?php
/** V2.4.4z20f — Forms detail and global-settings layout regression. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
define('PUBLICPATH', $root . '/public_html');
require_once $root . '/core/classes/Autoload.php';
function t(string $key): string { return str_starts_with($key, 'forms:') ? substr($key, 6) : $key; }
function admin_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function admin_csrf_token(): string { return 'z20f-security-token'; }
function admin_icon(string $name, string $class = ''): string { return AdminIcons::render($name, $class); }
function admin_render_pagination(int $page, int $pages, callable $link, string $label): void {}
$pass = $fail = 0;
$check = static function (bool $ok, string $description) use (&$pass, &$fail): void {
    ++${$ok ? 'pass' : 'fail'}; echo ($ok ? 'PASS: ' : 'FAIL: ') . $description . "\n";
};
$workspace = require $root . '/modules/forms/admin/workspace.php';
$submission = [
    'id' => 17, 'form_id' => 3,
    'form_name' => 'Contact <img src=x onerror=alert(1)>',
    'submitted_at' => '2026-10-17 10:25:00',
    'source_url' => '/form/<unsafe>?p=1&d=2',
    'data' => [
        ['label' => 'Full name', 'value' => 'A & B'],
        ['label' => 'Message', 'value' => "Hi <script>alert(2)</script>\nLine two"],
    ],
];
ob_start();
$workspace('submission', ['submission' => $submission, 'notice' => null]);
$html = (string)ob_get_clean();
$check(str_contains($html, 'class="admin-detail-header"'), 'Submission uses shared Core detail header');
$check(str_contains($html, 'class="admin-detail-header__copy"'), 'Submission title and timestamp grouped together');
$check(str_contains($html, 'class="admin-page-actions"'), 'Submission actions use shared Core action row');
$check(!str_contains($html, 'class="admin-list-summary"'), 'List-summary border is no longer used as a detail header');
$check(!str_contains($html, 'admin-form-actions--left'), 'Detached legacy button row is removed');
$check(strpos($html, 'admin-detail-header') < strpos($html, 'admin-detail-grid'), 'Detail header appears before both cards');
$check(str_contains($html, '<dl class="admin-meta-list admin-meta-list--wide">'), 'Submitted fields use wide Core metadata rows');
$check(substr_count($html, '<dl class="admin-meta-list') === 2, 'One data list and one metadata list');
$check(!str_contains($html, '<div class="admin-form-section"><dl'), 'Neither card has double nested padding');
$check(substr_count($html, 'class="admin-panel"') === 2, 'Data and metadata each have one Core card');
$check(str_contains($html, 'href="/admin/forms/submissions?form_id=3"'), 'Contextual return link keeps correct form ID');
$check(str_contains($html, 'action="/admin/forms/submission?id=17"'), 'Original submission POST endpoint retained');
$check(str_contains($html, 'name="submission_action" value="mark_unread"'), 'Original mark-unread POST action retained');
$check(str_contains($html, 'name="id" value="17"'), 'Original POST submission ID retained');
$check(substr_count($html, 'name="csrf_token"') === 2, 'Unread POST and delete modal each carry one CSRF field');
$check(substr_count($html, 'z20f-security-token') === 2, 'Both forms contain issued CSRF token');
$check(str_contains($html, 'data-forms-delete-submission'), 'Delete remains a non-submitting modal trigger');
$check(str_contains($html, 'data-forms-delete-dialog'), 'Existing delete modal JS hook retained');
$check(substr_count($html, 'id="forms-submission-delete-dialog"') === 1, 'Exactly one Core Delete dialog');
$check(str_contains($html, 'name="submission_action" value="delete"'), 'Original modal delete POST action preserved');
$check(str_contains($html, 'data-forms-delete-dialog') && str_contains($html, 'showModal'), 'Unchanged modal open handler retained');
$check(str_contains($html, 'Contact &lt;img src=x onerror=alert(1)&gt;'), 'Untrusted submission title escaped as inert text');
$check(!str_contains($html, '<script>alert(2)</script>') && str_contains($html, '&lt;script&gt;alert(2)&lt;/script&gt;'), 'Untrusted submitted HTML escaped');
$check(str_contains($html, 'Hi &lt;script&gt;alert(2)&lt;/script&gt;<br'), 'Multiline form values preserve line breaks');
$check(str_contains($html, '/form/&lt;unsafe&gt;?p=1&amp;d=2'), 'Untrusted source URL escaped');
$check(str_contains($html, 'admin-section-layout--full') && !str_contains($html, 'admin-section-layout__sidebar'), 'Submission uses full-width workspace with no Forms-specific left sidebar');
$check(str_contains($html, 'class="admin-panel__header"'), 'Core panel headings retained');

ob_start();
$workspace('settings', [
  'formData' => ['default_save_submissions' => 1, 'default_send_notification' => 1, 'default_success_message' => 'OK', 'rate_limit_count' => 6, 'rate_limit_window_minutes' => 10],
  'errors' => [], 'notice' => null,
]);
$settings = (string)ob_get_clean();
$check(str_contains($settings, '<div class="admin-editor-bar">'), 'Global settings uses the same shared Core editor actionbar');
$check(strpos($settings, 'admin-editor-bar') < strpos($settings, 'admin-detail-grid'), 'Settings save action is above the settings cards');
$check(substr_count($settings, 'type="submit"') === 1, 'Exactly one global-settings Save button');
$check(substr_count($settings, 'Gem indstillinger') === 1, 'No floating duplicate Save button in right sidebar');
$check(str_contains($settings, 'action="/admin/forms/settings"') && str_contains($settings, 'name="csrf_token"'), 'Settings remain one original POST form with CSRF');
$check(str_contains($settings, 'name="default_save_submissions"') && str_contains($settings, 'name="rate_limit_count"'), 'Original settings fields preserved');
$check(str_contains($settings, '<aside class="admin-detail-sidebar">'), 'Right settings help panel preserved');
$check(str_contains($settings, 'admin-section-layout--full') && !str_contains($settings, 'admin-section-layout__sidebar'), 'Global settings do not reintroduce the left Forms navigation');

$scss = (string)file_get_contents($root . '/public_html/admin/assets/scss/admin.scss');
$css = (string)file_get_contents($root . '/public_html/admin/assets/css/admin.css');
$check($scss === $css, 'Core admin SCSS and CSS byte-identical');
$check(str_contains($css, '.admin-detail-header {') && str_contains($css, '.admin-detail-header__copy h2'), 'Core owns the detail header styling');
$check(str_contains($css, 'flex-wrap: wrap;') && str_contains($css, '.admin-detail-header .admin-page-actions form'), 'Shared header supports wrapping and inline POST action');
$check(!str_contains($css, '.forms-submission-detail') && !str_contains($css, '.forms-settings-actions'), 'No module-specific global styling overrides introduced');
$check(str_contains($css, '.admin-detail-grid {') && str_contains($css, '.admin-meta-list > div {'), 'Existing Core grid and metadata styling reused');
$check(!str_contains((string)file_get_contents($root.'/modules/forms/admin/controllers/FormsAdminController.php'), 'admin-detail-header'), 'Controller is not coupled to visual styling');
$meta = (string)file_get_contents($root . '/public_html/incl/init.php');
$check(preg_match("/BOILERPLATE_VERSION', '([0-9]+(?:\\.[0-9]+){2,3})'/", $meta, $releaseMatch) === 1 && version_compare($releaseMatch[1], '2.4.4.83', '>='), 'Internal CMS version is 2.4.4.83');
echo "Forms Workspace Styling V2.4.4z20f: $pass PASS / $fail FAIL\n";
exit($fail === 0 ? 0 : 1);
