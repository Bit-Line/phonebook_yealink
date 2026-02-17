# Yealink Phonebook Manager (PHP 7.2 / MySQL 5.7)

A small self-hosted phonebook backend for Yealink phones (tested with T46G UI), designed for old homelab stacks (Ubuntu 16.04 / Apache 2.4 / PHP 7.2 / MySQL 5.7) might also work on newer phones and ubuntu version.

## Features

- Contacts CRUD (add/edit/delete)
- Multiple numbers per contact (Office/Mobile/Other + Extras)
- Department grouping (Yealink menu groups)
- **Tags** (filter chips in UI)
- **Revision system**
  - No auto-revisions on contact changes
  - Manual publish with **Diff preview** (+new / −deleted / ~changed)
  - One active revision at a time (phone pulls XML)
  - **Export any revision** as XML or CSV
  - **Rollback** (admin) restores backend contacts to an old revision snapshot
- CSV import/export
  - Template export
  - Current contacts export
  - Tags column supported
- **Role-Based Access** (viewer/editor/admin)
- **Audit Log** (who changed what)
- **Backup/Restore** (admin) as JSON
- Phonebook endpoint hardening
  - Optional IP allowlist for `phonebook.php` (Yealink T46G can't do BasicAuth)

## Installation

1) Copy `config.sample.php` to `config.php` and set DB credentials.

2) Create DB + tables:

- New install: `install/mysql_install.sql`
- Upgrade from previous version: `install/mysql_upgrade_v2_to_v3.sql`

3) Point Apache vhost `DocumentRoot` to `public/`.

4) Login:

- Default: `admin` / `admin`
- Change password in UI: **Account → Passwort ändern**.

## Yealink Remote Phonebook URL

On the phone's web UI:

- Directory → Remote Phone Book
- URL: `http://YOUR_SERVER/phonebook.php`

If you enable an IP allowlist (Admin → Settings), add the phone's IP/subnet.

## Notes

- This is intended for a trusted homelab. If exposed to the internet, add HTTPS and additional hardening.
