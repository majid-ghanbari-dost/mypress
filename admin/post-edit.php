<?php
// admin/post-edit.php — ساخت/ویرایش نوشته یا برگه (?type=post|page&id=)
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_login();
$type = ($_GET['type'] ?? 'post') === 'page' ? 'page' : 'post';
$id = (int)($_GET['id'] ?? 0);
$pdo = mp_pdo();
$row = null;
if ($id) { $st = $pdo->prepare("SELECT * FROM posts WHERE id=? AND type=?"); $st->execute([$id,$type]); $row = $st->fetch(); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_csrf_check();
    $title = trim($_POST['title'] ?? ''); $slug = mp_slug($_POST['slug'] ?? $title);
    $content = $_POST['content'] ?? ''; $excerpt = trim($_POST['excerpt'] ?? '');
    $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $cat = (int)($_POST['category_id'] ?? 0) ?: null; $tags = trim($_POST['tags'] ?? '');
    if ($title === '') { mp_flash('عنوان الزامی است.', 'err'); }
    else {
        if ($id && $row) {
            $pdo->prepare("UPDATE posts SET title=?,slug=?,content=?,excerpt=?,status=?,category_id=?,tags=?,updated_at=? WHERE id=?")
                ->execute([$title,$slug,$content,$excerpt,$status,$cat,$tags,date('Y-m-d H:i:s'),$id]);
            mp_flash('ذخیره شد.');
        } else {
            $pdo->prepare("INSERT INTO posts (title,slug,content,excerpt,type,status,category_id,tags,author_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$title,$slug,$content,$excerpt,$type,$status,$cat,$tags,$user['id'],date('Y-m-d H:i:s'),date('Y-m-d H:i:s')]);
            $id = (int)$pdo->lastInsertId(); mp_flash('ساخته شد.');
        }
        mp_redirect($type === 'page' ? 'pages.php' : 'posts.php');
    }
}
$cats = $pdo->query("SELECT * FROM categories WHERE type='post' ORDER BY name")->fetchAll();
mp_admin_header(($row ? 'ویرایش ' : 'جدید — ') . ($type === 'page' ? 'برگه' : 'نوشته'), $user);
?>
<form method="post">
<?= mp_csrf_field() ?>
<label>عنوان <input id="title-field" name="title" required value="<?= e($row['title'] ?? '') ?>"></label>
<label>اسلاگ (نشانی) <input id="slug-field" name="slug" dir="ltr" value="<?= e($row['slug'] ?? '') ?>"></label>
<label>محتوا <textarea name="content" rows="12"><?= e($row['content'] ?? '') ?></textarea></label>
<label>خلاصه <input name="excerpt" value="<?= e($row['excerpt'] ?? '') ?>"></label>
<?php if ($type === 'post'): ?>
<label>دسته <select name="category_id"><option value="0">— بدون دسته —</option>
<?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ((int)($row['category_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
<label>برچسب‌ها (با ویرگول) <input name="tags" dir="ltr" value="<?= e($row['tags'] ?? '') ?>"></label>
<?php endif; ?>
<label>وضعیت <select name="status"><option value="draft" <?= (($row['status'] ?? '') === 'draft') ? 'selected' : '' ?>>پیش‌نویس</option><option value="published" <?= (($row['status'] ?? '') === 'published') ? 'selected' : '' ?>>منتشرشده</option></select></label>
<button class="btn" type="submit">💾 ذخیره</button>
<a href="<?= $type === 'page' ? 'pages.php' : 'posts.php' ?>">انصراف</a>
</form>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
