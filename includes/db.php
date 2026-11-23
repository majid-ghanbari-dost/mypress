<?php
// PDO connection, portable schema and seed data.
declare(strict_types=1);

function mp_config(): array {
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/../config.php';
        $config = file_exists($file) ? require $file : require __DIR__ . '/../config.sample.php';
        if (!is_array($config)) throw new RuntimeException('Invalid MyPress configuration.');
    }
    return $config;
}

function mp_table_exists(PDO $pdo, string $tableName): bool {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
        throw new InvalidArgumentException('Invalid table name.');
    }
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE() AND table_name=?'
        );
        $statement->execute([$tableName]);
        return (int)$statement->fetchColumn() > 0;
    }
    $statement = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=?");
    $statement->execute([$tableName]);
    return (int)$statement->fetchColumn() > 0;
}

function mp_index_exists(PDO $pdo, string $tableName, string $indexName): bool {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName) ||
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $indexName)) {
        throw new InvalidArgumentException('Invalid index identifier.');
    }
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name=? AND index_name=?'
        );
        $statement->execute([$tableName, $indexName]);
        return (int)$statement->fetchColumn() > 0;
    }

    $indexes = $pdo->query('PRAGMA index_list("' . $tableName . '")')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($indexes as $index) {
        if (($index['name'] ?? '') === $indexName) return true;
    }
    return false;
}

function mp_ensure_index(PDO $pdo, string $tableName, string $indexName, string $columns): void {
    $columnNames = array_map('trim', explode(',', $columns));
    if (!$columnNames || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName) ||
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $indexName)) {
        throw new InvalidArgumentException('Invalid index definition.');
    }
    foreach ($columnNames as $columnName) {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $columnName)) {
            throw new InvalidArgumentException('Invalid index column.');
        }
    }
    if (mp_index_exists($pdo, $tableName, $indexName)) return;
    try {
        $pdo->exec("CREATE INDEX {$indexName} ON {$tableName} (" . implode(', ', $columnNames) . ')');
    } catch (PDOException $exception) {
        // Another request may have created the index after the existence check.
        if (!mp_index_exists($pdo, $tableName, $indexName)) throw $exception;
    }
}

