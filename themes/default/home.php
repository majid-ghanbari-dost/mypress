<?php // themes/default/home.php — فهرست نوشته‌ها + محصولات منتخب ?>
<h2><?= $route['type'] === 'search' ? 'نتایج جستجو' : 'آخرین نوشته‌ها' ?></h2>
<?php if (!$posts): ?><p>هنوز نوشته‌ای منتشر نشده است.</p><?php endif; ?>
<?php foreach ($posts as $p): ?>
<article class="post-card">
  <h3><a href="index.php?post=<?= e($p['slug']) ?>"><?= e($p['title']) ?></a></h3>
  <p class="meta"><?= e($p['created_at']) ?> — <?= e($p['author_name'] ?? '') ?></p>
  <?php if (!empty($p['excerpt'])): ?><p><?= e($p['excerpt']) ?></p><?php endif; ?>
  <a href="index.php?post=<?= e($p['slug']) ?>">ادامه مطلب ←</a>
</article>
<?php endforeach; ?>
<?php if (mp_module_enabled('shop') && $route['type'] === 'home'):
  $products = mp_list_products(4); if ($products): ?>
<section class="shop-teaser"><h2>🛍 محصولات</h2><div class="products">
<?php foreach ($products as $pr): ?>
  <div class="product-card"><h3><a href="index.php?product=<?= e($pr['slug']) ?>"><?= e($pr['name']) ?></a></h3>
  <p class="price"><?= mp_price((int)$pr['price']) ?></p>
  <a class="btn" href="index.php?product=<?= e($pr['slug']) ?>">مشاهده</a></div>
<?php endforeach; ?></div><p><a href="index.php?shop=1">مشاهده همه محصولات ←</a></p></section>
<?php endif; endif; ?>
