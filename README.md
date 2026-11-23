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
- CSRF protection, hardened session cookies and baseline browser security headers
- Login throttling (10 failed attempts per username/IP and 30 per IP in 15 minutes)
- Image upload validation by file bytes, MIME and dimensions
- SQLite and MySQL/MariaDB connection support
- Shop with server-side price/stock validation, transactional order creation and atomic stock decrement
- Order cancellation restores stock once; order statuses only move forward (new → processing → shipped), shipped/cancelled orders are terminal
- Comments moderation with public anti-spam throttling, menus, media library and basic SEO metadata
- COD order throttling by client IP and normalized phone number to reduce automated stock reservation abuse
- PHP syntax checks and SQLite smoke tests in GitHub Actions

## Install

1. Upload the project files to your PHP host.
2. Ensure data/ and uploads/ are writable by the PHP process.
3. Open /install.php over HTTPS.
4. Create the first administrator with a unique password of at least 12 characters.
5. Sign in at /admin/login.php.
6. Confirm config.php and data/installed.lock exist and are not publicly downloadable.

The installer creates a random application secret and initializes the security schema. MySQL DDL runs before the data transaction to account for MySQL's implicit DDL commits. MySQL schema upgrades may require temporary ALTER permission, including the migration of long content fields to MEDIUMTEXT; the database account also needs ALTER for the legacy MyISAM-to-InnoDB and 64-bit money migration. Never commit config.php, database files, backups, or uploaded customer data.

## Deployment checklist

Use [docs/deployment-checklist.md](docs/deployment-checklist.md) to verify hosting prerequisites, web-server protections, installation, functional acceptance and the production security gate. A deployment is not verified until these checks pass on the actual host.

## Backup and restore

See [docs/backup-restore.md](docs/backup-restore.md) for SQLite restore, MySQL dump/import, JSON export limitations and the recovery checklist. The procedure must still be rehearsed against the actual hosting provider before production use.

## Web server hardening

The root .htaccess and data/.htaccess protect Apache deployments. **Nginx does not read .htaccess.** Nginx operators must apply the equivalent restrictions in docs/nginx.conf.example and verify them on their actual virtual host. The current installer writes `config.php` in the project root and the bootstrap expects it there; protect direct access with the supplied Apache/Nginx rules. Relocating it requires a tested deployment-specific path/symlink arrangement. Keep the SQLite database, backups, logs and other private runtime files outside the public document root whenever possible.

## Development checks

    find . -type f -name '*.php' -not -path './uploads/*' -print0 | xargs -0 -n1 php -l
    php tests/run.php
    php tests/http-e2e.php

GitHub Actions runs these checks on PHP 8.1, 8.2 and 8.3 and runs MySQL 8 integration tests for installer transactions, schema migrations, large monetary values and order/stock behavior, including an upgrade fixture from legacy MyISAM tables and a CLI dump/restore rehearsal into an empty database. The SQLite smoke suite covers role ownership, protection against deleting the final administrator, login throttling, safe menu URLs, failed/valid checkout, stock rollback, order cancellation idempotency and shipped-order restrictions. The isolated HTTP E2E suite makes a temporary runtime copy and exercises installation, installer lock, public rendering, CSRF enforcement, admin login, content publication, valid/invalid image uploads, product creation, cart and COD checkout, JSON/SQLite backups, author permissions, draft privacy and POST-only logout. It requires the PHP cURL, pdo_sqlite and fileinfo extensions; CI runs it on PHP 8.1, 8.2 and 8.3. The installer is structured to avoid MySQL's implicit-commit behavior around DDL. After all test jobs pass on `main`, CI builds a clean `mypress-2.0.0-staging.zip` runtime package and a SHA-256 checksum as a 14-day Actions artifact; use this for staging validation, not as evidence of production deployment.

## Important limitations before production use

- No independent penetration test or live hosting test has been performed by this repository change.
- MySQL/MariaDB compatibility must be verified against the exact host/version and its SQL mode.
- The shop currently supports manual/COD order handling only; it is not an online payment integration.
- No visual page builder, mature third-party plugin marketplace, full multilingual content model, REST API, automated update service or revision history is included.
- Backup/restore is operator-run; a recovery rehearsal on the actual target host is still required before production use.

MyPress is intentionally a focused lightweight CMS. Feature parity with WordPress is not a goal of this hardening release.