function mp_ensure_security_schema(PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id $id,
        username_key CHAR(64) NOT NULL,
        ip_key CHAR(64) NOT NULL,
        attempted_at DATETIME NOT NULL
    )");
    $indexes = [
        'idx_login_attempts_lookup' => 'username_key, ip_key, attempted_at',
        // The per-IP throttle query filters by ip_key without username_key.
        // A separate leading-column index prevents a full scan as the table grows.
        'idx_login_attempts_ip_lookup' => 'ip_key, attempted_at',
    ];
    foreach ($indexes as $indexName => $columns) {
        mp_ensure_index($pdo, 'login_attempts', $indexName, $columns);
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(64) PRIMARY KEY,
        applied_at DATETIME NOT NULL
    )");

    // Order administration loads line items by order_id. Add this index to
    // existing installations once, not just to newly created databases.
    $orderIndexMigration = 'order-items-order-index-v1';
    $orderIndexCheck = $pdo->prepare('SELECT version FROM schema_migrations WHERE version=?');
    $orderIndexCheck->execute([$orderIndexMigration]);
    if (!$orderIndexCheck->fetchColumn() && mp_table_exists($pdo, 'order_items')) {
        mp_ensure_index($pdo, 'order_items', 'idx_order_items_order_id', 'order_id');
        $insertSql = $mysql
            ? 'INSERT IGNORE INTO schema_migrations (version,applied_at) VALUES (?,?)'
            : 'INSERT OR IGNORE INTO schema_migrations (version,applied_at) VALUES (?,?)';
        $pdo->prepare($insertSql)->execute([$orderIndexMigration, date('Y-m-d H:i:s')]);
    }

    // Cover the stable sort/filter patterns used by high-volume admin screens
    // and the public product listing. This migration runs once per installation.
    $adminIndexMigration = 'admin-list-indexes-v1';
    $adminIndexCheck = $pdo->prepare('SELECT version FROM schema_migrations WHERE version=?');
    $adminIndexCheck->execute([$adminIndexMigration]);
    if (!$adminIndexCheck->fetchColumn() && mp_table_exists($pdo, 'posts')) {
        $adminIndexes = [
            'posts' => [
                'idx_posts_type_created_id' => 'type, created_at, id',
                'idx_posts_author_type_created_id' => 'author_id, type, created_at, id',
            ],
            'comments' => [
                'idx_comments_created_id' => 'created_at, id',
            ],
            'media' => [
                'idx_media_created_id' => 'created_at, id',
                'idx_media_owner_created_id' => 'uploaded_by, created_at, id',
            ],
            'products' => [
                'idx_products_created_id' => 'created_at, id',
                'idx_products_status_created_id' => 'status, created_at, id',
            ],
            'orders' => [
                'idx_orders_created_id' => 'created_at, id',
                'idx_orders_customer_name_created_id' => 'customer_name, created_at, id',
                'idx_orders_customer_phone_created_id' => 'customer_phone, created_at, id',
            ],
        ];
        foreach ($adminIndexes as $tableName => $indexesForTable) {
            if (!mp_table_exists($pdo, $tableName)) continue;
            foreach ($indexesForTable as $indexName => $columns) {
                mp_ensure_index($pdo, $tableName, $indexName, $columns);
            }
        }
        $insertSql = $mysql
            ? 'INSERT IGNORE INTO schema_migrations (version,applied_at) VALUES (?,?)'
            : 'INSERT OR IGNORE INTO schema_migrations (version,applied_at) VALUES (?,?)';
        $pdo->prepare($insertSql)->execute([$adminIndexMigration, date('Y-m-d H:i:s')]);
    }

    // Existing MySQL installations used 32-bit INTEGER for money fields.
    // Upgrade them once so large order totals cannot overflow on MySQL.
    if ($mysql) {
        $tablesReady = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=DATABASE() AND table_name IN ('products','orders','order_items')"
        )->fetchColumn();
        if ($tablesReady === 3) {
            $check = $pdo->prepare('SELECT version FROM schema_migrations WHERE version=?');
            $check->execute(['mysql-commerce-engine-money-v1']);
            if (!$check->fetchColumn()) {
                $pdo->exec('ALTER TABLE products ENGINE=InnoDB, MODIFY price BIGINT NOT NULL DEFAULT 0');
                $pdo->exec('ALTER TABLE orders ENGINE=InnoDB, MODIFY total BIGINT NOT NULL DEFAULT 0, MODIFY discount_amount BIGINT NOT NULL DEFAULT 0');
                $pdo->exec('ALTER TABLE order_items ENGINE=InnoDB, MODIFY price BIGINT NOT NULL DEFAULT 0');
                $pdo->prepare('INSERT IGNORE INTO schema_migrations (version,applied_at) VALUES (?,?)')
                    ->execute(['mysql-commerce-engine-money-v1', date('Y-m-d H:i:s')]);
            }
        }
    }
}
 
