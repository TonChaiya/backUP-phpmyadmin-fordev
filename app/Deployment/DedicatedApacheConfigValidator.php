<?php

declare(strict_types=1);

namespace BackupApp\Deployment;

final class DedicatedApacheConfigValidator
{
    /** @param array<string, mixed> $contract @return list<string> */
    public function validate(string $apache, string $phpIni, array $contract): array
    {
        $errors = [];
        $paths = is_array($contract['paths'] ?? null) ? $contract['paths'] : [];
        $listener = is_string($contract['listener'] ?? null) ? $contract['listener'] : '';

        if ($listener === '' || $this->directives($apache, 'Listen') !== [$listener]) {
            $errors[] = 'apache_listener_invalid';
        }
        if ($this->directives($apache, 'ServerName') !== [$listener]) {
            $errors[] = 'apache_server_name_invalid';
        }

        $defineMap = [
            'APACHE_ROOT' => $paths['apache_root'] ?? '',
            'PHP_ROOT' => $paths['php_root'] ?? '',
            'APP_ROOT' => $paths['application_root'] ?? '',
            'RUNTIME_ROOT' => $paths['runtime_data_root'] ?? '',
        ];
        foreach ($defineMap as $name => $expected) {
            if (preg_match('/^\s*Define\s+' . preg_quote($name, '/') . '\s+"([^"]+)"\s*$/mi', $apache, $define) !== 1
                || $this->windows((string) ($define[1] ?? '')) !== $this->windows((string) $expected)) {
                $errors[] = 'apache_path_define_invalid';
                break;
            }
        }

        $documentRoots = array_map([$this, 'unquote'], $this->directives($apache, 'DocumentRoot'));
        if ($documentRoots !== ['${APP_ROOT}/public']) {
            $errors[] = 'apache_document_root_invalid';
        }

        if (preg_match('/^\s*(?:Include|IncludeOptional|Alias|ScriptAlias)\b/im', $apache) === 1
            || preg_match('/<\s*VirtualHost\b/i', $apache) === 1) {
            $errors[] = 'apache_external_mapping_forbidden';
        }
        if (preg_match('#C:[\\/]laragon(?:[\\/]|$)#i', $apache) === 1) {
            $errors[] = 'apache_laragon_reference_forbidden';
        }

        $loadedModules = [];
        if (preg_match_all('/^\s*LoadModule\s+([A-Za-z0-9_]+)\s+/mi', $apache, $matches) === 1
            || ($matches[1] ?? []) !== []) {
            $loadedModules = array_values(array_filter(
                $matches[1] ?? [],
                static fn (string $name): bool => $name !== 'php_module'
            ));
        }
        $expectedModules = is_array($contract['apache_modules'] ?? null)
            ? array_values(array_filter($contract['apache_modules'], 'is_string')) : [];
        sort($loadedModules);
        sort($expectedModules);
        if ($loadedModules !== $expectedModules) {
            $errors[] = 'apache_module_allowlist_invalid';
        }

        if (preg_match_all('/^\s*AllowOverride\s+([^\r\n#]+)/mi', $apache, $allowOverrides) < 1
            || array_filter($allowOverrides[1], static fn (string $value): bool => strcasecmp(trim($value), 'None') !== 0) !== []) {
            $errors[] = 'apache_override_policy_invalid';
        }
        if (preg_match('/<Directory\s+\/>.*?Require\s+all\s+denied.*?<\/Directory>/is', $apache) !== 1) {
            $errors[] = 'apache_filesystem_default_deny_missing';
        }
        if (preg_match('/<Directory\s+"\$\{APP_ROOT\}\/public">.*?Options\s+None.*?Require\s+local.*?<\/Directory>/is', $apache) !== 1) {
            $errors[] = 'apache_public_scope_invalid';
        }
        if (preg_match('/^\s*Options\s+(?!None\s*$)/mi', $apache) === 1) {
            $errors[] = 'apache_options_unsafe';
        }

        $ini = @parse_ini_string($phpIni, false, INI_SCANNER_RAW);
        if (!is_array($ini)) {
            return array_values(array_unique([...$errors, 'php_ini_invalid']));
        }

        $expectedRuntime = (string) ($paths['runtime_data_root'] ?? '');
        $expectedPhpRoot = (string) ($paths['php_root'] ?? '');
        $expectedSecretRoot = (string) ($paths['secret_provider_root'] ?? '');
        $expectedApplicationRoot = (string) ($paths['application_root'] ?? '');
        $expectedIniPaths = [
            'session.save_path' => $expectedRuntime . '\\sessions',
            'sys_temp_dir' => $expectedRuntime . '\\temporary',
            'upload_tmp_dir' => $expectedRuntime . '\\temporary',
            'error_log' => $expectedRuntime . '\\logs\\php-error.log',
            'extension_dir' => $expectedPhpRoot . '\\ext',
        ];
        foreach ($expectedIniPaths as $key => $expected) {
            if ($this->windows((string) ($ini[$key] ?? '')) !== $this->windows($expected)) {
                $errors[] = 'php_' . str_replace('.', '_', $key) . '_invalid';
            }
        }

        $openBaseDir = array_map([$this, 'windows'], explode(';', (string) ($ini['open_basedir'] ?? '')));
        $expectedBaseDirs = array_map([$this, 'windows'], [
            $expectedApplicationRoot, $expectedPhpRoot, $expectedRuntime, $expectedSecretRoot,
        ]);
        if ($openBaseDir !== $expectedBaseDirs) {
            $errors[] = 'php_open_basedir_invalid';
        }

        $extensions = [];
        if (preg_match_all('/^\s*extension\s*=\s*["\']?([A-Za-z0-9_]+)(?:\.dll)?["\']?\s*$/mi', $phpIni, $extensionMatches) > 0) {
            $extensions = array_values(array_unique(array_map('strtolower', $extensionMatches[1])));
        }
        $expectedExtensions = is_array($contract['php_extensions'] ?? null)
            ? array_values(array_map('strtolower', array_filter($contract['php_extensions'], 'is_string'))) : [];
        sort($extensions);
        sort($expectedExtensions);
        if ($extensions !== $expectedExtensions) {
            $errors[] = 'php_extension_allowlist_invalid';
        }

        foreach (['display_errors' => 'Off', 'display_startup_errors' => 'Off', 'log_errors' => 'On',
            'expose_php' => 'Off', 'allow_url_include' => 'Off', 'session.use_strict_mode' => '1'] as $key => $expected) {
            if (strcasecmp((string) ($ini[$key] ?? ''), $expected) !== 0) {
                $errors[] = 'php_security_setting_invalid';
                break;
            }
        }
        $disabled = array_map('trim', explode(',', (string) ($ini['disable_functions'] ?? '')));
        if (!in_array('shell_exec', $disabled, true) || !in_array('system', $disabled, true)
            || in_array('proc_open', $disabled, true)) {
            $errors[] = 'php_function_policy_invalid';
        }

        return array_values(array_unique($errors));
    }

    /** @return list<string> */
    private function directives(string $source, string $name): array
    {
        preg_match_all('/^\s*' . preg_quote($name, '/') . '\s+([^\r\n#]+)\s*$/mi', $source, $matches);
        return array_values(array_map('trim', $matches[1] ?? []));
    }

    private function unquote(string $value): string
    {
        return trim($value, " \t\n\r\0\x0B\"'");
    }

    private function slash(string $value): string
    {
        return strtolower(rtrim(str_replace('\\', '/', $value), '/'));
    }

    private function windows(string $value): string
    {
        return strtolower(rtrim(str_replace('/', '\\', $this->unquote($value)), '\\'));
    }
}
