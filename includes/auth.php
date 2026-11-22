<?php
// auth.php — ورود، نشست، نقش‌ها (مدیر / ویرایشگر / نویسنده)

function mp_current_user(): ?array {
    mp_session_start();
    if (empty($_SESSION['user_id'])) return null;
    $st = mp_pdo()->prepare("SELECT id,username,email,display_name,role,created_at FROM users WHERE id=?");
    $st->execute([$_SESSION['user_id']]);
    return $st->fetch() ?: null;
}
function mp_login(string $username, string $password): bool {
    $st = mp_pdo()->prepare("SELECT * FROM users WHERE username=?");
    $st->execute([$username]);
    $u = $st->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) return false;
    mp_session_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$u['id'];
    return true;
}
function mp_logout(): void {
    mp_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
function mp_require_login(): array {
    $u = mp_current_user();
    if (!$u) mp_redirect('login.php');
    return $u;
}
// سلسله‌مراتب نقش: admin > editor > author
function mp_can(array $user, string $role): bool {
    $rank = ['author' => 1, 'editor' => 2, 'admin' => 3];
    return ($rank[$user['role']] ?? 0) >= ($rank[$role] ?? 99);
}
function mp_require_role(string $role): array {
    $u = mp_require_login();
    if (!mp_can($u, $role)) { http_response_code(403); exit('دسترسی ندارید.'); }
    return $u;
}
function mp_role_label(string $role): string {
    return ['admin' => 'مدیر', 'editor' => 'ویرایشگر', 'author' => 'نویسنده'][$role] ?? $role;
}
