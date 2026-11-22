<?php
// install.php — ویزارد نصب MyPress (۵ مرحله در یک صفحه)
// اگر config.php و قفل نصب وجود دارد، نصب دوباره مسدود است.
declare(strict_types=1);
error_reporting(E_ALL);
$root = __DIR__;
$configFile = $root . '/config.php';
$lockFile = $root . '/data/installed.lock';
if (file_exists($configFile) && file_exists($lockFile)) {
    exit('MyPress قبلاً نصب شده است. برای نصب دوباره، فایل‌های config.php و data/installed.lock را حذف کنید.');
}
require_once $root . '/includes/db.php';
require_once $root . '/includes/helpers.php';
mp_session_start();
$error = ''; $done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteTitle = trim($_POST['site_title'] ?? 'سایت من');
    $adminUser = trim($_POST['admin_user'] ?? '');
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $dbType = ($_POST['db_type'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    if ($siteTitle === '' || $adminUser === '' || strlen($adminPass) < 6 || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'همه فیلدها را درست پر کنید؛ رمز عبور حداقل ۶ کاراکتر و ایمیل معتبر باشد.';
    } else {
        $secret = bin2hex(random_bytes(32));
        $cfg = [
            'db_type' => $dbType,
            'db_path' => $root . '/data/mypress.sqlite',
            'db_host' => trim($_POST['db_host'] ?? 'localhost'),
            'db_name' => trim($_POST['db_name'] ?? 'mypress'),
            'db_user' => trim($_POST['db_user'] ?? 'root'),
            'db_pass' => (string)($_POST['db_pass'] ?? ''),
            'db_charset' => 'utf8mb4',
            'secret_key' => $secret, 'lang' => 'fa', 'debug' => false,
        ];
        try {
            // config.php را بنویس و بعد با همان پیکربندی وصل شو
            $export = var_export($cfg, true);
            file_put_contents($configFile, "<?php\n// ساخته‌شده توسط install.php — هرگز در گیت عمومی منتشر نکنید.\nreturn $export;\n");
            $pdo = mp_pdo();
            mp_create_tables($pdo);
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $st = $pdo->prepare("INSERT INTO users (username,password_hash,email,display_name,role) VALUES (?,?,?,?, 'admin')");
            $st->execute([$adminUser, $hash, $adminEmail, $adminUser]);
            $adminId = (int)$pdo->lastInsertId();
            mp_seed($pdo, $adminId);
            mp_set_option('site_title', $siteTitle);
            mp_set_option('site_description', 'سایت شخصی من با MyPress');
            mp_set_option('site_lang', 'fa');
            mp_set_option('module_shop', '1');
            mp_set_option('module_comments', '1');
            mp_set_option('module_seo', '1');
            if (!is_dir($root . '/data')) mkdir($root . '/data', 0755, true);
            file_put_contents($lockFile, date('Y-m-d H:i:s'));
            $done = true;
        } catch (Throwable $e) {
            $error = 'خطا در نصب: ' . $e->getMessage();
            @unlink($configFile);
        }
    }
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>نصب MyPress</title><link rel="stylesheet" href="assets/css/admin.css"></head>
<body><main class="install-box">
<h1>🧩 نصب MyPress</h1>
<p>سیستم مدیریت وب‌سایت شخصی — سبک، بدون وابستگی خارجی، با SQLite پیش‌فرض.</p>
<?php if ($done): ?>
  <div class="flash flash-ok">نصب کامل شد! یک نوشته، یک برگه و یک محصول نمونه ساخته شد.</div>
  <p><a class="btn" href="index.php">مشاهده سایت</a> <a class="btn" href="admin/login.php">ورود به پنل مدیریت</a></p>
  <p><small>نام کاربری مدیر: <b><?= e($adminUser ?? '') ?></b> — رمز را امن نگه دارید.</small></p>
<?php else: ?>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <label>عنوان سایت <input name="site_title" required value="سایت من"></label>
    <label>نام کاربری مدیر <input name="admin_user" required dir="ltr" autocomplete="username"></label>
    <label>رمز عبور مدیر (حداقل ۶ کاراکتر) <input type="password" name="admin_pass" required minlength="6" dir="ltr" autocomplete="new-password"></label>
    <label>ایمیل مدیر <input type="email" name="admin_email" required dir="ltr"></label>
    <label>پایگاه داده
      <select name="db_type" id="db_type">
        <option value="sqlite">SQLite (پیش‌فرض — بدون نیاز به دیتابیس جدا)</option>
        <option value="mysql">MySQL / MariaDB</option>
      </select></label>
    <div id="mysql-fields" style="display:none">
      <label>هاست <input name="db_host" value="localhost" dir="ltr"></label>
      <label>نام دیتابیس <input name="db_name" value="mypress" dir="ltr"></label>
      <label>کاربر دیتابیس <input name="db_user" value="root" dir="ltr"></label>
      <label>رمز دیتابیس <input type="password" name="db_pass" dir="ltr"></label>
    </div>
    <button class="btn" type="submit">نصب MyPress</button>
  </form>
  <script>document.getElementById('db_type').addEventListener('change',function(){document.getElementById('mysql-fields').style.display=this.value==='mysql'?'block':'none';});</script>
<?php endif; ?>
</main></body></html>
