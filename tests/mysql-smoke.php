<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../modules/shop/shop.php';

$dsn = getenv('MYSQL_TEST_DSN') ?: 'mysql:host=127.0.0.1;dbname=mypress_test;charset=utf8mb4';
$user = getenv('MYSQL_TEST_USER') ?: 'root';
$password = getenv('MYSQL_TEST_PASSWORD') ?: 'root';
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec('SET SESSION default_storage_engine=InnoDB');

// MySQL commits DDL implicitly; the installer's table creation must precede data transactions.
mp_create_tables($pdo);
$pdo->beginTransaction();
$pdo->prepare("INSERT INTO users (username,password_hash,email,display_name,role) VALUES (?,?,?,?, 'admin')")
    ->execute(['mysql-test', password_hash('long-test-password', PASSWORD_DEFAULT), 'mysql@example.test', 'MySQL Test']);
$adminId = (int)$pdo->lastInsertId();
mp_seed($pdo, $adminId);
$pdo->prepare('INSERT INTO options (opt_key,opt_value) VALUES (?,?)')->execute(['site_title','MySQL Test']);
$pdo->commit();

$migration = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version=?');
$migration->execute(['money-columns-bigint-v1']);
if ((int)$migration->fetchColumn() !== 1) throw new RuntimeException('Money column migration was not recorded');

$columns = $pdo->query(
    "SELECT table_name,column_name,data_type FROM information_schema.columns
     WHERE table_schema=DATABASE() AND ((table_name='products' AND column_name='price')
     OR (table_name='orders' AND column_name='total')
     OR (table_name='order_items' AND column_name='price'))"
)->fetchAll();
foreach ($columns as $column) {
    if (strtolower($column['data_type']) !== 'bigint') {
        throw new RuntimeException('Expected BIGINT for ' . $column['table_name'] . '.' . $column['column_name']);
    }
}

$pdo->prepare("INSERT INTO products (name,slug,description,price,stock,status) VALUES (?,?,?,?,?,'published')")
    ->execute(['High value','high-value','Test product',3000000000,1]);
$productId = (int)$pdo->lastInsertId();
mp_session_start();
$customer = ['customer_name'=>'MySQL Buyer','customer_phone'=>'09120000000','customer_address'=>'Test address'];
$_SESSION['cart'] = [$productId => 2];
if (mp_checkout($customer, $pdo) !== 0) throw new RuntimeException('Oversell attempt unexpectedly succeeded');
if ((int)$pdo->query('SELECT stock FROM products WHERE id=' . $productId)->fetchColumn() !== 1) {
    throw new RuntimeException('Failed checkout changed stock');
}
$_SESSION['cart'] = [$productId => 1];
$orderId = mp_checkout($customer, $pdo);
if ($orderId < 1) throw new RuntimeException('Valid MySQL checkout failed');
$total = (int)$pdo->query('SELECT total FROM orders WHERE id=' . $orderId)->fetchColumn();
if ($total !== 3000000000) throw new RuntimeException('BIGINT order total was not preserved');
if (!mp_update_order_status($orderId, 'cancelled', $pdo)) throw new RuntimeException('MySQL cancellation failed');
if ((int)$pdo->query('SELECT stock FROM products WHERE id=' . $productId)->fetchColumn() !== 1) {
    throw new RuntimeException('MySQL cancellation did not restore stock');
}

echo "PASS: MySQL schema, migration, installer transaction, BIGINT money and shop transaction\n";
