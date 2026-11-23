<?php
// admin/backup.php — خروجی JSON + دانلود فایل SQLite (فقط مدیر)
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_role('admin');
$pdo = mp_pdo();
if (isset($_GET['download']) && $_GET['download'] === 'json') {
    $data = [];
    foreach (['users','categories','posts','comments','media','menus','products','orders','order_items','coupons','options'] as $tbl) {
        $data[$tbl] = $pdo->query("SELECT * FROM $tbl")->fetchAll();
    }
    // رمز عبور کاربران در خروجی JSON حذف می‌شود
    foreach ($data['users'] as &$u) unset($u['password_hash']);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="mypress-backup-' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
