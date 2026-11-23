<?php
// Pages are editorial content; authors cannot browse or edit them.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_role('editor');
$pdo = mp_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    mp_csrf_check();
    $pdo->prepare("DELETE FROM posts WHERE id=? AND type='page'")->execute([(int)$_POST['delete_id']]);
    mp_flash('برگه حذف شد.');
    mp_redirect('pages.php');
}
$perPage = 25;
$totalRows = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE type='page'")->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min(mp_admin_page_number(), $totalPages);
$offset = ($page - 1) * $perPage;
$statement = $pdo->prepare("SELECT * FROM posts WHERE type='page' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?");
$statement->bindValue(1, $perPage, PDO::PARAM_INT);
$statement->bindValue(2, $offset, PDO::PARAM_INT);
$statement->execute();
$rows = $statement->fetchAll();
mp_admin_header(t('pages'), $user);
mp_admin_pagination($page, $totalPages, 'pages.php');
?>
<p><a class="btn" href="post-edit.php?type=page">＋ برگه جدید</a></p>
<table class="table"><tr><th>عنوان</th><th>اسلاگ</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr>
<?php foreach ($rows as $row): ?><tr>
<td><?= e($row['title']) ?></td><td dir="ltr"><?= e($row['slug']) ?></td><td><?= e($row['status']) ?></td><td><?= e($row['created_at']) ?></td>
<td><a href="post-edit.php?type=page&id=<?= (int)$row['id'] ?>">ویرایش</a> | <a href="../index.php?page=<?= rawurlencode((string)$row['slug']) ?>" target="_blank" rel="noopener">مشاهده</a>
<form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form>
</td></tr><?php endforeach; ?></table>
<?php mp_admin_pagination($page, $totalPages, 'pages.php'); ?>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
