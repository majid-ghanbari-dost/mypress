<?php
// Order management with stock restoration on cancellation.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
require_once __DIR__ . '/../modules/shop/shop.php';
$user = mp_require_role('editor');
$pdo = mp_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    mp_csrf_check();
    $orderId = (int)$_POST['order_id'];
    $requested = (string)$_POST['status'];
    if (!in_array($requested, ['new','processing','shipped','cancelled'], true)) {
        mp_flash('وضعیت سفارش معتبر نیست.', 'err');
        mp_redirect('shop-orders.php');
    }
    try {
        $pdo->beginTransaction();
        $sql = 'SELECT id,status FROM orders WHERE id=?';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $find = $pdo->prepare($sql);
        $find->execute([$orderId]);
        $order = $find->fetch();
        if (!$order) throw new RuntimeException('Order not found');
        if ($order['status'] === 'cancelled' && $requested !== 'cancelled') {
            throw new RuntimeException('Cancelled orders are terminal');
        }
        if ($order['status'] === 'shipped' && $requested === 'cancelled') {
            throw new RuntimeException('Shipped orders cannot be cancelled automatically');
        }
        if ($order['status'] !== 'cancelled' && $requested === 'cancelled') {
            $items = $pdo->prepare('SELECT product_id,qty FROM order_items WHERE order_id=?');
            $items->execute([$orderId]);
            $restore = $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?');
            foreach ($items->fetchAll() as $item) {
                $restore->execute([(int)$item['qty'], (int)$item['product_id']]);
            }
        }
        $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$requested, $orderId]);
        $pdo->commit();
        mp_flash('وضعیت سفارش به‌روزرسانی شد.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mp_flash('تغییر وضعیت سفارش انجام نشد؛ سفارش ارسال‌شده یا لغوشده قابل لغو/بازگشایی خودکار نیست.', 'err');
    }
    mp_redirect('shop-orders.php');
}

$orders = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll();
mp_admin_header(t('orders'), $user);
?>
<?php if (!$orders): ?><p>هنوز سفارشی ثبت نشده است.</p><?php endif; ?>
<?php foreach ($orders as $order): ?>
<div class="post-card">
<b>سفارش #<?= (int)$order['id'] ?></b> — <?= e($order['customer_name']) ?> — <?= e($order['customer_phone']) ?><br>
<small><?= e($order['created_at']) ?> — <?= e($order['customer_address']) ?></small><br>
<?php $items = $pdo->prepare('SELECT * FROM order_items WHERE order_id=?'); $items->execute([(int)$order['id']]); ?>
<ul><?php foreach ($items->fetchAll() as $item): ?><li><?= e($item['product_name']) ?> × <?= (int)$item['qty'] ?> — <?= mp_price((int)$item['price'] * (int)$item['qty']) ?></li><?php endforeach; ?></ul>
<p>جمع: <b><?= mp_price((int)$order['total']) ?></b><?php if (!empty($order['discount_code'])) echo ' — تخفیف ' . e($order['discount_code']) . ': ' . mp_price((int)$order['discount_amount']); ?> — وضعیت: <b><?= e(mp_order_status_label($order['status'])) ?></b></p>
<form method="post"><?= mp_csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
<select name="status" style="width:auto;display:inline-block">
<?php foreach (['new'=>'جدید','processing'=>'در حال پردازش','shipped'=>'ارسال‌شده','cancelled'=>'لغو شده'] as $key=>$label): ?><option value="<?= $key ?>" <?= $order['status']===$key?'selected':'' ?> <?= ($order['status']==='cancelled' && $key!=='cancelled') || ($order['status']==='shipped' && $key==='cancelled')?'disabled':'' ?>><?= $label ?></option><?php endforeach; ?>
</select> <button class="btn btn-small" type="submit">به‌روزرسانی</button></form>
</div>
<?php endforeach; ?>
<?php mp_admin_footer(); ?>
