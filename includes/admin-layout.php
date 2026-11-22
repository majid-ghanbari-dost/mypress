<?php
// admin-layout.php — سربرگ/پاصفحه مشترک پنل مدیریت (RTL فارسی)
function mp_admin_header(string $title, array $user): void {
    $lang = mp_lang(); $dir = mp_dir();
    echo '<!DOCTYPE html><html lang="' . $lang . '" dir="' . $dir . '"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' — MyPress</title>'
        . '<link rel="stylesheet" href="../assets/css/admin.css"></head><body>'
        . '<header class="topbar"><a class="brand" href="../index.php" target="_blank">MyPress</a>'
        . '<span class="site-name">' . e(mp_option('site_title', 'MyPress')) . '</span>'
        . '<span class="user">' . e($user['display_name']) . ' (' . e(mp_role_label($user['role'])) . ') — '
        . '<a href="logout.php">' . t('logout') . '</a></span></header>'
        . '<div class="layout"><nav class="sidebar">'
        . '<a href="index.php">📊 ' . t('dashboard') . '</a>'
        . '<a href="posts.php">📝 ' . t('posts') . '</a>'
        . '<a href="pages.php">📄 ' . t('pages') . '</a>'
        . '<a href="categories.php">🗂 دسته‌ها</a>'
        . '<a href="media.php">🖼 ' . t('media') . '</a>'
        . (mp_module_enabled('comments') ? '<a href="comments.php">💬 ' . t('comments') . '</a>' : '')
        . (mp_module_enabled('shop') ? '<a href="shop-products.php">🛍 ' . t('products') . '</a><a href="shop-orders.php">📦 ' . t('orders') . '</a>' : '')
        . '<a href="menus.php">🔗 ' . t('menus') . '</a>'
        . (mp_can($user, 'admin') ? '<a href="users.php">👤 ' . t('users') . '</a><a href="modules.php">🧩 ' . t('modules') . '</a><a href="settings.php">⚙️ ' . t('settings') . '</a><a href="backup.php">💾 ' . t('backup') . '</a>' : '')
        . '<a href="../index.php" target="_blank">🌐 مشاهده سایت</a>'
        . '</nav><main class="content"><h1>' . e($title) . '</h1>' . mp_flash_show();
}
function mp_admin_footer(): void { echo '</main></div></body></html>'; }
