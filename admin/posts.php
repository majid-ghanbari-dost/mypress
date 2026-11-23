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
$search = trim((string)($_GET['q'] ?? ''));
if (strlen($search) > 400) {
    http_response_code(400);
    exit('عبارت جست‌وجو بیش از حد طولانی است.');
}
$allowedStatuses = ['draft', 'published'];
$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, $allowedStatuses, true)) $status = 'all';
$where = ["p.type='post'"];
$params = [];
if (!$isEditor) {
    $where[] = 'p.author_id=?';
    $params[] = (int)$user['id'];
}
if ($search !== '') {
    $where[] = '(p.title LIKE ? OR p.slug LIKE ?)';
    $params[] = $search . '%';
    $params[] = $search . '%';
}
if ($status !== 'all') {
    $where[] = 'p.status=?';
    $params[] = $status;
}
$whereSql = ' WHERE ' . implode(' AND ', $where);
$count = $pdo->prepare('SELECT COUNT(*) FROM posts p' . $whereSql);
$count->execute($params);
$totalRows = (int)$count->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min(mp_admin_page_number(), $totalPages);
$offset = ($page - 1) * $perPage;
$statement = $pdo->prepare('SELECT p.*,u.display_name AS author_name FROM posts p LEFT JOIN users u ON u.id=p.author_id' . $whereSql . ' ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?');
foreach ($params as $index => $value) {
    $statement->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$statement->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$statement->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$statement->execute();
$rows = $statement->fetchAll();
$paginationQuery = [];
if ($search !== '') $paginationQuery['q'] = $search;
if ($status !== 'all') $paginationQuery['status'] = $status;
mp_admin_header(t('posts'), $user);
?>
<form method="get" action="posts.php" style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0">
<label>جست‌وجوی نوشته <input type="search" name="q" maxlength="100" value="<?= e($search) ?>" placeholder="عنوان یا اسلاگ"></label>
<label>وضعیت <select name="status" style="width:auto">
<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>همه وضعیت‌ها</option>
<option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
<option value="published" <?= $status === 'published' ? 'selected' : '' ?>>منتشرشده</option>
</select></label>
<button class="btn btn-small" type="submit">اعمال فیلتر</button>
<a href="posts.php">پاک‌کردن</a>
</form>
<?php mp_admin_pagination($page, $totalPages, 'posts.php', $paginationQuery); ?>
<p><a class="btn" href="post-edit.php?type=post">＋ نوشته جدید</a></p>
<table class="table"><tr><th>عنوان</th><th>وضعیت</th><th>نویسنده</th><th>تاریخ</th><th>عملیات</th></tr>
<?php foreach ($rows as $row): ?><tr>
<td><?= e($row['title']) ?></td><td><?= e($row['status']) ?></td><td><?= e($row['author_name'] ?? '') ?></td><td><?= e($row['created_at']) ?></td>
<td><a href="post-edit.php?type=post&id=<?= (int)$row['id'] ?>">ویرایش</a>
<?php if (mp_can($user,'editor')): ?><form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form><?php endif; ?>
</td></tr><?php endforeach; ?></table>
<?php mp_admin_pagination($page, $totalPages, 'posts.php', $paginationQuery); ?>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
