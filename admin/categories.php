<?php
// admin/categories.php — دسته‌های نوشته و محصول
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_role('editor');
$pdo = mp_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_csrf_check();
    if (isset($_POST['delete_id'])) {
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([(int)$_POST['delete_id']]);
        mp_flash('دسته حذف شد.');
    } else {
        $name = trim($_POST['name'] ?? ''); $ctype = ($_POST['ctype'] ?? 'post') === 'product' ? 'product' : 'post';
        $slug = mp_slug($name);
        if ($name !== '' && strlen($name) <= 120 && $slug !== '' && strlen($slug) <= 120) {
            $pdo->prepare("INSERT INTO categories (name,slug,type) VALUES (?,?,?)")->execute([$name, $slug, $ctype]);
            mp_flash('دسته ساخته شد.', 'ok');
        } else {
            mp_flash('نام دسته باید بین ۱ تا ۱۲۰ بایت باشد.', 'err');
        }
    }
    mp_redirect('categories.php');
}
$rows = $pdo->query("SELECT * FROM categories ORDER BY type, name")->fetchAll();
mp_admin_header('دسته‌ها', $user);
?>
<form method="post"><?= mp_csrf_field() ?>
<label>نام دسته <input name="name" required maxlength="120"></label>
<label>نوع <select name="ctype"><option value="post">نوشته</option><option value="product">محصول</option></select></label>
<button class="btn" type="submit">افزودن دسته</button></form>
<table class="table"><tr><th>نام</th><th>اسلاگ</th><th>نوع</th><th>عملیات</th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td dir="ltr"><?= e($r['slug']) ?></td><td><?= e($r['type']) ?></td>
<td><form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form></td></tr><?php endforeach; ?></table>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
