<?php
// modules/shop/shop.php — منطق فروشگاه: محصولات، سبد، کوپن، سفارش (COD)
// بدون درگاه اجباری؛ تسویه دستی / پرداخت در محل.

function mp_get_product(string $slug): ?array {
    $st = mp_pdo()->prepare("SELECT p.*, c.name AS category_name FROM products p
        LEFT JOIN categories c ON c.id=p.category_id WHERE p.slug=? AND p.status='published'");
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}
function mp_list_products(int $limit = 24): array {
    $st = mp_pdo()->prepare("SELECT * FROM products WHERE status='published' ORDER BY created_at DESC LIMIT ?");
    $st->execute([$limit]);
    return $st->fetchAll();
}
function mp_cart(): array { mp_session_start(); return $_SESSION['cart'] ?? []; }
function mp_cart_add(int $productId, int $qty = 1): void {
    mp_session_start();
    $st = mp_pdo()->prepare("SELECT stock FROM products WHERE id=? AND status='published'");
    $st->execute([$productId]);
    $p = $st->fetch();
    if (!$p) return;
    $cur = $_SESSION['cart'][$productId] ?? 0;
    $_SESSION['cart'][$productId] = min($cur + $qty, max(1, (int)$p['stock']));
}
function mp_cart_items(): array {
    $cart = mp_cart();
    if (!$cart) return [];
    $ids = implode(',', array_map('intval', array_keys($cart)));
    $rows = mp_pdo()->query("SELECT * FROM products WHERE id IN ($ids)")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $r['qty'] = $cart[(int)$r['id']]; $r['line_total'] = $r['price'] * $r['qty'];
        $out[] = $r;
    }
    return $out;
}
function mp_cart_total(): int {
    $t = 0; foreach (mp_cart_items() as $i) $t += $i['line_total']; return $t;
}
function mp_coupon(string $code): ?array {
    // تاریخ انقضا در PHP بررسی می‌شود تا هم SQLite و هم MySQL کار کنند
    $st = mp_pdo()->prepare("SELECT * FROM coupons WHERE code=? AND active=1");
    $st->execute([$code]);
    $c = $st->fetch();
    if (!$c) return null;
    if (!empty($c['expires_at']) && $c['expires_at'] < date('Y-m-d')) return null;
    return $c;
}
// ثبت سفارش؛ در صورت موفقیت موجودی کم می‌شود. برگردان: شناسه سفارش یا 0
function mp_checkout(array $data): int {
    $items = mp_cart_items();
    if (!$items) return 0;
    $name = trim($data['customer_name'] ?? '');
    $phone = trim($data['customer_phone'] ?? '');
    $addr = trim($data['customer_address'] ?? '');
    if ($name === '' || $phone === '' || $addr === '') return 0;
    $total = mp_cart_total(); $discount = 0; $code = trim($data['discount_code'] ?? '');
    if ($code !== '') { $c = mp_coupon($code); if ($c) $discount = (int)round($total * $c['percent'] / 100); }
    $pdo = mp_pdo();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("INSERT INTO orders (customer_name,customer_phone,customer_address,total,discount_code,discount_amount,status) VALUES (?,?,?,?,?,?,'new')");
        $st->execute([$name, $phone, $addr, $total - $discount, $code, $discount]);
        $orderId = (int)$pdo->lastInsertId();
        $it = $pdo->prepare("INSERT INTO order_items (order_id,product_id,product_name,price,qty) VALUES (?,?,?,?,?)");
        $up = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id=? AND stock>=?");
        foreach ($items as $i) {
            $it->execute([$orderId, $i['id'], $i['name'], $i['price'], $i['qty']]);
            $up->execute([$i['qty'], $i['id'], $i['qty']]);
        }
        $pdo->commit();
        return $orderId;
    } catch (Throwable $e) { $pdo->rollBack(); return 0; }
}
function mp_order_status_label(string $s): string {
    return ['new'=>'جدید','processing'=>'در حال پردازش','shipped'=>'ارسال‌شده','cancelled'=>'لغو شده'][$s] ?? $s;
}
