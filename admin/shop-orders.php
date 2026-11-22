<?php
// admin/shop-orders.php — فهرست سفارش‌ها و تغییر وضعیت
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
require_once __DIR__ . '/../modules/shop/shop.php';
$user = mp_require_role('editor');
$pdo = mp_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    mp_csrf_check();
    $s = in_array($_POST['status'], ['new','processing','shipped','cancelled'], true) ? $_POST['status'] : 'new';
    $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$s, (int)$_POST['order_id']]);
    mp_flash('وضعیت سفارش به‌روز شد.'); mp_redirect('shop-orders.php');
}
$orders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC")->fetchAll();
mp_admin_header(t('orders'), $user);
?>
<?php if (!$orders): ?><p>هنوز سفارشی ثبت نشده است.</p><?php endif; ?>
<?php foreach ($orders as $o): ?>
<div class="post-card">
  <b>سفارش #<?= (int)$o['id'] ?></b> — <?= e($o['customer_name']) ?> — <?= e($o['customer_phone']) ?><br>
  <small><?= e($o['created_at']) ?> — <?= e($o['customer_address']) ?></small><br>
  <?php $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?"); $items->execute([(int)$o['id']]); ?>
  <ul><?php foreach ($items->fetchAll() as $it): ?><li><?= e($it['product_name']) ?> × <?= (int)$it['qty'] ?> — <?= mp_price((int)$it['price'] * (int)$it['qty']) ?></li><?php endforeach; ?></ul>
  <p>جمع: <b><?= mp_price((int)$o['total']) ?></b><?php if (!empty($o['discount_code'])) echo ' — تخفیف ' . e($o['discount_code']) . ': ' . mp_price((int)$o['discount_amount']); ?> — وضعیت: <b><?= e(mp_order_status_label($o['status'])) ?></b></p>
  <form method="post"><?= mp_csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
  <select name="status" style="width:auto;display:inline-block">
    <?php foreach (['new'=>'جدید','processing'=>'در حال پردازش','shipped'=>'ارسال‌شده','cancelled'=>'لغو شده'] as $k=>$v): ?><option value="<?= $k ?>" <?= $o['status']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
  </select> <button class="btn btn-small" type="submit">به‌روزرسانی</button></form>
</div>
<?php endforeach; ?>
<?php mp_admin_footer(); ?>
