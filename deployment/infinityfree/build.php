<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$projectRoot = dirname(__DIR__, 2);
$settingsFile = getenv('FRANSCOFFEE_BUILD_SETTINGS') ?: __DIR__ . '/production-settings.php';
if (!is_file($settingsFile)) {
    fwrite(STDERR, "Copy production-settings.example.php to production-settings.php and fill it in.\n");
    exit(1);
}
$settings = require $settingsFile;
if (!is_array($settings)) {
    throw new RuntimeException('Production settings must return an array.');
}
$siteUrlOverride = getenv('FRANSCOFFEE_BUILD_SITE_URL');
if ($siteUrlOverride !== false && $siteUrlOverride !== '') {
    $settings['site_url'] = $siteUrlOverride;
}
$appPath = $settings['app_path'] ?? '/franscoffee';
if (!is_string($appPath)
    || ($appPath !== '' && !preg_match('#^/(?!/)[A-Za-z0-9._~/-]*$#', $appPath))
    || preg_match('#(?:^|/)\.\.?(/|$)#', $appPath)) {
    throw new RuntimeException('app_path must be empty or a safe absolute URL path.');
}
$appPath = rtrim($appPath, '/');
foreach (['site_url', 'db_host', 'db_name', 'db_user', 'db_password', 'admin_email', 'admin_password'] as $key) {
    if (!isset($settings[$key]) || !is_string($settings[$key]) || trim($settings[$key]) === '') {
        throw new RuntimeException("Missing production setting: {$key}");
    }
}
if (!filter_var($settings['site_url'], FILTER_VALIDATE_URL)
    || parse_url($settings['site_url'], PHP_URL_SCHEME) !== 'https'
    || !filter_var($settings['admin_email'], FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('Use an HTTPS site URL and a valid admin email.');
}
if (strlen($settings['admin_password']) < 12
    || !preg_match('/[A-Z]/', $settings['admin_password'])
    || !preg_match('/[a-z]/', $settings['admin_password'])
    || !preg_match('/[0-9]/', $settings['admin_password'])) {
    throw new RuntimeException('Admin password needs at least 12 characters, uppercase, lowercase, and a number.');
}
foreach (['db_host', 'db_name', 'db_user'] as $key) {
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $settings[$key])) {
        throw new RuntimeException("Invalid characters in {$key}");
    }
}

$outputBase = getenv('FRANSCOFFEE_BUILD_OUTPUT') ?: __DIR__ . '/output';
$outputRoot = rtrim($outputBase, '/\\') . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
$webRoot = $outputRoot . '/htdocs';
if (!mkdir($webRoot, 0700, true)) {
    throw new RuntimeException('Could not create build directory.');
}

foreach (glob($projectRoot . '/*.php') ?: [] as $source) {
    if (basename($source) !== 'config.php') {
        copy($source, $webRoot . '/' . basename($source));
    }
}
foreach (['.htaccess', 'includes', 'actions', 'admin', 'assets', 'customer', 'staff', 'uploads'] as $entry) {
    $source = $projectRoot . '/' . $entry;
    $target = $webRoot . '/' . $entry;
    if (is_file($source)) {
        copy($source, $target);
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $destination = $target . '/' . $relative;
        if ($item->isDir()) {
            if (!is_dir($destination)) mkdir($destination, 0755, true);
        } elseif ($item->getFilename() !== '.gitkeep') {
            if (!is_dir(dirname($destination))) mkdir(dirname($destination), 0755, true);
            copy($item->getPathname(), $destination);
        }
    }
}

$config = require $projectRoot . '/config.php';
$config['app']['base_url'] = rtrim($settings['site_url'], '/') . $appPath;
$config['app']['environment'] = 'production';
$config['app']['debug'] = false;
$config['database']['host'] = $settings['db_host'];
$config['database']['name'] = $settings['db_name'];
$config['database']['username'] = $settings['db_user'];
$config['database']['password'] = $settings['db_password'];
file_put_contents($webRoot . '/config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n");

$sql = file_get_contents($projectRoot . '/database/franscoffee.sql');
if ($sql === false) throw new RuntimeException('Could not read the source database file.');
// InfinityFree creates the database in its control panel, not from an import.
$sql = preg_replace('/\ACREATE DATABASE[^\n]*\RUSE [^\n]*\R\RSET FOREIGN_KEY_CHECKS = 0;\R(?:DROP TABLE[^\n]*\R)SET FOREIGN_KEY_CHECKS = 1;\R\R/', '', $sql, 1, $count);
if ($count !== 1) throw new RuntimeException('Unexpected SQL header; refusing to make an unsafe import.');

$adminSeed = "INSERT INTO users (first_name,last_name,email,password_hash,role,status) VALUES ('Site','Admin',"
    . sql_quote(strtolower($settings['admin_email'])) . ','
    . sql_quote(password_hash($settings['admin_password'], PASSWORD_DEFAULT)) . ",'ADMIN','ACTIVE');";
$sql = preg_replace_callback(
    '/INSERT INTO users \(first_name,last_name,email,phone,password_hash,role,status\) VALUES\R.*?INSERT INTO customer_profiles \(user_id\)\RSELECT id FROM users WHERE email=\x27francis\.customer@franscoffee\.com\x27;/s',
    static fn (): string => $adminSeed,
    $sql,
    1,
    $count
);
if ($count !== 1) throw new RuntimeException('Could not replace demo users.');

$walletSeed = "INSERT INTO payment_methods (code,name,is_online,is_active) VALUES\n('GCASH','GCash',1,0),('MAYA','Maya',1,0);";
$sql = preg_replace(
    '/INSERT INTO payment_methods \(code,name,is_online,receiver_name,receiver_number\) VALUES\R.*?UPDATE payment_methods SET qr_path=.*?WHERE code=\x27MAYA\x27;/s',
    $walletSeed,
    $sql,
    1,
    $count
);
if ($count !== 1) throw new RuntimeException('Could not disable demo wallet details.');

$sql = preg_replace('/-- Phase 10 inventory & supplier seed\R.*?(?=-- Phase 12 admin & settings defaults)/s', '', $sql, 1, $count);
if ($count !== 1) throw new RuntimeException('Could not remove demo operating data.');
if (str_contains($sql, 'francis.admin@franscoffee.com') || str_contains($sql, 'Franscoffee Shop Demo')) {
    throw new RuntimeException('Demo credentials or payment data remain in the import.');
}
$sql = str_replace('ENGINE=InnoDB;', 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;', $sql);
file_put_contents($outputRoot . '/import.sql', $sql);
echo "Prepared: {$outputRoot}\nUpload htdocs contents to InfinityFree htdocs; import import.sql into the empty database.\n";

function sql_quote(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}
