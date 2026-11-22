<?php // themes/default/single.php — نوشته یا برگه + نظرات
// ثبت نظر در index.php (قبل از خروجی) انجام می‌شود؛ اینجا فقط نمایش است.
require_once __DIR__ . '/../../modules/comments/comments.php';
if (!$post): ?><h2>یافت نشد</h2><p>این صفحه وجود ندارد یا منتشر نشده است.</p><?php return; endif;
?>
<article class="single">
  <h2><?= e($post['title']) ?></h2>
  <p class="meta"><?= e($post['created_at']) ?><?php if (!empty($post['author_name'])) echo ' — ' . e($post['author_name']); ?><?php if (!empty($post['category_name'])) echo ' — ' . e($post['category_name']); ?></p>
  <div class="content"><?= nl2br(e($post['content'])) ?></div>
  <?php if (!empty($post['tags'])): ?><p class="tags">🏷 <?= e($post['tags']) ?></p><?php endif; ?>
</article>
<?php if ($route['type'] === 'post' && mp_module_enabled('comments')): ?>
<section class="comments"><h3>💬 نظرات</h3>
<?php foreach (mp_approved_comments((int)$post['id']) as $c): ?>
  <div class="comment"><b><?= e($c['author']) ?></b> <small><?= e($c['created_at']) ?></small><p><?= nl2br(e($c['content'])) ?></p></div>
<?php endforeach; ?>
<form method="post"><h4>ثبت نظر</h4><?= mp_csrf_field() ?>
  <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
  <input type="hidden" name="slug" value="<?= e($post['slug']) ?>">
  <input type="hidden" name="ptype" value="<?= e($route['type']) ?>">
  <label>نام <input name="author" required></label>
  <label>ایمیل <input type="email" name="email" required dir="ltr"></label>
  <label>نظر <textarea name="content" required rows="4"></textarea></label>
  <button class="btn" name="comment_submit" value="1" type="submit">ارسال نظر</button>
</form></section>
<?php endif; ?>
