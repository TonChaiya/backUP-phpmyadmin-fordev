<?php

declare(strict_types=1);

use BackupApp\Backup\BackupService;
use BackupApp\Database\ConnectionFactory;
use BackupApp\Database\DatabaseRepository;
use BackupApp\Support\Flash;
use BackupApp\Support\Request;
use BackupApp\Support\Security;

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$factory = new ConnectionFactory($config);
$repository = new DatabaseRepository();
$activeBackupPath = (string) ($_SESSION['backup_path'] ?? $config['backup_path']);
ensureBackupDirectory($activeBackupPath);
$backupService = new BackupService($activeBackupPath);

$connectionData = $_SESSION['connection'] ?? [
    'host' => '127.0.0.1',
    'port' => 3306,
    'username' => 'root',
    'password' => '',
];

$databases = $_SESSION['databases'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? null)) {
        Flash::add('error', 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
        header('Location: index.php');
        exit;
    }

    $action = Request::postString('action');
    $host = Request::postString('host', '127.0.0.1');
    $port = max(1, min(65535, (int) Request::postString('port', '3306')));
    $username = Request::postString('username', 'root');
    $password = Request::postString('password');
    $requestedBackupPath = Request::postString('backup_path', $activeBackupPath);

    $connectionData = compact('host', 'port', 'username', 'password');
    $_SESSION['connection'] = $connectionData;

    try {
        if ($action === 'set_backup_path' || $action === 'open_backup_folder') {
            $activeBackupPath = normalizeBackupPath($requestedBackupPath, (string) $config['backup_path']);
            ensureBackupDirectory($activeBackupPath);
            $_SESSION['backup_path'] = $activeBackupPath;

            if ($action === 'set_backup_path') {
                Flash::add('success', 'บันทึกตำแหน่งจัดเก็บไฟล์สำรองแล้ว');
            }

            if ($action === 'open_backup_folder') {
                if (openBackupFolder($activeBackupPath)) {
                    Flash::add('success', 'เปิดโฟลเดอร์จัดเก็บไฟล์สำรองแล้ว');
                } else {
                    Flash::add('warning', 'ไม่สามารถเปิดโฟลเดอร์อัตโนมัติได้ กรุณาเปิดจาก path ที่แสดงไว้');
                }
            }

            header('Location: index.php');
            exit;
        }

        $connection = $factory->make($host, $port, $username, $password);

        if ($action === 'connect') {
            $databases = $repository->listDatabases($connection, $config['excluded_databases']);
            $_SESSION['databases'] = $databases;
            Flash::add('success', 'เชื่อมต่อสำเร็จ พบฐานข้อมูลที่สำรองได้ ' . count($databases) . ' ฐาน');
        }

        if ($action === 'backup' || $action === 'backup_all') {
            $selected = Request::postArray('databases');
            $knownDatabases = $repository->listDatabases($connection, $config['excluded_databases']);
            $databases = $knownDatabases;
            $_SESSION['databases'] = $knownDatabases;

            if ($action === 'backup_all') {
                $selected = $knownDatabases;
            }

            $selected = array_values(array_intersect($selected, $knownDatabases));

            if ($selected === []) {
                Flash::add('warning', 'กรุณาเลือกฐานข้อมูลอย่างน้อย 1 ฐาน');
            } else {
                foreach ($selected as $database) {
                    $result = $backupService->backupDatabase($connection, $database);
                    Flash::add('success', 'สำรอง `' . $result['database'] . '` สำเร็จ: ' . $result['relative_path']);
                }
            }
        }

        $connection->close();
    } catch (Throwable $exception) {
        Flash::add('error', 'ไม่สามารถดำเนินการได้: ' . $exception->getMessage());
    }

    header('Location: index.php');
    exit;
}

$flashMessages = Flash::pull();
$backupFiles = array_slice($backupService->listBackupFiles(), 0, (int) $config['max_backup_files']);
$csrfToken = Security::csrfToken();
$databaseCount = count($databases);
$backupCount = count($backupFiles);
$latestBackupTime = $backupFiles[0]['modified'] ?? null;
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$baseHref = ($basePath === '' ? '/' : $basePath . '/');

