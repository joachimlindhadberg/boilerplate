<?php

declare(strict_types=1);
/** Source/runtime localization tests: no database, web requests, writes or active configuration. */
$root = dirname(__DIR__);
require_once $root . '/core/classes/Autoload.php';
$checks = 0;
$verify = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $checks++;
    echo "PASS: $label\n";
};
$expected = ['en', 'da', 'de', 'sv', 'nb'];
$verify(Translator::locales() === $expected, 'All five existing admin languages preserved');
$names = ['en' => 'Pages', 'da' => 'Sider', 'de' => 'Seiten', 'sv' => 'Sidor', 'nb' => 'Sider'];
Translator::boot('en');
$verify(Translator::locale() === 'en' && Translator::defaultLocale() === 'en', 'English is Core fallback');
foreach ($names as $locale => $label) {
    Translator::setLocale($locale);
    $verify(Translator::locale() === $locale && Translator::get('Sider') === $label, "Language $locale still available");
}
Translator::setLocale(null);
$verify(Translator::locale() === 'en', 'Inherited system language defaults to English');
Translator::setLocale('not-supported');
$verify(Translator::locale() === 'en', 'Unsupported locale safely falls back to English');
Translator::boot('da');
Translator::setLocale(null);
$verify(Translator::locale() === 'da', 'Existing Danish system preference remains effective');
Translator::setLocale('nb');
$verify(Translator::locale() === 'nb', 'Personal Norwegian preference overrides system Danish');
$files = [
    'core' => $root . '/core/lang/translations.json',
    'forms' => $root . '/modules/forms/lang/translations.json',
    'gallery' => $root . '/modules/gallery/lang/translations.json',
    'example' => $root . '/modules/example/lang/translations.json',
];
foreach ($files as $module => $path) {
    $entries = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $valid = is_array($entries) && $entries !== [];
    foreach ($entries as $record) {
        if (!is_array($record) || array_diff($expected, array_keys($record)) !== [] || array_diff(array_keys($record), $expected) !== []) { $valid = false; break; }
        foreach ($record as $translated) { if (!is_string($translated) || trim($translated) === '') { $valid = false; break; } }
    }
    $verify($valid, ucfirst($module) . ' catalog retains all five non-empty locales');
}
$seed = (string)file_get_contents($root . '/core/classes/FirstInstall.php');
$verify(str_contains($seed, "'admin_language' => 'en'") && str_contains($seed, "'language' => 'en_US'"), 'New installation seeds English locale settings');
$verify(str_contains($seed, "\$title = 'Home';") && str_contains($seed, "'Main menu', 'primary'"), 'Fresh visible content defaults to English');
$verify(str_contains($seed, "'forside'") && str_contains($seed, 'realpath(dirname($probe)) !== realpath($root)'), 'Homepage routing and 4A.6.2a security patch preserved');
$wizard = (string)file_get_contents($root . '/public_html/install.php');
$verify(str_contains($wizard, '<html lang="en">') && str_contains($wizard, 'Database connection') && str_contains($wizard, 'Installation complete'), 'English first-time installer UI present');
$verify(str_contains($wizard, 'hash_equals(') && str_contains($wizard, 'install_csrf') && str_contains($wizard, '!$https && !$local'), 'Installer auth, CSRF and HTTPS guard intact');
$verify(str_contains((string)file_get_contents($root . '/public_html/incl/init.php'), "\$settings->get('admin_language', 'en')"), 'No change to existing DB-backed admin language precedence');
$verify(str_contains((string)file_get_contents($root . '/modules/forms/FormsModule.php'), "'submit_label' => 'Submit'"), 'New Forms use English default button text');
$verify(str_contains((string)file_get_contents($root . '/database/install/empty-schema.sql'), "DEFAULT 'Thank you. Your message has been sent.'"), 'Fresh baseline Forms receipt default is English');
echo "English Default Localization QA1: $checks PASS / 0 FAIL (no database)\n";
