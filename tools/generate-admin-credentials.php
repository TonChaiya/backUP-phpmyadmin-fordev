<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This tool is available from the command line only.\n");
    exit(1);
}

$password = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
$hash = password_hash($password, PASSWORD_DEFAULT);

if ($hash === false) {
    fwrite(STDERR, "Unable to generate credentials.\n");
    exit(1);
}

echo "Generated administrator credentials (shown once)\n";
echo "Password: {$password}\n";
echo "Password hash for config/local.php:\n{$hash}\n";

