<?php
// Create/edit content with ownership and role enforcement.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';

$user = mp_require_login();
$type = ($_GET['type'] ?? 'post') === 'page' ? 'page' : 'post';
$id = (int)($_GET['id'] ?? 0);
if (($user['role'] ?? '') === 'author' && $type !== 'post') {
    http_response_code(403);
    exit('نویسنده فقط مجاز به مدیریت نوشته‌های خودش است.');
}

$pdo = mp_pdo();
$row = null;
if ($id > 0) {
    $statement = $pdo->prepare('SELECT * FROM posts WHERE id=? AND type=?');
    $statement->execute([$id, $type]);
    $row = $statement->fetch() ?: null;
    if (!$row) { http_response_code(404); exit('محتوا پیدا نشد.'); }
    if (!mp_can_edit_post($user, $row)) { http_response_code(403); exit('اجازه ویرایش این محتوا را ندارید.'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_csrf_check();
    $title = trim((string)($_POST['title'] ?? ''));
    $slug = mp_slug((string)($_POST['slug'] ?? $title));
    $content = (string)($_POST['content'] ?? '');
    $excerpt = trim((string)($_POST['excerpt'] ?? ''));
    $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    if (!mp_can($user, 'editor')) $status = 'draft';
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $tags = trim((string)($_POST['tags'] ?? ''));

    if ($title === '' || strlen($title) > 255 || strlen($slug) > 255 ||
        strlen($excerpt) > 500 || strlen($tags) > 255 || strlen($content) > 2000000) {
        mp_flash('عنوان و اطلاعات محتوا معتبر نیستند.', 'err');
    } else {
        if ($categoryId > 0) {
            $category = $pdo->prepare("SELECT id FROM categories WHERE id=? AND type='post'");
            $category->execute([$categoryId]);
            if (!$category->fetchColumn()) $categoryId = 0;
        }
        try {
            if ($id > 0 && $row) {
                $sql = 'UPDATE posts SET title=?,slug=?,content=?,excerpt=?,status=?,category_id=?,tags=?,updated_at=? WHERE id=?';
                $params = [$title,$slug,$content,$excerpt,$status,$categoryId ?: null,$tags,date('Y-m-d H:i:s'),$id];
                if (!mp_can($user, 'editor')) {
                    $sql .= ' AND author_id=? AND status=\'draft\'';
                    $params[] = (int)$user['id'];
                }
                $pdo->prepare($sql)->execute($params);
                if (!mp_can($user, 'editor')) {
                    $verify = $pdo->prepare('SELECT author_id,status FROM posts WHERE id=?');
                    $verify->execute([$id]);
                    $current = $verify->fetch();
                    if (!$current || (int)$current['author_id'] !== (int)$user['id'] || $current['status'] !== 'draft') {
                        http_response_code(403);
                        exit('این نوشته دیگر قابل ویرایش نیست.');
                    }
                }
                mp_flash('محتوا ذخیره شد.');
            } else {
                $pdo->prepare(
                    'INSERT INTO posts (title,slug,content,excerpt,type,status,category_id,tags,author_id,created_at,updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$title,$slug,$content,$excerpt,$type,$status,$categoryId ?: null,$tags,(int)$user['id'],date('Y-m-d H:i:s'),date('Y-m-d H:i:s')]);
                mp_flash('محتوا ساخته شد.');
            }
            mp_redirect($type === 'page' ? 'pages.php' : 'posts.php');
        } catch (PDOException $exception) {
            mp_flash('ذخیره انجام نشد؛ ممکن است نشانی محتوا تکراری باشد.', 'err');
        }
    }
}

$categories = $pdo->query("SELECT * FROM categories WHERE type='post' ORDER BY name")->fetchAll();
mp_admin_header(($row ? 'ویرایش ' : 'جدید — ') . ($type === 'page' ? 'برگه' : 'نوشته'), $user);
?>
<form method="post">
<?= mp_csrf_field() ?>
<label>عنوان <input id="title-field" name="title" required maxlength="255" value="<?= e($row['title'] ?? '') ?>"></label>
<label>اسلاگ <input id="slug-field" name="slug" maxlength="255" dir="ltr" value="<?= e($row['slug'] ?? '') ?>"></label>
<label>محتوا <textarea name="content" rows="12"><?= e($row['content'] ?? '') ?></textarea></label>
<label>خلاصه <input name="excerpt" maxlength="500" value="<?= e($row['excerpt'] ?? '') ?>"></label>
<?php if ($type === 'post'): ?>
<label>دسته <select name="category_id"><option value="0">— بدون دسته —</option>
<?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= ((int)($row['category_id'] ?? 0) === (int)$category['id']) ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
<label>برچسب‌ها <input name="tags" maxlength="255" value="<?= e($row['tags'] ?? '') ?>"></label>
<?php endif; ?>
<?php if (mp_can($user, 'editor')): ?>
<label>وضعیت <select name="status"><option value="draft" <?= (($row['status'] ?? '') === 'draft') ? 'selected' : '' ?>>پیش‌نویس</option><option value="published" <?= (($row['status'] ?? '') === 'published') ? 'selected' : '' ?>>منتشرشده</option></select></label>
<?php else: ?><p>نوشته‌های نویسنده به‌صورت پیش‌نویس ذخیره می‌شوند و برای انتشار نیاز به تأیید ویرایشگر دارند.</p><?php endif; ?>
<button class="btn" type="submit">💾 ذخیره</button>
<a href="<?= $type === 'page' ? 'pages.php' : 'posts.php' ?>">انصراف</a>
</form>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
