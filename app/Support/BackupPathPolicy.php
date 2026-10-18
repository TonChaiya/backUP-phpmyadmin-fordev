<?php

declare(strict_types=1);

namespace BackupApp\Support;

use RuntimeException;

final class BackupPathPolicy
{
    /** @var array<int, string> */
    private array $allowedRoots = [];

    /** @var array<int, string> */
    private array $protectedRoots = [];

    /**
     * @param array<int, string> $allowedRoots
     */
    public function __construct(array $allowedRoots, ?string $projectRoot = null)
    {
        if ($projectRoot !== null && ($canonicalProject = realpath($projectRoot)) !== false) {
            foreach (['app', 'config', 'public'] as $directory) {
                $path = realpath($canonicalProject . DIRECTORY_SEPARATOR . $directory);

                if ($path !== false) {
                    $this->protectedRoots[] = $this->normalizeCanonical($path);
                }
            }
        }

        foreach ($allowedRoots as $root) {
            if (!is_string($root) || !$this->isAbsolute($root)) {
                throw new RuntimeException('Backup allowlist contains an invalid path');
            }

            $canonical = realpath($root);

            if (
                $canonical === false
                || !is_dir($canonical)
                || $this->isFilesystemRoot($canonical)
                || $this->overlapsProtected($this->normalizeCanonical($canonical))
            ) {
                throw new RuntimeException('Backup allowlist root is unavailable or unsafe');
            }

            $this->allowedRoots[] = $this->normalizeCanonical($canonical);
        }

        if ($this->allowedRoots === []) {
            throw new RuntimeException('At least one backup root is required');
        }

    }

    public function defaultRoot(): string
    {
        return $this->allowedRoots[0];
    }

    public function resolve(string $path, bool $create = false): string
    {
        $path = trim($path);

        if (!$this->isAbsolute($path) || $this->containsTraversal($path)) {
            throw new RuntimeException('Backup path is not allowed');
        }

        $canonical = realpath($path);

        if ($canonical === false && $create) {
            $this->createContainedDirectory($path);
            $canonical = realpath($path);
        }

        if ($canonical === false || !is_dir($canonical)) {
            throw new RuntimeException('Backup path is unavailable');
        }

        $canonical = $this->normalizeCanonical($canonical);

        if (!$this->isAllowed($canonical) || $this->isProtected($canonical)) {
            throw new RuntimeException('Backup path is not allowed');
        }

        return $canonical;
    }

    private function createContainedDirectory(string $path): void
    {
        $ancestor = $path;

        while (!file_exists($ancestor)) {
            $parent = dirname($ancestor);

            if ($parent === $ancestor) {
                throw new RuntimeException('Backup path has no valid parent');
            }

            $ancestor = $parent;
        }

        $canonicalAncestor = realpath($ancestor);

        if ($canonicalAncestor === false || !$this->isAllowed($this->normalizeCanonical($canonicalAncestor))) {
            throw new RuntimeException('Backup path is outside the allowlist');
        }

        if (!mkdir($path, 0750, true) && !is_dir($path)) {
            throw new RuntimeException('Cannot create backup directory');
        }
    }

    private function isAllowed(string $path): bool
    {
        foreach ($this->allowedRoots as $root) {
            if ($this->contains($root, $path)) {
                return true;
            }
        }

        return false;
    }

    private function isProtected(string $path): bool
    {
        foreach ($this->protectedRoots as $root) {
            if ($this->contains($root, $path)) {
                return true;
            }
        }

        return false;
    }

    private function overlapsProtected(string $path): bool
    {
        foreach ($this->protectedRoots as $protectedRoot) {
            if ($this->contains($protectedRoot, $path) || $this->contains($path, $protectedRoot)) {
                return true;
            }
        }

        return false;
    }

    private function contains(string $root, string $path): bool
    {
        $rootForCompare = PHP_OS_FAMILY === 'Windows' ? strtolower($root) : $root;
        $pathForCompare = PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;

        return $pathForCompare === $rootForCompare
            || str_starts_with($pathForCompare, rtrim($rootForCompare, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    private function isAbsolute(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
            || str_starts_with($path, '\\\\')
            || str_starts_with($path, '/');
    }

    private function containsTraversal(string $path): bool
    {
        $segments = preg_split('/[\\\\\/]+/', $path) ?: [];

        return in_array('..', $segments, true) || str_contains($path, "\0");
    }

    private function normalizeCanonical(string $path): string
    {
        $trimmed = rtrim($path, "\\/");

        return $trimmed === '' ? DIRECTORY_SEPARATOR : $trimmed;
    }

    private function isFilesystemRoot(string $path): bool
    {
        $normalized = $this->normalizeCanonical($path);

        return $normalized === DIRECTORY_SEPARATOR
            || preg_match('/^[A-Za-z]:$/', $normalized) === 1;
    }
}

