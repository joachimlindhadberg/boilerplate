<?php

declare(strict_types=1);

/**
 * One-time CLI bootstrap for a FRESH, EMPTY users table.
 * Generates a one-time password; never accepts a password as a CLI argument.
 * Not an account recovery / password reset command.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
define('ROOTPATH', dirname(__DIR__, 2));
require ROOTPATH . '/core/classes/Autoload.php';

// F3d.2b: hold the same F1c shared admission lock as web requests/cron for
// the entire CLI job, including database/file cleanup. External writers are
// still outside this cooperative boundary.
try {
    $updateWriterAdmission = UpdateCliWriterAdmission::acquire(ROOTPATH);
} catch (Throwable $ignored) {
    fwrite(STDERR, "CMS update maintenance gate busy or unavailable; retry later.\n");
    exit(75);
}


$options = getopt('', ['email:', 'first:', 'last:', 'help']);
if (isset($options['help'])) {
    echo "Usage: php core/cli/create-admin.php --email=you@example.com --first=First --last=Last\n";
    echo "Only for a new installation with no existing users. Generated password is shown once.\n";
    exit(0);
}
$email = is_string($options['email'] ?? null) ? strtolower(trim($options['email'])) : '';
$first = is_string($options['first'] ?? null) ? trim($options['first']) : '';
$last = is_string($options['last'] ?? null) ? trim($options['last']) : '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
    || $first === '' || strlen($first) > 100 || $last === '' || strlen($last) > 100) {
    fwrite(STDERR, "Provide a valid email, first and last name; names must be at most 100 bytes.\n");
    exit(2);
}
if (!extension_loaded('mysqli')) {
    fwrite(STDERR, "The CLI PHP binary must have mysqli enabled. Use your server/MAMP PHP version.\n");
    exit(1);
}
try {
    $db = new Db();
    // Serialize bootstrap attempts. A fresh-site admin must be unique even if
    // an operator accidentally runs this command concurrently in two shells.
    $lock = 'boilerplate_first_admin_' . substr(hash('sha256', DbConf::name()), 0, 20);
    if ((int)$db->fetchValue('SELECT GET_LOCK(?, 10)', [$lock]) !== 1) {
        throw new RuntimeException('Another first-admin setup is running.');
    }
    try {
        if ((int)$db->fetchValue('SELECT COUNT(*) FROM users') !== 0) {
            throw new RuntimeException('The users table is not empty; bootstrap is restricted to new installations.');
        }
        $adminId = (int)$db->fetchValue("SELECT id FROM roles WHERE role_name = 'admin' LIMIT 1");
        if ($adminId < 1) {
            throw new RuntimeException('Admin role missing; import the clean database schema first.');
        }
        $password = bin2hex(random_bytes(20)); // 40 unpredictable characters.
        $db->beginTransaction();
        try {
            $db->query(
                'INSERT INTO users (role_id, firstname, lastname, phone, email, password, weekly_hours, status, '
                . 'remember_token, must_change_password, created_at, updated_at) '
                . "VALUES (?, ?, ?, NULL, ?, ?, 0, 'active', NULL, 1, NOW(), NOW())",
                [$adminId, $first, $last, $email, password_hash($password, PASSWORD_DEFAULT)]
            );
            // Only change notification settings if they still contain a placeholder.
            $db->query("UPDATE settings SET setting_value = ? WHERE setting_key IN ('admin_email', 'notification_email') "
                . "AND setting_value = 'admin@example.test'", [$email]);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollback();
            throw $error;
        }
        echo "First admin created for {$email}.\n";
        echo "Temporary password (shown ONCE; do not save in project files): {$password}\n";
        echo "Log in and choose a new password immediately.\n";
    } finally {
        $db->fetchValue('SELECT RELEASE_LOCK(?)', [$lock]);
        $db->close();
    }

} catch (Throwable $error) {
    // Detailed SQL diagnostics belong in private server logs, not the console.
    fwrite(STDERR, "Admin bootstrap failed: " . $error->getMessage() . "\n");
    exit(1);
}
