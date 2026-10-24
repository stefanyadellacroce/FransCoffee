<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$relative = $argv[1] ?? '';
if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, '\\')) {
    throw new InvalidArgumentException('Provide a relative build file path.');
}
$settings = require __DIR__ . '/production-settings.php';
$host = $settings['ftp_host'] ?? 'ftpupload.net';
$remoteDirectory = rtrim($settings['ftp_remote_dir'] ?? '/htdocs/franscoffee', '/');
if ($remoteDirectory === '' || preg_match('#(?:^|/)\.\.?(/|$)#', $remoteDirectory)) {
    throw new RuntimeException('ftp_remote_dir must be a safe absolute FTP directory.');
}
$builds = glob(__DIR__ . '/output/*', GLOB_ONLYDIR) ?: [];
rsort($builds, SORT_STRING);
$local = ($builds[0] ?? '') . '/htdocs/' . $relative;
if (!is_file($local)) throw new RuntimeException('Local build file missing.');
$remotePath = implode('/', array_map('rawurlencode', explode('/', $relative)));
$curl = curl_init('ftp://' . $host . $remoteDirectory . '/' . $remotePath);
$tlsVersion = CURL_SSLVERSION_TLSv1_2 | (defined('CURL_SSLVERSION_MAX_TLSv1_2') ? constant('CURL_SSLVERSION_MAX_TLSv1_2') : 0);
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
    CURLOPT_TIMEOUT => 180,
]);
$remote = curl_exec($curl);
if ($remote === false) throw new RuntimeException('FTP download failed: ' . curl_error($curl));
if (getenv('FRANSCOFFEE_SAVE_REMOTE') === '1' && str_ends_with(strtolower($relative), '.png')) {
    $savePath = sys_get_temp_dir() . '/franscoffee-remote-' . basename($relative);
    file_put_contents($savePath, $remote);
    echo "Saved remote PNG to {$savePath}\n";
}
echo $relative . ': ' . (hash('sha256', $remote) === hash_file('sha256', $local) ? 'MATCH' : 'MISMATCH')
    . ' (local ' . filesize($local) . ' bytes, remote ' . strlen($remote) . " bytes)\n";
if (hash('sha256', $remote) !== hash_file('sha256', $local)) {
    $localBytes = file_get_contents($local);
    $limit = min(strlen($localBytes), strlen($remote));
    $firstDifference = $limit;
    for ($i = 0; $i < $limit; $i++) {
        if ($localBytes[$i] !== $remote[$i]) { $firstDifference = $i; break; }
    }
    echo "First different byte: {$firstDifference}\n";
    if (str_ends_with(strtolower($relative), '.png')) {
        $localInfo = @getimagesizefromstring($localBytes);
        $remoteInfo = @getimagesizefromstring($remote);
        echo 'PNG dimensions (local/remote): '
            . ($localInfo ? $localInfo[0] . 'x' . $localInfo[1] : 'invalid') . ' / '
            . ($remoteInfo ? $remoteInfo[0] . 'x' . $remoteInfo[1] : 'invalid') . "\n";
        echo 'PNG header bytes (local/remote): ' . bin2hex(substr($localBytes, 0, 48))
            . ' / ' . bin2hex(substr($remote, 0, 48)) . "\n";
    }
}
$head = curl_init('ftp://' . $host . $remoteDirectory . '/' . $remotePath);
curl_setopt_array($head, [
    CURLOPT_USERNAME => $settings['ftp_user'] ?? $settings['db_user'],
    CURLOPT_PASSWORD => $settings['ftp_password'] ?? $settings['db_password'],
    CURLOPT_USE_SSL => CURLUSESSL_ALL,
    CURLOPT_FTPSSLAUTH => CURLFTPAUTH_TLS,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_SSLVERSION => $tlsVersion,
    CURLOPT_NOBODY => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 45,
    CURLOPT_TIMEOUT => 180,
]);
$headers = curl_exec($head);
if ($headers !== false) {
    $size = curl_getinfo($head, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    echo "FTP SIZE reports {$size} bytes.\n";
}
