<?php
// Media uploads are owner-scoped for authors; editorial roles can manage the library.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_login();
$pdo = mp_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    mp_csrf_check();
    $name = mp_upload_image($_FILES['image']);
    if ($name) {
        $path = __DIR__ . '/../uploads/' . $name;
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        $size = filesize($path) ?: 0;
        $original = basename((string)($_FILES['image']['name'] ?? 'image'));
        $pdo->prepare('INSERT INTO media (filename,original_name,mime,size,uploaded_by) VALUES (?,?,?,?,?)')
            ->execute([$name, $original, $mime, $size, (int)$user['id']]);
        mp_flash('تصویر با موفقیت آپلود شد.');
    } else {
        mp_flash('فایل نامعتبر است؛ فقط تصویر معتبر تا ۲ مگابایت پذیرفته می‌شود.', 'err');
    }
    mp_redirect('media.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    mp_csrf_check();
    $mediaId = (int)$_POST['delete_id'];
    $statement = $pdo->prepare('SELECT filename,uploaded_by FROM media WHERE id=?');
    $statement->execute([$mediaId]);
    $media = $statement->fetch();
    if ($media && (mp_can($user, 'editor') || (int)$media['uploaded_by'] === (int)$user['id'])) {
        $filename = basename((string)$media['filename']);
        $pdo->prepare('DELETE FROM media WHERE id=?')->execute([$mediaId]);
        $file = __DIR__ . '/../uploads/' . $filename;
        if (is_file($file)) @unlink($file);
        mp_flash('رسانه حذف شد.');
    } else {
        http_response_code(403);
        exit('اجازه حذف این رسانه را ندارید.');
    }
    mp_redirect('media.php');
}

$perPage = 25;
$isEditor = mp_can($user, 'editor');
$search = trim((string)($_GET['q'] ?? ''));
$search = function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100);
$uploaderFilter = '';
if ($isEditor) {
    $rawUploader = filter_var($_GET['uploader'] ?? '', FILTER_VALIDATE_INT);
    if ($rawUploader !== false && $rawUploader !== null && $rawUploader > 0) {
        $uploaderFilter = (string)$rawUploader;
    }
}

$where = [];
$params = [];
if (!$isEditor) {
    $where[] = 'm.uploaded_by = ?';
    $params[] = (int)$user['id'];
}
if ($search !== '') {
    $where[] = '(m.filename LIKE ? OR m.original_name LIKE ?)';
    $prefix = $search . '%';
    $params[] = $prefix;
    $params[] = $prefix;
}
if ($uploaderFilter !== '') {
    $where[] = 'm.uploaded_by = ?';
    $params[] = (int)$uploaderFilter;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$countQuery = $pdo->prepare('SELECT COUNT(*) FROM media m' . $whereSql);
$countQuery->execute($params);
$totalRows = (int)$countQuery->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min(mp_admin_page_number(), $totalPages);
$offset = ($page - 1) * $perPage;

$statement = $pdo->prepare(
    'SELECT m.*, u.display_name AS uploader_name, u.username AS uploader_username
     FROM media m LEFT JOIN users u ON u.id=m.uploaded_by' . $whereSql .
    ' ORDER BY m.created_at DESC, m.id DESC LIMIT ? OFFSET ?'
);
foreach ($params as $index => $value) {
    $statement->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$statement->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$statement->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$statement->execute();
$rows = $statement->fetchAll();

$paginationQuery = [];
if ($search !== '') $paginationQuery['q'] = $search;
if ($uploaderFilter !== '') $paginationQuery['uploader'] = $uploaderFilter;
$uploaders = [];
if ($isEditor) {
    $uploaders = $pdo->query('SELECT id,username,display_name FROM users ORDER BY display_name,id')->fetchAll();
}

mp_admin_header(t('media'), $user);
mp_admin_pagination($page, $totalPages, 'media.php', $paginationQuery);
?>
<form method="post" enctype="multipart/form-data"><?= mp_csrf_field() ?>
<label>انتخاب تصویر <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" required></label>
<button class="btn" type="submit">آپلود</button></form>
<form method="get" class="admin-filters">
<label>جستجوی نام فایل <input type="search" name="q" value="<?= e($search) ?>" maxlength="100"></label>
<?php if ($isEditor): ?><label>بارگذاری‌شده توسط
<select name="uploader"><option value="">همه کاربران</option>
<?php foreach ($uploaders as $uploader): ?><option value="<?= (int)$uploader['id'] ?>" <?= $uploaderFilter === (string)$uploader['id'] ? 'selected' : '' ?>><?= e($uploader['display_name'] . ' (' . $uploader['username'] . ')') ?></option><?php endforeach; ?>
</select></label><?php endif; ?>
<button class="btn" type="submit">اعمال فیلتر</button>
<a class="btn" href="media.php">پاک‌کردن</a>
</form>
<table class="table"><tr><th>پیش‌نمایش</th><th>نام فایل</th><th>حجم</th><th>بارگذار</th><th>تاریخ</th><th>عملیات</th></tr>
<?php foreach ($rows as $media): ?><tr>
<td><img src="../uploads/<?= e(basename((string)$media['filename'])) ?>" style="max-height:60px" alt=""></td>
<td dir="ltr"><?= e($media['filename']) ?><br><small><?= e($media['original_name']) ?></small></td>
<td><?= number_format((int)$media['size']) ?> بایت</td>
<td><?= e($media['uploader_name'] ?: $media['uploader_username'] ?: '—') ?></td>
<td><?= e($media['created_at']) ?></td>
<td><form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$media['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form></td></tr><?php endforeach; ?></table>
<?php mp_admin_pagination($page, $totalPages, 'media.php', $paginationQuery); ?>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
