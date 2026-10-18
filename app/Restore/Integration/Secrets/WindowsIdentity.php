<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Secrets;

final class WindowsIdentity
{
    public function __construct(private readonly string $account, private readonly string $sid)
    {
        if ($account === '' || strlen($account) > 256 || preg_match('/[\x00-\x1F\x7F]/', $account) === 1
            || preg_match('/^S-[0-9]+(?:-[0-9]+){2,15}$/D', $sid) !== 1) {
            throw new SecretProviderException('windows_identity_invalid');
        }
    }

    public function account(): string { return $this->account; }
    public function sid(): string { return $this->sid; }

    /** @return array{account: string, sid: string} */
    public function metadata(): array
    {
        return ['account' => $this->account, 'sid' => $this->sid];
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->sid, $other->sid) && hash_equals($this->account, $other->account);
    }
}
