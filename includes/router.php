<?php
// router.php — مسیریابی ساده سایت عمومی بر اساس query string
// ?post=slug | ?page=slug | ?product=slug | ?shop=1 | ?cart | ?checkout | ?s=query | خانه

function mp_route(): array {
    if (!empty($_GET['post']))    return ['type' => 'post',    'slug' => (string)$_GET['post']];
    if (!empty($_GET['page']))    return ['type' => 'page',    'slug' => (string)$_GET['page']];
    if (!empty($_GET['product'])) return ['type' => 'product', 'slug' => (string)$_GET['product']];
    if (isset($_GET['shop']))     return ['type' => 'shop',    'slug' => ''];
    if (isset($_GET['cart']))     return ['type' => 'cart',    'slug' => ''];
    if (isset($_GET['checkout'])) return ['type' => 'checkout','slug' => ''];
    if (!empty($_GET['s']))       return ['type' => 'search',  'slug' => (string)$_GET['s']];
    return ['type' => 'home', 'slug' => ''];
}

function mp_get_post(string $slug, string $type = 'post'): ?array {
    $st = mp_pdo()->prepare("SELECT p.*, u.display_name AS author_name, c.name AS category_name
        FROM posts p LEFT JOIN users u ON u.id=p.author_id LEFT JOIN categories c ON c.id=p.category_id
        WHERE p.slug=? AND p.type=? AND p.status='published'");
    $st->execute([$slug, $type]);
    return $st->fetch() ?: null;
}
function mp_list_posts(int $limit = 10, string $search = ''): array {
    $limit = max(1, min(100, $limit));
    if ($search !== '') {
        $st = mp_pdo()->prepare("SELECT p.id, p.title, p.slug, p.excerpt, p.created_at, u.display_name AS author_name FROM posts p
            LEFT JOIN users u ON u.id=p.author_id
            WHERE p.type='post' AND p.status='published' AND (p.title LIKE ? OR p.content LIKE ?)
            ORDER BY p.created_at DESC LIMIT ?");
        $q = '%' . $search . '%';
        $st->execute([$q, $q, $limit]);
    } else {
        $st = mp_pdo()->prepare("SELECT p.*, u.display_name AS author_name FROM posts p
            LEFT JOIN users u ON u.id=p.author_id
            WHERE p.type='post' AND p.status='published' ORDER BY p.created_at DESC LIMIT ?");
        $st->execute([$limit]);
    }
    return $st->fetchAll();
}
