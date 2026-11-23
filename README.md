# MyPress 2.0 — Lightweight Persian-first CMS

MyPress is a self-hosted PHP CMS for small business, portfolio and content websites. It includes a Persian RTL administration experience, posts/pages, media, users and roles, menus, comments, basic SEO and an optional simple COD shop.

**Project status:** 2.0.0 hardening release. Automated PHP and MySQL checks pass; this is not a penetration-tested or deployment-certified WordPress replacement or payment-ready commerce platform.

## Requirements

- PHP 8.1 or newer
- PDO
- pdo_sqlite for SQLite, or pdo_mysql for MySQL/MariaDB
- fileinfo for verified image uploads
- HTTPS for any public deployment

## Included capabilities

- Posts and pages with draft/published workflow
- Admin, editor and author roles; authors are limited to their own drafts and see only their own dashboard metrics; self-deletion and deletion of the final administrator are blocked transactionally
- CSRF protection and hardened session cookies
- Login throttling (10 failed attempts per username/IP and 30 per IP in 15 minutes)
- Image upload validation by file bytes, MIME and dimensions
- SQLite and MySQL/MariaDB connection support
- Shop with server-side price/stock validation, transactional order creation and atomic stock decrement
- Order cancellation restores stock once; order statuses only move forward (new → processing → shipped), shipped/cancelled orders are terminal
- Comments moderation, menus, media library and basic SEO metadata
- PHP syntax checks and SQLite smoke tests in GitHub Actions

## Install

1. Upload the project files to your PHP host.
2. Ensure data/ and uploads/ are writable by the PHP process.
3. Open /install.php over HTTPS.
4. Create the first administrator with a unique password of at least 12 characters.
5. Sign in at /admin/login.php.
6. Confirm config.php and data/installed.lock exist and are not publicly downloadable.

The installer creates a random application secret and initializes the security schema. MySQL DDL runs before the data transaction to account for MySQL's implicit DDL commits. The first MySQL schema initialization upgrades legacy MyISAM commerce tables to InnoDB and 64-bit money columns; the database account needs ALTER permission for this one-time migration. Never commit config.php, database files, backups, or uploaded customer data.

## Web server hardening

The root .htaccess and data/.htaccess protect Apache deployments. **Nginx does not read .htaccess.** Nginx operators must apply the equivalent restrictions in docs/nginx.conf.example and verify them on their actual virtual host. Keep config.php, data/, backups, logs and private runtime files outside the public document root whenever possible.

## Development checks

    find . -type f -name '*.php' -not -path './uploads/*' -print0 | xargs -0 -n1 php -l
    php tests/run.php

GitHub Actions runs these checks on PHP 8.1, 8.2 and 8.3 and runs MySQL 8 integration tests for installer transactions, schema migrations, large monetary values and order/stock behavior, including an upgrade fixture from legacy MyISAM tables. The SQLite smoke suite covers role ownership, protection against deleting the final administrator, login throttling, safe menu URLs, failed/valid checkout, stock rollback, order cancellation idempotency and shipped-order restrictions. The installer is structured to avoid MySQL's implicit-commit behavior around DDL.

## Important limitations before production use

- No independent penetration test or live hosting test has been performed by this repository change.
- MySQL/MariaDB compatibility must be verified against the exact host/version and its SQL mode.
- The shop currently supports manual/COD order handling only; it is not an online payment integration.
- No visual page builder, mature third-party plugin marketplace, full multilingual content model, REST API, automated update service or revision history is included.
- Backup/restore and migration procedures still require a documented operational test before production use.

MyPress is intentionally a focused lightweight CMS. Feature parity with WordPress is not a goal of this hardening release.
