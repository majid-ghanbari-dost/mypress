<?php
// admin/users.php — مدیریت کاربران (فقط مدیر)
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_role('admin');
$pdo = mp_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_csrf_check();
    if (isset($_POST['delete_id'])) {
        $targetId = (int)$_POST['delete_id'];
        if (mp_delete_user($pdo, $targetId, (int)$user['id'])) {
            mp_flash('کاربر حذف شد.');
        } else {
            mp_flash('حذف حساب خودتان یا آخرین مدیر سایت مجاز نیست.', 'err');
        }
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $email = trim((string)($_POST['email'] ?? ''));
        $role = in_array($_POST['role'] ?? '', ['admin','editor','author'], true) ? $_POST['role'] : 'author';
        if (preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $username) &&
            strlen($password) >= 12 && strlen($password) <= 4096 &&
            filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 150) {
            try {
                $pdo->prepare('INSERT INTO users (username,password_hash,email,display_name,role) VALUES (?,?,?,?,?)')
                    ->execute([$username,password_hash($password,PASSWORD_DEFAULT),$email,$username,$role]);
                mp_flash('کاربر ساخته شد.');
            } catch (PDOException $exception) {
                mp_flash('ساخت کاربر انجام نشد؛ نام کاربری ممکن است تکراری باشد.', 'err');
            }
        } else {
            mp_flash('نام کاربری، رمز (حداقل ۱۲ نویسه) یا ایمیل معتبر نیست.', 'err');
        }
    }
    mp_redirect('users.php');
}

// Prefix search keeps the list predictable and can use the matching indexes.
$search = trim((string)($_GET['q'] ?? ''));
$search = function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100);
$roleFilter = (string)($_GET['role'] ?? '');
if (!in_array($roleFilter, ['', 'admin', 'editor', 'author'], true)) $roleFilter = '';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(username LIKE ? OR display_name LIKE ? OR email LIKE ?)';
    $prefix = $search . '%';
    array_push($params, $prefix, $prefix, $prefix);
}
if ($roleFilter !== '') {
    $where[] = 'role = ?';
    $params[] = $roleFilter;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$countQuery = $pdo->prepare('SELECT COUNT(*) FROM users' . $whereSql);
$countQuery->execute($params);
$totalRows = (int)$countQuery->fetchColumn();

$perPage = 25;
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min(mp_admin_page_number(), $totalPages);
$offset = ($page - 1) * $perPage;
$userQuery = $pdo->prepare(
    'SELECT id,username,email,display_name,role,created_at FROM users' . $whereSql .
    ' ORDER BY created_at, id LIMIT ? OFFSET ?'
);
foreach ($params as $index => $value) {
    $userQuery->bindValue($index + 1, $value, PDO::PARAM_STR);
}
$userQuery->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$userQuery->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$userQuery->execute();
$rows = $userQuery->fetchAll();
$paginationQuery = [];
if ($search !== '') $paginationQuery['q'] = $search;
if ($roleFilter !== '') $paginationQuery['role'] = $roleFilter;

mp_admin_header(t('users'), $user);
mp_admin_pagination($page, $totalPages, 'users.php', $paginationQuery);
?>
<form method="post"><?= mp_csrf_field() ?>
<label>نام کاربری <input name="username" required minlength="3" maxlength="60" pattern="[A-Za-z0-9_.-]+" dir="ltr"></label>
<label>رمز عبور (حداقل ۱۲ نویسه) <input type="password" name="password" required minlength="12" maxlength="4096" dir="ltr"></label>
<label>ایمیل <input type="email" name="email" required maxlength="150" dir="ltr"></label>
<label>نقش <select name="role"><option value="author">نویسنده</option><option value="editor">ویرایشگر</option><option value="admin">مدیر</option></select></label>
<button class="btn" type="submit">ساخت کاربر</button></form>
<form method="get" class="admin-filters">
<label>جستجوی نام، نام کاربری یا ایمیل <input type="search" name="q" value="<?= e($search) ?>" maxlength="100"></label>
<label>نقش
<select name="role">
<option value="">همه نقش‌ها</option>
<option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>مدیر</option>
<option value="editor" <?= $roleFilter === 'editor' ? 'selected' : '' ?>>ویرایشگر</option>
<option value="author" <?= $roleFilter === 'author' ? 'selected' : '' ?>>نویسنده</option>
</select></label>
<button class="btn" type="submit">اعمال فیلتر</button>
<a class="btn" href="users.php">پاک‌کردن</a>
</form>
<table class="table"><tr><th>نام کاربری</th><th>نام نمایشی</th><th>ایمیل</th><th>نقش</th><th>عملیات</th></tr>
<?php foreach ($rows as $row): ?><tr><td dir="ltr"><?= e($row['username']) ?></td><td><?= e($row['display_name']) ?></td><td dir="ltr"><?= e($row['email']) ?></td><td><?= e(mp_role_label($row['role'])) ?></td>
<td><?php if ((int)$row['id'] !== (int)$user['id']): ?><form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form><?php endif; ?></td></tr><?php endforeach; ?></table>
<?php mp_admin_pagination($page, $totalPages, 'users.php', $paginationQuery); ?>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
