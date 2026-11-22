<?php
// admin/index.php — داشبورد با آمار
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
require_once __DIR__ . '/../modules/comments/comments.php';
$user = mp_require_login();
$pdo = mp_pdo();
$stats = [
    'نوشته‌ها' => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE type='post'")->fetchColumn(),
    'برگه‌ها' => (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE type='page'")->fetchColumn(),
    'نظرات در انتظار' => mp_pending_comments_count(),
    'محصولات' => (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'سفارش‌های جدید' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn(),
    'کاربران' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
];
$latest = $pdo->query("SELECT title,created_at FROM posts ORDER BY created_at DESC LIMIT 5")->fetchAll();
mp_admin_header(t('dashboard'), $user);
?>
<p><?= t('welcome') ?>، <?= e($user['display_name']) ?> 👋</p>
<div class="cards"><?php foreach ($stats as $k => $v): ?><div class="card"><b><?= $v ?></b><?= e($k) ?></div><?php endforeach; ?></div>
<h2>آخرین نوشته‌ها</h2>
<ul><?php foreach ($latest as $p): ?><li><?= e($p['title']) ?> — <?= e($p['created_at']) ?></li><?php endforeach; ?></ul>
<p><a class="btn" href="post-edit.php?type=post">✍️ نوشته جدید</a> <a class="btn" href="shop-products.php">🛍 محصول جدید</a></p>
<?php mp_admin_footer(); ?>
