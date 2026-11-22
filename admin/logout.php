<?php
// admin/logout.php — خروج امن
require_once __DIR__ . '/../includes/init.php';
mp_logout();
mp_redirect('login.php');
