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
$search = trim((string)($_GET['q'] ?? ''));
if (strlen($search) > 400) {
    http_response_code(400);
    exit('عبارت جست‌وجو بیش از حد طولانی است.');
}
$allowedStatuses = ['new', 'processing', 'shipped', 'cancelled'];
$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, $allowedStatuses, true)) $status = 'all';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(id = ? OR customer_name LIKE ? OR customer_phone LIKE ?)';
    $params[] = ctype_digit($search) ? (int)$search : -1;
    $params[] = $search . '%';
    $params[] = $search . '%';
}
if ($status !== 'all') {
    $where[] = 'status = ?';
    $params[] = $status;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$countQuery = $pdo->prepare('SELECT COUNT(*) FROM orders' . $whereSql);
$countQuery->execute($params);
$totalOrders = (int)$countQuery->fetchColumn();
$totalPages = max(1, (int)ceil($totalOrders / $perPage));
$page = min(mp_admin_page_number(), $totalPages);
$offset = ($page - 1) * $perPage;
$ordersQuery = $pdo->prepare('SELECT * FROM orders' . $whereSql . ' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?');
foreach ($params as $index => $value) {
    $ordersQuery->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$ordersQuery->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$ordersQuery->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$ordersQuery->execute();
$orders = $ordersQuery->fetchAll();
$paginationQuery = [];
if ($search !== '') $paginationQuery['q'] = $search;
if ($status !== 'all') $paginationQuery['status'] = $status;

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
?>
<form method="get" action="shop-orders.php" style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0 20px">
<label>جست‌وجوی سفارش <input type="search" name="q" maxlength="100" value="<?= e($search) ?>" placeholder="شماره سفارش، نام یا ابتدای تلفن"></label>
<label>وضعیت <select name="status" style="width:auto">
<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>همه وضعیت‌ها</option>
<option value="new" <?= $status === 'new' ? 'selected' : '' ?>>جدید</option>
<option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>در حال پردازش</option>
<option value="shipped" <?= $status === 'shipped' ? 'selected' : '' ?>>ارسال‌شده</option>
<option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>لغو شده</option>
</select></label>
<button class="btn btn-small" type="submit">اعمال فیلتر</button>
<a href="shop-orders.php">پاک‌کردن</a>
</form>
<?php mp_admin_pagination($page, $totalPages, 'shop-orders.php', $paginationQuery); ?>
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
<?php mp_admin_pagination($page, $totalPages, 'shop-orders.php', $paginationQuery); ?>
<?php mp_admin_footer(); ?>
