<?php
// Logout is POST-only and protected against cross-site request forgery.
require_once __DIR__ . '/../includes/init.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}
mp_csrf_check();
mp_logout();
mp_redirect('login.php');
