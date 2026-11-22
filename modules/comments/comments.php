<?php
// modules/comments/comments.php — ثبت و نمایش نظرات تاییدشده
function mp_approved_comments(int $postId): array {
    $st = mp_pdo()->prepare("SELECT * FROM comments WHERE post_id=? AND status='approved' ORDER BY created_at ASC");
    $st->execute([$postId]);
    return $st->fetchAll();
}
function mp_add_comment(int $postId, string $author, string $email, string $content): bool {
    if ($author === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $content === '') return false;
    $st = mp_pdo()->prepare("INSERT INTO comments (post_id,author,email,content,status) VALUES (?,?,?,?,'pending')");
    return $st->execute([$postId, $author, $email, $content]);
}
function mp_pending_comments_count(): int {
    return (int)mp_pdo()->query("SELECT COUNT(*) FROM comments WHERE status='pending'")->fetchColumn();
}
