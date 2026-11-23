# MyPress backup and restore procedure

This guide describes an operator-run recovery procedure. MyPress currently provides administrator-only JSON and SQLite downloads for SQLite installations; it does **not** provide a one-click restore screen or a MySQL backup endpoint.

## Before the first production deployment

- Choose and document the database type and the exact database path/name.
- Keep `config.php`, database files, SQL dumps, JSON exports and uploaded customer data outside public download paths wherever possible.
- Keep a second encrypted copy of backups outside the hosting account.
- Record the PHP version, database engine/version, database charset and the owner/group needed for writable directories.
- Rehearse the procedure on a staging copy before relying on it for production recovery.

## SQLite backup and restore

The administrator can download a SQLite database from **Admin → Backup → SQLite**. The file contains the database, including user password hashes, so treat it as sensitive and encrypt it at rest. The JSON export intentionally omits password hashes and is a data export, not a complete restorable database.

MyPress creates each downloaded SQLite backup with SQLite's `VACUUM INTO` snapshot operation. This captures a consistent committed database state (including committed WAL content) without requiring the site to enter maintenance mode for the backup itself. Maintenance mode is still recommended before replacing a live database during restore.

1. Download the SQLite backup over HTTPS while signed in as an administrator.
2. Store it outside the web root and verify it before relying on it:

   ```sh
   sqlite3 mypress-backup.sqlite 'PRAGMA integrity_check;'
   ```

   The expected result is `ok`.
3. Stop PHP-FPM/the web server or otherwise ensure that no request can write to the database.
4. Preserve the current `config.php`, current database and `uploads/` directory as a rollback copy.
5. Replace the configured SQLite database file (by default `data/mypress.sqlite`) with the verified backup. Keep the existing `config.php` and its `secret_key` when restoring to the same installation.
6. Restore the expected file owner and restrictive permissions, then bring the site back online.
7. Verify administrator login, public pages, media images, products, recent orders and inventory. Keep the rollback copy until the restored site has been checked.

If the configured `db_path` differs from the default, restore to the exact path in the private `config.php`; do not expose that file or its contents.

## MySQL / MariaDB backup and restore

MyPress does not currently implement a MySQL dump/download endpoint. Use the hosting provider's database backup facility or trusted command-line database tools.

Example backup (replace placeholders and store the output outside the document root):

```sh
mysqldump --single-transaction --default-character-set=utf8mb4 \
  -h DB_HOST -u DB_USER -p DB_NAME > mypress-backup.sql
```

For restore, stop application writes, create/select the intended target database, and import the dump using the provider's supported MySQL client or phpMyAdmin. Preserve a copy of the matching `config.php`, confirm its database name/host/user, and restore `uploads/` separately. Reapply the required file ownership and verify the same application workflows listed in the SQLite section.

The single-transaction option is intended for transactional InnoDB tables. If any legacy MyISAM tables remain, stop writes for the dump so the backup is consistent. MyPress's first MySQL schema initialization converts its commerce tables to InnoDB and upgrades monetary columns, but that migration is not a substitute for a backup.

## JSON export limitations

The administrator-only JSON download is useful for inspection or custom migration scripts. It omits password hashes by design and is **not** a full restore package. There is no supported JSON import/restore workflow in the current release. The export writes one row at a time to a private temporary file. On MySQL, it also disables PDO_MYSQL's default buffered-result mode for the duration of the export and closes each table cursor before reading the next table, avoiding a full result-set copy in PHP memory.

## Recovery checklist

- [ ] Backup file stored outside the public web root and encrypted.
- [ ] Database integrity check passes (SQLite) or dump/import completes (MySQL).
- [ ] `config.php` points to the intended database and retains the correct application secret.
- [ ] `uploads/` restored with safe file permissions.
- [ ] Admin login works.
- [ ] Public pages and media render.
- [ ] Product stock and order status are correct.
- [ ] A test order can be placed and its stock transition behaves correctly.
- [ ] Rollback copy retained until verification is complete.
