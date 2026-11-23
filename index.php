<?php
// Public front controller.
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/router.php';
require_once __DIR__ . '/modules/shop/shop.php';
require_once __DIR__ . '/modules/seo/seo.php';

$route = mp_route();
$shopRoutes = ['product', 'shop', 'cart', 'checkout'];
if (!mp_module_enabled('shop') && in_array($route['type'], $shopRoutes, true)) {
    http_response_code(404);
    $route = ['type' => 'not_found', 'slug' => ''];
}

$siteTitle = mp_option('site_title', 'MyPress');
$siteDesc = mp_option('site_description', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_action']) && mp_module_enabled('shop')) {
    mp_csrf_check();
    if ($_POST['cart_action'] === 'add') {
        mp_cart_add((int)($_POST['product_id'] ?? 0), max(1, min(99, (int)($_POST['qty'] ?? 1))));
        mp_redirect('index.php?cart=1');
    }
    if ($_POST['cart_action'] === 'clear') {
        $_SESSION['cart'] = [];
        mp_redirect('index.php?cart=1');
    }
    if ($_POST['cart_action'] === 'checkout') {
        $orderId = mp_checkout($_POST);
        if ($orderId > 0) mp_redirect('index.php?checkout=done&order=' . $orderId);
        mp_flash('ثبت سفارش انجام نشد؛ موجودی، اطلاعات تماس یا محدودیت تعداد سفارش را بررسی کنید.', 'err');
        mp_redirect('index.php?checkout=1');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_submit']) && mp_module_enabled('comments')) {
    require_once __DIR__ . '/modules/comments/comments.php';
    mp_csrf_check();
    $postId = (int)($_POST['post_id'] ?? 0);
    $ok = mp_add_comment($postId, trim((string)($_POST['author'] ?? '')),
        trim((string)($_POST['email'] ?? '')), trim((string)($_POST['content'] ?? '')));
    mp_flash($ok ? 'نظر شما ثبت شد و پس از تایید مدیر نمایش داده می‌شود.' : 'اطلاعات نظر ناقص است یا محدودیت ارسال اعمال شده است.', $ok ? 'ok' : 'err');
    $slug = (string)($_POST['slug'] ?? '');
    $type = ($_POST['ptype'] ?? 'post') === 'page' ? 'page' : 'post';
    mp_redirect('index.php?' . $type . '=' . rawurlencode($slug));
}

$pageTitle = $siteTitle;
$post = null;
$product = null;
$posts = [];
if ($route['type'] === 'post' || $route['type'] === 'page') {
    $post = mp_get_post($route['slug'], $route['type']);
    if (!$post) { http_response_code(404); $pageTitle = 'یافت نشد'; }
    else $pageTitle = $post['title'] . ' — ' . $siteTitle;
} elseif ($route['type'] === 'product') {
    $product = mp_get_product($route['slug']);
    if (!$product) { http_response_code(404); $pageTitle = 'یافت نشد'; }
    else $pageTitle = $product['name'] . ' — ' . $siteTitle;
} elseif ($route['type'] === 'shop') $pageTitle = 'فروشگاه — ' . $siteTitle;
elseif ($route['type'] === 'cart') $pageTitle = 'سبد خرید — ' . $siteTitle;
elseif ($route['type'] === 'checkout') $pageTitle = 'تسویه — ' . $siteTitle;
elseif ($route['type'] === 'search') { $pageTitle = 'جستجو: ' . $route['slug']; $posts = mp_list_posts(20, $route['slug']); }
elseif ($route['type'] === 'not_found') $pageTitle = 'یافت نشد';
else $posts = mp_list_posts(10);

include __DIR__ . '/themes/default/header.php';
if ($route['type'] === 'post' || $route['type'] === 'page') include __DIR__ . '/themes/default/single.php';
elseif ($route['type'] === 'product') include __DIR__ . '/themes/default/product.php';
elseif ($route['type'] === 'shop') include __DIR__ . '/themes/default/shop.php';
elseif ($route['type'] === 'cart' || $route['type'] === 'checkout') include __DIR__ . '/themes/default/cart.php';
elseif ($route['type'] === 'not_found') echo '<h2>یافت نشد</h2><p>این بخش در دسترس نیست.</p>';
else include __DIR__ . '/themes/default/home.php';
include __DIR__ . '/themes/default/footer.php';
