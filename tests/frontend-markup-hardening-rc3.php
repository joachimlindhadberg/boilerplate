<?php
/**
 * V2.5.0-RC3: isolated, read-only frontend HTML render regressions.
 * No database, server, bootstrap, filesystem writes, or active site access.
 */
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
require_once $root . '/public_html/incl/helpers/page_builder_renderer.php';
require_once $root . '/modules/forms/FormsModule.php';

// Mimic active-module component availability without booting the CMS.
function cms_module_manager(): object {
    static $modules;
    return $modules ??= new class {
        public function componentOptions(): array {
            return ['module-empty' => 'Empty component', 'module-rich' => 'Rich component'];
        }
    };
}
function component_renderer(string $component): void {
    if ($component === 'module-rich') echo '<div class="module-output">Content</div>';
}

$_SERVER['REQUEST_URI'] = '/kontakt?source=test';
$_SERVER['REQUEST_METHOD'] = 'GET';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$n = 0;
$check = static function (bool $ok, string $description) use (&$n): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$description}\n"); exit(1); }
    ++$n;
    echo "PASS: {$description}\n";
};
$render = static function (string $type, array $data): string {
    ob_start();
    render_page_builder_block(['block_type'=>$type, 'status'=>'active', 'data'=>$data]);
    return (string)ob_get_clean();
};
$legacy = static function (string $type, array $data) use ($render): string {
    return $render($type, $data);
};
$bad = ['javascript:alert(1)', 'data:text/html,test', '//example.com/evil', '/foo\\bar', "https://example.com/space here", "java\nscript:alert(1)"];
foreach ($bad as $index => $url) {
    $check(page_builder_public_url($url) === '', 'URL rejects unsafe scheme or malformed path #' . ($index + 1));
}
foreach (['/kontakt', '/kontakt?x=1', '#kontakt', 'https://example.com/kontakt', 'http://example.com/'] as $url) {
    $check(page_builder_public_url($url) === $url, 'URL preserves allowed address: ' . $url);
}
$check(page_builder_public_image_url('/uploads/photo.jpg') === '/uploads/photo.jpg', 'Current upload root retained');
$check(page_builder_public_image_url('/assets/uploads/photo.jpg') === '/assets/uploads/photo.jpg', 'Legacy upload root retained');
$check(page_builder_public_image_url('javascript:alert(1)') === '', 'Imported unsafe image URL removed');
$check(page_builder_public_image_url('/admin') === '', 'Arbitrary internal path rejected as image source');
$check(page_builder_image_dimensions(['width'=>640,'height'=>480]) === ' width="640" height="480"', 'Valid dimensions retained');
$check(page_builder_image_dimensions(['width'=>0,'height'=>480]) === '', 'Unknown image dimensions omitted');
$check(page_builder_image_dimensions(['width'=>999999,'height'=>480]) === '', 'Unreasonable dimensions rejected');

$legacyGrid = ['column_count'=>2, 'preset'=>'33-67', 'columns'=>[
    ['blocks'=>[['type'=>'cta','status'=>'active','data'=>['title'=>'CTA','button_label'=>'Click','button_url'=>'javascript:alert(1)']]]],
    ['blocks'=>[['type'=>'text','status'=>'active','data'=>['title'=>'Second']]]],
]];
$html = $legacy('grid', $legacyGrid);
$check(!str_contains($html, 'href="javascript:'), 'Legacy grid drops dangerous CTA href');
$check(str_contains($html, 'grid--split-33-67'), 'Legacy asymmetric grid classes remain intact');
$check(str_contains($html, 'Second'), 'Legacy sibling column survives blocked CTA');
$legacyGrid['columns'][0]['blocks'][0]['data']['button_url'] = '/kontakt';
$check(str_contains($legacy('grid', $legacyGrid), 'href="/kontakt"'), 'Legacy grid still links to valid internal URL');
$check(!str_contains($legacy('hero', ['primary_label'=>'Go','primary_url'=>'javascript:alert(1)']), 'href="javascript:'), 'Legacy hero link is safely rejected');
$check(!str_contains($legacy('cta', ['button_label'=>'Go','button_url'=>'data:text/html,foo']), '<a '), 'Legacy CTA link is safely rejected');
$check(str_contains($render('cta',['button_label'=>'Go','button_url'=>'#destination']), 'href="#destination"'), 'V2 safe fragment CTA still renders');
$check(!str_contains($render('text_image',['title'=>'Image','image_path'=>'javascript:alert(1)']), 'javascript:'), 'V2 unsafe image source suppressed');
$check(!str_contains($render('grid',['layout_version'=>'v2', 'preset'=>'equal', 'column_count'=>2,
    'columns'=>[['blocks'=>[['type'=>'image','data'=>['image_path'=>'javascript:alert(1)']]]], ['blocks'=>[]]]]), '<img'), 'V2 grid unsafe image omitted');
for ($columns=1;$columns<=6;$columns++) {
    $grid = $render('grid', ['layout_version'=>'v2','column_count'=>$columns,'preset'=>'equal','columns'=>array_fill(0,$columns,['blocks'=>[]])]);
    $check(substr_count($grid, 'class="site-grid--item"') === $columns, "V2 {$columns}-column empty Row layout preserved");
}
$check($render('component', ['component'=>'nonexistent']) === '', 'Unknown module does not create an empty section');
$check($render('component', ['component'=>'module-empty']) === '', 'Active but empty module does not create empty section');
$check(str_contains($render('component', ['component'=>'module-empty','title'=>'Heading']), 'Heading'), 'Explicit component heading is retained');
$check(str_contains($render('component', ['component'=>'module-rich']), 'module-output'), 'Active module output renders once');
$check($legacy('component', ['component'=>'nonexistent','title'=>'']) === '', 'Legacy invalid component does not render empty section');

