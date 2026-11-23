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

$search = trim((string)($_GET['q'] ?? ''));
$search = function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100);
$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, ['', 'pending', 'approved'], true)) $statusFilter = '';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(c.author LIKE ? OR c.email LIKE ?)';
    $prefix = $search . '%';
    $params[] = $prefix;
    $params[] = $prefix;
}
if ($statusFilter !== '') {
    $where[] = 'c.status = ?';
    $params[] = $statusFilter;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$countQuery = $pdo->prepare('SELECT COUNT(*) FROM comments c' . $whereSql);
$countQuery->execute($params);
$totalRows = (int)$countQuery->fetchColumn();

$perPage = 25;
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min(mp_admin_page_number(), $totalPages);
$offset = ($page - 1) * $perPage;
$statement = $pdo->prepare(
    'SELECT c.*, p.title AS post_title FROM comments c LEFT JOIN posts p ON p.id=c.post_id' .
    $whereSql . ' ORDER BY c.created_at DESC, c.id DESC LIMIT ? OFFSET ?'
);
foreach ($params as $index => $value) {
    $statement->bindValue($index + 1, $value, PDO::PARAM_STR);
}
$statement->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$statement->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$statement->execute();
$rows = $statement->fetchAll();
$paginationQuery = [];
if ($search !== '') $paginationQuery['q'] = $search;
if ($statusFilter !== '') $paginationQuery['status'] = $statusFilter;

mp_admin_header(t('comments'), $user);
mp_admin_pagination($page, $totalPages, 'comments.php', $paginationQuery);
?>
<form method="get" class="admin-filters">
<label>جستجوی نام یا ایمیل نویسنده <input type="search" name="q" value="<?= e($search) ?>" maxlength="100"></label>
<label>وضعیت
<select name="status">
<option value="">همه وضعیت‌ها</option>
<option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>در انتظار تایید</option>
<option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>تاییدشده</option>
</select></label>
<button class="btn" type="submit">اعمال فیلتر</button>
<a class="btn" href="comments.php">پاک‌کردن</a>
</form>
<table class="table"><tr><th>نوشته</th><th>نویسنده</th><th>نظر</th><th>وضعیت</th><th>عملیات</th></tr>
<?php foreach ($rows as $r): ?><tr>
<td><?= e($r['post_title'] ?? '') ?></td><td><?= e($r['author']) ?><br><small dir="ltr"><?= e($r['email']) ?></small></td>
<td><?= nl2br(e($r['content'])) ?></td><td><?= e($r['status']) ?></td>
<td><?php if ($r['status'] !== 'approved'): ?><form method="post" style="display:inline"><?= mp_csrf_field() ?><input type="hidden" name="approve_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-small" type="submit">تایید</button></form><?php endif; ?>
<form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form></td></tr><?php endforeach; ?></table>
<?php mp_admin_pagination($page, $totalPages, 'comments.php', $paginationQuery); ?>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
