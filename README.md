# SecureDocFlow

A classification-driven, compliance-focused document management system (DMS) for PHP —
built on top of [OpenDocMan](https://github.com/opendocman/opendocman), the free PHP DMS
originally designed to comply with ISO 17025 and OIE standards for document management.

## Credit where it's due

**SecureDocFlow is a hard fork of [OpenDocMan](https://github.com/opendocman/opendocman)**,
licensed GPL-2.0 (see [`LICENSE.txt`](LICENSE.txt)). OpenDocMan's original authors and
contributors built the foundation this project extends: the core document repository,
department/category structure, revision tracking, and review-process model. None of that
is reimplemented here — it's inherited, and this fork stays GPL-2.0 in turn, as the license
requires.

What changed is significant enough — a different access-control model, new workflow
primitives, a security-hardening pass, and a modernized PHP/MySQL baseline — that this fork
has its own name and its own identity as a portfolio piece, rather than presenting itself as
upstream OpenDocMan. It is not affiliated with, endorsed by, or a replacement for the
upstream project; anyone who wants the original should go to
[github.com/opendocman/opendocman](https://github.com/opendocman/opendocman).

## What SecureDocFlow adds

This fork extends upstream OpenDocMan (upgraded to PHP 8.2, MySQL 8) into a document
management system built around **classification-driven access control** for a
compliance-heavy environment — originally shaped by the document-control requirements of
ISO/IEC 17029 (conformity assessment / accreditation), but the model generalizes to any org
that needs "who can see this depends on how sensitive it is," enforced by the system rather
than by policy alone.

**What's built on top of upstream:**

- **Classification scheme (Public → Internal → Limited → Sensitive → Highly sensitive)**, enforced at multiple points rather than treated as metadata: a checkout lock restricts Sensitive/Highly-sensitive documents to whoever holds them, and classification sets a *minimum* Staged Approval floor at upload time.
- **Staged Approval workflow** — named, reusable multi-stage templates with per-stage designated approvers, so a Sensitive document can require, say, a two-person sign-off before publication, distinct from the classic single-reviewer path for lower-classification documents.
- **Groups** — reusable, named permission groups, layered on top of the existing individual/department permission model rather than replacing it.
- **Access Request workflow** — a user who lacks sufficient access can request a specific rights level; a Department Head or Admin resolves it. The resolution screen surfaces the document's *current* classification and flags it visibly if it's changed since the request was submitted, so a stale request can't get rubber-stamped against outdated context.
- **In-browser document preview** for Word/Excel/PowerPoint/OpenDocument files via headless LibreOffice conversion, cached per document version — with the converted PDF's metadata stripped (author, source-application, etc.) before it's ever served, since a preview shouldn't leak more about a Sensitive document than the classification intends.
- **Traceable document serial numbers**, auto-generated per department, carried through into every filename the document is ever served under.
- **A full security-hardening pass**: CSRF protection audited and closed across every mutating admin/document action; an IDOR in the file-serving path (a crafted id could retrieve a different document's actual bytes while passing permission checks against an authorized one) found and fixed; the installer locked down so it can't be reached to reinitialize a live database without an authenticated Admin session; passwords migrated from unsalted MD5 to `password_hash()` with a transparent per-login upgrade path for existing accounts; session fixation and cookie-hardening fixes. Full write-up in [`SECURITY.md`](SECURITY.md).

None of this requires the classification/workflow features to be used — a deployment that doesn't need them behaves like standard OpenDocMan.

**Using these features day-to-day?** See the [User Guide](USER_GUIDE.md) — classification, Staged
Approval, Access Requests, and Groups, from the end user's side rather than the admin/dev side.

## Inherited from OpenDocMan

    * Upload files using web browser
    * Control access to files based on department or individual user permissions
    * Track revisions of documents
    * Option to send new and updated files through review process
    * Installs on most web servers with PHP
    * Set up a review process for all new files

# License
- GPL 2.0

# Technologies
- PHP 8.2 (what this fork is built and deployed on; the installer's own requirement check only
  enforces PHP 7.4+, so older PHP may work but isn't what's tested)
- Database: MySQL 8+, or MariaDB (the bundled Docker image pins 10.4 — no specific MariaDB
  version is enforced in code)
- PHP-capable web server (Apache with `mod_rewrite` for a non-Docker install)

# Support

This is a portfolio fork, not a maintained product with its own community channels — the
[Discord](https://discord.gg/c92ksu4x) and [Discourse](https://discourse.opendocman.com/) links
you'll see linked from upstream OpenDocMan are their community, not this fork's. For issues or
questions specific to what's built here, use
[GitHub Issues on this repo](https://github.com/teekaysharma/securedocflow/issues).

# Installation

Prerequisites either way: PHP 8.2 (7.4+ is the enforced floor, see Technologies below), MySQL 8+
or MariaDB, and (for a non-Docker deployment) an Apache server with `mod_rewrite`.

## Installing via Docker (recommended)

Docker Compose runs the app and database together; `docker-compose.yml` mounts persistent
volumes for the document repository, the database, and the generated `config.php`.

1. **Generate environment configuration** (creates `.env` with secure generated passwords and
   prompts for basic settings like ports/hostname):
   ```bash
   ./scripts/generate-env-secrets.sh
   ```
   Prefer to configure by hand instead? Copy `.env.sample` to `.env` and edit it directly — at
   minimum set `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `APP_DB_PASS` (must match
   `MYSQL_PASSWORD`), and `ADMIN_PASSWORD`. Then check it with:
   ```bash
   ./scripts/validate-env.sh
   ```

2. **Start the application:**
   ```bash
   make up
   # or: docker-compose up -d --build
   ```

3. **Access it:**
   - URL: `http://localhost:8080` (or whatever `HTTP_PORT` you configured)
   - Username: `admin`
   - Password: whatever you set as `ADMIN_PASSWORD` in `.env`

Other useful `make` targets: `make setup` (generate + validate + start in one step), `make status`,
`make logs`, `make backup`, `make restart`, `make down`, `make clean` (destructive — removes
volumes). Run `make help` for the full list. `.env.sample` documents every available variable
(database, ports, SMTP, SSL/TLS, upload limits, and more).

## Installing with an AI coding agent

`scripts/generate-env-secrets.sh` normally prompts for basic settings (hostname, ports, email,
etc.) — fine for a human, awkward for an AI agent driving a shell. Pass `--non-interactive` and it
skips every prompt, accepting the same bracketed defaults an interactive user gets by pressing
Enter, auto-generating secure passwords as usual, and backing up any existing `.env` automatically
instead of asking. Paste the block below as a prompt into an AI coding agent (Claude Code, Cursor,
etc.) to have it install and start SecureDocFlow for local/demo use without asking you anything
along the way:

```text
Set up SecureDocFlow for local Docker use. Docker and Docker Compose must already be installed.

1. If this repo isn't already cloned locally, clone it:
   git clone https://github.com/teekaysharma/securedocflow.git && cd securedocflow
2. Generate the environment config without prompting:
   ./scripts/generate-env-secrets.sh --non-interactive
3. Start the stack:
   make up
   (or: docker-compose up -d --build)
4. Wait for the containers to come up (check with `make status` or `docker-compose ps`),
   then read the HTTP_PORT and ADMIN_PASSWORD values out of the generated .env file.
5. Report back the login URL (http://localhost:<HTTP_PORT>) and the admin password
   (username: admin) so I can log in.

Every value already has a safe, generated default -- don't ask me any follow-up questions,
just run it and report the result.
```

## Installing to a web server (without Docker)

1. Unzip/clone the repository into your web server's document folder.
2. Point your web server's document root at the `public/` folder — not the repo root.
3. Create a MySQL/MariaDB database and a user with full privileges on it (the installer's config
   step needs to connect with these credentials to build the schema itself — you don't need to
   run any SQL by hand):
   ```sql
   CREATE DATABASE your_db_name;
   CREATE USER 'your_db_user'@'localhost' IDENTIFIED WITH mysql_native_password BY 'your_password';
   GRANT ALL PRIVILEGES ON your_db_name.* TO 'your_db_user'@'localhost';
   ```
4. Create the document storage directory outside the web root (e.g. `mkdir /var/www/document_repository`)
   and make sure it's writable by the web server user but not directly browsable.
5. Visit your installation's URL. With no `application/configs/config.php` present yet, you're
   redirected automatically to the setup wizard at `/installer/setup-config`. Fill in the database
   credentials from step 3, an admin password, and the data directory from step 4 — this writes
   `config.php` for you.
6. You're then taken to `/installer`, which builds the schema (tables, seed data) via the
   migration runner described below — no manual SQL import needed. Log in as `admin` with the
   password you set, and you're done.

## Upgrades

Visiting the app after a code update automatically detects a schema version mismatch and routes
you to `/installer`, which offers an upgrade rather than a fresh install. **Once the app has been
installed once, all installer operations require an authenticated Admin session** — this closes
what was originally an unauthenticated-installer vulnerability (see [`SECURITY.md`](SECURITY.md)),
so log in as an Admin first if you're prompted.

## How the installer actually works

Schema install/upgrade is handled by `application/installer/`: `SchemaBuilder` builds the base
schema, and `application/installer/migrations/Version*.php` are ordered, versioned migration
classes (implementing `MigrationInterface`) applied in sequence by `MigrationRunner` — not a
hand-maintained `upgrade_x.php`/`odm.php` pair kept in sync by hand. `database.sql` at the repo
root is a generated reference dump of the schema (`php application/installer/cli.php dump-sql`),
not something you import manually during a real install.

## Contributing

This is a personal portfolio project rather than an actively-maintained open-source product
looking for contributors, but if you spot something worth fixing, feel free to open an issue or a
pull request against `master` and it'll get a look.