$form = ['id'=>7,'submit_label'=>'Send'];
$fields = [
    ['field_key'=>'name','field_type'=>'text','label'=>'Navn','placeholder'=>'','help_text'=>'Navnehjælp','is_required'=>1,'row_order'=>0,'row_layout'=>'100','column_index'=>0],
    ['field_key'=>'email','field_type'=>'email','label'=>'E-mail','placeholder'=>'Email','help_text'=>'','is_required'=>1,'row_order'=>1,'row_layout'=>'50-50','column_index'=>0],
    ['field_key'=>'accept','field_type'=>'checkbox','label'=>'Accept','help_text'=>'Acceptér','is_required'=>0,'row_order'=>1,'row_layout'=>'50-50','column_index'=>1],
    ['field_key'=>'choice','field_type'=>'radio','label'=>'Valg','options'=>['A','B'],'help_text'=>'','is_required'=>0,'row_order'=>2,'row_layout'=>'100','column_index'=>0],
];
$forms = new FormsModule();
$renderForm = new ReflectionMethod(FormsModule::class, 'renderFrontendForm');
ob_start();
$renderForm->invoke($forms, $form, $fields);
$renderForm->invoke($forms, $form, $fields);
$html = (string)ob_get_clean();
preg_match_all('/\bid="([^"]+)"/', $html, $matches);
$ids = $matches[1];
$check(count($ids) === count(array_unique($ids)), 'Repeated Forms instance has no duplicate HTML ids');
$check(str_contains($html,'id="form-7"') && str_contains($html,'id="form-7-2"'), 'First Forms anchor remains backward-compatible; second unique');
$check(str_contains($html,'id="form-7-name"') && str_contains($html,'id="form-7-2-name"'), 'Field ids use unique instance prefix');
$check(str_contains($html,'for="form-7-2-email"') && str_contains($html,'id="form-7-2-email"'), 'Second instance email label matches input');
$check(str_contains($html,'aria-describedby="form-7-2-name-help"') && str_contains($html,'id="form-7-2-name-help"'), 'Second instance help association preserved');
$check(str_contains($html,'id="form-7-2-choice-0"') && str_contains($html,'id="form-7-2-choice-1"'), 'Second instance radio choices unique');
$check(substr_count($html, 'name="form_id" value="7"') === 2, 'Both Forms keep the same server-side form_id');
$check(str_contains($html,'name="form_instance" value="1"') && str_contains($html,'name="form_instance" value="2"'), 'POST distinguishes presentation instance');
$check(substr_count($html,'name="form_token"')===2, 'Separate per-rendered-form CSRF-equivalent tokens retained');
$check(substr_count($html,'name="_module_action" value="forms:submit"')===2, 'Form POST contract unchanged');
$check(substr_count($html,'name="website"')===2, 'Both Forms retain honeypot');
$check(substr_count($html,'placeholder=""')===0, 'Redundant empty placeholders omitted');
$check(substr_count($html,'form__row--50-50')===2, 'Two-column responsive layout preserved');

// Flash is directed only to the submitted duplicate instance.
$flashMethod = new ReflectionMethod(FormsModule::class, 'setFrontendFlash');
$pullMethod = new ReflectionMethod(FormsModule::class, 'pullFrontendFlash');
$_POST = ['form_id'=>'7','form_instance'=>'2'];
$flashMethod->invoke($forms, 7, ['success'=>'Saved']);
$check($pullMethod->invoke($forms,7,1)===[], 'Duplicate Forms flash not stolen by first instance');
$check(($pullMethod->invoke($forms,7,2)['success']??'')==='Saved', 'Correct duplicate Forms instance receives flash');
$_POST = ['form_id'=>'7','form_instance'=>['malformed']];
$flashMethod->invoke($forms,7,['errors'=>['_form'=>'Invalid']]);
$check(($pullMethod->invoke($forms,7,1)['errors']['_form']??'')==='Invalid', 'Malformed instance identifier safely defaults to first');

$navigationSource = (string)file_get_contents($root . '/public_html/incl/header.php');
$footerSource = (string)file_get_contents($root . '/public_html/incl/footer.php');
$navigationHelper = (string)file_get_contents($root . '/public_html/incl/helpers/navigation_builder.php');
$check(str_contains($navigationSource, 'trim($headerNavigation) !=='), 'Mobile toggle is gated by actual navigation output');
$check(str_contains($navigationHelper, 'if (!$tree) return;'), 'No <nav> is emitted for an empty menu');
$check(str_contains($navigationHelper, 'aria-label=\\"Main navigation'), 'Legacy navigation includes accessible label');
$check(!str_contains($footerSource, 'href="/admin"'), 'Public footer no longer exposes permanent CMS shortcut');
$check(!str_contains($footerSource, 'admin-topbar'), 'RC3 does not implement Phase 4 topbar');

echo "Frontend Markup Hardening V2.5.0-RC3: {$n} PASS / 0 FAIL (no database)\n";
