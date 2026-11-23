<?php
// Shop domain logic. Stock changes are atomic and checked before an order commits.
declare(strict_types=1);

function mp_get_product(string $slug): ?array {
    $statement = mp_pdo()->prepare(
        'SELECT p.*, c.name AS category_name FROM products p
         LEFT JOIN categories c ON c.id=p.category_id
         WHERE p.slug=? AND p.status=\'published\''
    );
    $statement->execute([$slug]);
    return $statement->fetch() ?: null;
}

function mp_list_products(int $limit = 24): array {
    $limit = max(1, min(100, $limit));
    $statement = mp_pdo()->prepare(
        'SELECT id,name,slug,price,stock,image,created_at FROM products WHERE status=\'published\' ORDER BY created_at DESC LIMIT ?'
    );
    $statement->bindValue(1, $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function mp_cart(): array {
    mp_session_start();
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) return [];
    $clean = [];
    $seen = 0;
    foreach ($cart as $id => $qty) {
        if ($seen++ >= 100) break;
        $id = filter_var($id, FILTER_VALIDATE_INT);
        $qty = filter_var($qty, FILTER_VALIDATE_INT);
        if ($id && $id > 0 && $qty && $qty > 0) $clean[(int)$id] = min(99, (int)$qty);
    }
    $_SESSION['cart'] = $clean;
    return $clean;
}

function mp_cart_add(int $productId, int $qty = 1): void {
    if ($productId < 1 || $qty < 1) return;
    mp_session_start();
    $statement = mp_pdo()->prepare(
        'SELECT stock FROM products WHERE id=? AND status=\'published\''
    );
    $statement->execute([$productId]);
    $product = $statement->fetch();
    if (!$product || (int)$product['stock'] < 1) return;
    $cart = mp_cart();
    if (!isset($cart[$productId]) && count($cart) >= 100) {
        mp_flash('سبد خرید نمی‌تواند بیش از ۱۰۰ محصول متفاوت داشته باشد.', 'err');
        return;
    }
    $cart[$productId] = min((int)$product['stock'], 99, ($cart[$productId] ?? 0) + $qty);
    $_SESSION['cart'] = $cart;
}

function mp_cart_items(): array {
    $cart = mp_cart();
    if (!$cart) return [];
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = mp_pdo()->prepare(
        "SELECT id,name,price,stock FROM products WHERE status='published' AND id IN ($placeholders)"
    );
    $statement->execute($ids);
    $items = [];
    foreach ($statement->fetchAll() as $product) {
        $id = (int)$product['id'];
        $qty = min((int)$cart[$id], (int)$product['stock'], 99);
        if ($qty < 1) continue;
        $product['qty'] = $qty;
        $product['line_total'] = (int)$product['price'] * $qty;
        $items[] = $product;
    }
    return $items;
}

function mp_cart_total(): int {
    $total = 0;
    foreach (mp_cart_items() as $item) $total += (int)$item['line_total'];
    return $total;
}

function mp_coupon(string $code): ?array {
    $statement = mp_pdo()->prepare('SELECT * FROM coupons WHERE code=? AND active=1');
    $statement->execute([$code]);
    $coupon = $statement->fetch();
    if (!$coupon) return null;
    if (!empty($coupon['expires_at']) && $coupon['expires_at'] < date('Y-m-d')) return null;
    $coupon['percent'] = max(0, min(100, (int)$coupon['percent']));
    return $coupon;
}

/**
 * The database is the source of truth for prices and stock at checkout.
 * Optional PDO injection makes the transaction testable without a live site database.
 */
function mp_checkout(array $data, ?PDO $connection = null): int {
    $cart = mp_cart();
    if (!$cart) return 0;
    $name = trim((string)($data['customer_name'] ?? ''));
    $phone = trim((string)($data['customer_phone'] ?? ''));
    $phone = strtr($phone, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ]);
    $phone = preg_replace('/[\\s().-]+/', '', $phone) ?? $phone;
    $address = trim((string)($data['customer_address'] ?? ''));
    if ($name === '' || strlen($name) > 150 || strlen($phone) > 50 ||
        !preg_match('/^\\+?[0-9]{7,20}$/', $phone) ||
        $address === '' || strlen($address) > 5000) return 0;

    $pdo = $connection ?? mp_pdo();
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!mp_public_rate_limit('order-ip', $ip, 5, 900, $pdo) ||
        !mp_public_rate_limit('order-phone', $phone, 5, 86400, $pdo)) {
        return 0;
    }
    try {
        $pdo->beginTransaction();
        $items = [];
        $total = 0;
        $selectSql = 'SELECT id,name,price,stock,status FROM products WHERE id=?';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $selectSql .= ' FOR UPDATE';
        $select = $pdo->prepare($selectSql);
        $decrement = $pdo->prepare(
            'UPDATE products SET stock=stock-? WHERE id=? AND status=\'published\' AND stock>=?'
        );

        foreach ($cart as $productId => $quantity) {
            $select->execute([(int)$productId]);
            $product = $select->fetch();
            if (!$product || $product['status'] !== 'published' ||
                (int)$product['stock'] < (int)$quantity) {
                throw new RuntimeException('Insufficient stock');
            }
            $price = max(0, (int)$product['price']);
            $quantity = (int)$quantity;
            if ($quantity < 1 || $price > intdiv(PHP_INT_MAX, $quantity)) {
                throw new RuntimeException('Invalid order amount');
            }
            $lineTotal = $price * $quantity;
            if ($total > PHP_INT_MAX - $lineTotal) throw new RuntimeException('Order total overflow');
            $total += $lineTotal;
            $items[] = [
                'id' => (int)$product['id'],
                'name' => (string)$product['name'],
                'price' => $price,
                'qty' => $quantity,
            ];
        }
        if (!$items) throw new RuntimeException('Empty order');

        $code = strtoupper(trim((string)($data['discount_code'] ?? '')));
        $discount = 0;
        if ($code !== '') {
            $couponStatement = $pdo->prepare(
                'SELECT percent,expires_at FROM coupons WHERE code=? AND active=1'
            );
            $couponStatement->execute([$code]);
            $coupon = $couponStatement->fetch();
            if ($coupon && (empty($coupon['expires_at']) || $coupon['expires_at'] >= date('Y-m-d'))) {
                $percent = max(0, min(100, (int)$coupon['percent']));
                $discount = intdiv($total, 100) * $percent +
                    (int)round(($total % 100) * $percent / 100);
            } else {
                $code = '';
            }
        }

        $order = $pdo->prepare(
            "INSERT INTO orders (customer_name,customer_phone,customer_address,total,discount_code,discount_amount,status)
             VALUES (?,?,?,?,?,?,'new')"
        );
        $order->execute([$name, $phone, $address, $total - $discount, $code, $discount]);
        $orderId = (int)$pdo->lastInsertId();
        $insertItem = $pdo->prepare(
            'INSERT INTO order_items (order_id,product_id,product_name,price,qty) VALUES (?,?,?,?,?)'
        );

        foreach ($items as $item) {
            $decrement->execute([$item['qty'], $item['id'], $item['qty']]);
            if ($decrement->rowCount() !== 1) throw new RuntimeException('Stock changed during checkout');
            $insertItem->execute([$orderId, $item['id'], $item['name'], $item['price'], $item['qty']]);
        }

        $pdo->commit();
        mp_session_start();
        $_SESSION['cart'] = [];
        return $orderId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return 0;
    }
}

