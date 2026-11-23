<?php
// Role-scoped dashboard: authors never see site-wide sales or user statistics.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
require_once __DIR__ . '/../modules/comments/comments.php';
$user = mp_require_login();
$pdo = mp_pdo();
$stats = [];

if (mp_can($user, 'editor')) {
    $stats['نوشته‌ها'] = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE type='post'")->fetchColumn();
    $stats['برگه‌ها'] = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE type='page'")->fetchColumn();
    if (mp_module_enabled('comments')) $stats['نظرات در انتظار'] = mp_pending_comments_count();
    if (mp_module_enabled('shop')) {
        $stats['محصولات'] = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='published'")->fetchColumn();
        $stats['سفارش‌های جدید'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn();
    }
    if (mp_can($user, 'admin')) $stats['کاربران'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $latest = $pdo->query("SELECT title,created_at FROM posts ORDER BY created_at DESC LIMIT 5")->fetchAll();
} else {
    $count = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE type='post' AND author_id=?");
    $count->execute([(int)$user['id']]);
    $stats['نوشته‌های من'] = (int)$count->fetchColumn();
    $mediaCount = $pdo->prepare('SELECT COUNT(*) FROM media WHERE uploaded_by=?');
    $mediaCount->execute([(int)$user['id']]);
    $stats['رسانه‌های من'] = (int)$mediaCount->fetchColumn();
    $latestQuery = $pdo->prepare("SELECT title,created_at FROM posts WHERE author_id=? AND type='post' ORDER BY created_at DESC LIMIT 5");
    $latestQuery->execute([(int)$user['id']]);
    $latest = $latestQuery->fetchAll();
}

mp_admin_header(t('dashboard'), $user);
?>
<p><?= e(t('welcome')) ?>، <?= e($user['display_name']) ?> 👋</p>
<div class="cards"><?php foreach ($stats as $label => $value): ?><div class="card"><b><?= (int)$value ?></b><?= e($label) ?></div><?php endforeach; ?></div>
<h2>آخرین نوشته‌ها</h2>
<ul><?php foreach ($latest as $post): ?><li><?= e($post['title']) ?> — <?= e($post['created_at']) ?></li><?php endforeach; ?></ul>
<p><a class="btn" href="post-edit.php?type=post">✍️ نوشته جدید</a>
<?php if (mp_can($user, 'editor') && mp_module_enabled('shop')): ?> <a class="btn" href="shop-products.php">🛍 محصول جدید</a><?php endif; ?></p>
<?php mp_admin_footer(); ?>
