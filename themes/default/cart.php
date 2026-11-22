<?php // themes/default/cart.php — سبد خرید + تسویه دستی / COD
$items = mp_cart_items(); $total = mp_cart_total();
if ($route['type'] === 'checkout' && isset($_GET['order'])): ?>
  <h2>✅ سفارش ثبت شد</h2>
  <p>شماره سفارش شما: <b><?= (int)$_GET['order'] ?></b></p>
  <p>پرداخت در محل (COD) یا هماهنگی دستی با فروشنده انجام می‌شود. به‌زودی با شما تماس گرفته می‌شود.</p>
  <p><a class="btn" href="index.php">بازگشت به خانه</a></p>
  <?php return; endif; ?>
<h2><?= $route['type'] === 'checkout' ? 'تسویه (پرداخت در محل)' : '🛒 سبد خرید' ?></h2>
<?php if (!$items): ?><p>سبد خرید شما خالی است.</p><p><a class="btn" href="index.php?shop=1">رفتن به فروشگاه</a></p><?php return; endif; ?>
<table class="table"><tr><th>محصول</th><th>قیمت</th><th>تعداد</th><th>جمع</th></tr>
<?php foreach ($items as $i): ?><tr>
  <td><?= e($i['name']) ?></td><td><?= mp_price((int)$i['price']) ?></td><td><?= (int)$i['qty'] ?></td><td><?= mp_price((int)$i['line_total']) ?></td>
</tr><?php endforeach; ?></table>
<p><b>جمع کل: <?= mp_price($total) ?></b></p>
<?php if ($route['type'] === 'cart'): ?>
  <form method="post" style="display:inline"><?= mp_csrf_field() ?><input type="hidden" name="cart_action" value="clear"><button class="btn btn-danger" type="submit">خالی کردن سبد</button></form>
  <a class="btn" href="index.php?checkout=1">ادامه به تسویه ←</a>
<?php else: ?>
  <form method="post"><h3>اطلاعات گیرنده</h3><?= mp_csrf_field() ?>
    <input type="hidden" name="cart_action" value="checkout">
    <label>نام و نام خانوادگی <input name="customer_name" required></label>
    <label>تلفن <input name="customer_phone" required dir="ltr"></label>
    <label>آدرس <textarea name="customer_address" required rows="3"></textarea></label>
    <label>کد تخفیف (اختیاری — مثلاً WELCOME10) <input name="discount_code" dir="ltr"></label>
    <p>روش پرداخت: <b>پرداخت در محل (COD)</b> — بدون درگاه آنلاین.</p>
    <button class="btn" type="submit">ثبت سفارش</button>
  </form>
<?php endif; ?>
