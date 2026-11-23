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

if (mp_can($user, 'editor')) {
    $rows = $pdo->query('SELECT * FROM media ORDER BY created_at DESC')->fetchAll();
} else {
    $statement = $pdo->prepare('SELECT * FROM media WHERE uploaded_by=? ORDER BY created_at DESC');
    $statement->execute([(int)$user['id']]);
    $rows = $statement->fetchAll();
}
mp_admin_header(t('media'), $user);
?>
<form method="post" enctype="multipart/form-data"><?= mp_csrf_field() ?>
<label>انتخاب تصویر <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" required></label>
<button class="btn" type="submit">آپلود</button></form>
<table class="table"><tr><th>پیش‌نمایش</th><th>نام فایل</th><th>حجم</th><th>تاریخ</th><th>عملیات</th></tr>
<?php foreach ($rows as $media): ?><tr>
<td><img src="../uploads/<?= e(basename((string)$media['filename'])) ?>" style="max-height:60px" alt=""></td>
<td dir="ltr"><?= e($media['filename']) ?><br><small><?= e($media['original_name']) ?></small></td>
<td><?= number_format((int)$media['size']) ?> بایت</td><td><?= e($media['created_at']) ?></td>
<td><form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$media['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form></td></tr><?php endforeach; ?></table>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
