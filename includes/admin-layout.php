<?php
// Shared administration shell with navigation filtered by the current user's role.
function mp_admin_header(string $title, array $user): void {
    $lang = mp_lang();
    $dir = mp_dir();
    $canEdit = mp_can($user, 'editor');
    $isAdmin = mp_can($user, 'admin');
    echo '<!DOCTYPE html><html lang="' . e($lang) . '" dir="' . e($dir) . '"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' — MyPress</title>'
        . '<link rel="stylesheet" href="../assets/css/admin.css"></head><body>'
        . '<header class="topbar"><a class="brand" href="../index.php" target="_blank" rel="noopener">MyPress</a>'
        . '<span class="site-name">' . e(mp_option('site_title', 'MyPress')) . '</span>'
        . '<div class="user">' . e($user['display_name']) . ' (' . e(mp_role_label($user['role'])) . ') — '
        . '<form method="post" action="logout.php" style="display:inline">' . mp_csrf_field()
        . '<button type="submit" style="background:none;border:0;color:inherit;text-decoration:underline;cursor:pointer">' . e(t('logout')) . '</button></form></div></header>'
        . '<div class="layout"><nav class="sidebar">'
        . '<a href="index.php">📊 ' . e(t('dashboard')) . '</a>'
        . '<a href="posts.php">📝 ' . e(t('posts')) . '</a>'
        . ($canEdit ? '<a href="pages.php">📄 ' . e(t('pages')) . '</a><a href="categories.php">🗂 دسته‌ها</a>' : '')
        . '<a href="media.php">🖼 ' . e(t('media')) . '</a>'
        . ($canEdit && mp_module_enabled('comments') ? '<a href="comments.php">💬 ' . e(t('comments')) . '</a>' : '')
        . ($canEdit && mp_module_enabled('shop') ? '<a href="shop-products.php">🛍 ' . e(t('products')) . '</a><a href="shop-orders.php">📦 ' . e(t('orders')) . '</a>' : '')
        . ($canEdit ? '<a href="menus.php">🔗 ' . e(t('menus')) . '</a>' : '')
        . ($isAdmin ? '<a href="users.php">👤 ' . e(t('users')) . '</a><a href="modules.php">🧩 ' . e(t('modules')) . '</a><a href="settings.php">⚙️ ' . e(t('settings')) . '</a><a href="backup.php">💾 ' . e(t('backup')) . '</a>' : '')
        . '<a href="../index.php" target="_blank" rel="noopener">🌐 مشاهده سایت</a>'
        . '</nav><main class="content"><h1>' . e($title) . '</h1>' . mp_flash_show();
}
function mp_admin_footer(): void { echo '</main></div></body></html>'; }
