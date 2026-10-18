<?php

declare(strict_types=1);

namespace BackupApp\Restore\Integration\Attestation;

class MySqlPrivilegeDialect implements PrivilegeDialect
{
    public function parseDatabaseGrant(string $line): ?array
    {
        if (preg_match('/^GRANT\s+(.+?)\s+ON\s+`((?:\\.|[^`])+)`\.\*\s+TO\s+/i', trim($line), $match) !== 1) {
            return null;
        }
        $rawScope = $match[2];
        if ($this->containsUnescapedWildcard($rawScope)) {
            return ['privileges' => [], 'scope' => '__wildcard__'];
        }
        $scope = strtr($rawScope, ['\\_' => '_', '\\%' => '%', '\\\\' => '\\']);
        $privileges = array_map(
            static fn (string $value): string => strtoupper(trim($value)),
            explode(',', $match[1])
        );
        sort($privileges);
        return ['privileges' => array_values(array_unique($privileges)), 'scope' => $scope];
    }

    private function containsUnescapedWildcard(string $scope): bool
    {
        $escaped = false;
        foreach (str_split($scope) as $char) {
            if ($escaped) { $escaped = false; continue; }
            if ($char === '\\') { $escaped = true; continue; }
            if ($char === '_' || $char === '%') { return true; }
        }
        return $escaped;
    }
}
