<?php
// admin/comments.php — تایید / حذف نظرات
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_role('editor');
$pdo = mp_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_csrf_check();
    if (isset($_POST['approve_id'])) $pdo->prepare("UPDATE comments SET status='approved' WHERE id=?")->execute([(int)$_POST['approve_id']]);
    if (isset($_POST['delete_id'])) $pdo->prepare("DELETE FROM comments WHERE id=?")->execute([(int)$_POST['delete_id']]);
    mp_redirect('comments.php');
}
$rows = $pdo->query("SELECT c.*, p.title AS post_title FROM comments c LEFT JOIN posts p ON p.id=c.post_id ORDER BY c.created_at DESC")->fetchAll();
mp_admin_header(t('comments'), $user);
?>
<table class="table"><tr><th>نوشته</th><th>نویسنده</th><th>نظر</th><th>وضعیت</th><th>عملیات</th></tr>
<?php foreach ($rows as $r): ?><tr>
<td><?= e($r['post_title'] ?? '') ?></td><td><?= e($r['author']) ?><br><small dir="ltr"><?= e($r['email']) ?></small></td>
<td><?= nl2br(e($r['content'])) ?></td><td><?= e($r['status']) ?></td>
<td><?php if ($r['status'] !== 'approved'): ?><form method="post" style="display:inline"><?= mp_csrf_field() ?><input type="hidden" name="approve_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-small" type="submit">تایید</button></form><?php endif; ?>
<form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form></td></tr><?php endforeach; ?></table>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
