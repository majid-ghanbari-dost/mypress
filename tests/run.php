<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../modules/shop/shop.php';

$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    $checks++;
}

check(e('<script>') === '&lt;script&gt;', 'HTML output is escaped');
check(mp_slug('سلام دنیا') === 'سلام-دنیا', 'Persian slug is preserved');
check(mp_slug('') !== '', 'Empty slug receives a safe fallback');

$author = ['id' => 7, 'role' => 'author'];
$editor = ['id' => 8, 'role' => 'editor'];
$ownDraft = ['author_id' => 7, 'status' => 'draft'];
$otherDraft = ['author_id' => 9, 'status' => 'draft'];
$ownPublished = ['author_id' => 7, 'status' => 'published'];
check(mp_can_edit_post($author, $ownDraft), 'Author can edit own draft');
check(!mp_can_edit_post($author, $otherDraft), 'Author cannot edit another author draft');
check(!mp_can_edit_post($author, $ownPublished), 'Author cannot edit published content');
check(mp_can_edit_post($editor, $otherDraft), 'Editor can edit other authors content');
check(mp_safe_menu_url('javascript:alert(1)') === '#', 'JavaScript menu URL is rejected');
check(mp_safe_menu_url('//evil.example') === '#', 'Protocol-relative menu URL is rejected');
check(mp_safe_menu_url('/?page=about') === '/?page=about', 'Internal menu URL is accepted');
check(mp_safe_menu_url('https://example.com/path') === 'https://example.com/path', 'HTTPS menu URL is accepted');

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "pdo_sqlite is required for integration smoke tests.\n");
    exit(1);
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
mp_create_tables($pdo);
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
check(in_array('login_attempts', $tables, true), 'Login throttling schema exists');
check(in_array('schema_migrations', $tables, true), 'Schema migration ledger exists');
check(in_array('orders', $tables, true), 'Shop order schema exists');
check(in_array('order_items', $tables, true), 'Shop order item schema exists');

$pdo->prepare("INSERT INTO users (username,password_hash,email,display_name,role) VALUES (?,?,?,?,?)")
    ->execute(['testadmin', password_hash('correct-horse-battery-staple', PASSWORD_DEFAULT), 'test@example.com', 'Test Admin', 'admin']);
$adminId = (int)$pdo->lastInsertId();
check(!mp_can_delete_user($pdo, $adminId, $adminId), 'Administrator cannot delete own account');
check(!mp_can_delete_user($pdo, $adminId, 999), 'Last administrator account cannot be deleted');
$pdo->prepare("INSERT INTO users (username,password_hash,email,display_name,role) VALUES (?,?,?,?,?)")
    ->execute(['secondadmin', password_hash('another-long-password', PASSWORD_DEFAULT), 'second@example.com', 'Second Admin', 'admin']);
$secondAdminId = (int)$pdo->lastInsertId();
check(mp_can_delete_user($pdo, $secondAdminId, $adminId), 'One of multiple administrator accounts can be deleted');
$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$secondAdminId]);
check(!mp_can_delete_user($pdo, 99999, $adminId), 'Unknown user cannot be deleted');
$_SERVER['REMOTE_ADDR'] = '203.0.113.17';
for ($attempt = 0; $attempt < 10; $attempt++) {
    check(!mp_login('testadmin', 'incorrect-password', $pdo), 'Incorrect password is rejected');
}
check(!mp_login('testadmin', 'correct-horse-battery-staple', $pdo), 'Login throttle blocks correct password after repeated failures');
$pdo->exec('DELETE FROM login_attempts');
check(mp_login('testadmin', 'correct-horse-battery-staple', $pdo), 'Valid credentials succeed after throttle reset');

$pdo->exec("INSERT INTO products (name,slug,price,stock,status) VALUES ('Test','test-product',100,1,'published')");
$pdo->exec("INSERT INTO products (name,slug,price,stock,status) VALUES ('Second','second-product',200,2,'published')");
mp_session_start();
$_SESSION['cart'] = [1 => 2];
$customer = ['customer_name'=>'Test Buyer','customer_phone'=>'09120000000','customer_address'=>'Test address'];
check(mp_checkout($customer, $pdo) === 0, 'Checkout rejects quantity above stock');
check((int)$pdo->query('SELECT stock FROM products WHERE id=1')->fetchColumn() === 1, 'Failed checkout leaves stock unchanged');
check((int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() === 0, 'Failed checkout creates no order');

$_SESSION['cart'] = [1 => 1];
$orderId = mp_checkout($customer, $pdo);
check($orderId > 0, 'Valid checkout creates an order');
check((int)$pdo->query('SELECT stock FROM products WHERE id=1')->fetchColumn() === 0, 'Valid checkout decrements stock');
check(mp_update_order_status($orderId, 'cancelled', $pdo), 'Eligible order can be cancelled');
check((int)$pdo->query('SELECT stock FROM products WHERE id=1')->fetchColumn() === 1, 'Cancellation restores stock');
check(mp_update_order_status($orderId, 'cancelled', $pdo), 'Repeated cancellation is idempotent');
check((int)$pdo->query('SELECT stock FROM products WHERE id=1')->fetchColumn() === 1, 'Repeated cancellation does not restore stock twice');
check(!mp_update_order_status($orderId, 'processing', $pdo), 'Cancelled order cannot be reopened');

$_SESSION['cart'] = [2 => 1];
$shippedOrder = mp_checkout($customer, $pdo);
check($shippedOrder > 0, 'Second checkout creates an order');
check(mp_update_order_status($shippedOrder, 'processing', $pdo), 'New order can enter processing');
check(mp_update_order_status($shippedOrder, 'shipped', $pdo), 'Processing order can be marked shipped');
check(!mp_update_order_status($shippedOrder, 'processing', $pdo), 'Shipped order cannot move backwards');
check(!mp_update_order_status($shippedOrder, 'cancelled', $pdo), 'Shipped order cannot be auto-cancelled');
check((int)$pdo->query('SELECT stock FROM products WHERE id=2')->fetchColumn() === 1, 'Rejected shipped cancellation leaves stock unchanged');

echo "PASS: {$checks} checks\n";
