<?php
declare(strict_types=1);

/**
 * Isolated HTTP end-to-end smoke tests.
 *
 * A temporary copy is used so the installer can create config.php, SQLite data,
 * sessions and uploads without touching the checkout or a developer's files.
 */

$sourceRoot = dirname(__DIR__);
$tempRoot = sys_get_temp_dir() . '/mypress-http-' . bin2hex(random_bytes(6));
$docRoot = $tempRoot . '/app';
$cookieAdmin = $tempRoot . '/admin.cookies';
$cookieAuthor = $tempRoot . '/author.cookies';
$serverLog = $tempRoot . '/server.log';
$server = null;
$checks = 0;

function e2e_check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $checks++;
}

function e2e_copy_runtime(string $source, string $destination): void {
    if (!mkdir($destination, 0750, true) && !is_dir($destination)) {
        throw new RuntimeException('Cannot create temporary document root.');
    }

    $skip = ['.git', '.github', 'tests', 'data', 'uploads'];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $parts = preg_split('~[\\\\/]~', $relative) ?: [];
        if (array_intersect($parts, $skip)) {
            continue;
        }
        if ($relative === 'config.php' || $relative === 'config.php.installing') {
            continue;
        }

        $target = $destination . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0750, true) && !is_dir($target)) {
                throw new RuntimeException('Cannot copy runtime directory: ' . $relative);
            }
        } else {
            $parent = dirname($target);
            if (!is_dir($parent) && !mkdir($parent, 0750, true) && !is_dir($parent)) {
                throw new RuntimeException('Cannot create runtime directory: ' . $parent);
            }
            if (!copy($item->getPathname(), $target)) {
                throw new RuntimeException('Cannot copy runtime file: ' . $relative);
            }
        }
    }

    foreach (['data', 'uploads'] as $directory) {
        $target = $destination . DIRECTORY_SEPARATOR . $directory;
        if (!is_dir($target) && !mkdir($target, 0750, true) && !is_dir($target)) {
            throw new RuntimeException('Cannot create runtime storage directory.');
        }
        $protection = $source . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . '.htaccess';
        if (is_file($protection)) {
            copy($protection, $target . DIRECTORY_SEPARATOR . '.htaccess');
        }
    }
}

function e2e_request(string $baseUrl, string $cookieFile, string $method, string $path, ?array $data = null): array {
    $handle = curl_init($baseUrl . $path);
    if ($handle === false) {
        throw new RuntimeException('Unable to initialize HTTP client.');
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_USERAGENT => 'MyPress-E2E/1.0',
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $data ?? [];
    } else {
        $options[CURLOPT_HTTPGET] = true;
    }

    curl_setopt_array($handle, $options);
    $raw = curl_exec($handle);
    if ($raw === false) {
        $error = curl_error($handle);
        curl_close($handle);
        throw new RuntimeException('HTTP request failed: ' . $error);
    }

    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $headerSize = (int)curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);

    return [
        'status' => $status,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize),
    ];
}

function e2e_csrf(array $response): string {
    if (!preg_match('/name="csrf"\s+value="([^"]+)"/', $response['body'], $matches)) {
        throw new RuntimeException('CSRF field was not found in the response.');
    }
    return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function e2e_png(string $path): void {
    $chunk = static function (string $type, string $data): string {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    };
    $png = "\x89PNG\r\n\x1a\n";
    $png .= $chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 6, 0, 0, 0));
    $png .= $chunk('IDAT', gzcompress("\x00\xff\x00\x00\xff"));
    $png .= $chunk('IEND', '');
    if (file_put_contents($path, $png) === false) {
        throw new RuntimeException('Could not create image fixture.');
    }
}

function e2e_remove_tree(string $path): void {
    if (!is_dir($path)) {
        if (is_file($path)) @unlink($path);
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) @rmdir($item->getPathname());
        else @unlink($item->getPathname());
    }
    @rmdir($path);
}

