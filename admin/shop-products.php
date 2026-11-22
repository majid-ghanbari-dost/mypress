<?php
// admin/shop-products.php — فهرست و ساخت محصول + کوپن
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
require_once __DIR__ . '/../modules/shop/shop.php';
$user = mp_require_role('editor');
$pdo = mp_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_csrf_check();
    if (isset($_POST['delete_id'])) {
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([(int)$_POST['delete_id']]);
        mp_flash('محصول حذف شد.');
    } elseif (isset($_POST['coupon_code'])) {
        $code = strtoupper(trim($_POST['coupon_code']));
        $chk = $pdo->prepare("SELECT COUNT(*) FROM coupons WHERE code=?"); $chk->execute([$code]);
        if ((int)$chk->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO coupons (code,percent,active) VALUES (?,?,1)")
                ->execute([$code, max(0, min(100, (int)$_POST['coupon_percent']))]);
        }
        mp_flash('کوپن ساخته شد.');
    } else {
        $name = trim($_POST['name'] ?? ''); $price = max(0, (int)($_POST['price'] ?? 0));
        $stock = max(0, (int)($_POST['stock'] ?? 0)); $desc = $_POST['description'] ?? '';
        $cat = (int)($_POST['category_id'] ?? 0) ?: null;
        $image = '';
        if (!empty($_FILES['image']['tmp_name'])) $image = mp_upload_image($_FILES['image']) ?? '';
        if ($name !== '') {
            $pdo->prepare("INSERT INTO products (name,slug,description,price,stock,image,category_id,status) VALUES (?,?,?,?,?,?,?, 'published')")
                ->execute([$name, mp_slug($name), $desc, $price, $stock, $image, $cat]);
            mp_flash('محصول ساخته شد.');
        }
    }
    mp_redirect('shop-products.php');
}
$rows = $pdo->query("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.created_at DESC")->fetchAll();
$cats = $pdo->query("SELECT * FROM categories WHERE type='product' ORDER BY name")->fetchAll();
$coupons = $pdo->query("SELECT * FROM coupons ORDER BY code")->fetchAll();
mp_admin_header(t('products'), $user);
?>
<form method="post" enctype="multipart/form-data"><?= mp_csrf_field() ?>
<label>نام محصول <input name="name" required></label>
<label>قیمت (تومان) <input type="number" name="price" min="0" value="0" dir="ltr"></label>
<label>موجودی <input type="number" name="stock" min="0" value="0" dir="ltr"></label>
<label>دسته <select name="category_id"><option value="0">—</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
<label>توضیحات <textarea name="description" rows="3"></textarea></label>
<label>عکس محصول <input type="file" name="image" accept="image/*"></label>
<button class="btn" type="submit">افزودن محصول</button></form>
<table class="table"><tr><th>نام</th><th>قیمت</th><th>موجودی</th><th>دسته</th><th>عملیات</th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= mp_price((int)$r['price']) ?></td><td><?= (int)$r['stock'] ?></td><td><?= e($r['cat_name'] ?? '') ?></td>
<td><a href="../index.php?product=<?= e($r['slug']) ?>" target="_blank">مشاهده</a>
<form method="post" style="display:inline" data-confirm="حذف شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-small" type="submit">حذف</button></form></td></tr><?php endforeach; ?></table>
<h2>کدهای تخفیف</h2>
<form method="post"><?= mp_csrf_field() ?>
<label>کد <input name="coupon_code" dir="ltr" required></label>
<label>درصد تخفیف <input type="number" name="coupon_percent" min="1" max="100" value="10" dir="ltr"></label>
<button class="btn" type="submit">ساخت کوپن</button></form>
<ul><?php foreach ($coupons as $c): ?><li><code><?= e($c['code']) ?></code> — <?= (int)$c['percent'] ?>٪</li><?php endforeach; ?></ul>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
