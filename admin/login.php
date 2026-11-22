<?php
// admin/login.php — ورود مدیر
require_once __DIR__ . '/../includes/init.php';
if (mp_current_user()) mp_redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_csrf_check();
    $ok = mp_login(trim($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($ok) mp_redirect('index.php');
    $error = 'نام کاربری یا رمز عبور اشتباه است.';
}
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود — MyPress</title><link rel="stylesheet" href="../assets/css/admin.css"></head>
<body><main class="login-box">
<h1>🔐 ورود به MyPress</h1>
<?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= mp_csrf_field() ?>
  <label>نام کاربری <input name="username" required dir="ltr" autocomplete="username"></label>
  <label>رمز عبور <input type="password" name="password" required dir="ltr" autocomplete="current-password"></label>
  <button class="btn" type="submit">ورود</button>
</form><p><a href="../index.php">← بازگشت به سایت</a></p>
</main></body></html>
