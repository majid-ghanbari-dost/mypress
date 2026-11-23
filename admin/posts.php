<?php
// Post list. Authors only see their own content.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_login();
$pdo = mp_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    mp_csrf_check();
    mp_require_role('editor');
    $pdo->prepare("DELETE FROM posts WHERE id=? AND type='post'")->execute([(int)$_POST['delete_id']]);
    mp_flash('نوشته حذف شد.');
    mp_redirect('posts.php');
}
$perPage = 25;
$isEditor = mp_can($user, 'editor');
if ($isEditor) {
    $totalRows = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE type='post'")->fetchColumn();
} else {
    $count = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE type='post' AND author_id=?");
    $count->execute([(int)$user['id']]);
    $totalRows = (int)$count->fetchColumn();
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min(mp_admin_page_number(), $totalPages);
$offset = ($page - 1) * $perPage;
if ($isEditor) {
    $statement = $pdo->prepare("SELECT p.*,u.display_name AS author_name FROM posts p LEFT JOIN users u ON u.id=p.author_id WHERE p.type='post' ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?");
    $statement->bindValue(1, $perPage, PDO::PARAM_INT);
    $statement->bindValue(2, $offset, PDO::PARAM_INT);
} else {
    $statement = $pdo->prepare("SELECT p.*,u.display_name AS author_name FROM posts p LEFT JOIN users u ON u.id=p.author_id WHERE p.type='post' AND p.author_id=? ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?");
    $statement->bindValue(1, (int)$user['id'], PDO::PARAM_INT);
    $statement->bindValue(2, $perPage, PDO::PARAM_INT);
    $statement->bindValue(3, $offset, PDO::PARAM_INT);
}
$statement->execute();
$rows = $statement->fetchAll();
mp_admin_header(t('posts'), $user);
mp_admin_pagination($page, $totalPages, 'posts.php');
?>
<p><a class="btn" href="post-edit.php?type=post">＋ نوشته جدید</a></p>
<table class="table"><tr><th>عنوان</th><th>وضعیت</th><th>نویسنده</th><th>تاریخ</th><th>عملیات</th></tr>
<?php foreach ($rows as $row): ?><tr>
<td><?= e($row['title']) ?></td><td><?= e($row['status']) ?></td><td><?= e($row['author_name'] ?? '') ?></td><td><?= e($row['created_at']) ?></td>
<td><a href="post-edit.php?type=post&id=<?= (int)$row['id'] ?>">ویرایش</a>
<?php if (mp_can($user,'editor')): ?><form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form><?php endif; ?>
</td></tr><?php endforeach; ?></table>
<?php mp_admin_pagination($page, $totalPages, 'posts.php'); ?>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
