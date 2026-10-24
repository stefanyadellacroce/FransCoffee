<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$settings = require __DIR__ . '/production-settings.php';
$host = $settings['ftp_host'] ?? 'ftpupload.net';
$username = $settings['ftp_user'] ?? $settings['db_user'];
$password = $settings['ftp_password'] ?? $settings['db_password'];

$path = $argv[1] ?? '/';
if (!in_array($path, [
    '/',
    '/htdocs/',
    '/htdocs/index.php',
    '/htdocs/franscoffee/',
    '/htdocs/franscoffee/admin/',
    '/franscoffee.infinityfree.io/',
    '/franscoffee.infinityfree.io/htdocs/',
    '/franscoffee.infinityfree.io/htdocs/index.php',
], true)) {
    throw new InvalidArgumentException('This FTP path cannot be inspected.');
}
$curl = curl_init('ftp://' . $host . $path);
curl_setopt_array($curl, [
    CURLOPT_USERNAME => $username,
    CURLOPT_PASSWORD => $password,
    CURLOPT_USE_SSL => CURLUSESSL_ALL,
    CURLOPT_FTPSSLAUTH => CURLFTPAUTH_TLS,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_DIRLISTONLY => $path !== '/htdocs/index.php',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT => 40,
]);
$listing = curl_exec($curl);
if ($listing === false) {
    fwrite(STDERR, 'FTP probe failed: ' . curl_error($curl) . PHP_EOL);
    exit(1);
}
echo in_array($path, ['/htdocs/index.php', '/franscoffee.infinityfree.io/htdocs/index.php'], true)
    ? "FTP {$path} excerpt:\n" . substr($listing, 0, 1200) . "\n"
    : "FTP {$path} entries:\n" . $listing;
