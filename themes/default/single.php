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
<?php
$commentPostId = (int)$post['id'];
$commentsPerPage = 25;
$commentTotal = mp_approved_comments_count($commentPostId);
$commentTotalPages = max(1, (int)ceil($commentTotal / $commentsPerPage));
$rawCommentPage = filter_var($_GET['comment_page'] ?? 1, FILTER_VALIDATE_INT);
$commentPage = min(max(1, $rawCommentPage === false || $rawCommentPage === null ? 1 : $rawCommentPage), $commentTotalPages);
$comments = mp_approved_comments($commentPostId, $commentsPerPage, ($commentPage - 1) * $commentsPerPage);
?>
<?php foreach ($comments as $c): ?>
  <div class="comment"><b><?= e($c['author']) ?></b> <small><?= e($c['created_at']) ?></small><p><?= nl2br(e($c['content'])) ?></p></div>
<?php endforeach; ?>
<?php if ($commentTotalPages > 1): ?>
<nav class="pagination" aria-label="صفحه‌بندی نظرات" style="display:flex;gap:12px;align-items:center;margin:16px 0">
  <?php if ($commentPage > 1): ?><a href="index.php?post=<?= rawurlencode((string)$post['slug']) ?>&amp;comment_page=<?= $commentPage - 1 ?>">نظرات قبلی</a><?php endif; ?>
  <span>صفحه <?= $commentPage ?> از <?= $commentTotalPages ?></span>
  <?php if ($commentPage < $commentTotalPages): ?><a href="index.php?post=<?= rawurlencode((string)$post['slug']) ?>&amp;comment_page=<?= $commentPage + 1 ?>">نظرات بعدی</a><?php endif; ?>
</nav>
<?php endif; ?>
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
