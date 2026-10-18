<?php
declare(strict_types=1);
namespace BackupApp\Restore\Integration\Secrets;
interface RuntimeIdentityProvider { public function currentIdentity(): WindowsIdentity; }
