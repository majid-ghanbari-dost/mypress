<?php
// Public comments are limited to existing published posts and bounded input sizes.
function mp_approved_comments(int $postId): array {
    $statement = mp_pdo()->prepare("SELECT * FROM comments WHERE post_id=? AND status='approved' ORDER BY created_at ASC");
    $statement->execute([$postId]);
    return $statement->fetchAll();
}

function mp_add_comment(int $postId, string $author, string $email, string $content): bool {
    $author = trim($author);
    $email = trim($email);
    $content = trim($content);
    if ($postId < 1 || $author === '' || strlen($author) > 120 ||
        strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $content === '' || strlen($content) > 10000) return false;

    $post = mp_pdo()->prepare("SELECT id FROM posts WHERE id=? AND type='post' AND status='published'");
    $post->execute([$postId]);
    if (!$post->fetchColumn()) return false;

    $statement = mp_pdo()->prepare(
        "INSERT INTO comments (post_id,author,email,content,status) VALUES (?,?,?,?,'pending')"
    );
    return $statement->execute([$postId, $author, $email, $content]);
}

function mp_pending_comments_count(): int {
    return (int)mp_pdo()->query("SELECT COUNT(*) FROM comments WHERE status='pending'")->fetchColumn();
}
