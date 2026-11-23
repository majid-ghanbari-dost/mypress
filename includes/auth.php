<?php
// Authentication, throttling and role/ownership authorization.
declare(strict_types=1);

function mp_current_user(): ?array {
    mp_session_start();
    if (empty($_SESSION['user_id'])) return null;
    $statement = mp_pdo()->prepare('SELECT id,username,email,display_name,role,created_at FROM users WHERE id=?');
    $statement->execute([(int)$_SESSION['user_id']]);
    $user = $statement->fetch();
    if (!$user) unset($_SESSION['user_id']);
    return $user ?: null;
}

function mp_login(string $username, string $password, ?PDO $connection = null): bool {
    $username = trim($username);
    if ($username === '' || strlen($username) > 190 || strlen($password) > 4096) return false;
    $pdo = $connection ?? mp_pdo();
    $secret = (string)(mp_config()['secret_key'] ?? '');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ipKey = hash_hmac('sha256', $ip, $secret !== '' ? $secret : __FILE__);
    $userKey = hash('sha256', strtolower($username));
    $cutoff = date('Y-m-d H:i:s', time() - 900);

    $byIp = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_key=? AND attempted_at>=?');
    $byIp->execute([$ipKey, $cutoff]);
    if ((int)$byIp->fetchColumn() >= 30) return false;
    $byUser = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE username_key=? AND ip_key=? AND attempted_at>=?');
    $byUser->execute([$userKey, $ipKey, $cutoff]);
    if ((int)$byUser->fetchColumn() >= 10) return false;

    $statement = $pdo->prepare('SELECT id,username,password_hash FROM users WHERE username=?');
    $statement->execute([$username]);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        $pdo->prepare('INSERT INTO login_attempts (username_key,ip_key,attempted_at) VALUES (?,?,?)')
            ->execute([$userKey, $ipKey, date('Y-m-d H:i:s')]);
        $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')
            ->execute([date('Y-m-d H:i:s', time() - 86400)]);
        return false;
    }

    if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);
    }
    $pdo->prepare('DELETE FROM login_attempts WHERE username_key=? AND ip_key=?')
        ->execute([$userKey, $ipKey]);

    mp_session_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return true;
}

function mp_logout(): void {
    mp_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool)$params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

function mp_require_login(): array {
    $user = mp_current_user();
    if (!$user) mp_redirect('login.php');
    return $user;
}

function mp_can(array $user, string $role): bool {
    $rank = ['author' => 1, 'editor' => 2, 'admin' => 3];
    return ($rank[$user['role'] ?? ''] ?? 0) >= ($rank[$role] ?? 99);
}

function mp_can_edit_post(array $user, array $post): bool {
    return mp_can($user, 'editor') || (
        ($user['role'] ?? '') === 'author' &&
        (int)($post['author_id'] ?? 0) === (int)($user['id'] ?? 0) &&
        ($post['status'] ?? '') === 'draft'
    );
}

function mp_delete_user(PDO $pdo, int $targetId, int $actorId): bool {
    if ($targetId < 1 || $targetId === $actorId) return false;
    try {
        $pdo->beginTransaction();
        $adminSql = "SELECT id FROM users WHERE role='admin'";
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $adminSql .= ' FOR UPDATE';
        $adminIds = $pdo->query($adminSql)->fetchAll(PDO::FETCH_COLUMN);

        $targetSql = 'SELECT role FROM users WHERE id=?';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $targetSql .= ' FOR UPDATE';
        $target = $pdo->prepare($targetSql);
        $target->execute([$targetId]);
        $targetRole = $target->fetchColumn();
        if ($targetRole === false || ($targetRole === 'admin' && count($adminIds) <= 1)) {
            $pdo->rollBack();
            return false;
        }

        $delete = $pdo->prepare('DELETE FROM users WHERE id=?');
        $delete->execute([$targetId]);
        if ($delete->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }
        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }
}
function mp_require_role(string $role): array {
    $user = mp_require_login();
    if (!mp_can($user, $role)) {
        http_response_code(403);
        exit('دسترسی ندارید.');
    }
    return $user;
}

function mp_role_label(string $role): string {
    return ['admin' => 'مدیر', 'editor' => 'ویرایشگر', 'author' => 'نویسنده'][$role] ?? $role;
}
