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
    $path = $cfg['db_path'];
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="mypress-' . date('Y-m-d') . '.sqlite"');
    readfile($path);
    exit;
}
mp_admin_header(t('backup'), $user);
?>
<p>پشتیبان کامل محتوا، کاربران، محصولات و سفارش‌ها:</p>
<p><a class="btn" href="backup.php?download=json">⬇️ دانلود JSON</a>
<a class="btn" href="backup.php?download=sqlite">⬇️ دانلود فایل SQLite</a></p>
<p><small>فایل SQLite در <code>data/mypress.sqlite</code> است. نسخه JSON رمز عبور کاربران را ندارد و برای مهاجرت مناسب است.</small></p>
<?php mp_admin_footer(); ?>
