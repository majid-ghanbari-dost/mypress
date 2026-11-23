<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    $checks++;
}

check(e('<script>') === '&lt;script&gt;', 'HTML output is escaped');
check(mp_slug('سلام دنیا') === 'سلام-دنیا', 'Persian slug is preserved');
check(mp_slug('') !== '', 'Empty slug receives a safe fallback');

$author = ['id' => 7, 'role' => 'author'];
$editor = ['id' => 8, 'role' => 'editor'];
$ownDraft = ['author_id' => 7, 'status' => 'draft'];
$otherDraft = ['author_id' => 9, 'status' => 'draft'];
$ownPublished = ['author_id' => 7, 'status' => 'published'];
check(mp_can_edit_post($author, $ownDraft), 'Author can edit own draft');
check(!mp_can_edit_post($author, $otherDraft), 'Author cannot edit another author draft');
check(!mp_can_edit_post($author, $ownPublished), 'Author cannot edit published content');
check(mp_can_edit_post($editor, $otherDraft), 'Editor can edit other authors content');

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "pdo_sqlite is required for integration smoke tests.\n");
    exit(1);
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
mp_create_tables($pdo);
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
check(in_array('login_attempts', $tables, true), 'Login throttling schema exists');
check(in_array('orders', $tables, true), 'Shop order schema exists');
check(in_array('order_items', $tables, true), 'Shop order item schema exists');

$pdo->exec("INSERT INTO products (name,slug,price,stock,status) VALUES ('Test','test-product',100,1,'published')");
$stock = $pdo->prepare("UPDATE products SET stock=stock-1 WHERE id=1 AND stock>=1");
$stock->execute();
check($stock->rowCount() === 1, 'Conditional stock decrement succeeds once');
$stock->execute();
check($stock->rowCount() === 0, 'Conditional stock decrement prevents overselling');

echo "PASS: {$checks} checks\n";
