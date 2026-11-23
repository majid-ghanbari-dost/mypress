<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../modules/shop/shop.php';

$host = getenv('MYSQL_TEST_HOST') ?: '127.0.0.1';
$user = getenv('MYSQL_TEST_USER') ?: 'root';
$password = getenv('MYSQL_TEST_PASSWORD') ?: 'root';
$dsn = getenv('MYSQL_TEST_DSN') ?: 'mysql:host=' . $host . ';dbname=mypress_test;charset=utf8mb4';
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec('SET SESSION default_storage_engine=InnoDB');

// Simulate a MyPress 1.0 database: legacy 32-bit money columns on MyISAM tables.
$pdo->exec("CREATE TABLE products (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL, slug VARCHAR(191) NOT NULL UNIQUE,
    description TEXT NOT NULL, price INT NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0, image VARCHAR(255) DEFAULT '',
    category_id INT DEFAULT NULL, status VARCHAR(20) NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM");
$pdo->exec("CREATE TABLE orders (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL, customer_phone VARCHAR(50) NOT NULL,
    customer_address TEXT NOT NULL, total INT NOT NULL DEFAULT 0,
    discount_code VARCHAR(50) DEFAULT '', discount_amount INT NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM");
$pdo->exec("CREATE TABLE order_items (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL, product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL, price INT NOT NULL DEFAULT 0,
    qty INT NOT NULL DEFAULT 1
) ENGINE=MyISAM");

// The migration must convert the legacy tables before transactional shop use.
mp_create_tables($pdo);

// The per-IP throttle query has no username_key predicate, so its leading
// index must exist on MySQL as well as SQLite.
$throttleIndexes = $pdo->prepare(
    "SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics
     WHERE table_schema=DATABASE() AND table_name='login_attempts'
       AND index_name IN ('idx_login_attempts_lookup','idx_login_attempts_ip_lookup')"
);
$throttleIndexes->execute();
if ((int)$throttleIndexes->fetchColumn() !== 2) {
    throw new RuntimeException('Expected both MySQL login throttle indexes');
}
mp_ensure_security_schema($pdo);
$throttleIndexes->execute();
if ((int)$throttleIndexes->fetchColumn() !== 2) {
    throw new RuntimeException('Repeated MySQL security schema setup changed throttle indexes');
}
$orderItemIndex = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema=DATABASE() AND table_name='order_items'
       AND index_name='idx_order_items_order_id'"
);
$orderItemIndex->execute();
if ((int)$orderItemIndex->fetchColumn() !== 1) {
    throw new RuntimeException('Expected order_items order_id lookup index on MySQL');
}
$adminIndexNames = [
    'idx_posts_type_created_id','idx_posts_author_type_created_id',
    'idx_comments_created_id','idx_media_created_id','idx_media_owner_created_id',
    'idx_products_created_id','idx_products_status_created_id','idx_orders_created_id',
    'idx_orders_customer_name_created_id','idx_orders_customer_phone_created_id',
];
$adminIndexQuery = $pdo->prepare(
    'SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics
     WHERE table_schema=DATABASE() AND index_name IN (' . implode(',', array_fill(0, count($adminIndexNames), '?')) . ')'
);
$adminIndexQuery->execute($adminIndexNames);
if ((int)$adminIndexQuery->fetchColumn() !== count($adminIndexNames)) {
    throw new RuntimeException('Expected all admin query indexes on MySQL');
}

$pdo->prepare("INSERT INTO users (username,password_hash,email,display_name,role) VALUES (?,?,?,?, 'admin')")
    ->execute(['mysql-test', password_hash('long-test-password', PASSWORD_DEFAULT), 'mysql@example.test', 'MySQL Test']);
$adminId = (int)$pdo->lastInsertId();
if (mp_delete_user($pdo, $adminId, $adminId)) {
    throw new RuntimeException('Administrator self-deletion unexpectedly succeeded');
}
$pdo->prepare("INSERT INTO users (username,password_hash,email,display_name,role) VALUES (?,?,?,?, 'admin')")
    ->execute(['mysql-second-admin', password_hash('another-long-password', PASSWORD_DEFAULT), 'second-mysql@example.test', 'Second MySQL Admin']);
$secondAdminId = (int)$pdo->lastInsertId();
if (!mp_delete_user($pdo, $secondAdminId, $adminId)) {
    throw new RuntimeException('Transactional deletion of one of multiple administrators failed');
}
if (mp_delete_user($pdo, $adminId, 999)) {
    throw new RuntimeException('Deletion of the final administrator unexpectedly succeeded');
}
$pdo->beginTransaction();
mp_seed($pdo, $adminId);
$pdo->prepare('INSERT INTO options (opt_key,opt_value) VALUES (?,?)')->execute(['site_title','MySQL Test']);
$pdo->commit();

$migration = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version=?');
$migration->execute(['mysql-commerce-engine-money-v1']);
if ((int)$migration->fetchColumn() !== 1) throw new RuntimeException('MySQL commerce migration was not recorded');

foreach ([['products','price'],['orders','total'],['order_items','price']] as [$tableName,$columnName]) {
    $typeQuery = $pdo->prepare(
        'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
    );
    $typeQuery->execute([$tableName,$columnName]);
    if (strtolower((string)$typeQuery->fetchColumn()) !== 'bigint') {
        throw new RuntimeException('Expected BIGINT for ' . $tableName . '.' . $columnName);
    }
}
foreach (['products','orders','order_items'] as $tableName) {
    $engineQuery = $pdo->prepare(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
    );
    $engineQuery->execute([$tableName]);
    if (strtolower((string)$engineQuery->fetchColumn()) !== 'innodb') {
        throw new RuntimeException('Expected InnoDB engine for ' . $tableName);
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

// Also exercise a clean installation path with newly created InnoDB tables.
$server = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$server->exec('CREATE DATABASE mypress_fresh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$fresh = new PDO('mysql:host=' . $host . ';dbname=mypress_fresh;charset=utf8mb4', $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$fresh->exec('SET SESSION default_storage_engine=InnoDB');
mp_create_tables($fresh);
$fresh->beginTransaction();
$fresh->prepare("INSERT INTO users (username,password_hash,email,display_name,role) VALUES (?,?,?,?, 'admin')")
    ->execute(['fresh-admin', password_hash('long-test-password', PASSWORD_DEFAULT), 'fresh@example.test', 'Fresh Admin']);
mp_seed($fresh, (int)$fresh->lastInsertId());
$fresh->prepare('INSERT INTO options (opt_key,opt_value) VALUES (?,?)')->execute(['site_title','Fresh MyPress']);
$fresh->commit();

echo "PASS: MySQL legacy migration, clean install transaction, InnoDB, BIGINT and shop transaction\n";
