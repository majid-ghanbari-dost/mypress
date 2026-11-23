<?php
// MyPress core helpers — output escaping, sessions, CSRF, uploads and localization.
declare(strict_types=1);

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mp_redirect(string $url): void {
    header('Location: ' . $url, true, 303);
    exit;
}

function mp_slug(string $text): string {
    $text = trim($text);
    $text = preg_replace('/[^\\p{L}\\p{N}]+/u', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item-' . bin2hex(random_bytes(4));
}

function mp_session_start(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    // Set cookie policy before PHP creates the session cookie.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function mp_csrf_token(): string {
    mp_session_start();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function mp_csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(mp_csrf_token()) . '">';
}

function mp_csrf_check(): void {
    mp_session_start();
    $provided = $_POST['csrf'] ?? '';
    $expected = $_SESSION['csrf'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_string($provided) ||
        !is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        exit('درخواست معتبر نیست. صفحه را تازه‌سازی و دوباره تلاش کنید.');
    }
}

function mp_flash(string $msg, string $type = 'ok'): void {
    mp_session_start();
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type === 'err' ? 'err' : 'ok'];
}

function mp_flash_show(): string {
    mp_session_start();
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) return '';
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="flash flash-' . e((string)($flash['type'] ?? 'ok')) . '">' .
        e((string)($flash['msg'] ?? '')) . '</div>';
}

function mp_lang(): string {
    mp_session_start();
    $language = $_GET['lang'] ?? ($_SESSION['lang'] ?? null);
    if (in_array($language, ['fa', 'en'], true)) {
        $_SESSION['lang'] = $language;
        return $language;
    }
    $configured = mp_option('site_lang', 'fa');
    return in_array($configured, ['fa', 'en'], true) ? $configured : 'fa';
}
function mp_is_rtl(): bool { return mp_lang() === 'fa'; }
function mp_dir(): string { return mp_is_rtl() ? 'rtl' : 'ltr'; }

function t(string $key): string {
    $fa = [
        'dashboard'=>'داشبورد','posts'=>'نوشته‌ها','pages'=>'برگه‌ها','media'=>'رسانه','comments'=>'نظرات',
        'users'=>'کاربران','menus'=>'منوها','settings'=>'تنظیمات','modules'=>'ماژول‌ها','shop'=>'فروشگاه',
        'orders'=>'سفارش‌ها','products'=>'محصولات','backup'=>'پشتیبان‌گیری','logout'=>'خروج','login'=>'ورود',
        'site_title'=>'عنوان سایت','save'=>'ذخیره','add'=>'افزودن','edit'=>'ویرایش','delete'=>'حذف',
        'search'=>'جستجو','home'=>'خانه','cart'=>'سبد خرید','checkout'=>'تسویه','welcome'=>'خوش آمدید',
    ];
    $en = [
        'dashboard'=>'Dashboard','posts'=>'Posts','pages'=>'Pages','media'=>'Media','comments'=>'Comments',
        'users'=>'Users','menus'=>'Menus','settings'=>'Settings','modules'=>'Modules','shop'=>'Shop',
        'orders'=>'Orders','products'=>'Products','backup'=>'Backup','logout'=>'Logout','login'=>'Login',
        'site_title'=>'Site title','save'=>'Save','add'=>'Add','edit'=>'Edit','delete'=>'Delete',
        'search'=>'Search','home'=>'Home','cart'=>'Cart','checkout'=>'Checkout','welcome'=>'Welcome',
    ];
    $dictionary = mp_lang() === 'en' ? $en : $fa;
    return $dictionary[$key] ?? $key;
}

function mp_safe_menu_url(string $url): string {
    $url = trim($url);
    if ($url === '' || preg_match('/[\\x00-\\x20\\\\]/', $url) || str_starts_with($url, '//')) return '#';
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) ||
            !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) return '#';
    }
    return $url;
}

function mp_price(int $amount): string {
    return number_format($amount) . ' تومان';
}

/**
 * Store only verified raster images. The generated name is unrelated to the
 * client filename; MIME is detected from bytes, not the browser-supplied header.
 */
function mp_upload_image(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
        empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) return null;

    $tmp = (string)$file['tmp_name'];
    $actualSize = filesize($tmp);
    if ($actualSize === false || $actualSize < 1 || $actualSize > 2 * 1024 * 1024) return null;

    $image = @getimagesize($tmp);
    if ($image === false || empty($image['mime'])) return null;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $mime = (string)$image['mime'];
    if (!isset($allowed[$mime])) return null;
    if (($image[0] ?? 0) < 1 || ($image[1] ?? 0) < 1 ||
        $image[0] > 8000 || $image[1] > 8000) return null;

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if ($finfo->file($tmp) !== $mime) return null;
    }

    $directory = dirname(__DIR__) . '/uploads';
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) return null;
    if (!is_writable($directory)) return null;

    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $destination = $directory . '/' . $name;
    return move_uploaded_file($tmp, $destination) ? $name : null;
}
