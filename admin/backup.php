<?php
// admin/backup.php — خروجی JSON + دانلود فایل SQLite (فقط مدیر)
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_role('admin');
$pdo = mp_pdo();
if (isset($_GET['download']) && $_GET['download'] === 'json') {
    // Build the export incrementally on disk: fetchAll() for every table made
    // memory usage proportional to the entire database size.
    $backupDirectory = dirname(__DIR__) . '/data';
    if (!is_dir($backupDirectory) || !is_writable($backupDirectory)) {
        http_response_code(500);
        exit('پوشه داده برای ساخت نسخه پشتیبان قابل نوشتن نیست.');
    }

    $backupPath = tempnam($backupDirectory, '.mypress-json-backup-');
    if ($backupPath === false) {
        http_response_code(500);
        exit('ساخت فایل موقت پشتیبان انجام نشد.');
    }
    @chmod($backupPath, 0600);
    $handle = fopen($backupPath, 'wb');
    if ($handle === false) {
        @unlink($backupPath);
        http_response_code(500);
        exit('باز کردن فایل موقت پشتیبان انجام نشد.');
    }

    register_shutdown_function(static function () use ($backupPath): void {
        if (is_file($backupPath)) @unlink($backupPath);
    });

    try {
        // Keep all table reads in one database snapshot. MySQL's default
        // REPEATABLE READ gives the export a consistent view across tables.
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
        $pdo->beginTransaction();

        $write = static function (string $chunk) use ($handle): void {
            $offset = 0;
            $length = strlen($chunk);
            while ($offset < $length) {
                $written = fwrite($handle, substr($chunk, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Unable to write JSON backup file.');
                }
                $offset += $written;
            }
        };

        $tables = ['users','categories','posts','comments','media','menus','products','orders','order_items','coupons','options'];
        $write("{\n");
        foreach ($tables as $tableIndex => $table) {
            if ($tableIndex > 0) $write(",\n");
            $write('  ' . json_encode($table, JSON_THROW_ON_ERROR) . ': [');
            $statement = $pdo->query("SELECT * FROM `{$table}`");
            $firstRow = true;
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                if ($table === 'users') unset($row['password_hash']);
                $encoded = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
                $write(($firstRow ? "\n    " : ",\n    ") . $encoded);
                $firstRow = false;
            }
            if (!$firstRow) $write("\n  ");
            $write(']');
        }
        $write("\n}\n");
        $pdo->commit();

        if (!fclose($handle)) throw new RuntimeException('Unable to close JSON backup file.');
        $handle = false;
        $size = filesize($backupPath);
        if ($size === false) throw new RuntimeException('Unable to read JSON backup size.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (is_resource($handle)) fclose($handle);
        @unlink($backupPath);
        error_log('MyPress JSON backup failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('ساخت نسخه پشتیبان JSON انجام نشد.');
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Length: ' . $size);
    header('Content-Disposition: attachment; filename="mypress-backup-' . date('Y-m-d') . '.json"');
    readfile($backupPath);
    @unlink($backupPath);
    exit;
}
if (isset($_GET['download']) && $_GET['download'] === 'sqlite') {
    $cfg = mp_config();
    if (($cfg['db_type'] ?? 'sqlite') !== 'sqlite') exit('پشتیبان SQLite فقط برای دیتابیس SQLite است؛ برای MySQL از ابزار هاست (phpMyAdmin) استفاده کنید.');
    $path = (string)($cfg['db_path'] ?? (__DIR__ . '/../data/mypress.sqlite'));
    if (!is_file($path) || !is_readable($path)) {
        http_response_code(404);
        exit('فایل پایگاه داده در دسترس نیست.');
    }

    // VACUUM INTO creates a transactionally consistent SQLite snapshot, including
    // committed WAL content, without requiring the site to stop receiving writes.
    $backupPath = dirname($path) . DIRECTORY_SEPARATOR . '.mypress-backup-' . bin2hex(random_bytes(16)) . '.sqlite';
    register_shutdown_function(static function () use ($backupPath): void {
        if (is_file($backupPath)) @unlink($backupPath);
    });

    try {
        $quotedPath = str_replace("'", "''", $backupPath);
        $pdo->exec("VACUUM INTO '{$quotedPath}'");
        @chmod($backupPath, 0600);
        $size = filesize($backupPath);
        if ($size === false) throw new RuntimeException('Unable to read SQLite snapshot size.');
        $handle = fopen($backupPath, 'rb');
        if ($handle === false) throw new RuntimeException('Unable to open SQLite snapshot.');
    } catch (Throwable $exception) {
        @unlink($backupPath);
        error_log('MyPress SQLite backup failed: ' . $exception->getMessage());
        http_response_code(500);
        exit('ساخت نسخه پشتیبان SQLite انجام نشد.');
    }

    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $size);
    header('Content-Disposition: attachment; filename="mypress-' . date('Y-m-d') . '.sqlite"');
    fpassthru($handle);
    fclose($handle);
    @unlink($backupPath);
    exit;
}
mp_admin_header(t('backup'), $user);
?>
<p>پشتیبان کامل محتوا، کاربران، محصولات و سفارش‌ها:</p>
<p><a class="btn" href="backup.php?download=json">⬇️ دانلود JSON</a>
<a class="btn" href="backup.php?download=sqlite">⬇️ دانلود فایل SQLite</a></p>
<p><small>فایل SQLite در <code>data/mypress.sqlite</code> است. نسخه JSON رمز عبور کاربران را ندارد و برای مهاجرت مناسب است.</small></p>
<?php mp_admin_footer(); ?>