function normalizeBackupPath(string $path, string $fallback): string
{
    $path = trim($path);

    if ($path === '') {
        return $fallback;
    }

    return rtrim($path, "\\/");
}

function ensureBackupDirectory(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!mkdir($path, 0750, true) && !is_dir($path)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์จัดเก็บไฟล์สำรองได้');
    }
}

function openBackupFolder(string $path): bool
{
    if (!is_dir($path) || !function_exists('proc_open')) {
        return false;
    }

    $family = PHP_OS_FAMILY;
    $command = match ($family) {
        'Windows' => ['explorer.exe', $path],
        'Darwin' => ['open', $path],
        default => ['xdg-open', $path],
    };

    $process = @proc_open($command, [
        ['pipe', 'r'],
        ['pipe', 'w'],
        ['pipe', 'w'],
    ], $pipes);

    if (!is_resource($process)) {
        return false;
    }

    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    proc_close($process);

    return true;
}

function humanSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $size = (float) $bytes;

    foreach ($units as $unit) {
        if ($size < 1024 || $unit === 'GB') {
            return number_format($size, $unit === 'B' ? 0 : 2) . ' ' . $unit;
        }

        $size /= 1024;
    }

    return $bytes . ' B';
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Security::e($config['app_name']) ?></title>
    <base href="<?= Security::e($baseHref) ?>">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
    <main class="app-shell">
        <header class="hero">
            <div class="hero-copy">
                <div class="eyebrow">Secure MySQL Backup</div>
                <h1>สำรองฐานข้อมูล phpMyAdmin</h1>
                <p class="hero-subtitle">เชื่อมต่อ MySQL/MariaDB ด้วย IP หรือ Host เลือกฐานข้อมูล และสร้างไฟล์ SQL แยกเป็นระเบียบในตำแหน่งที่กำหนด</p>
            </div>

            <div class="status-board" aria-label="ภาพรวมระบบ">
                <div class="status-item">
                    <span class="status-label">ฐานข้อมูล</span>
                    <strong><?= Security::e((string) $databaseCount) ?></strong>
                </div>
                <div class="status-item">
                    <span class="status-label">ไฟล์สำรอง</span>
                    <strong><?= Security::e((string) $backupCount) ?></strong>
                </div>
                <div class="status-item wide">
                    <span class="status-label">ล่าสุด</span>
                    <strong><?= $latestBackupTime ? Security::e(date('Y-m-d H:i', (int) $latestBackupTime)) : 'ยังไม่มี' ?></strong>
                </div>
            </div>
        </header>

        <form class="storage-bar" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken) ?>">
            <input type="hidden" name="host" value="<?= Security::e((string) $connectionData['host']) ?>">
            <input type="hidden" name="port" value="<?= Security::e((string) $connectionData['port']) ?>">
            <input type="hidden" name="username" value="<?= Security::e((string) $connectionData['username']) ?>">
            <input type="hidden" name="password" value="<?= Security::e((string) $connectionData['password']) ?>">

            <label class="storage-field">
                <span class="storage-label">ตำแหน่งจัดเก็บไฟล์สำรอง</span>
                <input name="backup_path" type="text" value="<?= Security::e($activeBackupPath) ?>" required>
            </label>

            <div class="storage-actions">
                <button class="secondary-action compact-action" type="submit" name="action" value="set_backup_path">บันทึกตำแหน่ง</button>
                <button class="primary-action compact-action" type="submit" name="action" value="open_backup_folder">เปิดโฟลเดอร์</button>
            </div>
        </form>

        <?php if ($flashMessages !== []): ?>
            <div class="flash-stack">
                <?php foreach ($flashMessages as $message): ?>
                    <div class="flash <?= Security::e($message['type']) ?>">
                        <?= Security::e($message['message']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="workspace-grid">
            <form class="panel connect-panel" method="post" autocomplete="off">
                <div class="panel-header">
                    <div>
                        <span class="panel-kicker">Connection</span>
                        <h2>เชื่อมต่อฐานข้อมูล</h2>
                    </div>
                    <span class="panel-badge">MySQL</span>
                </div>

                <div class="panel-body">
                    <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken) ?>">

                    <div class="form-grid">
                        <div class="field">
                            <label for="host">Host / IP</label>
                            <input id="host" name="host" type="text" value="<?= Security::e((string) $connectionData['host']) ?>" required>
                        </div>
                        <div class="field">
                            <label for="port">Port</label>
                            <input id="port" name="port" type="number" min="1" max="65535" value="<?= Security::e((string) $connectionData['port']) ?>" required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" value="<?= Security::e((string) $connectionData['username']) ?>" required>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" value="<?= Security::e((string) $connectionData['password']) ?>">
                    </div>

                    <button class="primary-action full-width" type="submit" name="action" value="connect">
                        ทดสอบและดึงรายการฐานข้อมูล
                    </button>
                </div>
            </form>

            <div class="main-column">
                <form class="panel" method="post" autocomplete="off">
                    <div class="panel-header">
                        <div>
                            <span class="panel-kicker">Databases</span>
                            <h2>เลือกฐานข้อมูลเพื่อสำรอง</h2>
                        </div>
                        <?php if ($databaseCount > 0): ?>
                            <span class="panel-badge"><?= Security::e((string) $databaseCount) ?> ฐาน</span>
                        <?php endif; ?>
                    </div>

                    <div class="panel-body">
                        <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken) ?>">
                        <input type="hidden" name="host" value="<?= Security::e((string) $connectionData['host']) ?>">
                        <input type="hidden" name="port" value="<?= Security::e((string) $connectionData['port']) ?>">
                        <input type="hidden" name="username" value="<?= Security::e((string) $connectionData['username']) ?>">
                        <input type="hidden" name="password" value="<?= Security::e((string) $connectionData['password']) ?>">

                        <?php if ($databases === []): ?>
                            <div class="empty-state">
                                <strong>ยังไม่มีรายการฐานข้อมูล</strong>
                                <span>กรอกข้อมูลเชื่อมต่อแล้วกดทดสอบเพื่อดึงรายการฐานข้อมูล</span>
                            </div>
                        <?php else: ?>
                            <div class="database-list">
                                <?php foreach ($databases as $database): ?>
                                    <label class="db-option">
                                        <input type="checkbox" name="databases[]" value="<?= Security::e($database) ?>">
                                        <span><?= Security::e($database) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="action-footer">
                                <button class="primary-action" type="submit" name="action" value="backup">สำรองฐานที่เลือก</button>
                                <button class="secondary-action" type="submit" name="action" value="backup_all">สำรองทั้งหมดทีละฐาน</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>

                <section class="panel section-gap">
                    <div class="panel-header">
                        <div>
                            <span class="panel-kicker">Backup Files</span>
                            <h2>ไฟล์สำรองล่าสุด</h2>
                        </div>
                        <span class="panel-badge"><?= Security::e((string) $backupCount) ?> ไฟล์</span>
                    </div>

                    <div class="panel-body">
                        <?php if ($backupFiles === []): ?>
                            <div class="empty-state">
                                <strong>ยังไม่มีไฟล์สำรอง</strong>
                                <span>เมื่อสำรองสำเร็จ ไฟล์จะแสดงในตารางนี้ทันที</span>
                            </div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table class="backup-table">
                                    <thead>
                                        <tr>
                                            <th>ไฟล์</th>
                                            <th>โฟลเดอร์</th>
                                            <th>ขนาด</th>
                                            <th>วันที่สร้าง</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backupFiles as $file): ?>
                                            <tr>
                                                <td class="file-name"><?= Security::e($file['name']) ?></td>
                                                <td><?= Security::e($file['folder'] !== '' ? $file['folder'] : '-') ?></td>
                                                <td><?= Security::e(humanSize((int) $file['size'])) ?></td>
                                                <td><?= Security::e(date('Y-m-d H:i:s', (int) $file['modified'])) ?></td>
                                                <td class="download-cell">
                                                    <a class="download-link" href="download.php?file=<?= rawurlencode($file['relative_path']) ?>">ดาวน์โหลด</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </section>
    </main>
</body>
</html>