function mp_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $config = mp_config();
    $type = $config['db_type'] ?? 'sqlite';
    if ($type === 'mysql') {
        $host = (string)($config['db_host'] ?? 'localhost');
        $database = (string)($config['db_name'] ?? '');
        $charset = (string)($config['db_charset'] ?? 'utf8mb4');
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $charset) || $database === '') {
            throw new RuntimeException('Invalid MySQL database configuration.');
        }
        $dsn = "mysql:host={$host};dbname={$database};charset={$charset}";
        $pdo = new PDO($dsn, (string)($config['db_user'] ?? ''), (string)($config['db_pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('SET SESSION default_storage_engine=InnoDB');
    } else {
        $path = (string)($config['db_path'] ?? (__DIR__ . '/../data/mypress.sqlite'));
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create database directory.');
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }
    mp_ensure_security_schema($pdo);
    return $pdo;
}

function mp_create_tables(PDO $pdo): void {
    $isMysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $auto = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $text = 'TEXT';
    // MySQL versions before 8.0.13 reject defaults on TEXT/BLOB columns.
    $textDefault = $isMysql ? 'TEXT NOT NULL' : "TEXT NOT NULL DEFAULT ''";
    $money = $isMysql ? 'BIGINT' : 'INTEGER';
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $auto, username VARCHAR(60) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(150) NOT NULL, display_name VARCHAR(120) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'author', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id $auto, name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL UNIQUE,
        type VARCHAR(20) NOT NULL DEFAULT 'post')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id $auto, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL UNIQUE,
        content $text NOT NULL, excerpt VARCHAR(500) DEFAULT '',
        type VARCHAR(20) NOT NULL DEFAULT 'post', status VARCHAR(20) NOT NULL DEFAULT 'draft',
        category_id INTEGER DEFAULT NULL, tags VARCHAR(255) DEFAULT '',
        author_id INTEGER NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id $auto, post_id INTEGER NOT NULL, author VARCHAR(120) NOT NULL,
        email VARCHAR(150) NOT NULL, content $text NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS media (
        id $auto, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL,
        mime VARCHAR(100) NOT NULL, size INTEGER NOT NULL DEFAULT 0,
        uploaded_by INTEGER NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS menus (
        id $auto, title VARCHAR(120) NOT NULL, url VARCHAR(255) NOT NULL,
        position INTEGER NOT NULL DEFAULT 0, parent_id INTEGER NOT NULL DEFAULT 0)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id $auto, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL UNIQUE,
        description $textDefault, price $money NOT NULL DEFAULT 0,
        stock INTEGER NOT NULL DEFAULT 0, image VARCHAR(255) DEFAULT '',
        category_id INTEGER DEFAULT NULL, status VARCHAR(20) NOT NULL DEFAULT 'published',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id $auto, customer_name VARCHAR(150) NOT NULL, customer_phone VARCHAR(50) NOT NULL,
        customer_address $text NOT NULL, total $money NOT NULL DEFAULT 0,
        discount_code VARCHAR(50) DEFAULT '', discount_amount $money NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'new',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id $auto, order_id INTEGER NOT NULL, product_id INTEGER NOT NULL,
        product_name VARCHAR(255) NOT NULL, price $money NOT NULL DEFAULT 0,
        qty INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
        id $auto, code VARCHAR(50) NOT NULL UNIQUE, percent INTEGER NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1, expires_at DATE DEFAULT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS options (
        opt_key VARCHAR(100) PRIMARY KEY, opt_value $textDefault)");
    mp_ensure_security_schema($pdo);
}

function mp_seed(PDO $pdo, int $adminId): void {
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO categories (id,name,slug,type) VALUES (1,'عمومی','general','post')")->execute();
    $pdo->prepare("INSERT INTO categories (id,name,slug,type) VALUES (2,'فروشگاه','shop','product')")->execute();
    $statement = $pdo->prepare("INSERT INTO posts (title,slug,content,excerpt,type,status,category_id,author_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $statement->execute(['سلام دنیا!','hello-world',"به MyPress خوش آمدید.\n\nاین یک نوشته نمونه است.",'نوشته نمونه MyPress','post','published',1,$adminId,$now,$now]);
    $statement->execute(['درباره ما','about','این برگه نمونه «درباره ما» است.','برگه نمونه درباره ما','page','published',null,$adminId,$now,$now]);
    $pdo->prepare("INSERT INTO products (id,name,slug,description,price,stock,category_id,status) VALUES (1,'محصول نمونه','sample-product','محصول نمونه فروشگاه MyPress',150000,10,2,'published')")->execute();
    $pdo->prepare("INSERT INTO menus (title,url,position) VALUES ('خانه','/',0),('فروشگاه','/?shop=1',1),('درباره ما','/?page=about',2)")->execute();
    $pdo->prepare("INSERT INTO coupons (code,percent,active) VALUES ('WELCOME10',10,1)")->execute();
}

function mp_option(string $key, string $default = ''): string {
    try {
        $statement = mp_pdo()->prepare('SELECT opt_value FROM options WHERE opt_key=?');
        $statement->execute([$key]);
        $row = $statement->fetch();
        return $row ? (string)$row['opt_value'] : $default;
    } catch (Throwable $exception) {
        return $default;
    }
}

function mp_set_option(string $key, string $value): void {
    $pdo = mp_pdo();
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $pdo->prepare('INSERT INTO options (opt_key,opt_value) VALUES (?,?) ON DUPLICATE KEY UPDATE opt_value=?')
            ->execute([$key, $value, $value]);
    } else {
        $pdo->prepare('INSERT INTO options (opt_key,opt_value) VALUES (?,?) ON CONFLICT(opt_key) DO UPDATE SET opt_value=excluded.opt_value')
            ->execute([$key, $value]);
    }
}

function mp_module_enabled(string $module): bool {
    return mp_option('module_' . $module, '1') === '1';
}
