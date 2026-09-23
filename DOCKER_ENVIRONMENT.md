# SecureDocFlow Docker Environment Setup

This document covers the Docker environment management tooling: generating a secure `.env`,
validating it, and what actually gets pre-populated into the installer when you deploy via Docker.
For the general install steps, see the [README](README.md#installing-via-docker-recommended).

## Overview

The Docker setup:
- **Generates secure passwords and secrets** automatically via `scripts/generate-env-secrets.sh`
- **Pre-populates the installer's config form** from environment variables when `IS_DOCKER=true`
- **Validates configuration** via `scripts/validate-env.sh`
- **Is driven entirely through the Makefile** — `make setup`, `make up`, `make status`, etc.

## Quick Start

### 1. Generate environment configuration
```bash
./scripts/generate-env-secrets.sh
```
This interactive script prompts for basic configuration (hostname, ports, email), generates secure
passwords for the database and admin user, and writes `.env` from `.env.sample`.

### 2. Start the application
```bash
make up
# OR
docker-compose up -d --build
```

### 3. Access the installer
- Navigate to `http://localhost:8080` (or your configured `HTTP_PORT`) — with no `config.php`
  present yet, you're redirected automatically to `/installer/setup-config`.
- The database fields and admin password are **pre-populated** from your `.env` file (see below).
- Step through the wizard; it writes `config.php` and then builds the schema.

## Environment Management Tools

| Script | Purpose | Usage |
|--------|---------|-------|
| `scripts/generate-env-secrets.sh` | Interactive setup with secure password generation | `./scripts/generate-env-secrets.sh` |
| `scripts/validate-env.sh` | Validate `.env` configuration | `./scripts/validate-env.sh` |
| `Makefile` | Wraps the above plus container management | `make help` |

### Makefile commands

```bash
make setup      # Complete setup: generate .env + validate + start
make up         # Start services
make down       # Stop services
make restart    # Restart all services
make status     # Show service status and access URLs
make logs       # View all service logs
make logs-app   # Application logs only
make logs-db    # Database logs only
make shell      # Open shell in app container
make shell-db   # Open MySQL shell
make backup     # Create backup of database and files
make clean      # Remove all data (WARNING: destructive)
make rebuild    # Full rebuild of containers
```

## Installer Form Auto-Population

When `IS_DOCKER=true`, `application/installer/InstallerController.php`'s config form
(`showConfigForm()`) pre-fills its defaults from environment variables rather than hardcoded
values:

- **Database name / user / host** — from `APP_DB_NAME` / `APP_DB_USER` / `APP_DB_HOST`
- **Admin password** — from `ADMIN_PASSWORD`
- **Table prefix** — from `DB_PREFIX` (default `odm_`)
- **Data directory** — from `ODM_DATADIR`

These are defaults on the form, not locked values — you can still change them before submitting.

## Environment Configuration Reference

### Required
```bash
MYSQL_DATABASE=your_db_name
MYSQL_USER=your_db_user
MYSQL_PASSWORD=your_secure_password
MYSQL_ROOT_PASSWORD=your_root_password

APP_DB_HOST=db
APP_DB_NAME=your_db_name        # must match MYSQL_DATABASE
APP_DB_USER=your_db_user        # must match MYSQL_USER
APP_DB_PASS=your_secure_password # must match MYSQL_PASSWORD

ADMIN_PASSWORD=your_admin_password
SESSION_SECRET=your_session_secret
```

### Optional
```bash
HTTP_PORT=8080
HTTPS_PORT=443
DB_EXTERNAL_PORT=3306

ODM_HOSTNAME=odm.local
DB_PREFIX=odm_
DEFAULT_LANGUAGE=english
DEFAULT_THEME=tweeter

SMTP_HOST=localhost
SMTP_PORT=587
MAIL_FROM_ADDRESS=admin@yourdomain.com

HOST_DATA_PATH=/path/to/data
HOST_DB_PATH=/path/to/db
HOST_CONFIG_PATH=/path/to/configs
```

`.env.sample` at the repo root documents every available variable — it's the source of truth, this
list is a summary.

## Common Issues

| Issue | Symptoms | Solution |
|-------|----------|----------|
| Admin password not pre-populated | Installer form shows empty password field | Check `ADMIN_PASSWORD` in `.env` |
| Database connection fails | Installer errors on database connection | Verify `MYSQL_*` and `APP_DB_*` values match |
| Env vars not picked up | Form shows defaults instead of `.env` values | Ensure `IS_DOCKER=true` and restart containers |
| Port conflicts | Services fail to start | Check `.env` port values, run `scripts/validate-env.sh` |

## File Structure

```
securedocflow/
├── .env.sample                    # Environment template
├── .env                           # Generated environment file (git-ignored)
├── scripts/
│   ├── generate-env-secrets.sh    # Interactive setup script
│   └── validate-env.sh            # Configuration validation
├── Makefile                       # Management commands
├── docker-compose.yml
├── DOCKER_ENVIRONMENT.md          # This document
└── application/installer/         # Config wizard + schema/migration system
    ├── InstallerController.php    # Routes setup-config / install / upgrade
    ├── ConfigManager.php
    ├── SchemaBuilder.php
    └── migrations/
```

## Support

- **Environment/config problems:** run `scripts/validate-env.sh` first.
- **Container issues:** `make logs` (or `make logs-app` / `make logs-db`).
- **General setup:** follow the Quick Start above, or the README's Docker section.
