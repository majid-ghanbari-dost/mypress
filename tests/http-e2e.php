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

// Keep server-level secret and upload protections from regressing. The built-in
// PHP server used below does not parse Apache/Nginx configuration files.
$apacheRules = (string)file_get_contents($sourceRoot . '/.htaccess');
$uploadRules = (string)file_get_contents($sourceRoot . '/uploads/.htaccess');
$nginxRules = (string)file_get_contents($sourceRoot . '/docs/nginx.conf.example');
$backupSource = (string)file_get_contents($sourceRoot . '/admin/backup.php');
e2e_check(
    str_contains($backupSource, 'PDO::MYSQL_ATTR_USE_BUFFERED_QUERY') &&
    str_contains($backupSource, 'setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false)') &&
    str_contains($backupSource, 'setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true)'),
    'JSON backup explicitly disables and restores PDO MySQL result buffering'
);
e2e_check(str_contains($apacheRules, 'config\\.php(?:\\..*)?'), 'Apache denies config.php and temporary sibling files');
e2e_check(str_contains($nginxRules, 'config\\.php(?:\\..*)?'), 'Nginx denies config.php and temporary sibling files');
e2e_check(str_contains($apacheRules, 'X-Content-Type-Options') && str_contains($apacheRules, 'Content-Security-Policy'), 'Apache config includes baseline security headers');
e2e_check(str_contains($nginxRules, 'X-Content-Type-Options') && str_contains($nginxRules, 'Content-Security-Policy'), 'Nginx config includes baseline security headers');
e2e_check(!str_contains($uploadRules, 'php_flag') && str_contains($uploadRules, 'Require all denied'), 'Uploads protection is PHP-FPM compatible and denies script extensions');

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
    e2e_check(str_contains($install['headers'], 'X-Content-Type-Options: nosniff') &&
        str_contains($install['headers'], 'Content-Security-Policy:'), 'Installer sends baseline security headers');
    $installToken = e2e_csrf($install);

    // Simulate another PHP worker holding the installer mutex. A second
    // installation request must fail closed without creating config or DB state.
    $installDataDirectory = $docRoot . '/data';
    if (!is_dir($installDataDirectory) && !mkdir($installDataDirectory, 0750, true)) {
        throw new RuntimeException('Cannot create installer lock fixture directory.');
    }
    $heldInstallLock = fopen($installDataDirectory . '/.install.lock', 'c');
    if ($heldInstallLock === false || !flock($heldInstallLock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Cannot acquire installer lock fixture.');
    }
    $blockedInstall = e2e_request($baseUrl, $cookieAdmin, 'POST', 'install.php', [
        'csrf' => $installToken,
        'site_title' => 'MyPress HTTP Test',
        'admin_user' => 'e2e-admin',
        'admin_pass' => 'E2E-Admin-Password-2026!',
        'admin_email' => 'admin@example.test',
        'db_type' => 'sqlite',
    ]);
    flock($heldInstallLock, LOCK_UN);
    fclose($heldInstallLock);
    e2e_check($blockedInstall['status'] === 409, 'Concurrent installer request is rejected while another worker owns the lock');
    e2e_check(!is_file($docRoot . '/config.php') && !is_file($docRoot . '/data/installed.lock'), 'Rejected concurrent installer request creates no partial installation');

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
    e2e_check(str_contains($home['headers'], 'X-Frame-Options: SAMEORIGIN') &&
        str_contains($home['headers'], 'Referrer-Policy: strict-origin-when-cross-origin') &&
        str_contains($home['headers'], 'Permissions-Policy:'), 'Public pages send baseline security headers');
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
    $publicComment = e2e_request($baseUrl, $cookieAdmin, 'POST', 'index.php?post=http-e2e-published-article', [
        'csrf' => e2e_csrf($publicPost),
        'comment_submit' => '1',
        'post_id' => '3',
        'slug' => 'http-e2e-published-article',
        'ptype' => 'post',
        'author' => 'HTTP Commenter',
        'email' => 'commenter@example.test',
        'content' => 'A public comment submitted through the real form.',
    ]);
    e2e_check($publicComment['status'] === 303, 'Public comment submission succeeds through the CSRF-protected HTTP form');
    $commentDbProbe = new PDO('sqlite:' . $docRoot . '/data/mypress.sqlite');
    $commentProbe = $commentDbProbe->prepare("SELECT COUNT(*) FROM comments WHERE author=? AND status='pending'");
    $commentProbe->execute(['HTTP Commenter']);
    e2e_check((int)$commentProbe->fetchColumn() === 1, 'Public comment is stored pending moderation');

    $adminIdForFixtures = 0;
    $fixtureDb = new PDO('sqlite:' . $docRoot . '/data/mypress.sqlite');
    $fixtureDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $adminIdForFixtures = (int)$fixtureDb->query("SELECT id FROM users WHERE username='e2e-admin'")->fetchColumn();
    $postFixture = $fixtureDb->prepare("INSERT INTO posts (title,slug,content,type,status,author_id) VALUES (?,?,?,'post','draft',?)");
    for ($fixture = 1; $fixture <= 26; $fixture++) {
        $postFixture->execute(['Post Fixture ' . $fixture, 'post-fixture-' . $fixture, 'Fixture body', $adminIdForFixtures]);
    }
    $postsPageOne = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/posts.php');
    e2e_check(substr_count($postsPageOne['body'], '<tr>') - 1 === 25 && str_contains($postsPageOne['body'], 'صفحه 1 از 2'), 'Post administration paginates the first 25 records');
    $postsPageTwo = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/posts.php?page=2');
    e2e_check(substr_count($postsPageTwo['body'], '<tr>') - 1 === 3 && str_contains($postsPageTwo['body'], 'صفحه 2 از 2'), 'Post administration paginates remaining records');
    $filteredPosts = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/posts.php?q=Post+Fixture&status=draft');
    e2e_check($filteredPosts['status'] === 200 && substr_count($filteredPosts['body'], '<tr>') - 1 === 25, 'Post search and draft status filter compose correctly');
    e2e_check(str_contains($filteredPosts['body'], 'q=Post+Fixture') && str_contains($filteredPosts['body'], 'status=draft'), 'Post pagination preserves active filters');
    $filteredPostsPageTwo = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/posts.php?q=Post+Fixture&status=draft&page=2');
    e2e_check($filteredPostsPageTwo['status'] === 200 && substr_count($filteredPostsPageTwo['body'], '<tr>') - 1 === 1, 'Filtered post pagination returns the remaining matching post');
    $noMatchPosts = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/posts.php?q=NoSuchPost&status=published');
    e2e_check($noMatchPosts['status'] === 200 && substr_count($noMatchPosts['body'], '<tr>') === 1, 'Post filters handle empty results');
    $pageFixture = $fixtureDb->prepare("INSERT INTO posts (title,slug,content,type,status,author_id) VALUES (?,?,?,'page','draft',?)");
    for ($fixture = 1; $fixture <= 3; $fixture++) {
        $pageFixture->execute(['Page Fixture ' . $fixture, 'page-fixture-' . $fixture, 'Fixture page', $adminIdForFixtures]);
    }
    $filteredPages = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/pages.php?q=Page+Fixture+2&status=draft');
    e2e_check($filteredPages['status'] === 200 && substr_count($filteredPages['body'], '<tr>') - 1 === 1 && str_contains($filteredPages['body'], 'Page Fixture 2'), 'Page search and status filters return the matching page');

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

    $mediaFixture = $fixtureDb->prepare('INSERT INTO media (filename,original_name,mime,size,uploaded_by) VALUES (?,?,?,?,?)');
    for ($fixture = 1; $fixture <= 26; $fixture++) {
        $mediaFixture->execute([basename($uploadedFiles[0]), 'fixture-' . $fixture . '.png', 'image/png', 68, $adminIdForFixtures]);
    }
    $mediaListPageOne = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/media.php');
    e2e_check(substr_count($mediaListPageOne['body'], '<tr>') - 1 === 25 && str_contains($mediaListPageOne['body'], 'صفحه 1 از 2'), 'Media library paginates its first 25 records');
    $mediaListPageTwo = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/media.php?page=2');
    e2e_check(substr_count($mediaListPageTwo['body'], '<tr>') - 1 === 2, 'Media library renders the remaining two records on page two');
    $filteredMedia = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/media.php?q=fixture-&uploader=' . $adminIdForFixtures);
    e2e_check($filteredMedia['status'] === 200 && substr_count($filteredMedia['body'], '<tr>') - 1 === 25, 'Media filename search and uploader filter compose correctly');
    e2e_check(str_contains($filteredMedia['body'], 'q=fixture-') && str_contains($filteredMedia['body'], 'uploader=' . $adminIdForFixtures), 'Media pagination preserves search and uploader filters');
    $filteredMediaPageTwo = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/media.php?q=fixture-&uploader=' . $adminIdForFixtures . '&page=2');
    e2e_check($filteredMediaPageTwo['status'] === 200 && substr_count($filteredMediaPageTwo['body'], '<tr>') - 1 === 1, 'Filtered media pagination returns the final matching upload');
    $emptyMediaSearch = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/media.php?q=missing-file-prefix');
    e2e_check($emptyMediaSearch['status'] === 200 && substr_count($emptyMediaSearch['body'], '<tr>') === 1, 'Media search handles no matching files');


    $mediaPage = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/media.php');
    $fakePath = $tempRoot . '/fake.php';
    file_put_contents($fakePath, '<?php echo "not an image";');
    $fakeUpload = e2e_request($baseUrl, $cookieAdmin, 'POST', 'admin/media.php', [
        'csrf' => e2e_csrf($mediaPage),
        'image' => new CURLFile($fakePath, 'image/png', 'fake.png'),
    ]);
    e2e_check($fakeUpload['status'] === 303, 'Invalid upload is handled without crashing the HTTP route');
    $mediaPage = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/media.php');
    e2e_check(substr_count($mediaPage['body'], '<tr>') === 26, 'Invalid PHP disguised as an image is not added to the media library');

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
    $publicProduct = e2e_request($baseUrl, $cookieAdmin, 'GET', 'index.php?product=HTTP-E2E-Product');
    e2e_check($publicProduct['status'] === 200 && str_contains($publicProduct['body'], 'HTTP E2E Product'), 'Created product is publicly visible');

    $productFixture = $fixtureDb->prepare("INSERT INTO products (name,slug,description,price,stock,status) VALUES (?,?,?,1000,1,'draft')");
    for ($fixture = 1; $fixture <= 25; $fixture++) {
        $productFixture->execute(['Product Fixture ' . $fixture, 'product-fixture-' . $fixture, 'Fixture product']);
    }
    $productsListOne = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/shop-products.php');
    e2e_check(substr_count($productsListOne['body'], '<tr>') - 1 === 25 && str_contains($productsListOne['body'], 'صفحه 1 از 2'), 'Product administration paginates its first 25 records');
    $productsListTwo = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/shop-products.php?page=2');
    e2e_check(substr_count($productsListTwo['body'], '<tr>') - 1 === 2, 'Product administration renders only remaining records on page two');
    $filteredProducts = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/shop-products.php?q=Product+Fixture+1&status=draft&stock=low');
    e2e_check($filteredProducts['status'] === 200 && substr_count($filteredProducts['body'], '<tr>') - 1 === 11, 'Product search, archived status and low-stock filters compose correctly');
    e2e_check(str_contains($filteredProducts['body'], 'Product Fixture 1') && str_contains($filteredProducts['body'], 'name="stock"'), 'Product filter results and controls are rendered');
    $noMatchProducts = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/shop-products.php?q=NoSuchProduct&status=published');
    e2e_check($noMatchProducts['status'] === 200 && substr_count($noMatchProducts['body'], '<tr>') === 1, 'Product filters handle empty results without breaking the table');

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

    $orderId = (int)$orderMatch[1];
    $processing = e2e_request($baseUrl, $cookieAdmin, 'POST', 'admin/shop-orders.php', [
        'csrf' => e2e_csrf($ordersPage),
        'order_id' => (string)$orderId,
        'status' => 'processing',
    ]);
    e2e_check($processing['status'] === 303, 'Administrator can move a new order into processing');
    $ordersPage = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/shop-orders.php');
    e2e_check(str_contains($ordersPage['body'], 'در حال پردازش'), 'Order processing status is visible in the admin list');
    $cancelled = e2e_request($baseUrl, $cookieAdmin, 'POST', 'admin/shop-orders.php', [
        'csrf' => e2e_csrf($ordersPage),
        'order_id' => (string)$orderId,
        'status' => 'cancelled',
    ]);
    e2e_check($cancelled['status'] === 303, 'Administrator can cancel an eligible order through the HTTP route');
    $orderDb = new PDO('sqlite:' . $docRoot . '/data/mypress.sqlite');
    $orderDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $orderState = $orderDb->query('SELECT status FROM orders WHERE id=' . $orderId)->fetchColumn();
    $restoredStock = $orderDb->query('SELECT stock FROM products WHERE id=2')->fetchColumn();
    e2e_check($orderState === 'cancelled' && (int)$restoredStock === 2, 'Order cancellation restores stock exactly once');

    // Fill the order list beyond one page and verify bounded rendering.
    $paginationInsert = $orderDb->prepare(
        "INSERT INTO orders (customer_name,customer_phone,customer_address,total,status) VALUES (?,?,?,?, 'new')"
    );
    for ($fixture = 1; $fixture <= 26; $fixture++) {
        $paginationInsert->execute(['Pagination Fixture ' . $fixture, '09120000000', 'Fixture Address', 1000]);
    }
    $ordersPageOne = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/shop-orders.php');
    e2e_check($ordersPageOne['status'] === 200 && substr_count($ordersPageOne['body'], '<div class="post-card">') === 25, 'Order administration renders only 25 records on the first page');
    e2e_check(str_contains($ordersPageOne['body'], 'صفحه 1 از 2'), 'Order administration exposes the first pagination page');
    $ordersPageTwo = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/shop-orders.php?page=2');
    e2e_check($ordersPageTwo['status'] === 200 && substr_count($ordersPageTwo['body'], '<div class="post-card">') === 2, 'Order administration renders only remaining records on the second page');
    e2e_check(str_contains($ordersPageTwo['body'], 'HTTP Test Buyer'), 'Second order page retains the older real order and its details');
    $filteredOrdersOne = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/shop-orders.php?q=Pagination+Fixture&status=new');
    e2e_check($filteredOrdersOne['status'] === 200 && substr_count($filteredOrdersOne['body'], '<div class="post-card">') === 25, 'Order search and status filters return the first 25 matching records');
    e2e_check(str_contains($filteredOrdersOne['body'], 'name="q"') && str_contains($filteredOrdersOne['body'], 'name="status"'), 'Order search and status filters remain visible');
    e2e_check(str_contains($filteredOrdersOne['body'], 'q=Pagination+Fixture') && str_contains($filteredOrdersOne['body'], 'status=new'), 'Order pagination preserves active search and status filters');
    $filteredOrdersTwo = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/shop-orders.php?q=Pagination%20Fixture&status=new&page=2');
    e2e_check($filteredOrdersTwo['status'] === 200 && substr_count($filteredOrdersTwo['body'], '<div class="post-card">') === 1, 'Filtered order pagination returns the final matching record');
    $noMatchOrders = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/shop-orders.php?q=NoSuchCustomer&status=new');
    e2e_check($noMatchOrders['status'] === 200 && str_contains($noMatchOrders['body'], 'هنوز سفارشی ثبت نشده است'), 'Order search handles empty result sets');

    $commentPostId = (int)$fixtureDb->query("SELECT id FROM posts WHERE slug='http-e2e-published-article'")->fetchColumn();
    $commentFixture = $fixtureDb->prepare("INSERT INTO comments (post_id,author,email,content,status) VALUES (?,?,?,?, 'pending')");
    for ($fixture = 1; $fixture <= 26; $fixture++) {
        $commentFixture->execute([$commentPostId, 'Comment Fixture ' . $fixture, 'comment@example.test', 'Fixture comment']);
    }
    $approvedCommentFixture = $fixtureDb->prepare("INSERT INTO comments (post_id,author,email,content,status) VALUES (?,?,?,?, 'approved')");
    for ($fixture = 1; $fixture <= 26; $fixture++) {
        $approvedCommentFixture->execute([$commentPostId, 'Approved Fixture ' . $fixture, 'approved@example.test', 'Approved public comment']);
    }
    $publicCommentsPageOne = e2e_request($baseUrl, $cookieAdmin, 'GET', 'index.php?post=http-e2e-published-article');
    e2e_check(substr_count($publicCommentsPageOne['body'], '<div class="comment">') === 25 &&
        str_contains($publicCommentsPageOne['body'], 'صفحه 1 از 2'), 'Public comments render a bounded first page of 25');
    $publicCommentsPageTwo = e2e_request($baseUrl, $cookieAdmin, 'GET', 'index.php?post=http-e2e-published-article&comment_page=2');
    e2e_check(substr_count($publicCommentsPageTwo['body'], '<div class="comment">') === 1 &&
        str_contains($publicCommentsPageTwo['body'], 'صفحه 2 از 2'), 'Public comment pagination renders only the remaining comment');
    $commentsPageOne = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/comments.php');
    e2e_check(substr_count($commentsPageOne['body'], '<tr>') - 1 === 25 && str_contains($commentsPageOne['body'], 'صفحه 1 از 2'), 'Comment moderation paginates its first 25 records');
    $commentsPageTwo = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/comments.php?page=2');
    e2e_check(substr_count($commentsPageTwo['body'], '<tr>') - 1 === 1, 'Comment moderation renders only the remaining record on page two');
    $filteredComments = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/comments.php?q=Comment+Fixture&status=pending');
    e2e_check($filteredComments['status'] === 200 && substr_count($filteredComments['body'], '<tr>') - 1 === 25, 'Comment author prefix search and pending status filter compose correctly');
    e2e_check(str_contains($filteredComments['body'], 'q=Comment+Fixture') && str_contains($filteredComments['body'], 'status=pending'), 'Comment pagination preserves active search and status filters');
    $filteredCommentsPageTwo = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/comments.php?q=Comment+Fixture&status=pending&page=2');
    e2e_check($filteredCommentsPageTwo['status'] === 200 && substr_count($filteredCommentsPageTwo['body'], '<tr>') - 1 === 1, 'Filtered comment pagination returns the final matching comment');
    $approvedComments = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/comments.php?status=approved');
    e2e_check($approvedComments['status'] === 200 && substr_count($approvedComments['body'], '<tr>') === 1, 'Comment status filter handles an empty approved queue');
    $emailComments = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/comments.php?q=comment%40example.test');
    e2e_check($emailComments['status'] === 200 && substr_count($emailComments['body'], '<tr>') - 1 === 25, 'Comment search matches author email prefixes');


    // Put a committed row into an uncheckpointed WAL. The downloaded snapshot
    // must include it even though the main .sqlite file alone does not.
    $walDb = new PDO('sqlite:' . $docRoot . '/data/mypress.sqlite');
    $walDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $walDb->exec('PRAGMA journal_mode=WAL');
    $walDb->exec('PRAGMA wal_autocheckpoint=0');
    $walDb->prepare('INSERT INTO options (opt_key,opt_value) VALUES (?,?)')
        ->execute(['backup_wal_probe','committed-in-wal']);
    $walFile = $docRoot . '/data/mypress.sqlite-wal';
    e2e_check(is_file($walFile) && filesize($walFile) > 0, 'WAL fixture contains committed data before backup');

    // Backups must be downloadable and must not disclose password hashes.
    $jsonBackup = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/backup.php?download=json');
    e2e_check($jsonBackup['status'] === 200 && str_contains($jsonBackup['headers'], 'application/json'), 'Administrator can download JSON backup');
    e2e_check(str_contains($jsonBackup['body'], 'HTTP E2E Product') && !str_contains($jsonBackup['body'], 'password_hash'), 'JSON backup contains business data but omits password hashes');
    $decodedJsonBackup = json_decode($jsonBackup['body'], true, 512, JSON_THROW_ON_ERROR);
    e2e_check(is_array($decodedJsonBackup) && isset($decodedJsonBackup['users'], $decodedJsonBackup['orders']), 'JSON backup is valid structured JSON with expected tables');
    $temporaryJsonBackups = glob($docRoot . '/data/.mypress-json-backup-*') ?: [];
    e2e_check(count($temporaryJsonBackups) === 0, 'Temporary JSON export file is removed after the download');
    $sqliteBackup = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/backup.php?download=sqlite');
    e2e_check($sqliteBackup['status'] === 200 && str_starts_with($sqliteBackup['body'], 'SQLite format 3'), 'Administrator can download a valid SQLite database backup');
    $temporaryBackups = glob($docRoot . '/data/.mypress-backup-*.sqlite') ?: [];
    e2e_check(count($temporaryBackups) === 0, 'Temporary SQLite snapshot is removed after the download');

    $restoredPath = $tempRoot . '/restored.sqlite';
    e2e_check(file_put_contents($restoredPath, $sqliteBackup['body']) === strlen($sqliteBackup['body']), 'SQLite backup bytes can be written as a restore candidate');
    $restoredDb = new PDO('sqlite:' . $restoredPath);
    $restoredDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $integrity = $restoredDb->query('PRAGMA integrity_check')->fetchColumn();
    $restoredOrder = $restoredDb->query('SELECT status FROM orders WHERE id=' . $orderId)->fetchColumn();
    $restoredStock = $restoredDb->query('SELECT stock FROM products WHERE id=2')->fetchColumn();
    e2e_check($integrity === 'ok', 'Downloaded SQLite backup passes PRAGMA integrity_check after restore');
    e2e_check($restoredOrder === 'cancelled' && (int)$restoredStock === 2, 'Restored SQLite backup preserves order and inventory state');
    $walProbe = $restoredDb->prepare('SELECT opt_value FROM options WHERE opt_key=?');
    $walProbe->execute(['backup_wal_probe']);
    e2e_check($walProbe->fetchColumn() === 'committed-in-wal', 'SQLite snapshot includes committed data that was still in the WAL');

    // Role boundary checks use a second, independent browser session.
    $userFixture = $fixtureDb->prepare("INSERT INTO users (username,password_hash,email,display_name,role) VALUES (?,?,?,?, 'author')");
    for ($fixture = 1; $fixture <= 26; $fixture++) {
        $userFixture->execute(['fixture-user-' . $fixture, password_hash('Fixture-Password-2026!', PASSWORD_DEFAULT), 'fixture-' . $fixture . '@example.test', 'Fixture User ' . $fixture]);
    }
    $usersPageOne = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/users.php');
    e2e_check(substr_count($usersPageOne['body'], '<tr>') - 1 === 25 && str_contains($usersPageOne['body'], 'صفحه 1 از 2'), 'User administration paginates its first 25 records');
    $usersPageTwo = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/users.php?page=2');
    e2e_check(substr_count($usersPageTwo['body'], '<tr>') - 1 === 2, 'User administration renders the remaining two records on page two');
    $filteredUsers = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/users.php?q=Fixture+User&role=author');
    e2e_check($filteredUsers['status'] === 200 && substr_count($filteredUsers['body'], '<tr>') - 1 === 25, 'User prefix search and author role filter return matching records');
    e2e_check(str_contains($filteredUsers['body'], 'q=Fixture+User') && str_contains($filteredUsers['body'], 'role=author'), 'User pagination preserves search and role filters');
    $filteredUsersPageTwo = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/users.php?q=Fixture+User&role=author&page=2');
    e2e_check($filteredUsersPageTwo['status'] === 200 && substr_count($filteredUsersPageTwo['body'], '<tr>') - 1 === 1, 'Filtered user pagination returns the final matching user');
    $emailSearchUsers = e2e_request($baseUrl, $cookieAdmin, 'GET', 'admin/users.php?q=fixture-26%40example.test');
    e2e_check($emailSearchUsers['status'] === 200 && substr_count($emailSearchUsers['body'], '<tr>') - 1 === 1 && str_contains($emailSearchUsers['body'], 'fixture-user-26'), 'User search matches email prefixes');


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
    $authorMedia = e2e_request($baseUrl, $cookieAuthor, 'GET', 'admin/media.php');
    e2e_check($authorMedia['status'] === 200 && !str_contains($authorMedia['body'], 'fixture-1.png'), 'Author media library remains scoped to the author own uploads');
    $authorDashboard = e2e_request($baseUrl, $cookieAuthor, 'GET', 'admin/index.php');
    e2e_check($authorDashboard['status'] === 200 && str_contains($authorDashboard['body'], 'نوشته‌های من'), 'Author sees the role-scoped dashboard');
    $authorProducts = e2e_request($baseUrl, $cookieAuthor, 'GET', 'admin/shop-products.php');
    e2e_check($authorProducts['status'] === 403, 'Author cannot access product administration');
    $authorBackup = e2e_request($baseUrl, $cookieAuthor, 'GET', 'admin/backup.php?download=sqlite');
    e2e_check($authorBackup['status'] === 403, 'Author cannot download the administrator SQLite backup');
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
    $exitCode = 1;
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    e2e_remove_tree($tempRoot);
}

if (isset($exitCode)) exit($exitCode);
