<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
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
$webRoot = ($builds[0] ?? '') . '/htdocs';
if (!is_dir($webRoot)) throw new RuntimeException('No production build found.');
$config = require $webRoot . '/config.php';
$appPath = $settings['app_path'] ?? '/franscoffee';
$expectedUrl = rtrim($settings['site_url'], '/') . rtrim($appPath, '/');
if ($config['app']['base_url'] !== $expectedUrl || $config['app']['environment'] !== 'production') {
    throw new RuntimeException('Latest build URL does not match the configured production app path.');
}

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($webRoot, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile()) $files[] = $file->getPathname();
}
sort($files, SORT_STRING);
$argument = $argv[1] ?? null;
$resumeAfter = $argument !== null && str_starts_with($argument, '--after=') ? substr($argument, 8) : null;
$only = $resumeAfter === null ? $argument : null;
if ($only !== null) {
    if ($only === '' || str_contains($only, '..') || str_starts_with($only, '/') || str_contains($only, '\\')) {
        throw new InvalidArgumentException('Provide a relative build file path.');
    }
    $files = array_values(array_filter($files, static fn (string $file): bool =>
        str_replace('\\', '/', substr($file, strlen($webRoot) + 1)) === $only));
    if (count($files) !== 1) throw new RuntimeException('Requested build file was not found.');
}
if ($resumeAfter !== null) {
    if ($resumeAfter === '' || str_contains($resumeAfter, '..') || str_starts_with($resumeAfter, '/') || str_contains($resumeAfter, '\\')) {
        throw new InvalidArgumentException('Provide a relative resume path.');
    }
    $files = array_values(array_filter($files, static fn (string $file): bool =>
        strcmp(str_replace('\\', '/', substr($file, strlen($webRoot) + 1)), $resumeAfter) > 0));
}
$curl = curl_init();
$diagnostic = getenv('FRANSCOFFEE_FTP_DIAG') === '1' ? fopen('php://temp', 'w+') : null;
$tlsVersion = CURL_SSLVERSION_TLSv1_2 | (defined('CURL_SSLVERSION_MAX_TLSv1_2') ? constant('CURL_SSLVERSION_MAX_TLSv1_2') : 0);
curl_setopt_array($curl, [
    CURLOPT_USERNAME => $username,
    CURLOPT_PASSWORD => $password,
    CURLOPT_USE_SSL => CURLUSESSL_ALL,
    CURLOPT_FTPSSLAUTH => CURLFTPAUTH_TLS,
    CURLOPT_FTP_USE_EPSV => false,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_SSLVERSION => $tlsVersion,
    CURLOPT_FTP_CREATE_MISSING_DIRS => CURLFTP_CREATE_DIR_RETRY,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 45,
    CURLOPT_TIMEOUT => 180,
]);
if ($diagnostic !== null) {
    curl_setopt($curl, CURLOPT_VERBOSE, true);
    curl_setopt($curl, CURLOPT_STDERR, $diagnostic);
}

$done = 0;
foreach ($files as $source) {
    $relative = str_replace('\\', '/', substr($source, strlen($webRoot) + 1));
    $remote = implode('/', array_map('rawurlencode', explode('/', $relative)));
    $input = fopen($source, 'rb');
    if ($input === false) throw new RuntimeException("Cannot read {$relative}");
    curl_setopt($curl, CURLOPT_URL, 'ftp://' . $host . $remoteDirectory . '/' . $remote);
    curl_setopt($curl, CURLOPT_UPLOAD, true);
    curl_setopt($curl, CURLOPT_INFILE, $input);
    curl_setopt($curl, CURLOPT_INFILESIZE, filesize($source));
    $result = false;
    for ($attempt = 1; $attempt <= 6; $attempt++) {
        rewind($input);
        $result = curl_exec($curl);
        if ($result !== false) break;
        fwrite(STDERR, "Retrying {$relative} after FTP error ({$attempt}/6): " . curl_error($curl) . "\n");
        if ($diagnostic !== null) {
            rewind($diagnostic);
            $trace = stream_get_contents($diagnostic);
            foreach (preg_split('/\R/', $trace) as $line) {
                if (preg_match('/^<\s*451\b/', $line)) fwrite(STDERR, $line . "\n");
            }
            ftruncate($diagnostic, 0);
            rewind($diagnostic);
        }
        if ($attempt < 6) sleep(min(5, $attempt));
    }
    fclose($input);
    if ($result === false) {
        throw new RuntimeException("Upload failed for {$relative}: " . curl_error($curl));
    }
    $done++;
    if ($done % 10 === 0 || $done === count($files)) {
        echo "Uploaded {$done}/" . count($files) . " files (latest: {$relative})\n";
    }
}
echo "Files uploaded to {$remoteDirectory}/\n";
