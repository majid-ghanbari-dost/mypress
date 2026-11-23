<?php
// Order management delegates all state transitions to the shop domain service.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
require_once __DIR__ . '/../modules/shop/shop.php';
$user = mp_require_role('editor');
$pdo = mp_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    mp_csrf_check();
    $ok = mp_update_order_status((int)$_POST['order_id'], (string)$_POST['status'], $pdo);
    mp_flash($ok ? 'وضعیت سفارش به‌روزرسانی شد.' :
        'تغییر وضعیت سفارش انجام نشد؛ سفارش ارسال‌شده یا لغوشده قابل لغو/بازگشایی خودکار نیست.', $ok ? 'ok' : 'err');
    mp_redirect('shop-orders.php');
}

$perPage = 25;
$totalOrders = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$totalPages = max(1, (int)ceil($totalOrders / $perPage));
$page = min(mp_admin_page_number(), $totalPages);
$offset = ($page - 1) * $perPage;
$ordersQuery = $pdo->prepare('SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?');
$ordersQuery->bindValue(1, $perPage, PDO::PARAM_INT);
$ordersQuery->bindValue(2, $offset, PDO::PARAM_INT);
$ordersQuery->execute();
$orders = $ordersQuery->fetchAll();

// Fetch line items for the current page in one query instead of one query per order.
$itemsByOrder = [];
if ($orders) {
    $orderIds = array_map(static fn(array $order): int => (int)$order['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsQuery = $pdo->prepare("SELECT * FROM order_items WHERE order_id IN ({$placeholders}) ORDER BY order_id, id");
    $itemsQuery->execute($orderIds);
    while ($item = $itemsQuery->fetch(PDO::FETCH_ASSOC)) {
        $itemsByOrder[(int)$item['order_id']][] = $item;
    }
}
mp_admin_header(t('orders'), $user);
mp_admin_pagination($page, $totalPages, 'shop-orders.php');
?>
<?php if (!$orders): ?><p>هنوز سفارشی ثبت نشده است.</p><?php endif; ?>
<?php foreach ($orders as $order): ?>
<div class="post-card">
<b>سفارش #<?= (int)$order['id'] ?></b> — <?= e($order['customer_name']) ?> — <?= e($order['customer_phone']) ?><br>
<small><?= e($order['created_at']) ?> — <?= e($order['customer_address']) ?></small><br>
<ul><?php foreach (($itemsByOrder[(int)$order['id']] ?? []) as $item): ?><li><?= e($item['product_name']) ?> × <?= (int)$item['qty'] ?> — <?= mp_price((int)$item['price'] * (int)$item['qty']) ?></li><?php endforeach; ?></ul>
<p>جمع: <b><?= mp_price((int)$order['total']) ?></b><?php if (!empty($order['discount_code'])) echo ' — تخفیف ' . e($order['discount_code']) . ': ' . mp_price((int)$order['discount_amount']); ?> — وضعیت: <b><?= e(mp_order_status_label($order['status'])) ?></b></p>
<form method="post"><?= mp_csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
<select name="status" style="width:auto;display:inline-block">
<?php foreach (['new'=>'جدید','processing'=>'در حال پردازش','shipped'=>'ارسال‌شده','cancelled'=>'لغو شده'] as $key=>$label): ?><option value="<?= $key ?>" <?= $order['status']===$key?'selected':'' ?> <?= (($order['status']==='new' && $key==='shipped') || ($order['status']==='processing' && $key==='new') || ($order['status']==='shipped' && $key!=='shipped') || ($order['status']==='cancelled' && $key!=='cancelled'))?'disabled':'' ?>><?= $label ?></option><?php endforeach; ?>
</select> <button class="btn btn-small" type="submit">به‌روزرسانی</button></form>
</div>
<?php endforeach; ?>
<?php mp_admin_pagination($page, $totalPages, 'shop-orders.php'); ?>
<?php mp_admin_footer(); ?>
