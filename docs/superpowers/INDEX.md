# Project Index

**What this file is:** the one place to look to know where this fork stands, without reading
every spec/plan file or the codebase itself. One row per feature or process initiative. Detail
lives in the linked `intent.md`/`spec.md`/`plan.md` files where they exist — this table is
pointers and current status only.

**Client-anonymity rule:** this file is part of the public repo (see `CLAUDE.md`'s Ownership
section) — no client name, no real deployment specifics, no real document/user counts. Describe
features generically, the way `README.md` already does.

**Maintenance rule:** update this file in the same commit as any intent/spec/plan file being
created, or any row's status changing — never a separate remembered chore.

**Stage vocabulary:** Intent captured → Spec approved → Plan written → Building → Shipped, with
Deferred / On hold / Not started / Status unknown for work not moving through that pipeline
right now.

## Next priorities

Not yet sequenced by the repo owner as an explicit ordered list (unlike a single-owner project
with one active track, this fork's real priorities live in the private, non-public tracking
system — see `CLAUDE.md`'s Ownership section). What's visibly open from this repo's own state
alone, unordered:

- `.claude/skills/` for this repo's own recurring conventions (table-prefix SQL pattern, CSRF
  token patterns, file-path validation) — would close part of the playbook's Stage 2 (Design)
  dependency the same way `ghgcalculator`'s equivalent skills closed it there.
- CI/CD — currently none at all, not even lint/test on push.
- The installer's own UI (`application/installer/views/*.php`) still says "OpenDocMan Installer"
  in every page title, and `welcome.php` links to `docs/opendocman.txt` as "Installation
  Instructions" — found 2026-09-23 while verifying a fresh clone, deliberately not touched since
  it's live application code shared with the real internal deployment this fork was extracted
  from, not just repo docs. A real rebrand decision, not a docs fix.
- The Docker install path (the README's *recommended* one) has never actually been run
  end-to-end by a Claude session — Docker isn't installed on the machine this repo has been
  developed from. Everything verified so far (fresh clone, composer self-containment, the
  non-Docker PHP bootstrap + installer redirect) was checked without it.

## Product features

| Feature | Intent | Spec | Plan | Stage | Status |
|---|---|---|---|---|---|
| Classification-driven access control (5-tier: Public/Internal/Limited/Sensitive/Highly sensitive) | — | — | — | Shipped | Enforced via a checkout lock on Sensitive/Highly-sensitive documents and a classification-driven minimum Staged Approval floor at upload — not metadata-only. Predates this repo's playbook adoption; no intent/spec/plan recorded. |
| Staged Approval workflow | — | — | — | Shipped | Named, reusable multi-stage templates with per-stage designated approvers, distinct from the classic single-reviewer path. |
| Department Head assignment | — | — | — | Shipped | Elevated per-department authority (classification-setting, validity-date-setting, Access Request resolution). |
| Groups | — | — | — | Shipped | Reusable named permission groups layered on top of the existing individual/department permission model. |
| Document serial numbers | — | — | — | Shipped | Auto-generated per department, carried through into every filename the document is served under (view, download, and converted-preview paths alike). |
| Access Request workflow | — | — | — | Shipped, extended | A user lacking sufficient access can request a specific rights level; a Department Head or Admin resolves it. Extended later: the resolution screen now surfaces the document's *current* classification and visibly flags it if changed since the request was submitted — closes a real gap found by direct stress-testing (a resolver could previously approve a stale request with zero awareness the document's sensitivity had changed). Also closed a real TOCTOU race in the resolution logic (two concurrent resolutions could both pass a stale pending-check) — made atomic, verified via an isolated unit test. |
| In-browser document preview | — | — | — | Shipped | Word/Excel/PowerPoint/OpenDocument via headless LibreOffice conversion, cached per document version. Converted PDF metadata (author, source application, etc.) is stripped before being served — a preview shouldn't leak more about a Sensitive document than its classification intends. Includes a download fallback when conversion fails and serial-number-prefixed output filenames. |
| Security hardening pass | — | — | — | Shipped | Full audit against controllers, models, the CSRF layer, and the installer. 2 Critical, 4 High, 2 Medium, 3 Low/Informational findings, all fixed and verified against the running application (not just read and assumed correct) — including an unauthenticated installer path that could reinitialize the entire database, and a confirmed IDOR in the file-serving path. Full findings table in `SECURITY.md`. |
| GitHub publication prep | — | — | — | Shipped | Full-tree secret/real-data search (none found beyond the already-excluded local config file), `.gitignore` hardened, README rewritten to lead with this fork's distinct work, security summary added to `SECURITY.md`. Published 2026-09-23 as **SecureDocFlow** at https://github.com/teekaysharma/securedocflow. |
| AI-agent install path | — | — | — | Shipped | 2026-09-23, bounded change via `superpowers:brainstorming` (short in-chat design, approved, no spec file). `scripts/generate-env-secrets.sh --non-interactive` skips all 9 prompts, using the same bracketed defaults an interactive Enter-key user gets, auto-backing-up an existing `.env` instead of asking, and always using the generated admin password. Verified with stdin closed (`< /dev/null`) to prove it truly never blocks, and that interactive mode (no flag) is unchanged. README gained an "Installing with an AI coding agent" section with a ready-to-paste agent prompt; `DOCKER_ENVIRONMENT.md` updated to match. |
| End-user guide (USER_GUIDE.md) | — | — | — | Shipped | 2026-09-23. The repo owner's real, SustainaCert-specific how-to guide for the new features is explicitly out of scope for this repo (local-use only, tracked in the private, non-public system — see `CLAUDE.md`'s Ownership section); this is a generic, client-anonymous equivalent instead, covering Classification, Staged Approval, Access Requests, and Groups from an end-user's perspective. Every screen/button name in it was verified against the real `.tpl`/`.php` files (add/edit document forms, workflow.php, access_request.php, group.php), not paraphrased or invented — spot-checked directly after an initial research pass. Linked from the README. |

