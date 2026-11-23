<?php
// Product and coupon administration. Products referenced by orders are archived, not deleted.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/admin-layout.php';
require_once __DIR__ . '/../modules/shop/shop.php';
$user = mp_require_role('editor');
$pdo = mp_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_csrf_check();
    if (isset($_POST['delete_id'])) {
        $pdo->prepare("UPDATE products SET status='draft' WHERE id=?")->execute([(int)$_POST['delete_id']]);
        mp_flash('محصول از فروشگاه خارج شد؛ سوابق سفارش حفظ شدند.');
    } elseif (isset($_POST['coupon_code'])) {
        $code = strtoupper(trim((string)$_POST['coupon_code']));
        $percent = max(1, min(100, (int)($_POST['coupon_percent'] ?? 10)));
        if (preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM coupons WHERE code=?');
            $check->execute([$code]);
            if ((int)$check->fetchColumn() === 0) {
                $pdo->prepare('INSERT INTO coupons (code,percent,active) VALUES (?,?,1)')->execute([$code,$percent]);
                mp_flash('کوپن ساخته شد.');
            } else mp_flash('این کد تخفیف از قبل وجود دارد.', 'err');
        } else mp_flash('فرمت کد تخفیف معتبر نیست.', 'err');
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $price = max(0, (int)($_POST['price'] ?? 0));
        $stock = max(0, (int)($_POST['stock'] ?? 0));
        $description = (string)($_POST['description'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId > 0) {
            $categoryCheck = $pdo->prepare("SELECT id FROM categories WHERE id=? AND type='product'");
            $categoryCheck->execute([$categoryId]);
            if (!$categoryCheck->fetchColumn()) $categoryId = 0;
        }
        $image = '';
        if (!empty($_FILES['image']['tmp_name'])) $image = mp_upload_image($_FILES['image']) ?? '';
        if ($name !== '' && strlen($name) <= 255 && strlen($description) <= 2000000) {
            try {
                $pdo->prepare("INSERT INTO products (name,slug,description,price,stock,image,category_id,status) VALUES (?,?,?,?,?,?,?, 'published')")
                    ->execute([$name,mp_slug($name),$description,$price,$stock,$image,$categoryId ?: null]);
                mp_flash('محصول ساخته شد.');
            } catch (PDOException $exception) {
                mp_flash('ساخت محصول انجام نشد؛ ممکن است نام نشانی تکراری باشد.', 'err');
            }
        } else mp_flash('اطلاعات محصول معتبر نیست.', 'err');
    }
    mp_redirect('shop-products.php');
}