try {
    if (!extension_loaded('curl') || !extension_loaded('pdo_sqlite') || !extension_loaded('fileinfo')) {
        throw new RuntimeException('HTTP E2E tests require curl, pdo_sqlite and fileinfo PHP extensions.');
    }

    e2e_copy_runtime($sourceRoot, $docRoot);
    touch($cookieAdmin);
    touch($cookieAuthor);

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException('Cannot reserve local HTTP port: ' . $error);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int)substr(strrchr((string)$address, ':'), 1);
    $baseUrl = 'http://127.0.0.1:' . $port . '/';

    $server = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $docRoot],
        [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']],
        $pipes,
        $docRoot
    );
    if (!is_resource($server)) throw new RuntimeException('Could not start PHP built-in web server.');
    if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);

    $ready = false;
    for ($attempt = 0; $attempt < 40; $attempt++) {
        usleep(100000);
        $probe = @file_get_contents($baseUrl . 'install.php');
        if ($probe !== false) {
            $ready = true;
            break;
        }
    }
    if (!$ready) {
        $log = is_file($serverLog) ? (string)file_get_contents($serverLog) : '';
        throw new RuntimeException("PHP HTTP server did not become ready.\n" . $log);
    }

    // Fresh install and one-time installer lock.
    $install = e2e_request($baseUrl, $cookieAdmin, 'GET', 'install.php');
    e2e_check($install['status'] === 200 && str_contains($install['body'], 'نصب MyPress'), 'Installer page is served over HTTP');
    $installToken = e2e_csrf($install);
    $installed = e2e_request($baseUrl, $cookieAdmin, 'POST', 'install.php', [
        'csrf' => $installToken,
        'site_title' => 'MyPress HTTP Test',
        'admin_user' => 'e2e-admin',
        'admin_pass' => 'E2E-Admin-Password-2026!',
        'admin_email' => 'admin@example.test',
        'db_type' => 'sqlite',
    ]);
    e2e_check($installed['status'] === 200 && str_contains($installed['body'], 'نصب کامل شد'), 'Installer creates the first administrator and completes');
    e2e_check(is_file($docRoot . '/config.php') && is_file($docRoot . '/data/installed.lock'), 'Installer writes config and installation lock');
    $lockedInstaller = e2e_request($baseUrl, $cookieAdmin, 'GET', 'install.php');
    e2e_check($lockedInstaller['status'] === 403, 'Installer cannot be reused after installation');

    // Public front page and seeded content.
    $home = e2e_request($baseUrl, $cookieAdmin, 'GET', 'index.php');
    e2e_check($home['status'] === 200 && str_contains($home['body'], 'MyPress HTTP Test'), 'Public home page renders configured site title');
    $seedPost = e2e_request($baseUrl, $cookieAdmin, 'GET', 'index.php?post=hello-world');
    e2e_check($seedPost['status'] === 200 && str_contains($seedPost['body'], 'سلام دنیا'), 'Seeded public post is readable');

    // Login rejects missing CSRF and invalid credentials, then rotates the session on success.
    $loginPage = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/login.php');
    e2e_check($loginPage['status'] === 200, 'Admin login page is served');
    $noCsrf = e2e_request($baseUrl, $cookieAdmin, 'POST', 'admin/login.php', [
        'username' => 'e2e-admin', 'password' => 'E2E-Admin-Password-2026!',
    ]);
    e2e_check($noCsrf['status'] === 403, 'Login rejects a POST without a CSRF token');

    $loginPage = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/login.php');
    $badLogin = e2e_request($baseUrl, $cookieAdmin, 'POST', 'admin/login.php', [
        'csrf' => e2e_csrf($loginPage),
        'username' => 'e2e-admin',
        'password' => 'incorrect-password',
    ]);
    e2e_check($badLogin['status'] === 200 && str_contains($badLogin['body'], 'اشتباه'), 'Invalid administrator credentials are rejected');

    $loginPage = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/login.php');
    $goodLogin = e2e_request($baseUrl, $cookieAdmin, 'POST', 'admin/login.php', [
        'csrf' => e2e_csrf($loginPage),
        'username' => 'e2e-admin',
        'password' => 'E2E-Admin-Password-2026!',
    ]);
    e2e_check($goodLogin['status'] === 303 && str_contains($goodLogin['headers'], 'Location: index.php'), 'Valid administrator login redirects to dashboard');
    $dashboard = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/index.php');
    e2e_check($dashboard['status'] === 200 && str_contains($dashboard['body'], 'داشبورد'), 'Authenticated administrator can open dashboard');

    // Content creation, publication and public rendering.
    $editor = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/post-edit.php?type=post');
    $postToken = e2e_csrf($editor);
    $missingPostCsrf = e2e_request($baseUrl, $cookieAdmin, 'POST', 'admin/post-edit.php?type=post', [
        'title' => 'Must Not Save', 'slug' => 'must-not-save', 'content' => 'No CSRF',
        'status' => 'published',
    ]);
    e2e_check($missingPostCsrf['status'] === 403, 'Content creation rejects a POST without CSRF');

    $savedPost = e2e_request($baseUrl, $cookieAdmin, 'POST', 'admin/post-edit.php?type=post', [
        'csrf' => $postToken,
        'title' => 'HTTP E2E Published Article',
        'slug' => 'http-e2e-published-article',
        'content' => 'Rendered through the public HTTP route.',
        'excerpt' => 'E2E excerpt',
        'status' => 'published',
        'category_id' => '1',
        'tags' => 'e2e,http',
    ]);
    e2e_check($savedPost['status'] === 303, 'Administrator can create and publish a post');
    $publicPost = e2e_request($baseUrl, $cookieAdmin, 'GET', 'index.php?post=http-e2e-published-article');
    e2e_check($publicPost['status'] === 200 && str_contains($publicPost['body'], 'Rendered through the public HTTP route.'), 'Newly published post is rendered publicly');

    // Real multipart upload plus rejection of a PHP file disguised as an image.
    $mediaPage = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/media.php');
    $imagePath = $tempRoot . '/valid.png';
    e2e_png($imagePath);
    $upload = e2e_request($baseUrl, $cookieAdmin, 'POST', 'admin/media.php', [
        'csrf' => e2e_csrf($mediaPage),
        'image' => new CURLFile($imagePath, 'image/png', 'pixel.png'),
    ]);
    e2e_check($upload['status'] === 303, 'Valid PNG upload completes through multipart HTTP request');
    $uploadedFiles = glob($docRoot . '/uploads/*');
    $uploadedFiles = array_values(array_filter($uploadedFiles ?: [], 'is_file'));
    e2e_check(count($uploadedFiles) === 1 && preg_match('/^[a-f0-9]{32}\.png$/', basename($uploadedFiles[0]) ) === 1, 'Uploaded image is stored under a random non-user-controlled filename');

    $mediaPage = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/media.php');
    $fakePath = $tempRoot . '/fake.php';
    file_put_contents($fakePath, '<?php echo "not an image";');
    $fakeUpload = e2e_request($baseUrl, $cookieAdmin, 'POST', 'admin/media.php', [
        'csrf' => e2e_csrf($mediaPage),
        'image' => new CURLFile($fakePath, 'image/png', 'fake.png'),
    ]);
    e2e_check($fakeUpload['status'] === 303, 'Invalid upload is handled without crashing the HTTP route');
    $mediaPage = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/media.php');
    e2e_check(substr_count($mediaPage['body'], '<tr>') === 2, 'Invalid PHP disguised as an image is not added to the media library');

    // Public shop journey: create product, add to cart and place a COD order.
    $productsPage = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/shop-products.php');
    $createdProduct = e2e_request($baseUrl, $cookieAdmin, 'POST', 'admin/shop-products.php', [
        'csrf' => e2e_csrf($productsPage),
        'name' => 'HTTP E2E Product',
        'price' => '4200',
        'stock' => '2',
        'category_id' => '2',
        'description' => 'Product created through admin HTTP form.',
    ]);
    e2e_check($createdProduct['status'] === 303, 'Administrator can create a shop product');
    $publicProduct = e2e_request($baseUrl, $cookieAdmin, 'GET', 'index.php?product=http-e2e-product');
    e2e_check($publicProduct['status'] === 200 && str_contains($publicProduct['body'], 'HTTP E2E Product'), 'Created product is publicly visible');

    $cartAdd = e2e_request($baseUrl, $cookieAdmin, 'POST', 'index.php?cart=1', [
        'csrf' => e2e_csrf($publicProduct),
        'cart_action' => 'add',
        'product_id' => '2',
        'qty' => '1',
    ]);
    e2e_check($cartAdd['status'] === 303, 'Customer can add a product to the cart');
    $checkoutPage = e2e_request($baseUrl, $cookieAdmin, 'GET', 'index.php?checkout=1');
    e2e_check($checkoutPage['status'] === 200 && str_contains($checkoutPage['body'], 'HTTP E2E Product'), 'Cart contents reach checkout');
    $order = e2e_request($baseUrl, $cookieAdmin, 'POST', 'index.php?checkout=1', [
        'csrf' => e2e_csrf($checkoutPage),
        'cart_action' => 'checkout',
        'customer_name' => 'HTTP Test Buyer',
        'customer_phone' => '09120000000',
        'customer_address' => 'HTTP Test Address',
        'discount_code' => '',
    ]);
    e2e_check($order['status'] === 303 && preg_match('/order=([0-9]+)/', $order['headers'], $orderMatch) === 1, 'Customer can place a COD order');
    $orderDone = e2e_request($baseUrl, $cookieAdmin, 'GET', 'index.php?checkout=done&order=' . $orderMatch[1]);
    e2e_check($orderDone['status'] === 200 && str_contains($orderDone['body'], 'سفارش ثبت شد'), 'Order confirmation page is rendered');
    $ordersPage = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/shop-orders.php');
    e2e_check($ordersPage['status'] === 200 && str_contains($ordersPage['body'], 'HTTP Test Buyer'), 'Administrator can view the created order');

    // Backups must be downloadable and must not disclose password hashes.
    $jsonBackup = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/backup.php?download=json');
    e2e_check($jsonBackup['status'] === 200 && str_contains($jsonBackup['headers'], 'application/json'), 'Administrator can download JSON backup');
    e2e_check(str_contains($jsonBackup['body'], 'HTTP E2E Product') && !str_contains($jsonBackup['body'], 'password_hash'), 'JSON backup contains business data but omits password hashes');
    $sqliteBackup = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/backup.php?download=sqlite');
    e2e_check($sqliteBackup['status'] === 200 && str_starts_with($sqliteBackup['body'], 'SQLite format 3'), 'Administrator can download a valid SQLite database backup');

    // Role boundary checks use a second, independent browser session.
    $db = new PDO('sqlite:' . $docRoot . '/data/mypress.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->prepare("INSERT INTO users (username,password_hash,email,display_name,role) VALUES (?,?,?,?, 'author')")
        ->execute(['e2e-author', password_hash('E2E-Author-Password-2026!', PASSWORD_DEFAULT), 'author@example.test', 'E2E Author']);
    $authorId = (int)$db->lastInsertId();

    $authorLoginPage = e2e_request($baseUrl, $cookieAuthor, 'GET', 'admin/login.php');
    $authorLogin = e2e_request($baseUrl, $cookieAuthor, 'POST', 'admin/login.php', [
        'csrf' => e2e_csrf($authorLoginPage),
        'username' => 'e2e-author',
        'password' => 'E2E-Author-Password-2026!',
    ]);
    e2e_check($authorLogin['status'] === 303, 'Author can authenticate in an independent session');
    $authorDashboard = e2e_request($baseUrl, $cookieAuthor, 'GET', 'admin/index.php');
    e2e_check($authorDashboard['status'] === 200 && str_contains($authorDashboard['body'], 'نوشته‌های من'), 'Author sees the role-scoped dashboard');
    $authorProducts = e2e_request($baseUrl, $cookieAuthor, 'GET', 'admin/shop-products.php');
    e2e_check($authorProducts['status'] === 403, 'Author cannot access product administration');
    $authorPageEdit = e2e_request($baseUrl, $cookieAuthor, 'GET', 'admin/post-edit.php?type=page');
    e2e_check($authorPageEdit['status'] === 403, 'Author cannot create or edit pages');

    $authorForm = e2e_request($baseUrl, $cookieAuthor, 'GET', 'admin/post-edit.php?type=post');
    $authorDraft = e2e_request($baseUrl, $cookieAuthor, 'POST', 'admin/post-edit.php?type=post', [
        'csrf' => e2e_csrf($authorForm),
        'title' => 'Author Draft E2E',
        'slug' => 'author-draft-e2e',
        'content' => 'This must remain a draft.',
        'excerpt' => '',
        'status' => 'published',
        'category_id' => '1',
        'tags' => '',
    ]);
    e2e_check($authorDraft['status'] === 303, 'Author can submit own post');
    $draft = $db->query("SELECT status,author_id FROM posts WHERE slug='author-draft-e2e'")->fetch(PDO::FETCH_ASSOC);
    e2e_check(is_array($draft) && $draft['status'] === 'draft' && (int)$draft['author_id'] === $authorId, 'Author submission is forced to an owned draft even when published was requested');
    $privateDraft = e2e_request($baseUrl, $cookieAdmin, 'GET', 'index.php?post=author-draft-e2e');
    e2e_check($privateDraft['status'] === 404, 'Author draft is not publicly visible');

    $authorLogoutGet = e2e_request($baseUrl, $cookieAuthor, 'GET', 'admin/logout.php');
    e2e_check($authorLogoutGet['status'] === 405, 'Logout rejects GET requests');
    $authorLogoutPage = e2e_request($baseUrl, $cookieAuthor, 'GET', 'admin/index.php');
    e2e_check($authorLogoutPage['status'] === 200, 'Rejected GET logout does not destroy the author session');
    $authorLogout = e2e_request($baseUrl, $cookieAuthor, 'POST', 'admin/logout.php', [
        'csrf' => e2e_csrf($authorLogoutPage),
    ]);
    e2e_check($authorLogout['status'] === 303, 'Logout succeeds through a CSRF-protected POST');
    $afterLogout = e2e_request($baseUrl, $cookieAuthor, 'GET', 'admin/index.php');
    e2e_check($afterLogout['status'] === 303 && str_contains($afterLogout['headers'], 'Location: login.php'), 'Logged-out author is redirected to login');

    echo 'PASS: ' . $checks . " HTTP E2E checks\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    if (is_file($serverLog)) fwrite(STDERR, (string)file_get_contents($serverLog));
    exitCode:
    $exitCode = 1;
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    e2e_remove_tree($tempRoot);
}

if (isset($exitCode)) exit($exitCode);
