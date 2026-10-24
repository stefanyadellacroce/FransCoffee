<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$relative = $argv[1] ?? '';
if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, '\\')) {
    throw new InvalidArgumentException('Provide a relative build file path.');
}
$settings = require __DIR__ . '/production-settings.php';
$host = $settings['ftp_host'] ?? 'ftpupload.net';
$username = $settings['ftp_user'] ?? $settings['db_user'];
$password = $settings['ftp_password'] ?? $settings['db_password'];
$remoteDirectory = rtrim($settings['ftp_remote_dir'] ?? '/htdocs/franscoffee', '/');
if ($remoteDirectory === '' || preg_match('#(?:^|/)\.\.?(/|$)#', $remoteDirectory)) {
    throw new RuntimeException('ftp_remote_dir must be a safe absolute FTP directory.');
}
$builds = glob(__DIR__ . '/output/*', GLOB_ONLYDIR) ?: [];
rsort($builds, SORT_STRING);
$local = ($builds[0] ?? '') . '/htdocs/' . $relative;
if (!is_file($local)) throw new RuntimeException('Local build file missing.');
$configFile = tempnam(sys_get_temp_dir(), 'fcftps-');
if ($configFile === false) throw new RuntimeException('Could not make temporary curl settings.');
$credentials = addcslashes($username . ':' . $password, "\\\"");
file_put_contents($configFile, "user = \"{$credentials}\"\n");
try {
    $remotePath = implode('/', array_map('rawurlencode', explode('/', $relative)));
    $command = [
        'C:\\Windows\\System32\\curl.exe', '--config', $configFile,
        '--ssl-reqd', '--tls-max', '1.2', '--ftp-pasv', '--ftp-create-dirs',
        '--upload-file', $local,
        '--silent', '--show-error', '--write-out', '%{size_upload} %{response_code}',
        '--connect-timeout', '45', '--max-time', '180',
        'ftp://' . $host . $remoteDirectory . '/' . $remotePath,
    ];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) throw new RuntimeException('Could not start curl.exe.');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) throw new RuntimeException('Native curl upload failed: ' . trim($stderr));
    echo "Uploaded {$relative} using native curl ({$stdout}).\n";
} finally {
    unlink($configFile);
}
