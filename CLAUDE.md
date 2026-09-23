# CLAUDE.md — SecureDocFlow (fork of OpenDocMan)

This file is read automatically by Claude Code at the start of every session in this repo. Treat
it as authoritative project context. Written 2026-09-23, adopting the AI-native SDLC playbook
(https://claude.com/blog/the-ai-native-sdlc-playbook) per the repo owner's explicit instruction —
see `docs/superpowers/intents/2026-09-23-adopt-ai-native-sdlc-intent.md` for the full ask, and
`docs/superpowers/INDEX.md` for where every feature/initiative currently stands.

**Scope note (avoid confusing this with other files also named CLAUDE.md or tracking this same
project):** there is a global `~/.claude/CLAUDE.md` that applies to every Claude Code session on
this machine — unrelated to this repo, no authority here. There is also a **separate, non-public
tracking system for the real deployment this fork was extracted from** — `PROJECT_PLAN.md`,
`MEMORY_ARCHIVE_*.md`, and `SECURITY_AUDIT_2026-08-11.md` in
`C:\Users\LENOVO\Documents\ClaudeCowork\OUTPUTS\OpenDocMan\`, entirely outside this git repo. That
split is deliberate, not an oversight — see Ownership below. This file never names the client, the
real users, or real document/deployment specifics; that content stays in the non-public tracking
system, and this file only ever points across to it in the abstract.

## Ownership

This git repo is a **sanitized public GitHub portfolio fork**, extracted 2026-08-11 from a real
client engagement, renamed **SecureDocFlow** and published 2026-09-23. It contains no client name,
no real documents, no real user data — verified by a full repo-tree search before the first commit
(see `SECURITY.md`'s hardening summary and the README's "Credit where it's due" / "What
SecureDocFlow adds" sections). Do not reintroduce client-identifying content here under any
circumstance, including in code comments, test fixtures, `docs/superpowers/` entries, or commit
messages — if a future task's context includes real client specifics, generalize them before they
land in this repo, or route that work to the non-public tracking system instead.

## Repo

- **Public on GitHub as of 2026-09-23**: https://github.com/teekaysharma/securedocflow (renamed
  from the working "opendocman" fork name — the divergence from upstream is substantial enough to
  warrant its own identity; see the README's "Credit where it's due" section for the attribution
  back to upstream). Pushed via `gh repo create --push` using the repo owner's own `gh` auth.
- No CI/CD at all — `.github/workflows/release-please.yml` was removed 2026-09-23. It had been
  copied over from upstream unmodified (`package-name: opendocman`) and was live: it had already
  run successfully on every push to this repo (verified via `gh run list`) with no visible effect
  yet only because no commit message used a Conventional Commit `feat:`/`fix:` prefix — the next
  one would have opened a real, "opendocman"-branded GitHub Release on this repo. Removed rather
  than rebranded, per the repo owner's explicit choice (this is a portfolio piece, not a versioned
  product with a release cadence). `CHANGELOG.md` was removed in the same pass — it was upstream's
  actual changelog (real `opendocman/opendocman` commit hashes and issue links), not this fork's.
- No PR workflow observed — commits go directly to `master` with the same per-instance
  explicit-approval discipline any repo needs. Don't assume a formal branch/PR policy that hasn't
  actually been demonstrated.
- Dependencies: Composer, `vendor-dir` set to `application/vendor` (not the repo root's `vendor/`).
  **Both prod and dev dependencies are committed** (not gitignored) — a pre-existing project
  convention (the `Dockerfile`/`Makefile` don't run `composer install` anywhere), extended
  2026-09-23 to also commit dev deps (phpunit, mockery, phplint) so the test suite is runnable
  immediately after clone with no setup step. `application/vendor` is ~13MB as of this writing —
  reasonable, but don't casually add heavy dev tooling without checking that stays true.
  **Real, verified cost of that decision**: `phpunit`/`mockery`'s deepest committed paths are
  ~140 characters, which — combined with a nested clone destination — can exceed Windows' 260-char
  path limit and abort `git clone` partway through (`Filename too long`). Confirmed by actually
  cloning the pushed repo: fails at a deep scratch path without `core.longpaths=true`, succeeds
  cleanly at a short one (`C:\tmp-sdf-test`). Documented in the README's Installation section
  rather than reverted — the repo owner's explicit choice (2026-09-23, via `AskUserQuestion`) over
  dropping the committed-dev-deps convention.

## Development process — AI-native SDLC playbook (adopted 2026-09-23)

Every feature's artifact chain, in order:

1. **`docs/superpowers/intents/YYYY-MM-DD-<topic>-intent.md`** — the raw ask, in the repo owner's
   own words, timestamped. Written *before* the spec. Captures Stage 1 (Plan).
2. **`docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md`** — via `superpowers:brainstorming`.
   Questions asked one at a time, 2-3 approaches proposed with a recommendation, design presented
   in sections with approval after each, written up, self-reviewed, then the owner reviews the
   actual file before anything proceeds.
3. **`docs/superpowers/plans/YYYY-MM-DD-<topic>.md`** — via `superpowers:writing-plans`.
   Task-by-task, verbatim code, no placeholders.
4. **Execution** — via `superpowers:subagent-driven-development` when time allows: fresh
   implementer + task reviewer per task, a final whole-branch review, fix wave, scoped re-review
   before merge.
5. **`superpowers:finishing-a-development-branch`** — merge/PR/keep decision.

Plan-mandated findings (a defect traceable to the plan's own text, not implementer deviation)
always go to the repo owner via `AskUserQuestion` — never silently fixed or dismissed, even when
the "right" answer looks obvious.

**All feature work from 2026-08-04 through 2026-08-11 predates this process** — built through
direct plan-mode sessions and ad hoc fixes, not the intent→spec→plan chain. That work is real and
verified (see `docs/superpowers/INDEX.md`'s backfilled rows); it just wasn't produced this way.
Don't retroactively force it into the new structure — apply the playbook going forward.

**Known gaps against the playbook's later stages** (as of 2026-09-23, not yet closed):
- No `.claude/skills/` yet for this repo's own policy areas (SQL/table-prefix conventions, CSRF
  patterns, file-path validation) — candidate for a future initiative, see INDEX.md.
- No hooks (`.claude/settings.json`) — no build-time guardrails exist.
- No CI/CD, no PR-gated review, no `REVIEW.md`.
- No monitoring, no Stage 6 maintenance loop — this is a portfolio artifact, not a monitored
  production service.

## Commands

- **Install:** `php composer.phar install` (both are committed already; only needed after a
  version bump or on a machine without the current `application/vendor/` state).
- **Test:** `php application/vendor/phpunit/phpunit/phpunit --colors=never` (the `vendor/bin/`
  shell wrapper doesn't run directly under Windows PHP — call the real entry point instead).
  Or via Composer: `composer test` (same runner, with the project's own noise filter for a known
  benign diagnostic line — see "Things Claude gets wrong" below).
  **Current verified baseline (2026-09-23): 297 tests, 1819 assertions, 0 errors, 0 failures.**
  Clean — see the next paragraph for how it got there.
- **Lint:** `php application/vendor/overtrue/phplint/bin/phplint --no-configuration application/`
  (same real-entry-point caveat as phpunit). Verified clean: `OK! (Files: 78, Success: 78)` against
  `application/models` + `application/controllers` as of 2026-09-23.

**Test suite health — the 31-error debt disclosed earlier today is now closed.** The 31 errors
(concentrated in `DeptPermsTest` (3), `FileDataTest` (12), `UserMethodsTest` (4), `UserModelTest`
(7), `UserPermissionOrchestratorTest` (5)) were root-caused and fixed via `systematic-debugging`,
same day. Two real patterns, both stale test mocks lagging behind real feature additions, not
application bugs:
1. **Missing `doc_version`/`doc_revision`/`doc_classification`/`valid_until`/
   `workflow_template_id`/`workflow_stage_number`/`serial_number` keys** in `FileData` row mocks —
   `FileData::loadData()`'s SELECT includes these (document versioning/classification/workflow/
   serial-number features), but every mock row predated them.
2. **Missing mocks for methods added by later features** on shared code paths — `Group_Perms`
   (added with Groups) queried for real inside `UserPermission::getViewableFileIds()` when the
   test only overrode `dept_perms_obj`/`user_perms_obj`; `isReviewerForFile()`/`isReviewer()`
   falling through to a Staged-Approval `isStageApproverForFile()`/workflow-approver check that
   older tests didn't know existed; `getRevieweeIds()` now always also merges
   `getWorkflowRevieweeIds()` (Staged Approval); and the 2026-08-11 multi-department bug fix
   changed `getRevieweeIds()`'s SQL placeholders from one reused `:dept` to per-department
   `:dept0`, `:dept1`, ... (PDO only binds one value per named parameter — the old reused
   placeholder silently collapsed every department to the last one's value).

Also found and fixed two files (`UserModelTest.php`'s password tests, and one test each in
`UserMethodsTest.php`/`UserModelTest.php`) that were *still* asserting the pre-2026-08-11
two-query MD5/`PASSWORD()`-style `validatePassword()` behavior — apparently missed when its
sibling tests were fixed earlier the same day. Rewritten to test the actual current single-query
`password_hash()`/`password_verify()` flow, matching the fixes already applied elsewhere.

Earlier the same day, before this baseline was reached: a genuine class-autoload gap
(`Group_Perms.class.php` missing from a test file's manual require list), a CSRF-detection test
false positive (a form using an action-scoped token variable name the regex didn't recognize), and
two pairs of tests asserting pre-security-migration behavior (a `null`-vs-`[]` default) that the
2026-08-11 security hardening correctly changed out from under them.

## Stack and architecture rules

- PHP 8.2, MySQL 8+/MariaDB 10+, Apache with `mod_rewrite`. Legacy per-file controller/model
  structure (no framework router in active use — `public/index.php` maps request paths directly
  to `application/controllers/<name>.php`).
- CSRF: Paragonie AntiCSRF via a `CsrfProtection` singleton
  (`application/includes/csrf/CsrfProtection.php`). `getTokenField($action)` /
  `validateToken($data, $action)` — but `validateToken()`'s `$data` parameter is a no-op; the
  underlying library always reads real `$_POST` regardless of what's passed. A GET-triggered
  mutating action can't use this directly — see "Things Claude gets wrong" below for the pattern
  that works.
- Every SQL query must include the table prefix explicitly:
  `{$GLOBALS['CONFIG']['db_prefix']}$this->tablename` (or the literal `odm_` prefix) — see "Things
  Claude gets wrong," this is a real, repeated source of bugs in this codebase.
- File-serving code (anything building a filesystem path from a request parameter) must validate
  and cast to a clean type (usually `(int)`) *before* it's used in the path — never trust a
  request-derived string in a path just because a permission check elsewhere used a cast version
  of the same value. A real IDOR (crafted `id=5/../6` served a different document's bytes while
  passing permission checks against an authorized one) came from exactly this gap; fixed, but the
  pattern is worth remembering when adding any new file-serving code.

## Standing behavioral rules

Distilled from this project's own history — every one of these traces to a real bug or incident
found during the 2026-08 build/audit, not a generic checklist:

**Investigate before claiming.** Never state what a file, query, or config says without opening it
this session. This repo's own history includes: a table-prefix bug that only appeared once dead
code was actually wired up and called for the first time; a MySQL-8-removed function
(`PASSWORD()`) that would have kept silently corrupting error responses if the fix had trusted the
old code's apparent correctness instead of testing a real wrong-password login; and — from the
2026-09-23 test-suite audit — an assumption that fixing one class-autoload gap would fix "the"
test failures, when in fact most of the remaining failures were unrelated and already there,
just not visible in a truncated tail of the first test run. Verify empirically; don't extrapolate
from a partial view.

**Root-cause every test/bug before fixing it, every time — including bugs found mid-investigation,
not just ones reported to you.** The 2026-09-23 test-suite pass is the concrete example on file:
tracing "Class Group_Perms not found" all the way to a stale manual require-list (not a missing
file), and confirming a suspected regression by isolating variables (stash, revert one file,
`--filter` a single test class) rather than assuming causation from a plausible-looking
correlation.

**Surgical fixes, disclosed gaps.** Fix the specific, verified root cause. When a fix reveals a
larger pattern (like the 31 test failures disclosed earlier 2026-09-23 and closed later the same
day — see Commands above), stop, document what's known, and say so explicitly rather than either
chasing every thread to completion unprompted or quietly leaving the gap undocumented.
`docs/superpowers/INDEX.md` and this file's Commands section are where that disclosure lives —
keep them current.

**No client content in this repo, ever** — see Ownership above. This is the one rule in this file
with zero exceptions.

## Session start checklist

1. `git status`, `git log --oneline -10` — confirm what's actually committed vs. working-tree-only.
2. Read `docs/superpowers/INDEX.md` — one row per feature/initiative, current stage, links to the
   relevant intent/spec/plan files. Answers "where does everything stand" without re-deriving it.
3. `php application/vendor/phpunit/phpunit/phpunit --colors=never` and the phplint command above —
   report actual output, compare against the baseline in this file's Commands section, don't
   assume either is still accurate.
4. Report current verified state before starting new work.
