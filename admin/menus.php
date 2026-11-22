<?php
// admin/menus.php — منوی اصلی سایت
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_role('editor');
$pdo = mp_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_csrf_check();
    if (isset($_POST['delete_id'])) $pdo->prepare("DELETE FROM menus WHERE id=?")->execute([(int)$_POST['delete_id']]);
    else {
        $title = trim($_POST['title'] ?? ''); $url = trim($_POST['url'] ?? '/');
        if ($title !== '') {
            $pos = (int)$pdo->query("SELECT COALESCE(MAX(position),-1)+1 FROM menus")->fetchColumn();
            $pdo->prepare("INSERT INTO menus (title,url,position) VALUES (?,?,?)")->execute([$title,$url,$pos]);
            mp_flash('آیتم منو اضافه شد.');
        }
    }
    mp_redirect('menus.php');
}
$rows = $pdo->query("SELECT * FROM menus ORDER BY position")->fetchAll();
mp_admin_header(t('menus'), $user);
?>
<form method="post"><?= mp_csrf_field() ?>
<label>عنوان <input name="title" required></label>
<label>نشانی (مثل / یا /?page=about) <input name="url" required dir="ltr" value="/"></label>
<button class="btn" type="submit">افزودن به منو</button></form>
<table class="table"><tr><th>عنوان</th><th>نشانی</th><th>عملیات</th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['title']) ?></td><td dir="ltr"><?= e($r['url']) ?></td>
<td><form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form></td></tr><?php endforeach; ?></table>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
