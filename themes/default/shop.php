<?php // themes/default/shop.php — فهرست محصولات ?>
<h2>🛍 فروشگاه</h2>
<?php $products = mp_list_products(24); if (!$products): ?><p>هنوز محصولی ثبت نشده است.</p><?php endif; ?>
<div class="products">
<?php foreach ($products as $pr): ?>
  <div class="product-card">
    <?php if (!empty($pr['image'])): ?><img src="uploads/<?= e($pr['image']) ?>" alt="<?= e($pr['name']) ?>"><?php endif; ?>
    <h3><a href="index.php?product=<?= e($pr['slug']) ?>"><?= e($pr['name']) ?></a></h3>
    <p class="price"><?= mp_price((int)$pr['price']) ?></p>
    <p>موجودی: <?= (int)$pr['stock'] ?></p>
    <a class="btn" href="index.php?product=<?= e($pr['slug']) ?>">مشاهده و خرید</a>
  </div>
<?php endforeach; ?>
</div>
