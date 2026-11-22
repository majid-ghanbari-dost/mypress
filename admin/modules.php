<?php
// admin/modules.php — فعال/غیرفعال کردن ماژول‌ها (فقط مدیر)
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
$user = mp_require_role('admin');
$mods = ['shop' => 'فروشگاه', 'comments' => 'نظرات', 'seo' => 'سئو'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_csrf_check();
    foreach ($mods as $key => $label) {
        mp_set_option('module_' . $key, isset($_POST['module_' . $key]) ? '1' : '0');
    }
    mp_flash('ماژول‌ها به‌روزرسانی شد.'); mp_redirect('modules.php');
}
mp_admin_header(t('modules'), $user);
?>
<form method="post"><?= mp_csrf_field() ?>
<?php foreach ($mods as $key => $label):
    $man = json_decode(file_get_contents(__DIR__ . '/../modules/' . $key . '/manifest.json'), true);
?>
<label><input type="checkbox" name="module_<?= e($key) ?>" value="1" <?= mp_module_enabled($key) ? 'checked' : '' ?> style="width:auto"> <b><?= e($label) ?></b> — <?= e($man['description_fa'] ?? '') ?> <small>(v<?= e($man['version'] ?? '') ?>)</small></label>
<?php endforeach; ?>
<button class="btn" type="submit">💾 ذخیره ماژول‌ها</button></form>
<p><small>افزودن ماژول جدید: یک پوشه در <code>modules/</code> با <code>manifest.json</code> بسازید و توابع آن را در <code>index.php</code> یا پنل فراخوانی کنید.</small></p>
<?php mp_admin_footer(); ?>
