<?php
// نمونه پیکربندی MyPress — این فایل را کپی کنید: cp config.sample.php config.php
// یا بگذارید install.php آن را بسازد. هرگز رمز واقعی را در گیت عمومی نگذارید.
return [
    // 'sqlite' یا 'mysql'
    'db_type' => 'sqlite',
    // SQLite: مسیر فایل دیتابیس (نسبت به ریشه پروژه)
    'db_path' => __DIR__ . '/data/mypress.sqlite',
    // MySQL (فقط وقتی db_type=mysql است):
    'db_host' => 'localhost',
    'db_name' => 'mypress',
    'db_user' => 'root',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',
    // کلید محرمانه نشست/توکن (در نصب به‌صورت تصادفی ساخته می‌شود)
    'secret_key' => 'CHANGE-ME-TO-A-RANDOM-STRING',
    // زبان پیش‌فرض: 'fa' یا 'en'
    'lang' => 'fa',
    // true = نمایش خطا در توسعه؛ در هاست واقعی false بماند
    'debug' => false,
];
