<?php
// admin/settings.php — تنظیمات سایت و سئو (فقط مدیر)
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_csrf_check();
    foreach (['site_title','site_description','site_lang','seo_keywords'] as $k) {
        if (isset($_POST[$k])) mp_set_option($k, trim((string)$_POST[$k]));
    }
    mp_flash('تنظیمات ذخیره شد.'); mp_redirect('settings.php');
}
mp_admin_header(t('settings'), $user);
?>
<form method="post"><?= mp_csrf_field() ?>
<label><?= t('site_title') ?> <input name="site_title" value="<?= e(mp_option('site_title','MyPress')) ?>"></label>
<label>توضیحات سایت <input name="site_description" value="<?= e(mp_option('site_description','')) ?>"></label>
<label>زبان پیش‌فرض <select name="site_lang"><option value="fa" <?= mp_option('site_lang','fa')==='fa'?'selected':'' ?>>فارسی (RTL)</option><option value="en" <?= mp_option('site_lang','fa')==='en'?'selected':'' ?>>English (LTR)</option></select></label>
<label>کلمات کلیدی سئو <input name="seo_keywords" dir="ltr" value="<?= e(mp_option('seo_keywords','MyPress')) ?>"></label>
<p><small>ماژول‌های فروشگاه / نظرات / سئو از صفحه «ماژول‌ها» فعال یا غیرفعال می‌شوند.</small></p>
<button class="btn" type="submit">💾 <?= t('save') ?></button></form>
<?php mp_admin_footer(); ?>
