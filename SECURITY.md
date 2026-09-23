# Security Policy

## Supported Versions

This is a single-branch portfolio fork, not a versioned product — only `master` is maintained.

## Reporting a Vulnerability

To report a security vulnerability in **this fork**, please use GitHub's **private vulnerability
reporting** feature on this repository:

https://github.com/teekaysharma/securedocflow/security/advisories

(This is separate from upstream OpenDocMan — if you've found an issue in code this fork inherited
unchanged from upstream, consider also reporting it to
[opendocman/opendocman](https://github.com/opendocman/opendocman/security/advisories).)

This ensures the report is only visible to the maintainers and is not publicly accessible.

You should receive a response within 72 hours. If you don't hear back, follow up on the advisory thread.

Please do not report security vulnerabilities through public GitHub issues, Discord, or Discourse.

## Security Hardening in This Fork (2026-08-11)

A full audit was performed against this fork's controllers, models, CSRF layer, and installer. Every finding below was verified against the actual running application before being counted as real — not accepted from static analysis alone. Two Critical- and four High-severity issues were found and fixed; details of each are in the commit history around this date.

| Severity | Finding | Fix |
|---|---|---|
| Critical | Installer had no authentication or already-installed check — `?op=install&force_fresh=1` could drop and reinitialize the entire live database from an unauthenticated request. | Installer now requires an authenticated Admin session once the schema already exists; first-time setup is unaffected. |
| Critical | IDOR in document view/download: permission checks ran against the cast integer id, but the served file path was built from the raw request id — `id=5/../6` passed authorization for document 5 while serving document 6's actual bytes. | The id is validated and cast to a clean integer before it's used in *any* filesystem path, including revision lookups. |
| High | Front-controller router built an `include()` path from the raw request URI with no validation. | Added an explicit allow-list on the resolved controller name before any file-system access. |
| High | Passwords stored as unsalted MD5, with a fallback to MySQL's long-removed `PASSWORD()` function (which crashed, rather than just failed, on a wrong password). | Migrated to `password_hash()`/`password_verify()` (bcrypt) with a transparent lazy-upgrade path — an existing account's hash is silently upgraded the next time its owner logs in successfully. |
| High | Two admin controllers (document archive/delete/undelete; department management) had no CSRF protection on their mutating actions, including a GET-reachable delete. | CSRF token validation added to every mutating action, consistent with the rest of the app. |
| Medium | No session ID regeneration on login (session fixation); session cookie missing `HttpOnly`/`SameSite`, and a Docker code path unconditionally disabled the `Secure` flag even behind HTTPS. | `session_regenerate_id()` added on login; `php.ini` and the Docker cookie logic hardened to respect the actual request scheme. |
| Low | Password-reset flow (inactive by default) leaked whether a username existed via distinct messages, and generated its reset code with a non-cryptographic RNG. | Both closed: identical response regardless of whether the username exists; reset code now uses `random_int()`. |