## Platform/process initiatives

| Initiative | Stage | Status |
|---|---|---|
| `intent.md` → `spec.md` → `plan.md` artifact chain | Adopted | Established 2026-09-23 per https://claude.com/blog/the-ai-native-sdlc-playbook, on the repo owner's explicit instruction — see [intent](intents/2026-09-23-adopt-ai-native-sdlc-intent.md). Applied going forward; not backfilled onto the rows above (no value reconstructing intent that was never recorded — this index's rows are the equivalent lightweight pointer instead, matching the convention used on the repo owner's other playbook-adopting projects). |
| Fresh-clone install verification | Shipped (partial) | 2026-09-23. The repo owner asked "can someone download from GitHub and immediately install" — rather than assert yes, actually cloned the live public repo into an isolated directory and tested it. Found a real, previously-undisclosed issue: committing dev dependencies (see Repo section in `CLAUDE.md`) means a handful of `phpunit`/`mockery` paths run ~140 characters deep, which combined with a nested clone destination can exceed Windows' 260-char path limit and abort `git clone` (`Filename too long`) — confirmed by reproducing the failure at a deep path and the clean success at a short one. Repo owner chose to document it (README + `CLAUDE.md`) rather than stop committing dev deps. Also verified, from the working clone: `composer install` is a genuine no-op (vendor is self-contained), and the PHP bootstrap correctly redirects an unconfigured install to `/installer/setup-config` with zero fatal errors. **Not verified**: the Docker path (the README's recommended one) — Docker isn't installed on the machine this repo is developed from, and a full DB-backed install (schema creation, admin login) was intentionally not attempted rather than risk the live `MySQLOpenDocMan` service running on this shared machine. See "Next priorities" above. |
| `CLAUDE.md` as shared institutional knowledge | Done | Created 2026-09-23, modeled on the repo owner's other two playbook-adopting projects (same house style: Ownership, Repo, Development process, Commands with real verified output, Standing behavioral rules, Session start checklist). |
| This index | Done | Created 2026-09-23. |
| Test suite health audit | Shipped | 2026-09-23: ran the existing PHPUnit suite for the first time as part of establishing this file's "healthy output" baseline — found it wasn't healthy (32 errors, 4 failures) and fixed four real, verified root causes: a class-autoload gap (`Group_Perms.class.php` missing from a test file's manual require list — the class exists, just wasn't loaded), a CSRF-detection test false positive (regex only recognized one literal Smarty variable name, not the action-scoped one a real form correctly uses), and two pairs of tests asserting behavior the 2026-08 security migration intentionally changed (MD5/legacy-`PASSWORD()` password checks, a `null`-vs-`[]` default). Verified each fix in isolation via `git stash`/single-file revert/`--filter`, not assumed. 31 errors were left disclosed, not hidden, concentrated in `DeptPermsTest`, `FileDataTest`, `UserMethodsTest`, `UserModelTest`, `UserPermissionOrchestratorTest`. **Closed later the same day**, via `superpowers:systematic-debugging`: two real patterns, both stale test mocks lagging behind later feature additions — `FileData` row mocks missing the `doc_version`/`doc_revision`/`doc_classification`/`valid_until`/`workflow_template_id`/`workflow_stage_number`/`serial_number` columns `FileData::loadData()` actually selects, and several tests not accounting for code paths later features added onto shared methods (`Group_Perms` queried for real inside `getViewableFileIds()`, `isReviewerForFile()`/`isReviewer()` falling through to Staged-Approval approver checks, `getRevieweeIds()` always merging `getWorkflowRevieweeIds()`, and a 2026-08-11 bug fix that changed `getRevieweeIds()`'s SQL placeholders from one reused `:dept` to per-department `:dept0`/`:dept1`). Also found two more tests (in `UserModelTest.php`/`UserMethodsTest.php`) still asserting the old two-query MD5/`PASSWORD()`-style `validatePassword()` behavior, apparently missed when their siblings were fixed earlier the same day — rewritten to match. **Current verified baseline: 297 tests, 1819 assertions, 0 errors, 0 failures.** See `CLAUDE.md`'s Commands section. |
| Repo-wide documentation discrepancy audit | Shipped | 2026-09-23, repo owner asked for a README review, then a full-repo sweep. Every claim was checked against actual code (routes read in `public/index.php`/`application/installer/`, scripts checked for real paths, Makefile targets diffed against the guide docs), not assumed. Found and fixed: README's install instructions described upstream OpenDocMan's old install mechanism instead of this fork's real wizard+migrations installer, and referenced two diagnostics URLs (`/install/env-check.php`, `/test-env.php`) that don't exist anywhere in the codebase; `DOCKER_ENVIRONMENT.md` had the same fabricated-diagnostics problem at greater length, rewritten to match verified reality; `CONTRIBUTING.md` and `SECURITY.md` pointed contributors/reporters at upstream's repo instead of this one, rewritten; `CHANGELOG.md` was upstream's actual changelog (real `opendocman/opendocman` commit/issue links) and was removed; `.github/workflows/release-please.yml` was found to be **live** (verified via `gh run list` — 3 successful runs already) and branded `package-name: opendocman` — removed entirely per the repo owner's explicit choice rather than rebranded, since a future Conventional-Commit-prefixed commit would otherwise have opened a real "opendocman"-branded GitHub Release on this repo; `guides/TESTING_QUICK_START.md` had a stale "33/33 tests passing" snapshot next to a script path that doesn't exist at repo root; `docs/opendocman.txt` (upstream's 2021 user manual) got a header disclaimer so it isn't mistaken for current docs. `CREDITS.txt`, `PLANS/installer-overhaul.md`, `docs/unused-code-analysis.md`, `docs/COPYRIGHT_MANAGEMENT.md`, and `tests/README*.md` were checked and found accurate — no changes needed. |
