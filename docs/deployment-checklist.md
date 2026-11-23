# MyPress deployment checklist

Use this checklist for a staging or production installation. Completing automated CI is not a substitute for verifying the actual hosting account, web server and database.

## 1. Hosting prerequisites

- [ ] PHP 8.1 or newer is selected for the domain.
- [ ] PDO is enabled.
- [ ] Enable `pdo_sqlite` for SQLite or `pdo_mysql` for MySQL/MariaDB.
- [ ] Enable `fileinfo` for server-side image MIME validation.
- [ ] Confirm the host supports HTTPS and issue a valid certificate before installation.
- [ ] Confirm PHP can write to `data/` and `uploads/` using the hosting account's normal owner/group permissions. Do not use world-writable permissions such as `777`.
- [ ] Confirm upload limits are compatible with the application (image uploads are limited by the app to 2 MiB).
- [ ] Disable public PHP error display and enable protected server-side error logging.
- [ ] Verify responses include X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy and the configured Content-Security-Policy.
- [ ] Configure Strict-Transport-Security only on the HTTPS virtual host after confirming the entire site is HTTPS-ready.

## 2. Web server and filesystem protection

- [ ] Prefer keeping `config.php`, the SQLite database, backups and logs outside the public document root.
- [ ] If using Apache, confirm `mod_rewrite` and the required `.htaccess` overrides are enabled. The repository rules deny direct access to `config.php`, installer temporary siblings such as `config.php.installing`, and `data/`. The uploads rule must also work under the host's PHP-FPM/FastCGI mode; verify that it does not cause HTTP 500 responses.
- [ ] If using Nginx, merge the applicable rules from [nginx.conf.example](nginx.conf.example) into the correct virtual host. Nginx does not read `.htaccess`.
- [ ] Verify from a browser or command line that `/config.php` and `/data/` return 403/404 and do not reveal file contents.
- [ ] Verify PHP-like files placed in `uploads/` cannot be executed or downloaded as source.
- [ ] Disable directory listing.
- [ ] Restrict access to server logs, temporary files and all backup exports.

## 3. Database

- [ ] Choose SQLite or MySQL/MariaDB and record the actual database path/name.
- [ ] For MySQL/MariaDB, use `utf8mb4` and a dedicated database user with only the privileges needed by the application; the initial legacy commerce migration also needs `ALTER`.
- [ ] Confirm commerce tables use InnoDB after installation.
- [ ] Keep database credentials only in the private `config.php`; never commit them to Git or place them in a public diagnostic page.

## 4. Installation

- [ ] Upload a clean release copy over HTTPS.
- [ ] Open `/install.php` and create the first administrator with a unique password of at least 12 characters.
- [ ] Confirm the installer reports success and creates `config.php` and `data/installed.lock`.
- [ ] Confirm a second visit to `/install.php` is denied.
- [ ] After installation, remove `install.php` or block it at the web-server level if the hosting workflow permits.
- [ ] Sign in at `/admin/login.php` and confirm the session cookie is HttpOnly and SameSite=Lax; on HTTPS it should also be Secure.

## 5. Functional acceptance

- [ ] Open the public home page and seeded pages/posts.
- [ ] Create, edit, publish and unpublish a test post.
- [ ] Create an author account; confirm the author can only manage their own drafts and cannot access editor/admin functions.
- [ ] Upload a valid image and confirm a fake image/PHP file is rejected.
- [ ] Create a product with a known price and stock count.
- [ ] Place a COD test order and verify the total is recalculated by the server.
- [ ] Move the order from new to processing, then shipped; verify invalid backward transitions are rejected.
- [ ] Cancel a new/processing test order and verify stock is restored once only.
- [ ] Download a JSON export and a SQLite backup (SQLite installations only).
- [ ] Rehearse the restore procedure in [backup-restore.md](backup-restore.md), including a SQLite integrity check or MySQL import and a post-restore functional check.

## 6. Operations and security gate

- [ ] Schedule encrypted backups outside the hosting account and test recovery periodically.
- [ ] Record PHP, web server and database versions, deployment date and rollback procedure.
- [ ] Confirm HTTPS renewal, server updates, log rotation and backup retention are assigned to an operator.
- [ ] Perform an independent security review/penetration test before accepting real customer data.
- [ ] Run the acceptance checklist again after every major upgrade.

## Current product boundaries

- The built-in shop supports manual/COD order handling only; there is no payment gateway.
- The JSON export omits password hashes and is not a full restore package.
- MySQL backup/restore is performed with the hosting provider's tools or trusted database utilities.
- No automated update service, mature third-party plugin marketplace or full WordPress feature parity is claimed.

**Deployment status must remain “not verified” until the checks above have been completed on the actual target host.**
