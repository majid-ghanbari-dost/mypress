<?php
// db.php — اتصال PDO + ساخت جداول + داده نمونه
// فقط PHP خالص؛ بدون Composer و بدون وابستگی خارجی.

function mp_config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/../config.php';
        $cfg = file_exists($file) ? require $file : require __DIR__ . '/../config.sample.php';
    }
    return $cfg;
}

function mp_pdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $cfg = mp_config();
    $type = $cfg['db_type'] ?? 'sqlite';
    if ($type === 'mysql') {
        $dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=" . ($cfg['db_charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else {
        $path = $cfg['db_path'] ?? (__DIR__ . '/../data/mypress.sqlite');
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

// ساخت همه جداول (برای SQLite و MySQL سازگار نوشته شده)
function mp_create_tables(PDO $pdo): void {
    $isMysql = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql');
    $ai = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $txt = $isMysql ? 'TEXT' : 'TEXT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $ai, username VARCHAR(60) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(150) NOT NULL, display_name VARCHAR(120) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'author', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id $ai, name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL UNIQUE,
        type VARCHAR(20) NOT NULL DEFAULT 'post')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id $ai, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL UNIQUE,
        content $txt NOT NULL, excerpt VARCHAR(500) DEFAULT '',
        type VARCHAR(20) NOT NULL DEFAULT 'post', status VARCHAR(20) NOT NULL DEFAULT 'draft',
        category_id INTEGER DEFAULT NULL, tags VARCHAR(255) DEFAULT '',
        author_id INTEGER NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id $ai, post_id INTEGER NOT NULL, author VARCHAR(120) NOT NULL,
        email VARCHAR(150) NOT NULL, content $txt NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS media (
        id $ai, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL,
        mime VARCHAR(100) NOT NULL, size INTEGER NOT NULL DEFAULT 0,
        uploaded_by INTEGER NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS menus (
        id $ai, title VARCHAR(120) NOT NULL, url VARCHAR(255) NOT NULL,
        position INTEGER NOT NULL DEFAULT 0, parent_id INTEGER NOT NULL DEFAULT 0)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id $ai, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL UNIQUE,
        description $txt NOT NULL DEFAULT '', price INTEGER NOT NULL DEFAULT 0,
        stock INTEGER NOT NULL DEFAULT 0, image VARCHAR(255) DEFAULT '',
        category_id INTEGER DEFAULT NULL, status VARCHAR(20) NOT NULL DEFAULT 'published',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id $ai, customer_name VARCHAR(150) NOT NULL, customer_phone VARCHAR(50) NOT NULL,
        customer_address $txt NOT NULL, total INTEGER NOT NULL DEFAULT 0,
        discount_code VARCHAR(50) DEFAULT '', discount_amount INTEGER NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'new',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id $ai, order_id INTEGER NOT NULL, product_id INTEGER NOT NULL,
        product_name VARCHAR(255) NOT NULL, price INTEGER NOT NULL DEFAULT 0,
        qty INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
        id $ai, code VARCHAR(50) NOT NULL UNIQUE, percent INTEGER NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1, expires_at DATE DEFAULT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS options (
        opt_key VARCHAR(100) PRIMARY KEY, opt_value $txt NOT NULL DEFAULT '')");
}

// داده نمونه: یک برگه، یک نوشته، یک محصول، یک دسته، یک منو
function mp_seed(PDO $pdo, int $adminId): void {
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO categories (id,name,slug,type) VALUES (1,'عمومی','general','post')")->execute();
    $pdo->prepare("INSERT INTO categories (id,name,slug,type) VALUES (2,'فروشگاه','shop','product')")->execute();
    $st = $pdo->prepare("INSERT INTO posts (title,slug,content,excerpt,type,status,category_id,author_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $st->execute(['سلام دنیا!','hello-world',"به MyPress خوش آمدید.\n\nاین یک نوشته نمونه است. از پنل مدیریت می‌توانید آن را ویرایش یا حذف کنید.",'نوشته نمونه MyPress','post','published',1,$adminId,$now,$now]);
    $st->execute(['درباره ما','about','این برگه نمونه «درباره ما» است. معرفی سایت خود را اینجا بنویسید.','برگه نمونه درباره ما','page','published',null,$adminId,$now,$now]);
    $pdo->prepare("INSERT INTO products (id,name,slug,description,price,stock,category_id,status) VALUES (1,'محصول نمونه','sample-product','محصول نمونه فروشگاه MyPress — توضیحات را از پنل فروشگاه ویرایش کنید.',150000,10,2,'published')")->execute();
    $pdo->prepare("INSERT INTO menus (title,url,position) VALUES ('خانه','/',0),('فروشگاه','/?shop=1',1),('درباره ما','/?page=about',2)")->execute();
    $pdo->prepare("INSERT INTO coupons (code,percent,active) VALUES ('WELCOME10',10,1)")->execute();
}

// تنظیمات (options)
function mp_option(string $key, string $default = ''): string {
    try {
        $st = mp_pdo()->prepare("SELECT opt_value FROM options WHERE opt_key=?");
        $st->execute([$key]);
        $row = $st->fetch();
        return $row ? (string)$row['opt_value'] : $default;
    } catch (Throwable $e) { return $default; }
}
function mp_set_option(string $key, string $value): void {
    $pdo = mp_pdo();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        // بدون VALUES() منسوخ‌شده؛ سازگار با MySQL 8.4 هم هست
        $pdo->prepare("INSERT INTO options (opt_key,opt_value) VALUES (?,?) ON DUPLICATE KEY UPDATE opt_value=?")->execute([$key,$value,$value]);
    } else {
        $pdo->prepare("INSERT INTO options (opt_key,opt_value) VALUES (?,?) ON CONFLICT(opt_key) DO UPDATE SET opt_value=excluded.opt_value")->execute([$key,$value]);
    }
}
function mp_module_enabled(string $module): bool {
    // پیش‌فرض: shop و comments و seo فعال
    return mp_option('module_' . $module, '1') === '1';
}
