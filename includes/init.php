<?php
// init.php — بوت‌استرپ همه صفحات (به‌جز install.php)
// اگر نصب نشده، به نصب هدایت می‌کند.
declare(strict_types=1);
error_reporting(E_ALL);
$configFile = __DIR__ . '/../config.php';
$inInstall = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'install.php';
if (!file_exists($configFile) && !$inInstall) {
    header('Location: install.php'); exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
mp_security_headers();
require_once __DIR__ . '/auth.php';
mp_session_start();
if (!($GLOBALS['mp_no_db'] ?? false) && file_exists($configFile)) {
    // اتصال تنبل است؛ اولین mp_pdo() آن را می‌سازد.
}
