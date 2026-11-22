<?php
// admin/posts.php — فهرست نوشته‌ها + حذف
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_login();
$pdo = mp_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    mp_csrf_check(); mp_require_role('editor');
    $pdo->prepare("DELETE FROM posts WHERE id=? AND type='post'")->execute([(int)$_POST['delete_id']]);
    mp_flash('نوشته حذف شد.'); mp_redirect('posts.php');
}
$rows = $pdo->query("SELECT p.*, u.display_name AS author_name FROM posts p LEFT JOIN users u ON u.id=p.author_id WHERE p.type='post' ORDER BY p.created_at DESC")->fetchAll();
mp_admin_header(t('posts'), $user);
?>
<p><a class="btn" href="post-edit.php?type=post">＋ نوشته جدید</a></p>
<table class="table"><tr><th>عنوان</th><th>وضعیت</th><th>نویسنده</th><th>تاریخ</th><th>عملیات</th></tr>
<?php foreach ($rows as $r): ?><tr>
  <td><?= e($r['title']) ?></td><td><?= e($r['status']) ?></td><td><?= e($r['author_name'] ?? '') ?></td><td><?= e($r['created_at']) ?></td>
  <td><a href="post-edit.php?type=post&id=<?= (int)$r['id'] ?>">ویرایش</a>
  <?php if (mp_can($user,'editor')): ?>
  <form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form>
  <?php endif; ?></td></tr><?php endforeach; ?></table>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
