<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Secrets;

final class ProductionNativeHelperDeploymentVerifier implements NativeHelperDeploymentVerifier
{
    /** Populated only after an authorized Authenticode signing and deployment review. */
    private const EXPECTED_SHA256 = '';
    private const EXPECTED_PUBLISHER = '';
    private const EXPECTED_THUMBPRINT = '';
    private const FIXED_PATH = 'C:\\ProgramData\\BackupPhpMyAdmin\\SecretProvider\\backup-secret-helper.exe';

    public function verify(string $path): void
    {
        if (!hash_equals(self::FIXED_PATH, $path)
            || self::EXPECTED_SHA256 === ''
            || self::EXPECTED_PUBLISHER === ''
            || self::EXPECTED_THUMBPRINT === '') {
            throw new SecretProviderException('native_helper_deployment_unverified');
        }

        if (!is_file($path) || is_link($path)) {
            throw new SecretProviderException('native_helper_unavailable');
        }

        $actualHash = @hash_file('sha256', $path);
        if (!is_string($actualHash) || preg_match('/^[0-9a-f]{64}$/D', self::EXPECTED_SHA256) !== 1
            || !hash_equals(self::EXPECTED_SHA256, strtolower($actualHash))) {
            throw new SecretProviderException('native_helper_deployment_unverified');
        }

        // Authenticode status, publisher and thumbprint are verified by the authorized
        // deployment procedure before these reviewed pins and artifact hash are set.
    }
}
