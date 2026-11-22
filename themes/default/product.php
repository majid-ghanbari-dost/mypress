<?php // themes/default/product.php — جزئیات محصول + افزودن به سبد ?>
<?php if (!$product): ?><h2>یافت نشد</h2><p>این محصول وجود ندارد.</p><?php return; endif; ?>
<article class="product-single">
  <?php if (!empty($product['image'])): ?><img src="uploads/<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>"><?php endif; ?>
  <h2><?= e($product['name']) ?></h2>
  <p class="price"><?= mp_price((int)$product['price']) ?></p>
  <p>موجودی: <?= (int)$product['stock'] ?></p>
  <div class="content"><?= nl2br(e($product['description'])) ?></div>
  <?php if ((int)$product['stock'] > 0): ?>
  <form method="post" action="index.php?cart=1"><?= mp_csrf_field() ?>
    <input type="hidden" name="cart_action" value="add">
    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
    <label>تعداد <input type="number" name="qty" value="1" min="1" max="<?= (int)$product['stock'] ?>"></label>
    <button class="btn" type="submit">🛒 افزودن به سبد خرید</button>
  </form>
  <?php else: ?><p><b>ناموجود</b></p><?php endif; ?>
</article>
