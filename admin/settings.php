<?php
// Site settings with strict language allow-list and bounded values.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_csrf_check();
    $title = trim((string)($_POST['site_title'] ?? ''));
    $description = trim((string)($_POST['site_description'] ?? ''));
    $language = (string)($_POST['site_lang'] ?? 'fa');
    $keywords = trim((string)($_POST['seo_keywords'] ?? ''));
    if ($title === '' || strlen($title) > 150 || strlen($description) > 500 ||
        strlen($keywords) > 500 || !in_array($language, ['fa','en'], true)) {
        mp_flash('مقادیر تنظیمات معتبر نیستند.', 'err');
    } else {
        mp_set_option('site_title', $title);
        mp_set_option('site_description', $description);
        mp_set_option('site_lang', $language);
        mp_set_option('seo_keywords', $keywords);
        mp_flash('تنظیمات ذخیره شد.');
    }
    mp_redirect('settings.php');
}
mp_admin_header(t('settings'), $user);
?>
<form method="post"><?= mp_csrf_field() ?>
<label><?= t('site_title') ?> <input name="site_title" maxlength="150" required value="<?= e(mp_option('site_title','MyPress')) ?>"></label>
<label>توضیحات سایت <input name="site_description" maxlength="500" value="<?= e(mp_option('site_description','')) ?>"></label>
<label>زبان پیش‌فرض <select name="site_lang"><option value="fa" <?= mp_option('site_lang','fa')==='fa'?'selected':'' ?>>فارسی (RTL)</option><option value="en" <?= mp_option('site_lang','fa')==='en'?'selected':'' ?>>English (LTR)</option></select></label>
<label>کلمات کلیدی سئو <input name="seo_keywords" maxlength="500" dir="ltr" value="<?= e(mp_option('seo_keywords','MyPress')) ?>"></label>
<p><small>ماژول‌های فروشگاه / نظرات / سئو از صفحه «ماژول‌ها» فعال یا غیرفعال می‌شوند.</small></p>
<button class="btn" type="submit">💾 <?= t('save') ?></button></form>
<?php mp_admin_footer(); ?>
