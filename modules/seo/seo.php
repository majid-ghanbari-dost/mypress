<?php
// modules/seo/seo.php — متا تایتل/توضیحات سایت و صفحه
function mp_seo_meta(string $pageTitle, string $desc = ''): string {
    if (!mp_module_enabled('seo')) return '';
    $desc = $desc !== '' ? $desc : mp_option('site_description', '');
    $kw = mp_option('seo_keywords', 'MyPress');
    return '<meta name="description" content="' . e($desc) . '">' . "\n"
        . '<meta name="keywords" content="' . e($kw) . '">' . "\n"
        . '<meta property="og:title" content="' . e($pageTitle) . '">' . "\n"
        . '<meta property="og:description" content="' . e($desc) . '">' . "\n"
        . '<meta property="og:type" content="website">' . "\n";
}