/**
 * Order cancellation is idempotent. Shipped orders cannot be cancelled automatically,
 * and a cancelled order cannot be reopened (which could double-restore stock).
 */
function mp_update_order_status(int $orderId, string $requested, ?PDO $connection = null): bool {
    if ($orderId < 1 || !in_array($requested, ['new','processing','shipped','cancelled'], true)) return false;
    $pdo = $connection ?? mp_pdo();
    try {
        $pdo->beginTransaction();
        $sql = 'SELECT id,status FROM orders WHERE id=?';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $find = $pdo->prepare($sql);
        $find->execute([$orderId]);
        $order = $find->fetch();
        if (!$order) throw new RuntimeException('Order not found');

        $transitions = [
            'new' => ['new', 'processing', 'cancelled'],
            'processing' => ['processing', 'shipped', 'cancelled'],
            'shipped' => ['shipped'],
            'cancelled' => ['cancelled'],
        ];
        $currentStatus = (string)$order['status'];
        if (!in_array($requested, $transitions[$currentStatus] ?? [], true)) {
            throw new RuntimeException('Invalid order state transition');
        }
        if ($currentStatus === 'cancelled') {
            $pdo->commit();
            return true;
        }
        if ($requested === 'cancelled') {
            $items = $pdo->prepare('SELECT product_id,qty FROM order_items WHERE order_id=?');
            $items->execute([$orderId]);
            $restore = $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?');
            foreach ($items->fetchAll() as $item) {
                $restore->execute([(int)$item['qty'], (int)$item['product_id']]);
            }
        }
        $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$requested, $orderId]);
        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }
}

function mp_order_status_label(string $status): string {
    return ['new'=>'جدید','processing'=>'در حال پردازش','shipped'=>'ارسال‌شده','cancelled'=>'لغو شده'][$status] ?? $status;
}
