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
$perPage = 25;
$totalRows = (int)$pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min(mp_admin_page_number(), $totalPages);
$offset = ($page - 1) * $perPage;
$statement = $pdo->prepare("SELECT c.*, p.title AS post_title FROM comments c LEFT JOIN posts p ON p.id=c.post_id ORDER BY c.created_at DESC, c.id DESC LIMIT ? OFFSET ?");
$statement->bindValue(1, $perPage, PDO::PARAM_INT);
$statement->bindValue(2, $offset, PDO::PARAM_INT);
$statement->execute();
$rows = $statement->fetchAll();
mp_admin_header(t('comments'), $user);
mp_admin_pagination($page, $totalPages, 'comments.php');
?>
<table class="table"><tr><th>نوشته</th><th>نویسنده</th><th>نظر</th><th>وضعیت</th><th>عملیات</th></tr>
<?php foreach ($rows as $r): ?><tr>
<td><?= e($r['post_title'] ?? '') ?></td><td><?= e($r['author']) ?><br><small dir="ltr"><?= e($r['email']) ?></small></td>
<td><?= nl2br(e($r['content'])) ?></td><td><?= e($r['status']) ?></td>
<td><?php if ($r['status'] !== 'approved'): ?><form method="post" style="display:inline"><?= mp_csrf_field() ?><input type="hidden" name="approve_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-small" type="submit">تایید</button></form><?php endif; ?>
<form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form></td></tr><?php endforeach; ?></table>
<?php mp_admin_pagination($page, $totalPages, 'comments.php'); ?>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
