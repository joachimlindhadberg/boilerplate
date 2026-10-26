<?php

declare(strict_types=1);

/** Offline module scaffolding/validation. Does not need a database connection. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('ROOTPATH', dirname(__DIR__, 2));
require_once ROOTPATH . '/core/classes/Autoload.php';

function usage(): void
{
    fwrite(STDERR, "Usage:\n  php core/cli/module.php make <slug> [--name=\"Module Name\"]\n  php core/cli/module.php validate <slug|all>\n");
    exit(2);
}

/** Delete only a freshly-created private staging directory. Never touch an installed module. */
function discardStage(string $directory): void
{
    if (!is_dir($directory) || is_link($directory)) return;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $file) {
        if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
        else unlink($file->getPathname());
    }
    rmdir($directory);
}

function makeModule(string $slug, string $name): void
{
    if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $slug)) {
        throw new InvalidArgumentException('Use a lowercase slug (letters, digits, underscore or hyphen; 64 characters max).');
    }
    if ($name === '' || strlen($name) > 120 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
        throw new InvalidArgumentException('Name must be 1–120 bytes without control characters.');
    }
    $folder = ROOTPATH . '/modules';
    $target = $folder . '/' . $slug;
    if (!is_dir($folder) || is_link($folder) || file_exists($target) || is_link($target)) {
        throw new RuntimeException('Modules folder is missing or the target module already exists.');
    }
    $class = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug))) . 'Module';
    // Prevent collisions with other classes already present in Core or modules.
    if (class_exists($class) || interface_exists($class)) {
        throw new RuntimeException('Class name is already defined: ' . $class);
    }
    foreach (glob($folder . '/*/module.json') ?: [] as $otherManifestFile) {
        $raw = file_get_contents($otherManifestFile);
        $other = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($other) && strcasecmp((string)($other['class'] ?? ''), $class) === 0) {
            throw new RuntimeException('Class name conflicts with another module: ' . $class);
        }
    }
    $template = ROOTPATH . '/module-starter/template';
    if (!is_dir($template)) throw new RuntimeException('Module Starter templates are missing.');
    $stage = $folder . '/.module-build-' . $slug . '-' . bin2hex(random_bytes(8));
    if (!mkdir($stage, 0755)) throw new RuntimeException('Could not create a temporary module directory.');
    try {
        $replacements = [
            '__MODULE_SLUG__' => $slug,
            '__MODULE_CLASS__' => $class,
            '__JSON_MODULE_NAME__' => json_encode($name, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            '__PHP_MODULE_NAME__' => var_export($name, true),
            '__MODULE_NAME_TEXT__' => $name,
        ];
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($template, FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if ($file->isLink() || !$file->isFile()) throw new RuntimeException('Template contains an unsupported file.');
            $relative = substr($file->getPathname(), strlen($template) + 1);
            if (!str_ends_with($relative, '.tpl')) continue;
            $relative = substr($relative, 0, -4);
            if ($relative === 'ModuleEntry.php') $relative = $class . '.php';
            $destination = $stage . '/' . $relative;
            $directory = dirname($destination);
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                throw new RuntimeException('Could not create template folder: ' . $relative);
            }
            $source = file_get_contents($file->getPathname());
            if ($source === false || file_put_contents($destination, strtr($source, $replacements)) === false) {
                throw new RuntimeException('Failed to write module template: ' . $relative);
            }
            chmod($destination, 0644);
        }
        // Validate generated package before publishing it. Temporary directory name
        // differs from the final slug, so validate against the intended slug.
        $raw = file_get_contents($stage . '/module.json');
        $manifest = is_string($raw) ? json_decode($raw, true, 32, JSON_THROW_ON_ERROR) : null;
        if (!is_array($manifest)) throw new RuntimeException('Generated manifest is invalid.');
        ModuleManifestValidator::validate($slug, $stage, $manifest);
    } catch (Throwable $error) {
        discardStage($stage);
        throw $error;
    }
    if (file_exists($target) || is_link($target) || !rename($stage, $target)) {
        discardStage($stage);
        throw new RuntimeException('Could not publish generated module (target may already exist).');
    }
    echo "Created: modules/{$slug}\n";
    echo "Next: php core/cli/module.php validate {$slug}\n";
    echo "Then open /admin/modules, synchronize and activate the module.\n";
}

try {
    $command = $argv[1] ?? null;
    if ($command === 'validate' && count($argv) === 3) {
        $target = (string)$argv[2];
        $slugs = $target === 'all'
            ? array_map('basename', glob(ROOTPATH . '/modules/*', GLOB_ONLYDIR) ?: [])
            : [$target];
        if ($target !== 'all' && !preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/D', $target)) usage();
        if (!$slugs) throw new RuntimeException('No modules found.');
        $failed = false;
        foreach ($slugs as $slug) {
            try {
                $manifest = ModuleManifestValidator::load(ROOTPATH . '/modules/' . $slug);
                echo "OK {$slug} ({$manifest['version']})\n";
            } catch (Throwable $error) {
                fwrite(STDERR, "FAIL {$slug}: {$error->getMessage()}\n");
                $failed = true;
            }
        }
        if ($failed) exit(1);
    } elseif ($command === 'make' && (count($argv) === 3 || count($argv) === 4)) {
        $slug = (string)$argv[2];
        $name = ucwords(str_replace(['-', '_'], ' ', $slug));
        if (isset($argv[3])) {
            if (!str_starts_with((string)$argv[3], '--name=')) usage();
            $name = substr((string)$argv[3], 7);
        }
        makeModule($slug, $name);
    } else usage();
} catch (Throwable $error) {
    fwrite(STDERR, "Module CLI error: {$error->getMessage()}\n");
    exit(1);
}
