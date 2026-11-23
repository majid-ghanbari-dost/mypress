<?php
// Public comments are limited to existing published posts and bounded input sizes.
function mp_approved_comments_count(int $postId): int {
    $statement = mp_pdo()->prepare("SELECT COUNT(*) FROM comments WHERE post_id=? AND status='approved'");
    $statement->execute([$postId]);
    return (int)$statement->fetchColumn();
}

function mp_approved_comments(int $postId, int $limit = 25, int $offset = 0): array {
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $statement = mp_pdo()->prepare(
        "SELECT id, post_id, author, content, status, created_at
         FROM comments
         WHERE post_id=? AND status='approved'
         ORDER BY created_at ASC, id ASC
         LIMIT ? OFFSET ?"
    );
    $statement->bindValue(1, $postId, PDO::PARAM_INT);
    $statement->bindValue(2, $limit, PDO::PARAM_INT);
    $statement->bindValue(3, $offset, PDO::PARAM_INT);
    $statement->execute();
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

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!mp_public_rate_limit('comment-ip', $ip, 5, 900) ||
        !mp_public_rate_limit('comment-email', strtolower($email), 10, 86400)) {
        return false;
    }

    $statement = mp_pdo()->prepare(
        "INSERT INTO comments (post_id,author,email,content,status) VALUES (?,?,?,?,'pending')"
    );
    return $statement->execute([$postId, $author, $email, $content]);
}

function mp_pending_comments_count(): int {
    return (int)mp_pdo()->query("SELECT COUNT(*) FROM comments WHERE status='pending'")->fetchColumn();
}
