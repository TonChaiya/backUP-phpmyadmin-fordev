<?php

declare(strict_types=1);

use BackupApp\Support\Auth;
use BackupApp\Support\Config;
use BackupApp\Support\Logger;
use BackupApp\Support\Security;
use BackupApp\Support\Session;

test('configured authentication accepts the correct password', static function (): void {
    $auth = new Auth([
        'username' => 'admin',
        'password_hash' => password_hash('correct horse', PASSWORD_DEFAULT),
    ]);

    assertTrueValue($auth->attempt('admin', 'correct horse'));
    assertTrueValue($auth->isAuthorized('192.0.2.10'));
});

test('configured authentication rejects invalid credentials', static function (): void {
    $auth = new Auth([
        'username' => 'admin',
        'password_hash' => password_hash('correct horse', PASSWORD_DEFAULT),
    ]);

    assertFalseValue($auth->attempt('admin', 'wrong'));
    assertFalseValue($auth->isAuthorized('127.0.0.1'), 'Configured auth must also protect loopback');
});

test('unconfigured authentication permits only the direct loopback peer', static function (): void {
    $auth = new Auth(['username' => 'admin', 'password_hash' => '']);

    assertTrueValue($auth->isAuthorized('127.0.0.1'));
    assertTrueValue($auth->isAuthorized('::1'));
    assertFalseValue($auth->isAuthorized('192.168.1.10'));
    assertFalseValue($auth->isAuthorized('203.0.113.20'));
});

test('forwarded headers cannot turn a remote peer into loopback', static function (): void {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1';
    $auth = new Auth(['username' => 'admin', 'password_hash' => '']);

    assertFalseValue($auth->isAuthorized('192.168.1.10'));
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);
});

test('session cookie policy is strict and enables secure only for HTTPS', static function (): void {
    $http = Session::cookieOptions(false);
    $https = Session::cookieOptions(true);

    assertTrueValue($http['httponly']);
    assertSameValue('Strict', $http['samesite']);
    assertFalseValue($http['secure']);
    assertTrueValue($https['secure']);
});

test('logout invalidates authentication state', static function (): void {
    $_SESSION['auth'] = ['authenticated' => true, 'username' => 'admin'];
    $_SESSION['backup_job_ids'] = [str_repeat('a', 32)];
    $_SESSION['active_backup_job_id'] = str_repeat('a', 32);
    $_SESSION['backup_job_contexts'] = [str_repeat('a', 32) => str_repeat('b', 32)];
    $_SESSION['backup_connection_context'] = str_repeat('b', 32);
    $auth = new Auth(['username' => 'admin', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)]);

    $auth->logout();

    assertFalseValue(isset($_SESSION['auth']));
    assertFalseValue(isset($_SESSION['backup_job_ids']));
    assertFalseValue(isset($_SESSION['active_backup_job_id']));
    assertFalseValue(isset($_SESSION['backup_job_contexts']));
    assertFalseValue(isset($_SESSION['backup_connection_context']));
    assertFalseValue($auth->isAuthorized('127.0.0.1'));
});

test('csrf accepts the session token and rejects missing or invalid tokens', static function (): void {
    $token = Security::csrfToken();

    assertTrueValue(Security::validateCsrf($token));
    assertFalseValue(Security::validateCsrf(null));
    assertFalseValue(Security::validateCsrf('invalid'));
});

test('local configuration overrides selected nested values and preserves defaults', static function (): void {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup-config-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $base = $directory . DIRECTORY_SEPARATOR . 'base.php';
    $local = $directory . DIRECTORY_SEPARATOR . 'local.php';
    file_put_contents($base, "<?php return ['auth' => ['username' => 'admin', 'password_hash' => ''], 'max_backup_files' => 200];");
    file_put_contents($local, "<?php return ['auth' => ['password_hash' => 'local-hash']];");

    try {
        $config = Config::load($base, $local);
        assertSameValue('admin', $config['auth']['username']);
        assertSameValue('local-hash', $config['auth']['password_hash']);
        assertSameValue(200, $config['max_backup_files']);
    } finally {
        @unlink($local);
        @unlink($base);
        @rmdir($directory);
    }
});

test('technical logging redacts secrets and normalizes injected newlines', static function (): void {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup-log-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $logPath = $directory . DIRECTORY_SEPARATOR . 'app.log';
    $logger = new Logger($logPath);
    $secret = 'database-secret';

    try {
        $logger->exception(new RuntimeException("Connection failed for {$secret}\nforged-line"), [$secret]);
        $contents = (string) file_get_contents($logPath);
        assertNotContainsValue($secret, $contents);
        assertContainsValue('[REDACTED]', $contents);
        assertNotContainsValue("\nforged-line", $contents);
    } finally {
        @unlink($logPath);
        @rmdir($directory);
    }
});

test('failed connection redaction includes submitted and saved database passwords', static function (): void {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup-connect-log-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $logPath = $directory . DIRECTORY_SEPARATOR . 'app.log';
    $submitted = 'SUBMITTED-PASSWORD-SENTINEL';
    $saved = 'SAVED-PASSWORD-SENTINEL';
    $secrets = Security::connectionPasswordSecrets(
        ['password' => $submitted],
        ['connection' => ['password' => $saved]]
    );

    try {
        (new Logger($logPath))->exception(new RuntimeException("{$submitted} {$saved}"), $secrets);
        $contents = (string) file_get_contents($logPath);
        assertNotContainsValue($submitted, $contents);
        assertNotContainsValue($saved, $contents);
        assertSameValue(2, substr_count($contents, '[REDACTED]'));
    } finally {
        @unlink($logPath);
        @rmdir($directory);
    }
});

