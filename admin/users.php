<?php
// admin/users.php — مدیریت کاربران (فقط مدیر)
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_role('admin');
$pdo = mp_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_csrf_check();
    if (isset($_POST['delete_id'])) {
        if ((int)$_POST['delete_id'] !== (int)$user['id']) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int)$_POST['delete_id']]);
    } else {
        $u = trim($_POST['username'] ?? ''); $pass = (string)($_POST['password'] ?? '');
        $email = trim($_POST['email'] ?? ''); $role = in_array($_POST['role'] ?? '', ['admin','editor','author'], true) ? $_POST['role'] : 'author';
        if ($u !== '' && strlen($pass) >= 6 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pdo->prepare("INSERT INTO users (username,password_hash,email,display_name,role) VALUES (?,?,?,?,?)")
                ->execute([$u, password_hash($pass, PASSWORD_DEFAULT), $email, $u, $role]);
            mp_flash('کاربر ساخته شد.');
        } else mp_flash('اطلاعات کاربر ناقص است.', 'err');
    }
    mp_redirect('users.php');
}
$rows = $pdo->query("SELECT id,username,email,display_name,role,created_at FROM users ORDER BY created_at")->fetchAll();
mp_admin_header(t('users'), $user);
?>
<form method="post"><?= mp_csrf_field() ?>
<label>نام کاربری <input name="username" required dir="ltr"></label>
<label>رمز عبور (حداقل ۶ کاراکتر) <input type="password" name="password" required minlength="6" dir="ltr"></label>
<label>ایمیل <input type="email" name="email" required dir="ltr"></label>
<label>نقش <select name="role"><option value="author">نویسنده</option><option value="editor">ویرایشگر</option><option value="admin">مدیر</option></select></label>
<button class="btn" type="submit">ساخت کاربر</button></form>
<table class="table"><tr><th>نام کاربری</th><th>نام نمایشی</th><th>ایمیل</th><th>نقش</th><th>عملیات</th></tr>
<?php foreach ($rows as $r): ?><tr><td dir="ltr"><?= e($r['username']) ?></td><td><?= e($r['display_name']) ?></td><td dir="ltr"><?= e($r['email']) ?></td><td><?= e(mp_role_label($r['role'])) ?></td>
<td><?php if ((int)$r['id'] !== (int)$user['id']): ?><form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form><?php endif; ?></td></tr><?php endforeach; ?></table>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
