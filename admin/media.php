<?php
// admin/media.php — آپلود و فهرست رسانه (فقط تصویر، حداکثر 2MB)
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_login();
$pdo = mp_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    mp_csrf_check();
    $name = mp_upload_image($_FILES['image']);
    if ($name) {
        $pdo->prepare("INSERT INTO media (filename,original_name,mime,size,uploaded_by) VALUES (?,?,?,?,?)")
            ->execute([$name, $_FILES['image']['name'], $_FILES['image']['type'], (int)$_FILES['image']['size'], $user['id']]);
        mp_flash('آپلود شد: ' . $name);
    } else mp_flash('فایل نامعتبر است (فقط jpg/png/gif/webp تا ۲ مگابایت).', 'err');
    mp_redirect('media.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    mp_csrf_check(); mp_require_role('editor');
    $st = $pdo->prepare("SELECT filename FROM media WHERE id=?"); $st->execute([(int)$_POST['delete_id']]); $m = $st->fetch();
    if ($m) { @unlink(__DIR__ . '/../uploads/' . $m['filename']); $pdo->prepare("DELETE FROM media WHERE id=?")->execute([(int)$_POST['delete_id']]); }
    mp_flash('رسانه حذف شد.'); mp_redirect('media.php');
}
$rows = $pdo->query("SELECT * FROM media ORDER BY created_at DESC")->fetchAll();
mp_admin_header(t('media'), $user);
?>
<form method="post" enctype="multipart/form-data"><?= mp_csrf_field() ?>
<label>انتخاب تصویر <input type="file" name="image" accept="image/*" required></label>
<button class="btn" type="submit">آپلود</button></form>
<table class="table"><tr><th>پیش‌نمایش</th><th>نام فایل</th><th>حجم</th><th>تاریخ</th><th>عملیات</th></tr>
<?php foreach ($rows as $r): ?><tr>
<td><img src="../uploads/<?= e($r['filename']) ?>" style="max-height:60px" alt=""></td>
<td dir="ltr"><?= e($r['filename']) ?><br><small><?= e($r['original_name']) ?></small></td>
<td><?= number_format((int)$r['size']) ?> بایت</td><td><?= e($r['created_at']) ?></td>
<td><form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form></td></tr><?php endforeach; ?></table>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
