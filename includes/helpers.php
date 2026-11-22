<?php
// helpers.php — توابع کمکی: escape، CSRF، فلش، اسلاگ، زبان

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function mp_redirect(string $url): void { header('Location: ' . $url); exit; }

function mp_slug(string $text): string {
    $text = trim($text);
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item-' . time();
}

// نشست امن
function mp_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'use_strict_mode' => true]);
    }
}

// CSRF
function mp_csrf_token(): string {
    mp_session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function mp_csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(mp_csrf_token()) . '">'; }
function mp_csrf_check(): void {
    mp_session_start();
    $t = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)$t)) {
        http_response_code(403); exit('CSRF token invalid.');
    }
}

// پیام فلش یک‌بارمصرف
function mp_flash(string $msg, string $type = 'ok'): void { mp_session_start(); $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; }
function mp_flash_show(): string {
    mp_session_start();
    if (empty($_SESSION['flash'])) return '';
    $f = $_SESSION['flash']; unset($_SESSION['flash']);
    return '<div class="flash flash-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
}

// زبان: 'fa' پیش‌فرض، 'en' با ?lang=en یا تنظیمات
function mp_lang(): string {
    $l = $_GET['lang'] ?? ($_SESSION['lang'] ?? null);
    if (in_array($l, ['fa','en'], true)) { $_SESSION['lang'] = $l; return $l; }
    return mp_option('site_lang', 'fa') ?: 'fa';
}
function mp_is_rtl(): bool { return mp_lang() === 'fa'; }
function mp_dir(): string { return mp_is_rtl() ? 'rtl' : 'ltr'; }

// ترجمه‌های کوتاه رابط مدیریت و سایت
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
    $d = mp_lang() === 'en' ? $en : $fa;
    return $d[$key] ?? $key;
}

// قیمت با تومان (عدد صحیح ریال/تومان؛ نمایش با جداکننده هزارگان)
function mp_price(int $n): string { return number_format($n) . ' تومان'; }

// آپلود امن تصویر: فقط jpg/png/gif/webp، حداکثر 2MB
function mp_upload_image(array $file): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 2 * 1024 * 1024) return null;
    $info = getimagesize($file['tmp_name']);
    if ($info === false) return null;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$info['mime']])) return null;
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$info['mime']];
    $dest = __DIR__ . '/../uploads/' . $name;
    return move_uploaded_file($file['tmp_name'], $dest) ? $name : null;
}