$perPage = 25;
$search = trim((string)($_GET['q'] ?? ''));
if (strlen($search) > 400) {
    http_response_code(400);
    exit('عبارت جست‌وجو بیش از حد طولانی است.');
}
$allowedStatuses = ['published', 'draft'];
$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, $allowedStatuses, true)) $status = 'all';
$allowedStockFilters = ['out', 'low', 'available'];
$stockFilter = (string)($_GET['stock'] ?? 'all');
if (!in_array($stockFilter, $allowedStockFilters, true)) $stockFilter = 'all';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.slug LIKE ?)';
    $params[] = $search . '%';
    $params[] = $search . '%';
}
if ($status !== 'all') {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if ($stockFilter === 'out') {
    $where[] = 'p.stock = 0';
} elseif ($stockFilter === 'low') {
    $where[] = 'p.stock BETWEEN 1 AND 5';
} elseif ($stockFilter === 'available') {
    $where[] = 'p.stock > 5';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$countQuery = $pdo->prepare('SELECT COUNT(*) FROM products p' . $whereSql);
$countQuery->execute($params);
$totalRows = (int)$countQuery->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min(mp_admin_page_number(), $totalPages);
$offset = ($page - 1) * $perPage;
$productQuery = $pdo->prepare('SELECT p.id,p.name,p.slug,p.price,p.stock,p.category_id,p.status,p.created_at,c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id' . $whereSql . ' ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?');
foreach ($params as $index => $value) {
    $productQuery->bindValue($index + 1, $value, PDO::PARAM_STR);
}
$productQuery->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$productQuery->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$productQuery->execute();
$rows = $productQuery->fetchAll();
$paginationQuery = [];
if ($search !== '') $paginationQuery['q'] = $search;
if ($status !== 'all') $paginationQuery['status'] = $status;
if ($stockFilter !== 'all') $paginationQuery['stock'] = $stockFilter;
$categories = $pdo->query("SELECT * FROM categories WHERE type='product' ORDER BY name")->fetchAll();
$coupons = $pdo->query('SELECT * FROM coupons ORDER BY code')->fetchAll();
mp_admin_header(t('products'), $user);
?>
<form method="get" action="shop-products.php" style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0 20px">
<label>جست‌وجوی محصول <input type="search" name="q" maxlength="100" value="<?= e($search) ?>" placeholder="نام یا اسلاگ محصول"></label>
<label>وضعیت <select name="status" style="width:auto">
<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>همه وضعیت‌ها</option>
<option value="published" <?= $status === 'published' ? 'selected' : '' ?>>فعال</option>
<option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>بایگانی</option>
</select></label>
<label>موجودی <select name="stock" style="width:auto">
<option value="all" <?= $stockFilter === 'all' ? 'selected' : '' ?>>همه موجودی‌ها</option>
<option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>ناموجود</option>
<option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>کم (۱ تا ۵)</option>
<option value="available" <?= $stockFilter === 'available' ? 'selected' : '' ?>>کافی (بیش از ۵)</option>
</select></label>
<button class="btn btn-small" type="submit">اعمال فیلتر</button>
<a href="shop-products.php">پاک‌کردن</a>
</form>
<?php mp_admin_pagination($page, $totalPages, 'shop-products.php', $paginationQuery); ?>
<form method="post" enctype="multipart/form-data"><?= mp_csrf_field() ?>
<label>نام محصول <input name="name" required maxlength="255"></label>
<label>قیمت (تومان) <input type="number" name="price" min="0" value="0" dir="ltr"></label>
<label>موجودی <input type="number" name="stock" min="0" value="0" dir="ltr"></label>
<label>دسته <select name="category_id"><option value="0">—</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
<label>توضیحات <textarea name="description" rows="3"></textarea></label>
<label>عکس محصول <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
<button class="btn" type="submit">افزودن محصول</button></form>
<table class="table"><tr><th>نام</th><th>قیمت</th><th>موجودی</th><th>دسته</th><th>وضعیت</th><th>عملیات</th></tr>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= mp_price((int)$row['price']) ?></td><td><?= (int)$row['stock'] ?></td><td><?= e($row['cat_name'] ?? '') ?></td><td><?= $row['status']==='published'?'فعال':'بایگانی' ?></td>
<td><?php if ($row['status']==='published'): ?><a href="../index.php?product=<?= rawurlencode((string)$row['slug']) ?>" target="_blank" rel="noopener">مشاهده</a>
<form method="post" style="display:inline" data-confirm="محصول از فروشگاه خارج شود؟"><?= mp_csrf_field() ?><input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>"><button class="btn btn-danger btn-small" type="submit">بایگانی</button></form><?php endif; ?></td></tr><?php endforeach; ?></table>
<?php mp_admin_pagination($page, $totalPages, 'shop-products.php', $paginationQuery); ?>
<h2>کدهای تخفیف</h2>
<form method="post"><?= mp_csrf_field() ?>
<label>کد <input name="coupon_code" dir="ltr" required></label>
<label>درصد تخفیف <input type="number" name="coupon_percent" min="1" max="100" value="10" dir="ltr"></label>
<button class="btn" type="submit">ساخت کوپن</button></form>
<ul><?php foreach ($coupons as $coupon): ?><li><code><?= e($coupon['code']) ?></code> — <?= (int)$coupon['percent'] ?>٪</li><?php endforeach; ?></ul>
<script src="../assets/js/admin.js"></script>
<?php mp_admin_footer(); ?>
