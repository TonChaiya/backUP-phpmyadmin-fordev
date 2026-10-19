<?php
declare(strict_types=1);
namespace BackupApp\Support;
use BackupApp\Backup\MysqldumpProcess;
use RuntimeException;

/** SID-bound private plaintext directory; not encryption. */
final class WindowsPrivateDirectory
{
    public function verify(string $path,bool $create):string
    {
        if(PHP_OS_FAMILY!=='Windows'){throw new RuntimeException('connection_private_storage_unavailable');}
        $script= <<<'PS'
$ErrorActionPreference='Stop'
[Console]::InputEncoding=[Text.UTF8Encoding]::new($false)
try {
 $text=[Console]::In.ReadToEnd();if($text.Length -gt 8192){exit 21}
 $data=ConvertFrom-Json $text;$path=[IO.Path]::GetFullPath([string]$data.path)
 $base=[Environment]::GetFolderPath([Environment+SpecialFolder]::LocalApplicationData)
 $prefix=[IO.Path]::Combine($base,'BackupPhpMyAdmin','ConnectionSettings')+[IO.Path]::DirectorySeparatorChar
 if(-not $path.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase) -or $path.Substring($prefix.Length) -notmatch '^[a-f0-9]{32}$'){exit 22}
 $drive=[IO.DriveInfo]::new([IO.Path]::GetPathRoot($path));if($drive.DriveFormat -ne 'NTFS' -or $drive.DriveType -ne 'Fixed'){exit 23}
 $sid=[Security.Principal.WindowsIdentity]::GetCurrent().User
 $inherit=[Security.AccessControl.InheritanceFlags]'ContainerInherit,ObjectInherit'
 function Assert-Private($item,[bool]$directory) {
  if(($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){throw 'unsafe'}
  $acl=$item.GetAccessControl();if($acl.GetOwner([Security.Principal.SecurityIdentifier]).Value -ne $sid.Value){throw 'owner'}
  if($directory -and -not $acl.AreAccessRulesProtected){throw 'inheritance'}
  $rules=@($acl.GetAccessRules($true,$true,[Security.Principal.SecurityIdentifier]));if($rules.Count -ne 2){throw 'rules'}
  foreach($r in $rules){if($r.IdentityReference.Value -notin @($sid.Value,'S-1-5-18') -or $r.AccessControlType -ne 'Allow' -or $r.FileSystemRights -ne 'FullControl'){throw 'access'}}
 }
 for($p=[IO.DirectoryInfo]::new($base);$null -ne $p;$p=$p.Parent){if(($p.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){exit 24}}
 $parent=[IO.DirectoryInfo]::new($base)
 foreach($name in @('BackupPhpMyAdmin','ConnectionSettings',$path.Substring($prefix.Length))){
  $next=[IO.Path]::Combine($parent.FullName,$name)
  if(-not [IO.Directory]::Exists($next)){
   if(-not $data.create -or [IO.File]::Exists($next)){exit 25}
   $acl=[Security.AccessControl.DirectorySecurity]::new();$acl.SetAccessRuleProtection($true,$false);$acl.SetOwner($sid)
   foreach($id in @($sid,[Security.Principal.SecurityIdentifier]::new('S-1-5-18'))){$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new($id,[Security.AccessControl.FileSystemRights]::FullControl,$inherit,[Security.AccessControl.PropagationFlags]::None,[Security.AccessControl.AccessControlType]::Allow))}
   $parent=$parent.CreateSubdirectory($name,$acl)
  }else{$parent=[IO.DirectoryInfo]::new($next)}
  Assert-Private $parent $true
 }
 $items=@($parent.GetFileSystemInfos());if($items.Count -gt 32){exit 26}
 foreach($item in $items){if($item -isnot [IO.FileInfo] -or $item.Name -notmatch '^(settings\.json|settings\.lock|settings-[a-f0-9]{32}\.tmp)$'){exit 27};Assert-Private $item $false}
 [Console]::Out.Write($sid.Value);exit 0
} catch {exit 28}
PS;
        $out=tmpfile();$err=tmpfile();if($out===false||$err===false){throw new RuntimeException('connection_private_storage_unavailable');}
        try{
            $exit=(new MysqldumpProcess())->run(['C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe','-NoLogo','-NoProfile','-NonInteractive','-Command',$script],
                $out,$err,15,4096,4096,json_encode(['path'=>$path,'create'=>$create],JSON_THROW_ON_ERROR));
            rewind($out);$sid=stream_get_contents($out,4096);
            if($exit!==0||preg_match('/^S-1-(?:[0-9]+-)+[0-9]+$/D',(string)$sid)!==1){throw new RuntimeException('connection_private_storage_unavailable');}return $sid;
        }finally{fclose($out);fclose($err);}
    }
}
