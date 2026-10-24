<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$settings = require __DIR__ . '/production-settings.php';
$host = $settings['ftp_host'] ?? 'ftpupload.net';
$remoteDirectory = rtrim($settings['ftp_remote_dir'] ?? '/htdocs/franscoffee', '/');
if ($remoteDirectory === '' || preg_match('#(?:^|/)\.\.?(/|$)#', $remoteDirectory)) {
    throw new RuntimeException('ftp_remote_dir must be a safe absolute FTP directory.');
}
$builds = glob(__DIR__ . '/output/*', GLOB_ONLYDIR) ?: [];
rsort($builds, SORT_STRING);
$webRoot = ($builds[0] ?? '') . '/htdocs';
if (!is_dir($webRoot)) throw new RuntimeException('No production build found.');
$tlsVersion = CURL_SSLVERSION_TLSv1_2 | (defined('CURL_SSLVERSION_MAX_TLSv1_2') ? constant('CURL_SSLVERSION_MAX_TLSv1_2') : 0);
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_USERNAME => $settings['ftp_user'] ?? $settings['db_user'],
    CURLOPT_PASSWORD => $settings['ftp_password'] ?? $settings['db_password'],
    CURLOPT_USE_SSL => CURLUSESSL_ALL,
    CURLOPT_FTPSSLAUTH => CURLFTPAUTH_TLS,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_SSLVERSION => $tlsVersion,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 45,
    CURLOPT_TIMEOUT => 120,
]);
$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($webRoot, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile()) $files[] = $file->getPathname();
}
sort($files, SORT_STRING);
$bad = [];
foreach ($files as $index => $local) {
    $relative = str_replace('\\', '/', substr($local, strlen($webRoot) + 1));
    $remotePath = implode('/', array_map('rawurlencode', explode('/', $relative)));
    curl_setopt($curl, CURLOPT_URL, 'ftp://' . $host . $remoteDirectory . '/' . $remotePath);
    $remote = curl_exec($curl);
    if ($remote === false || hash('sha256', $remote) !== hash_file('sha256', $local)) {
        $bad[] = $relative;
        echo "MISMATCH {$relative}\n";
    }
    if (($index + 1) % 20 === 0 || $index + 1 === count($files)) {
        echo 'Verified ' . ($index + 1) . '/' . count($files) . " files\n";
    }
}
echo count($bad) === 0 ? "All remote files match.\n" : count($bad) . " files need repair.\n";
exit(count($bad) === 0 ? 0 : 1);
