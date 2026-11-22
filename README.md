# MyPress — سیستم مدیریت وب‌سایت شخصی

> مشابه وردپرس، ولی شخصی، سبک و بدون وابستگی خارجی. فقط PHP خالص 7.4+ / 8.x، بدون Composer، بدون CDN، دیتابیس پیش‌فرض SQLite.

# MyPress — Personal Website CMS

> Like WordPress, but personal, lightweight and dependency-free. Pure PHP 7.4+ / 8.x, no Composer, no CDN, SQLite by default.

---

## فارسی

### امکانات
- **هسته:** نوشته و برگه (پیش‌نویس/منتشرشده)، دسته و برچسب، رسانه (آپلود تصویر)، منوها، نظرات با تایید مدیر، کاربران با نقش مدیر/ویرایشگر/نویسنده، جستجو، داشبورد آمار.
- **سئو (ماژول):** متا تایتل، توضیحات و کلمات کلیدی.
- **فروشگاه (ماژول):** محصول (قیمت/موجودی/عکس/دسته)، سبد خرید، سفارش با وضعیت جدید/در حال پردازش/ارسال‌شده/لغو، کد تخفیف (نمونه: `WELCOME10` = ٪۱۰)، تسویه دستی / پرداخت در محل (COD) — بدون درگاه اجباری.
- **ماژول‌ها:** `modules/` با `manifest.json`؛ فروشگاه، نظرات و سئو از پنل مدیریت (ماژول‌ها) فعال/غیرفعال می‌شوند.
- **پشتیبان‌گیری:** دانلود JSON و دانلود فایل SQLite از پنل مدیریت.
- **امنیت:** `password_hash`، نشست امن، CSRF، Prepared Statements، escape همه خروجی‌ها، ممنوعیت اجرای PHP در `uploads/`، عدم دسترسی وب به `config.php` و `data/`.
- **زبان:** مدیریت پیش‌فرض فارسی راست‌چین (RTL) با امکان انگلیسی (`?lang=en` یا تنظیمات سایت).

### نصب محلی (تست)
```bash
cd mypress
php -S localhost:8000
# مرورگر: http://localhost:8000/install.php
# عنوان سایت + کاربر/رمز/ایمیل مدیر را وارد کنید (SQLite پیش‌فرض)
# سپس: http://localhost:8000/  و  http://localhost:8000/admin/login.php
```

### نصب روی هاست اشتراکی
1. همه فایل‌ها را در `public_html` (یا پوشه دامنه) آپلود کنید.
2. به `https://domain.com/install.php` بروید و فرم را پر کنید.
3. برای MySQL در همان فرم، هاست/نام دیتابیس/کاربر/رمز را وارد کنید (اگر هاست MySQL نمی‌خواهد، SQLite را انتخاب کنید).
4. مطمئن شوید پوشه‌های `data/` و `uploads/` قابل نوشتن هستند (معمولاً 755).
5. بعد از نصب، `install.php` قفل می‌شود؛ برای نصب دوباره `config.php` و `data/installed.lock` را حذف کنید.
6. ورود مدیر: `https://domain.com/admin/login.php`

### ساختار
```
index.php            نمایش سایت
install.php          ویزارد نصب + ساخت جداول + داده نمونه
config.sample.php    نمونه پیکربندی (config.php در نصب ساخته می‌شود)
includes/            db.php auth.php helpers.php router.php init.php admin-layout.php
admin/               داشبورد، ورود/خروج، نوشته، برگه، دسته، رسانه، نظر، کاربر، منو، تنظیمات، ماژول، پشتیبان، فروشگاه
themes/default/      قالب پیش‌فرض (header/footer/home/single/shop/product/cart)
modules/shop|comments|seo/   ماژول‌ها + manifest.json
assets/css|js/       فقط فایل محلی، بدون CDN
uploads/  data/      رسانه‌ها / دیتابیس SQLite (دسترسی وب بسته است)
```

### نمونه داده در نصب
یک نوشته («سلام دنیا!»)، یک برگه («درباره ما»)، یک محصول نمونه و کد تخفیف `WELCOME10`.

### نسخه
`1.0.0` — نسخه در `config` ثابت است و سیستم برای کارکردن نیازی به آپدیت خارجی ندارد (به‌روزرسانی یعنی جایگزینی فایل‌ها با نسخه جدید همین ریپو).

---

## English

### Features
- **Core:** posts & pages (draft/published), categories & tags, media upload, menus, moderated comments, users with admin/editor/author roles, search, dashboard stats.
- **SEO (module):** meta title, description, keywords.
- **Shop (module):** products (price/stock/image/category), cart, orders (new/processing/shipped/cancelled), discount codes (sample: `WELCOME10` = 10% off), manual checkout / Cash on Delivery — no payment gateway required.
- **Modules:** `modules/` with `manifest.json`; shop, comments and SEO can be toggled from Admin → Modules.
- **Backup:** JSON export and SQLite file download from Admin → Backup.
- **Security:** `password_hash`, secure sessions, CSRF tokens, prepared statements, escaped output, no PHP execution in `uploads/`, no web access to `config.php` and `data/`.
- **Language:** Persian RTL by default, English available (`?lang=en` or Site Settings).

### Local test
```bash
cd mypress
php -S localhost:8000
# open http://localhost:8000/install.php, fill in site title + admin user/pass/email (SQLite default)
# then http://localhost:8000/ and http://localhost:8000/admin/login.php
```

### Install on shared hosting
1. Upload all files to `public_html` (or the domain folder).
2. Open `https://domain.com/install.php` and fill the form.
3. For MySQL, enter host/db name/user/pass in the same form (or keep SQLite).
4. Make `data/` and `uploads/` writable (usually 755).
5. After install, `install.php` is locked; to reinstall delete `config.php` and `data/installed.lock`.
6. Admin login: `https://domain.com/admin/login.php`

### Version
`1.0.0` — the version is fixed in config; the system needs no external updates to keep working.
