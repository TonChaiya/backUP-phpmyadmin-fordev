<?php

declare(strict_types=1);

namespace BackupApp\Backup;

use RuntimeException;

/** Ephemeral plaintext option file: ACL protected; never persisted in jobs or logs. */
final class TemporaryMysqlCredentials
{
    private ?string $directory = null;

    public static function optionFile(string $password, string $username = 'root', string $host = '127.0.0.1', int $port = 3306): string
    {
        MysqldumpCommand::assertConnection(compact('host','port','username'));
        if (str_contains($password, "\0") || strlen($password) > 4096) {
            throw new RuntimeException('mysqldump_credential_invalid');
        }
        $escaped = strtr($password, ["\\" => "\\\\", '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']);
        return "[client]\nuser={$username}\npassword=\"{$escaped}\"\nhost={$host}\nport={$port}\n";
    }

    public function create(string $password, string $username = 'root', string $host = '127.0.0.1', int $port = 3306): string
    {
        if ($this->directory !== null) { throw new RuntimeException('mysqldump_credential_already_open'); }
        $payload = self::optionFile($password, $username, $host, $port);
        if (PHP_OS_FAMILY !== 'Windows') { throw new RuntimeException('mysqldump_private_storage_requires_windows'); }
        $root = realpath(sys_get_temp_dir());
        $source = realpath(dirname(__DIR__, 2));
        if ($root === false || $source === false || str_starts_with(strtolower($root . '\\'), strtolower($source . '\\'))
            || str_starts_with($root, '\\\\')) {
            throw new RuntimeException('mysqldump_private_storage_unsafe');
        }
        $path = $root . DIRECTORY_SEPARATOR . 'backup-mysql-' . bin2hex(random_bytes(16));
        // Fixed code; stdin is only a path. Execution policy and trust stores are never changed.
        $script = <<<'PS'
$ErrorActionPreference='Stop'
try {
 $path=[Console]::In.ReadToEnd()
 if($path.Length -gt 4096 -or $path -notmatch '^[A-Za-z]:\\' -or [IO.Directory]::Exists($path) -or [IO.File]::Exists($path)){exit 21}
 $parent=[IO.DirectoryInfo]::new([IO.Path]::GetDirectoryName($path))
 $drive=[IO.DriveInfo]::new([IO.Path]::GetPathRoot($path))
 if($drive.DriveFormat -ne 'NTFS' -or $drive.DriveType -ne 'Fixed'){exit 22}
 for($p=$parent;$null -ne $p;$p=$p.Parent){if(($p.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){exit 23}}
 $sid=[Security.Principal.WindowsIdentity]::GetCurrent().User
 $system=[Security.Principal.SecurityIdentifier]::new('S-1-5-18')
 $acl=[Security.AccessControl.DirectorySecurity]::new()
 $acl.SetAccessRuleProtection($true,$false)
 $acl.SetOwner($sid)
 $inherit=[Security.AccessControl.InheritanceFlags]'ContainerInherit,ObjectInherit'
 foreach($identity in @($sid,$system)){
  $rule=[Security.AccessControl.FileSystemAccessRule]::new($identity,[Security.AccessControl.FileSystemRights]::FullControl,$inherit,[Security.AccessControl.PropagationFlags]::None,[Security.AccessControl.AccessControlType]::Allow)
  $acl.AddAccessRule($rule)
 }
 $dir=$parent.CreateSubdirectory([IO.Path]::GetFileName($path),$acl)
 $actual=$dir.GetAccessControl()
 if(-not $actual.AreAccessRulesProtected -or $actual.GetOwner([Security.Principal.SecurityIdentifier]).Value -ne $sid.Value -or ($dir.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){exit 24}
 $rules=@($actual.GetAccessRules($true,$true,[Security.Principal.SecurityIdentifier]))
 if($rules.Count -ne 2){exit 25}
 foreach($r in $rules){if($r.IdentityReference.Value -notin @($sid.Value,'S-1-5-18') -or $r.AccessControlType -ne 'Allow' -or $r.IsInherited -or $r.FileSystemRights -ne 'FullControl' -or $r.InheritanceFlags -ne $inherit){exit 26}}
 [Console]::Out.Write('private_directory_verified')
 exit 0
} catch {exit 27}
PS;
        $out = tmpfile(); $err = tmpfile();
        if ($out === false || $err === false) { throw new RuntimeException('mysqldump_private_storage_failed'); }
        try {
            $exit = (new MysqldumpProcess())->run([
                'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe',
                '-NoLogo', '-NoProfile', '-NonInteractive', '-Command', $script,
            ], $out, $err, 15, 4096, 4096, $path);
            rewind($out);
            if ($exit !== 0 || stream_get_contents($out, 4096) !== 'private_directory_verified') {
                throw new RuntimeException('mysqldump_private_acl_failed');
            }
        } finally { fclose($out); fclose($err); }
        $this->directory = $path;
        register_shutdown_function(function (): void { $this->close(); });
        try {
            $file = $path . DIRECTORY_SEPARATOR . 'client.cnf';
            $handle = @fopen($file, 'xb');
            if ($handle === false) { throw new RuntimeException('mysqldump_credential_write_failed'); }
            try {
                if (fwrite($handle, $payload) !== strlen($payload) || !fflush($handle)) {
                    throw new RuntimeException('mysqldump_credential_write_failed');
                }
            } finally { fclose($handle); }
            return $file;
        } catch (\Throwable $e) { $this->close(); throw $e; }
    }

    public function stderrPath(): string
    {
        if ($this->directory === null) { throw new RuntimeException('mysqldump_private_storage_missing'); }
        return $this->directory . DIRECTORY_SEPARATOR . 'stderr';
    }

    public function stagingPath(string $name): string
    {
        if ($this->directory === null || !in_array($name, ['input.sql', 'stdout'], true)) {
            throw new RuntimeException('mysql_staging_path_invalid');
        }
        return $this->directory . DIRECTORY_SEPARATOR . $name;
    }

    public function close(): void
    {
        if ($this->directory === null) { return; }
        foreach (['client.cnf', 'stderr', 'input.sql', 'stdout'] as $name) {
            $file = $this->directory . DIRECTORY_SEPARATOR . $name;
            if (is_file($file) && !@unlink($file)) { throw new RuntimeException('mysqldump_private_cleanup_failed'); }
        }
        if (is_dir($this->directory) && !@rmdir($this->directory)) { throw new RuntimeException('mysqldump_private_cleanup_failed'); }
        $this->directory = null;
    }
}
